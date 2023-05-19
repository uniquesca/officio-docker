<?php

namespace Applicants\Controller;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Exception;
use Files\BufferedStream;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\AccessLogs;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Applicants Search Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class SearchController extends BaseController
{
    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Analytics */
    protected $_analytics;

    /** @var AccessLogs */
    protected $_accessLogs;

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_clients    = $services[Clients::class];
        $this->_analytics  = $services[Analytics::class];
        $this->_accessLogs = $services[AccessLogs::class];
    }

    public function indexAction () {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        return $view;
    }

    public function getApplicantsListAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError      = '';
        $totalCount    = 0;
        $arrApplicants = array();

        try {
            $filter         = new StripTags();
            $searchId       = $filter->filter($this->params()->fromPost('search_id', ''));
            $searchFor      = $filter->filter($this->params()->fromPost('search_for', 'applicants'));
            $searchQuery    = $filter->filter(trim(Json::decode($this->params()->fromPost('search_query', ''), Json::TYPE_ARRAY)));
            $booAllClients  = (bool)$this->params()->fromPost('boo_conflict_search', 0);
            $booQuickSearch = $this->params()->fromPost('quick_search', 0);

            if (empty($searchId) && $searchQuery == '') {
                $searchId = strtolower($this->_clients->getSearch()->getMemberDefaultSearch($this->_auth->getCurrentUserId(), 'clients', false) ?? '');
            }

            if ($searchQuery == '' && !in_array($searchId, array('last4me', 'last4all', 'all')) && !$this->_clients->getSearch()->hasAccessToSavedSearch($searchId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $limit = 50;

                $arrQueryWords = $searchQuery !== '' ? $this->_clients->getSearch()->getSearchStringExploded($searchQuery) : [];
                $booLoadTotalCount = true;
                if ($booQuickSearch) {
                    $booLoadTotalCount = false;

                    // We want to run the quick search,
                    // but if we force to use the detailed search in the config and very short text is provided - use a quick search anyway
                    if (!empty($this->_config['settings']['quick_search_use_detailed_search'])) {
                        $booQuickSearch = false;
                        foreach ($arrQueryWords as $word) {
                            if (mb_strlen($word) <= 2) {
                                $booQuickSearch = true;
                                break;
                            }
                        }
                    }
                }

                if ($booQuickSearch && $searchFor == 'applicants' && !empty($arrQueryWords)) {
                    list($arrApplicants, $totalCount) = $this->_clients->getSearch()->runQuickSearchByStaticFields(
                        $arrQueryWords,
                        $limit,
                        false,
                        false,
                        empty($this->_config['settings']['quick_search_active_clients_only']),
                        $booLoadTotalCount
                    );
                } else {
                    $arrApplicants = $this->_clients->getApplicantsAndCases($searchId, $arrQueryWords, $searchFor, $booAllClients, false, $limit);
                    $totalCount    = count($arrApplicants);
                }
            }

            // Save profiling information
            $time = round(microtime(true) - $_SERVER['REQUEST_TIME'], 2);
            if ($time > 20) {
                $oIdentity      = $this->_auth->getIdentity();
                $arrUserDetails = [
                    'company_id'   => $oIdentity->company_id,
                    'company_name' => $oIdentity->company_name,
                    'username'     => $oIdentity->username,
                ];

                $details = PHP_EOL . str_repeat('*', 40) . PHP_EOL;
                $details .= $this->_tr->translate('Time: ') . $time . 's' . PHP_EOL;
                $details .= $this->_tr->translate('User details: ') . print_r($arrUserDetails, true) . PHP_EOL;
                $details .= $this->_tr->translate('Params: ') . print_r($this->params()->fromPost(), true) . PHP_EOL;
                $details .= str_repeat('*', 40) . PHP_EOL;

                $this->_log->debugToFile($details, 1, 2, 'slow-search-' . date('Y_m_d') . '.log');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrApplicants,
            'count'   => $totalCount,
        );

        return new JsonModel($arrResult);
    }


    public function getSavedSearchesAction()
    {
        $strError = '';

        $arrSearches = array();
        try {
            $filter           = new StripTags();
            $booFavoritesOnly = (bool)$this->params()->fromPost('favorites', false);
            $searchType       = $filter->filter(Json::decode($this->params()->fromPost('search_type'), Json::TYPE_ARRAY));
            $searchType       = $searchType === 'contacts' ? 'contacts' : 'clients';

            /*
            // Temporary hidden

            // These are default system searches
            if ($searchType === 'contacts') {
                $arrSearches[] = array(
                    'search_id'                 => 'all',
                    'search_type'               => 'system',
                    'search_name'               => $this->_tr->translate('All contacts'),
                    'search_can_be_set_default' => true,
                    'search_default'            => true
                );
            } else {
                $defaultSearch = strtolower($this->_clients->getSearch()->getMemberDefaultSearch(null, 'clients', false));

                $arrSearches[] = array(
                    'search_id'                 => 'last4me',
                    'search_type'               => 'system',
                    'search_name'               => $this->_clients->getSearch()->getMemberDefaultSearchName('last4me'),
                    'search_can_be_set_default' => true,
                    'search_default'            => $defaultSearch == 'last4me',
                );

                $arrSearches[] = array(
                    'search_id'                 => 'last4all',
                    'search_type'               => 'system',
                    'search_name'               => $this->_clients->getSearch()->getMemberDefaultSearchName('last4all'),
                    'search_can_be_set_default' => true,
                    'search_default'            => $defaultSearch == 'last4all',
                );

                $arrSearches[] = array(
                    'search_id'                 => 'all',
                    'search_type'               => 'system',
                    'search_name'               => $this->_clients->getSearch()->getMemberDefaultSearchName('all'),
                    'search_can_be_set_default' => true,
                    'search_default'            => $defaultSearch == 'all'
                );
            }
            */

            $arrSavedSearches = $this->_clients->getSearch()->getCompanySearches(
                $this->_auth->getCurrentUserCompanyId(),
                ['*'],
                [$searchType]
            );


            $memberId            = $this->_auth->getCurrentUserId();
            $arrFavoriteSearches = $this->_clients->getSearch()->getMemberFavoriteSearches($memberId);
            foreach ($arrSavedSearches as $arrSavedSearchInfo) {
                $booIsSearchFavorite = in_array($arrSavedSearchInfo['search_id'], $arrFavoriteSearches);
                if ($booFavoritesOnly && !$booIsSearchFavorite) {
                    continue;
                }

                $arrSearches[] = array(
                    'search_id'                 => $arrSavedSearchInfo['search_id'],
                    'search_type'               => 'user',
                    'search_name'               => $arrSavedSearchInfo['title'],
                    'search_can_be_set_default' => false,
                    'search_default'            => false,
                    'search_is_favorite'        => $booIsSearchFavorite
                );
            }
        } catch (Exception $e) {
            $arrSearches = array();
            $strError    = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrSearches = array(
            'items' => $arrSearches,
            'count' => count($arrSearches),
        );


        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrSearches['items'],
            'count'   => $arrSearches['count'],
        );

        return new JsonModel($arrResult);
    }

    public function deleteSavedSearchAction()
    {
        $strError = '';

        try {
            // Check access rights for saved search id
            $searchId = (int)$this->params()->fromPost('search_id');
            if (!$this->_clients->getSearch()->hasAccessToSavedSearch($searchId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Delete saved search
            if (empty($strError)) {
                $this->_clients->getSearch()->delete($searchId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function setDefaultAction () {
        $view = new JsonModel();

        $strError = '';
        try {
            // Check access rights for saved search id
            $filter = new StripTags();
            $searchId = $filter->filter($this->findParam('search_id'));

            if(!in_array($searchId, array('all', 'last4me', 'last4all'))) {
                $strError = $this->_tr->translate('Incorrectly selected search.');
            }

            // Update default saved search
            if(empty($strError) && !$this->_clients->getSearch()->setMemberDefaultSearch(null, $searchId)) {
                $strError = $this->_tr->translate('Internal error. Please try again later.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );
        return $view->setVariables($arrResult);
    }

    public function runSearchAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $arrMembers      = array();
        $totalCount      = 0;
        $arrAllMemberIds = array();
        try {
            $filter                  = new StripTags();
            $arrAllParams            = Json::decode($this->params()->fromPost('arrSearchParams'), Json::TYPE_ARRAY);
            $arrAllParams['columns'] = Json::decode($this->params()->fromPost('arrColumns'), Json::TYPE_ARRAY);

            $arrAllParams['arrSortInfo'] = array(
                'start' => (int)$this->params()->fromPost('start', 0),
                'limit' => (int)$this->params()->fromPost('limit', 50),
                'dir'   => $filter->filter($this->params()->fromPost('dir', 'ASC')),
                'sort'  => $filter->filter($this->params()->fromPost('sort', 'individual_family_name')),
            );

            $searchType = Json::decode($this->params()->fromPost('searchType'), Json::TYPE_ARRAY);
            $searchType = in_array($searchType, array('applicants', 'contacts')) ? $searchType : 'applicants';

            $savedSearchId = $this->params()->fromPost('saved_search_id');
            if (in_array($savedSearchId, array('all', 'last4me', 'last4all', 'quick_search'))) {
                $arrAllParams['saved_search_id'] = $savedSearchId;

                if ($savedSearchId == 'quick_search') {
                    $arrAllParams['search_query'] = $this->params()->fromPost('search_query', '');
                }
            }

            list($strError, $arrMembers, $totalCount, $arrAllMemberIds) = $this->_clients->getSearch()->runAdvancedSearch($arrAllParams, $searchType);

            $this->_company->updateLastField(false, 'last_adv_search');
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'items'   => $arrMembers,
            'count'   => $totalCount,
            'all_ids' => $arrAllMemberIds
        );

        return new JsonModel($arrResult);
    }

    public function runAndGetMainInfoAction()
    {
        $view = new JsonModel();

        set_time_limit(2 * 60); // 2 minutes max!
        ini_set('memory_limit', '-1');
        session_write_close();

        $arrAllMemberIds = array();
        $searchName      = 'Advanced search';

        try {
            $filter                  = new StripTags();
            $arrAllParams            = Json::decode($this->findParam('arrSearchParams'), Json::TYPE_ARRAY);
            $arrAllParams['columns'] = Json::decode($this->findParam('arrColumns'), Json::TYPE_ARRAY);

            $maxRowsReturnCount = 500;
            $arrAllParams['arrSortInfo'] = array(
                'start' => 0,
                'limit' => $maxRowsReturnCount,
                'dir'   => $filter->filter($this->findParam('dir', 'DESC')),
                'sort'  => $filter->filter($this->findParam('sort', 'case_file_number')),
            );

            $searchType = Json::decode($this->findParam('searchType'), Json::TYPE_ARRAY);
            $searchType = in_array($searchType, array('applicants', 'contacts')) ? $searchType : 'applicants';

            list($strError, $arrMembers, $totalCount, ) = $this->_clients->getSearch()->runAdvancedSearch($arrAllParams, $searchType);

            if (empty($strError)) {
                foreach ($arrMembers as $arrMemberInfo) {
                    $arrAllMemberIds[] = array(
                        'applicant_id'   => $arrMemberInfo['applicant_id'] ?? 0,
                        'applicant_type' => $arrMemberInfo['applicant_type'] ?? '',
                        'applicant_name' => $arrMemberInfo['applicant_name'] ?? '',
                        'case_id'        => $arrMemberInfo['case_id'] ?? 0,
                        'case_name'      => $arrMemberInfo['case_name'] ?? '',
                    );
                }

                if ($totalCount > $maxRowsReturnCount) {
                    $searchName .= sprintf(' (show last %d records)', $maxRowsReturnCount);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'message'    => $strError,
            'items'      => $arrAllMemberIds,
            'searchName' => $searchName
        );
        return $view->setVariables($arrResult);
    }

    public function saveSearchAction()
    {
        $strError      = '';
        $savedSearchId = 0;
        try {
            $filter = new StripTags();

            $searchId     = (int)$this->params()->fromPost('search_id');
            $searchParams = Json::decode($this->params()->fromPost('advanced_search_params'), Json::TYPE_ARRAY);
            foreach ($searchParams as $key => $val) {
                $searchParams[$key] = $filter->filter($val);
            }

            $searchName = trim($filter->filter(Json::decode($this->params()->fromPost('search_name', ''), Json::TYPE_ARRAY)));

            // Check incoming params
            if (!empty($searchId) && !$this->_clients->getSearch()->hasAccessToSavedSearch($searchId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Search name is a required field
            if (empty($strError) && !strlen($searchName)) {
                $strError = $this->_tr->translate('Please enter search name.');
            }

            // Check columns, sorting + order
            $searchColumns = Json::decode($this->params()->fromPost('search_columns'), Json::TYPE_ARRAY);
            if (empty($strError) && (!isset($searchColumns['arrColumns']) || !is_array($searchColumns['arrColumns']) || !count($searchColumns['arrColumns']))) {
                $strError = $this->_tr->translate('Incorrectly selected list of columns.');
            }

            if (empty($strError) && (!isset($searchColumns['arrSortInfo']['dir']) || !isset($searchColumns['arrSortInfo']['sort']))) {
                $strError = $this->_tr->translate('Incorrectly selected sorting info.');
            }

            $searchType = Json::decode($this->params()->fromPost('search_type'), Json::TYPE_ARRAY);
            $searchType = $searchType === 'contacts' ? 'contacts' : 'clients';

            // Save the filter client type if needed
            $companyId = $this->_auth->getCurrentUserCompanyId();
            if (isset($searchParams['filter_client_type_radio'])) {
                if ($searchType === 'contacts') {
                    unset($searchParams['filter_client_type_radio']);
                } elseif (!in_array($searchParams['filter_client_type_radio'], ['individual', 'employer']) || !$this->_company->isEmployersModuleEnabledToCompany($companyId)) {
                    $searchParams['filter_client_type_radio'] = 'individual';
                }
            }

            $arrClientTypes               = array();
            $arrGroupedFieldsByMemberType = array();
            if (empty($strError)) {
                // List of search types allowed for the current user
                $arrSearchAllowedTypes = $searchType === 'contacts' ? array('contact') : $this->_clients->getApplicantFields()->getAdvancedSearchTypesList(true);

                // List of types for selected columns + for order column + for search fields
                for ($i = 1; $i <= $searchParams['max_rows_count']; $i++) {
                    if (isset($searchParams['field_client_type_' . $i]) && in_array($searchParams['field_client_type_' . $i], $arrSearchAllowedTypes)) {
                        $arrClientTypes[] = $searchParams['field_client_type_' . $i];
                    }
                }

                foreach ($arrSearchAllowedTypes as $searchAllowedType) {
                    $booFoundType = false;
                    foreach ($searchColumns['arrColumns'] as $arrColumnInfo) {
                        if (preg_match('/^' . $searchAllowedType . '_(.*)$/', $arrColumnInfo['id'])) {
                            $booFoundType = true;
                            break;
                        }
                    }

                    if (preg_match('/^' . $searchAllowedType . '_(.*)$/', $searchColumns['arrSortInfo']['sort'])) {
                        $booFoundType = true;
                    }

                    if ($booFoundType) {
                        $arrClientTypes[] = $searchAllowedType;
                    }
                }
                $arrClientTypes = array_unique($arrClientTypes);

                // Load and group fields data - only for required types
                foreach ($arrClientTypes as $strClientType) {
                    switch ($strClientType) {
                        case 'accounting':
                            $arrGroupedFieldsByMemberType[$strClientType] = $this->_clients->getFields()->getAccountingFields(true, false);
                            break;

                        case 'case':
                            $arrCompanyFields = $this->_clients->getFields()->getCompanyFields($companyId);
                            foreach ($arrCompanyFields as $arrFieldInfo) {
                                $arrGroupedFieldsByMemberType[$strClientType][$arrFieldInfo['field_id']] = $arrFieldInfo['company_field_id'];
                            }
                            break;

                        default:
                            $memberTypeId = $this->_clients->getMemberTypeIdByName($strClientType);
                            if ($memberTypeId) {
                                $arrApplicantFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $memberTypeId);
                                foreach ($arrApplicantFields as $arrFieldInfo) {
                                    $arrGroupedFieldsByMemberType[$strClientType][$arrFieldInfo['applicant_field_id']] = $arrFieldInfo['applicant_field_unique_id'];
                                }
                            }
                            break;
                    }
                }


                // Check if columns were selected correctly
                foreach ($searchColumns['arrColumns'] as $arrColumnInfo) {
                    if ($arrColumnInfo['id'] == 'case_dependants') {
                        // This is a specific column, don't check it, it is a valid one
                        continue;
                    }

                    foreach ($arrClientTypes as $searchAllowedType) {
                        if (!isset($arrGroupedFieldsByMemberType[$searchAllowedType])) {
                            continue;
                        }

                        if (preg_match('/^' . $searchAllowedType . '_(.*)$/', $arrColumnInfo['id'], $regs)) {
                            if (!in_array($regs[1], $arrGroupedFieldsByMemberType[$searchAllowedType])) {
                                // Field was found and is incorrect
                                $strError = $this->_tr->translate('Incorrectly selected list of columns.');
                                break 2;
                            } else {
                                // Field was found and is correct, check the next column
                                break;
                            }
                        }
                    }
                }
            }

            if (empty($strError) && !in_array($searchColumns['arrSortInfo']['dir'], array('ASC', 'DESC'))) {
                $strError = $this->_tr->translate('Incorrect sorting direction.');
            }


            // Check search fields in the row, use their text ids instead of a numeric ones
            if (empty($strError)) {
                for ($i = 1; $i <= $searchParams['max_rows_count']; $i++) {
                    if (!array_key_exists('field_' . $i, $searchParams) || !array_key_exists('field_client_type_' . $i, $searchParams)) {
                        continue;
                    }

                    $fieldId       = $searchParams['field_' . $i];
                    $rowClientType = $searchParams['field_client_type_' . $i];
                    if (isset($arrGroupedFieldsByMemberType[$rowClientType][$fieldId])) {
                        // Save text field id instead of a numeric one
                        $searchParams['field_' . $i] = $arrGroupedFieldsByMemberType[$rowClientType][$fieldId];
                    } elseif (in_array($fieldId, array('ob_total', 'ta_total', 'clients_completed_forms', 'clients_uploaded_documents', 'clients_have_payments_due', 'created_on'))) {
                        $searchParams['field_' . $i] = $fieldId;
                    } else {
                        $strError = $this->_tr->translate('Incorrectly selected field in search row.');
                        break;
                    }
                }
            }

            // Check and save filtering fields + chart settings from the "Analytics" tab
            if (empty($strError)) {
                $analyticsParams = Json::decode($this->params()->fromPost('search_analytics'), Json::TYPE_ARRAY);

                list(, $arrCheckedParams) = $this->_analytics->getAnalyticsParams($searchType, $analyticsParams);
                if (!empty($arrCheckedParams)) {
                    $searchParams['analytics'] = $arrCheckedParams;
                }
            }

            // Save search
            if (empty($strError)) {
                $savedSearchId = $this->_clients->getSearch()->saveSearchInfo($searchId, $searchName, $searchType, Json::encode($searchParams), Json::encode($searchColumns));
                if ($savedSearchId === false) {
                    $strError = $this->_tr->translate('Internal error. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'       => empty($strError),
            'message'       => $strError,
            'savedSearchId' => $savedSearchId
        );

        return new JsonModel($arrResult);
    }

    public function loadSearchAction()
    {
        $strError            = '';
        $searchName          = '';
        $arrSearchParams     = [];
        $arrSearchColumns    = [];
        $booIsFavoriteSearch = false;

        try {
            // Check access rights for saved search id
            $searchId   = $this->params()->fromPost('search_id');
            $searchType = $this->params()->fromPost('search_type', 'clients');
            $searchType = in_array($searchType, array('clients', 'contacts')) ? $searchType : 'clients';

            switch ($searchId) {
                case 'all':
                case 'last4me':
                case 'last4all':
                case 'today_clients':
                case 'clients_completed_forms':
                case 'clients_uploaded_documents':
                case 'clients_have_payments_due':
                case 'quick_search':
                    $companyId = $this->_auth->getCurrentUserCompanyId();

                    if ($searchType === 'contacts') {
                        if ($searchId === 'today_clients') {
                            $searchName = $this->_tr->translate('Today Contacts');
                        } elseif ($searchId === 'quick_search') {
                            $searchName = $this->params()->fromPost('search_query');
                        } else {
                            $searchName = $this->_tr->translate('All Contacts');
                        }

                        $arrSearchColumns = array(
                            'arrColumns'  => array(),
                            'arrSortInfo' => array(
                                'sort' => 'contact_' . $this->_clients->getFields()->getStaticColumnNameByFieldId('lName'),
                                'dir'  => 'ASC'
                            )
                        );

                        $memberType             = 'contact';
                        $arrGroupedClientFields = $this->_clients->getApplicantFields()->getGroupedCompanyFields(
                            $companyId,
                            $this->_clients->getMemberTypeIdByName($memberType),
                            0
                        );

                        foreach ($arrGroupedClientFields as $arrClientGroupInfo) {
                            if (!isset($arrClientGroupInfo['fields']) || !count($arrClientGroupInfo['fields'])) {
                                continue;
                            }

                            foreach ($arrClientGroupInfo['fields'] as $arrClientFieldInfo) {
                                if ($arrClientFieldInfo['field_column_show']) {
                                    $arrSearchColumns['arrColumns'][] = array(
                                        'id'    => $memberType . '_' . $arrClientFieldInfo['field_unique_id'],
                                        'width' => 150
                                    );
                                }
                            }
                        }

                        $arrQuery = array(
                            'max_rows_count'      => '1',
                            'field_client_type_1' => 'contact',
                            'field_type_1'        => 'text',
                            'operator_1'          => 'and',
                            'field_1'             => $this->_clients->getFields()->getStaticColumnNameByFieldId('lName'),
                            'filter_1'            => 'is_not_empty',
                            'active-clients'      => empty($this->_config['settings']['quick_search_active_clients_only']) ? '' : '1',
                            'related-cases'       => ''
                        );
                    } else {
                        if ($searchId === 'today_clients') {
                            $searchName = $this->_tr->translate('Today Clients');
                        } elseif ($searchId === 'clients_completed_forms') {
                            $searchName = $this->_tr->translate('Clients completed forms');
                        } elseif ($searchId === 'clients_uploaded_documents') {
                            $searchName = $this->_tr->translate('Clients uploaded documents');
                        } elseif ($searchId === 'clients_have_payments_due') {
                            $searchName = $this->_tr->translate('Clients have payments due');
                        } elseif ($searchId === 'quick_search') {
                            $searchName = $this->params()->fromPost('search_query');
                        } else {
                            $searchName = $this->_clients->getSearch()->getMemberDefaultSearchName($searchId);
                        }

                        $arrSearchColumns = array(
                            'arrColumns'  => array(),
                            'arrSortInfo' => array(
                                'sort' => 'individual_last_name',
                                'dir'  => 'ASC'
                            )
                        );

                        // Employers' and Individuals' fields we want to show
                        $arrMemberTypes = [];
                        if ($this->_auth->isCurrentMemberCompanyEmployerModuleEnabled()) {
                            $arrMemberTypes[] = 'employer';
                        }
                        $arrMemberTypes[] = 'individual';

                        foreach ($arrMemberTypes as $memberType) {
                            $arrGroupedClientFields = $this->_clients->getApplicantFields()->getGroupedCompanyFields(
                                $companyId,
                                $this->_clients->getMemberTypeIdByName($memberType),
                                0
                            );

                            foreach ($arrGroupedClientFields as $arrClientGroupInfo) {
                                if (!isset($arrClientGroupInfo['fields']) || !count($arrClientGroupInfo['fields'])) {
                                    continue;
                                }

                                foreach ($arrClientGroupInfo['fields'] as $arrClientFieldInfo) {
                                    if ($arrClientFieldInfo['field_column_show']) {
                                        $arrSearchColumns['arrColumns'][] = array(
                                            'id'    => $memberType . '_' . $arrClientFieldInfo['field_unique_id'],
                                            'width' => 150
                                        );
                                    }
                                }
                            }
                        }

                        // Cases' fields we want to show
                        $arrCaseFieldsToShow      = ['file_number'];
                        $arrCaseFields            = $this->_clients->getFields()->getCompanyFields($companyId);
                        $arrUserAllowedCaseFields = $this->_clients->getFields()->getUserAllowedFieldIds($companyId);
                        foreach ($arrCaseFields as $arrCaseFieldInfo) {
                            if (in_array($arrCaseFieldInfo['company_field_id'], $arrCaseFieldsToShow) && in_array($arrCaseFieldInfo['field_id'], $arrUserAllowedCaseFields)) {
                                $arrSearchColumns['arrColumns'][] = array(
                                    'id'    => 'case_' . $arrCaseFieldInfo['company_field_id'],
                                    'width' => 150
                                );
                            }
                        }

                        switch ($searchId) {
                            case 'quick_search':
                            case 'today_clients':
                                $arrQuery = array(
                                    'max_rows_count'      => '1',
                                    'field_client_type_1' => 'case',
                                    'field_type_1'        => 'date',
                                    'operator_1'          => 'and',
                                    'field_1'             => 'created_on',
                                    'filter_1'            => 'is',
                                    'date_1'              => date('Y-m-d'),
                                    'active-clients'      => empty($this->_config['settings']['quick_search_active_clients_only']) ? '' : '1',
                                    'related-cases'       => 'on'
                                );
                                break;

                            case 'clients_completed_forms':
                                $arrQuery = array(
                                    'max_rows_count'      => '1',
                                    'field_client_type_1' => 'case',
                                    'field_type_1'        => 'special',
                                    'operator_1'          => 'and',
                                    'field_1'             => 'clients_completed_forms',
                                    'active-clients'      => empty($this->_config['settings']['quick_search_active_clients_only']) ? '' : '1',
                                    'related-cases'       => 'on'
                                );
                                break;

                            case 'clients_uploaded_documents':
                                $arrQuery = array(
                                    'max_rows_count'      => '1',
                                    'field_client_type_1' => 'case',
                                    'field_type_1'        => 'special',
                                    'operator_1'          => 'and',
                                    'field_1'             => 'clients_uploaded_documents',
                                    'active-clients'      => empty($this->_config['settings']['quick_search_active_clients_only']) ? '' : '1',
                                    'related-cases'       => 'on'
                                );
                                break;

                            case 'clients_have_payments_due':
                                $arrQuery = array(
                                    'max_rows_count'      => '1',
                                    'field_client_type_1' => 'case',
                                    'field_type_1'        => 'special',
                                    'operator_1'          => 'and',
                                    'field_1'             => 'clients_have_payments_due',
                                    'active-clients'      => empty($this->_config['settings']['quick_search_active_clients_only']) ? '' : '1',
                                    'related-cases'       => 'on'
                                );
                                break;

                            default:
                                $arrQuery = [];
                                break;
                        }
                    }

                    $arrSearchColumns = Json::encode($arrSearchColumns);
                    $arrSearchParams  = Json::encode($arrQuery);
                    break;

                default:
                    if (!$this->_clients->getSearch()->hasAccessToSavedSearch($searchId)) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }

                    if (empty($strError)) {
                        // Load search
                        $arrSearchInfo    = $this->_clients->getSearch()->getSearchInfo($searchId);
                        $searchName       = $arrSearchInfo['title'];
                        $arrSearchParams  = $arrSearchInfo['query'];
                        $arrSearchColumns = $arrSearchInfo['columns'];

                        $memberId            = $this->_auth->getCurrentUserId();
                        $arrFavoriteSearches = $this->_clients->getSearch()->getMemberFavoriteSearches($memberId);
                        $booIsFavoriteSearch = in_array($searchId, $arrFavoriteSearches);
                    }
                    break;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'            => empty($strError),
            'message'            => $strError,
            'search_name'        => $searchName,
            'search'             => $arrSearchParams,
            'search_columns'     => $arrSearchColumns,
            'search_is_favorite' => $booIsFavoriteSearch
        );

        return new JsonModel($arrResult);
    }

    public function exportToExcelAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        set_time_limit(5 * 60); // I said you have 5 minutes, no more
        ini_set('memory_limit', '-1'); // But you can use as many resources, as you can

        // Close session for writing - so next requests can be done
        session_write_close();

        $strError = '';

        try {
            $format               = Json::decode($this->params()->fromPost('format'), Json::TYPE_ARRAY);
            $arrClientsOrCasesIds = Json::decode($this->params()->fromPost('arrAllIds'), Json::TYPE_ARRAY);
            $arrColumnsForExcel   = Json::decode($this->params()->fromPost('arrColumns'), Json::TYPE_ARRAY);

            if (empty($strError) && (!is_array($arrColumnsForExcel) || !count($arrColumnsForExcel))) {
                $strError = $this->_tr->translate('Incorrectly selected columns.');
            }

            if (empty($strError) && empty($arrClientsOrCasesIds)) {
                $strError = $this->_tr->translate('Nothing to export.');
            }

            if (empty($strError)) {
                $arrAllowedClients  = $this->_members->getMembersWhichICanAccess();
                $arrNoAccessClients = Settings::arrayDiff($arrClientsOrCasesIds, $arrAllowedClients);
                if (!empty($arrNoAccessClients)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            $searchType   = Json::decode($this->params()->fromPost('searchType'), Json::TYPE_ARRAY);
            $booSaveToLog = false;
            switch ($searchType) {
                case 'contacts':
                    $strFileName = $this->_tr->translate('Contacts search result');
                    break;

                case 'contacts_system_search':
                    $strFileName = $this->_tr->translate('All contacts');
                    break;

                case 'applicants_system_search':
                    $strFileName = $this->_tr->translate('Clients');
                    break;

                case 'applicants':
                default:
                    $booSaveToLog = true;
                    $strFileName  = $this->_tr->translate('Clients search result');
                    break;
            }

            $arrData = array();
            if (empty($strError)) {
                $arrClientsOrCasesIds = $this->_clients->getCasesFromTheList($arrClientsOrCasesIds, false);
                $arrCasesDetailedInfo = $this->_clients->getCasesStaticInfo($arrClientsOrCasesIds);
                $arrColumns           = array();
                foreach ($arrColumnsForExcel as $arrColumnInfo) {
                    $arrColumns[] = $arrColumnInfo['id'];
                }

                /** @var array $arrData */
                list($strError, $arrData, ,) = $this->_clients->getSearch()->loadDetailedClientsInfo($arrCasesDetailedInfo, $arrColumns, false, 0, 0, '', '', false);
            }

            // If there is case_dependants column -> replace it with the dependents list for each case
            if (empty($strMessage)) {
                $oFields            = $this->_clients->getFields();
                $arrDependentFields = $oFields->getDependantFields();
                $arrShowFields      = $this->_config['site_version']['dependants']['export_or_tooltip_fields'];

                $arrUpdatedColumns             = array();
                $booIsDependantsColumnExported = false;
                foreach ($arrColumnsForExcel as $arrColumnInfo) {
                    if ($arrColumnInfo['id'] == 'case_dependants') {
                        $booIsDependantsColumnExported = true;


                        foreach ($arrShowFields as $showDependantFieldId) {
                            foreach ($arrDependentFields as $arrDependentFieldInfo) {
                                if ($arrDependentFieldInfo['field_id'] == $showDependantFieldId) {
                                    $arrUpdatedColumns[] = array(
                                        'id'    => 'dependant_' . $arrDependentFieldInfo['field_id'],
                                        'name'  => $arrDependentFieldInfo['field_name'],
                                        'width' => 150
                                    );

                                    break;
                                }
                            }
                        }
                    } else {
                        $arrUpdatedColumns[] = $arrColumnInfo;
                    }
                }

                if ($booIsDependantsColumnExported) {
                    // case_dependants column found -> use updated list of columns + load the list of dependants for each case
                    $arrColumnsForExcel = $arrUpdatedColumns;

                    $arrCasesIds = array();
                    foreach ($arrData as $arrClientInfo) {
                        if (isset($arrClientInfo['case_id'])) {
                            $arrCasesIds[] = $arrClientInfo['case_id'];
                        }
                    }

                    if (!empty($arrCasesIds)) {
                        $arrDependents = $oFields->getDependents($arrCasesIds, false);

                        $arrGroupedDependents = array();
                        foreach ($arrDependents as $arrDependentInfo) {
                            $arrGroupedDependents[$arrDependentInfo['member_id']][] = $arrDependentInfo;
                        }


                        $arrUpdatedClientsData = array();
                        foreach ($arrData as $arrClientInfo) {
                            $arrUpdatedClientsData[] = $arrClientInfo;

                            if (isset($arrClientInfo['case_id']) && isset($arrGroupedDependents[$arrClientInfo['case_id']])) {
                                foreach ($arrGroupedDependents[$arrClientInfo['case_id']] as $arrDependentInfo) {
                                    $arrThisDependentFilteredData = array();
                                    foreach ($arrDependentFields as $arrDependentFieldInfo) {
                                        if (!in_array($arrDependentFieldInfo['field_id'], $arrShowFields)) {
                                            continue;
                                        }

                                        if (isset($arrDependentInfo[$arrDependentFieldInfo['field_id']])) {
                                            $value = $oFields->getDependentFieldReadableValue($arrDependentFieldInfo, $arrDependentInfo[$arrDependentFieldInfo['field_id']], '');
                                        } else {
                                            $value = '';
                                        }

                                        $arrThisDependentFilteredData['dependant_' . $arrDependentFieldInfo['field_id']] = $value;
                                    }

                                    if (!empty($arrThisDependentFilteredData)) {
                                        $arrUpdatedClientsData[] = $arrThisDependentFilteredData;
                                    }
                                }
                            }
                        }

                        $arrData = $arrUpdatedClientsData;
                    }
                }
            }


            if (empty($strError) && count($arrData)) {
                if ($booSaveToLog) {
                    $arrLog = array(
                        'log_section'     => 'client',
                        'log_action'      => 'export',
                        'log_description' => sprintf('Search Result Exported - %d records', count($arrData)),
                        'log_company_id'  => $this->_auth->getCurrentUserCompanyId(),
                        'log_created_by'  => $this->_auth->getCurrentUserId(),
                    );
                    $this->_accessLogs->saveLog($arrLog);
                }

                switch ($format) {
                    case 'csv':
                        $result = $this->_clients->getSearch()->exportSearchDataCSV($arrColumnsForExcel, $arrData);
                        if ($result !== false) {
                            $disposition    = "attachment; filename=\"$strFileName.csv\"";
                            $pointer        = fopen('php://output', 'wb');
                            $bufferedStream = new BufferedStream('text/csv', null, $disposition);
                            $bufferedStream->setStream($pointer);
                            foreach ($result as $row) {
                                fputcsv($pointer, $row);
                            }
                            return $bufferedStream;
                        }

                        break;

                    default:
                        $title  = 'Search Results';
                        $result = $this->_clients->getSearch()->exportSearchData($arrColumnsForExcel, $arrData, $title);
                        if ($result) {
                            $writer = new Xlsx($result);

                            $disposition = "attachment; filename=\"$strFileName.xlsx\"";

                            $pointer        = fopen('php://output', 'wb');
                            $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, $disposition);
                            $bufferedStream->setStream($pointer);

                            $writer->save('php://output');
                            fclose($pointer);

                            return $bufferedStream;
                        }
                        break;
                }

                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $view->setVariable('content', $strError);
        return $view;
    }

    public function printAction () {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setVariable('content', '');
        return $view;
    }

    public function toggleFavoriteAction()
    {
        $strError      = '';
        $strSuccess    = '';
        $booIsFavorite = false;
        try {
            // Check access rights for saved search id
            $searchId = $this->params()->fromPost('searchId');
            if (!$this->_clients->getSearch()->hasAccessToSavedSearch($searchId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $currentMemberId = $this->_auth->getCurrentUserId();
                $booIsFavorite   = $this->_clients->getSearch()->toggleMemberFavoriteSearch($currentMemberId, $searchId);
                $strSuccess      = $booIsFavorite ? $this->_tr->translate('Search was marked as favourite.') : $this->_tr->translate('Search was unmarked as favourite.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'            => empty($strError),
            'message'            => empty($strError) ? $strSuccess : $strError,
            'search_is_favorite' => $booIsFavorite
        );

        return new JsonModel($arrResult);
    }
}