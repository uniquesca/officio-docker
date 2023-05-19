<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Clients\Service\Clients\CaseCategories;
use Clients\Service\Clients\CaseStatuses;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Manage Case Statuses of the Company
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageCompanyCaseStatusesController extends BaseController
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var CaseCategories */
    protected $_caseCategories;

    /** @var CaseStatuses */
    protected $_caseStatuses;

    /** @var Clients\CaseTemplates */
    protected $_caseTemplates;

    public function initAdditionalServices(array $services)
    {
        $this->_company        = $services[Company::class];
        $this->_clients        = $services[Clients::class];
        $this->_caseCategories = $this->_clients->getCaseCategories();
        $this->_caseStatuses   = $this->_clients->getCaseStatuses();
        $this->_caseTemplates  = $this->_clients->getCaseTemplates();
    }

    private function _getCompanyId()
    {
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $companyId = $this->_auth->getCurrentUserCompanyId();
        } else {
            // Superadmin
            $companyId = (int)$this->params()->fromPost('company_id');
            if (empty($companyId)) {
                $companyId = 0;
            }
        }

        return $companyId;
    }

    /**
     * The default action - show "case statuses list"
     */
    public function indexAction()
    {
        $arrParams = [
            'booHideDefaultColumn'         => $this->_getCompanyId() == $this->_company->getDefaultCompanyId(),
            'statusesFieldLabelSingular'   => $this->_company->getCurrentCompanyDefaultLabel('case_status'),
            'statusesFieldLabelPlural'     => $this->_company->getCurrentCompanyDefaultLabel('case_status', true),
            'caseTypeFieldLabel'           => $this->_company->getCurrentCompanyDefaultLabel('case_type'),
            'caseTypeFieldLabelPlural'     => $this->_company->getCurrentCompanyDefaultLabel('case_type', true),
            'categoriesFieldLabelSingular' => $this->_company->getCurrentCompanyDefaultLabel('categories'),
            'categoriesFieldLabelPlural'   => $this->_company->getCurrentCompanyDefaultLabel('categories', true)
        ];

        $title = $this->_tr->translate('Workflow');
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $title = $this->_tr->translate('Default') . ' ' . $title;
        }

        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return new ViewModel($arrParams);
    }

    public function getCaseStatusesAction()
    {
        try {
            $arrCaseStatuses = $this->_caseStatuses->getCompanyCaseStatuses($this->_getCompanyId());
        } catch (Exception $e) {
            $arrCaseStatuses = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'rows'       => $arrCaseStatuses,
            'totalCount' => count($arrCaseStatuses)
        );

        return new JsonModel($arrResult);
    }

    public function saveCaseStatusAction()
    {
        $strError   = '';
        $statusId   = 0;
        $statusName = '';

        try {
            $filter = new StripTags();

            $caseStatusId     = $this->params()->fromPost('client_status_id');
            $statusName       = trim($filter->filter($this->params()->fromPost('client_status_name', '')));
            $booNewCaseStatus = empty($caseStatusId);

            if (empty($strError) && !$booNewCaseStatus && !$this->_caseStatuses->hasAccessToCaseStatus($caseStatusId, false)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !mb_strlen($statusName)) {
                $strError = $this->_tr->translate('Name is a required field.');
            }

            $companyId = $this->_getCompanyId();
            if (empty($strError)) {
                // Make sure that this name isn't used already
                $arrCaseStatuses = $this->_caseStatuses->getCompanyCaseStatuses($companyId);
                foreach ($arrCaseStatuses as $arrCaseStatusInfo) {
                    if ($arrCaseStatusInfo['client_status_name'] == $statusName && $caseStatusId != $arrCaseStatusInfo['client_status_id']) {
                        $strError = $this->_tr->translate('This name is already used.');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $savedStatusName = '';
                if (!$booNewCaseStatus) {
                    $arrSavedCaseStatusInfo = $this->_caseStatuses->getCompanyCaseStatusInfo($caseStatusId);
                    $savedStatusName        = $arrSavedCaseStatusInfo['client_status_name'];
                }

                // Don't update if the name wasn't changed
                if ($savedStatusName != $statusName) {
                    $arrCaseStatusInfo = [
                        'company_id'              => $companyId,
                        'client_status_id'        => $caseStatusId,
                        'client_status_parent_id' => null,
                        'client_status_name'      => $statusName,
                    ];

                    $statusId = $this->_caseStatuses->saveCompanyCaseStatus($booNewCaseStatus, $arrCaseStatusInfo);

                    if ($companyId == $this->_company->getDefaultCompanyId()) {
                        // Load the list of all companies ids only when we try to create new records
                        $arrCompaniesIds = $booNewCaseStatus ? $this->_company->getAllCompanies(true) : [];
                        $this->_caseStatuses->saveLinkedCompaniesCaseStatus($booNewCaseStatus, $statusId, $arrCaseStatusInfo, $arrCompaniesIds);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'     => empty($strError),
            'message'     => $strError,
            'status_id'   => $statusId,
            'status_name' => $statusName
        );

        return new JsonModel($arrResult);
    }

    public function deleteCaseStatusesAction()
    {
        $strError = '';

        try {
            $arrCaseStatusIds = Json::decode($this->params()->fromPost('arrCaseStatusIds'), Json::TYPE_ARRAY);

            if (!is_array($arrCaseStatusIds) || empty($arrCaseStatusIds)) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            if (empty($strError)) {
                foreach ($arrCaseStatusIds as $caseStatusId) {
                    if (!$this->_caseStatuses->hasAccessToCaseStatus($caseStatusId, false)) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $this->_caseStatuses->deleteCaseStatuses($arrCaseStatusIds);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function getCaseStatusesListsAction()
    {
        try {
            $arrCaseStatusesLists = $this->_caseStatuses->getCompanyCaseStatusLists($this->_getCompanyId());
            foreach ($arrCaseStatusesLists as $key => $arrCaseStatusListInfo) {
                unset($arrCaseStatusesLists[$key]['company_id']);

                $arrSavedCategories = $this->_caseStatuses->getCaseStatusListMappedCategories($arrCaseStatusListInfo['client_status_list_id'], false);

                $arrSavedCategoriesNamesOnly = [];
                foreach ($arrSavedCategories as $arrSavedCategoryInfo) {
                    $arrSavedCategoriesNamesOnly[] = $arrSavedCategoryInfo['client_category_name'];
                }
                $arrCaseStatusesLists[$key]['client_status_list_categories'] = $arrSavedCategoriesNamesOnly;

                $arrCaseStatusesLists[$key]['client_status_list_case_types'] = $this->_caseStatuses->getCaseStatusListMappedCaseTypes($arrCaseStatusListInfo['client_status_list_id']);

                $arrSavedStatuses        = $this->_caseStatuses->getCaseStatusListMappedStatuses($arrCaseStatusListInfo['client_status_list_id'], false);
                $arrSavedStatusNamesOnly = [];
                foreach ($arrSavedStatuses as $arrSavedStatusInfo) {
                    $arrSavedStatusNamesOnly[] = $arrSavedStatusInfo['client_status_name'];
                }
                $arrCaseStatusesLists[$key]['client_status_list_statuses'] = $arrSavedStatusNamesOnly;
            }
        } catch (Exception $e) {
            $arrCaseStatusesLists = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'rows'       => $arrCaseStatusesLists,
            'totalCount' => count($arrCaseStatusesLists)
        );

        return new JsonModel($arrResult);
    }

    public function getCaseStatusListInfoAction()
    {
        $strError                     = '';
        $caseStatusListName           = '';
        $arrCaseStatuses              = [];
        $arrAssignedCaseStatusesIds   = [];
        $arrCaseCategories            = [];
        $arrAssignedCaseCategoriesIds = [];

        try {
            $caseStatusListId = Json::decode($this->params()->fromPost('client_status_list_id'), Json::TYPE_ARRAY);

            if (!empty($caseStatusListId) && !$this->_caseStatuses->hasAccessToCaseStatusList($caseStatusListId, true)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $companyId = $this->_getCompanyId();

                if (!empty($caseStatusListId)) {
                    $arrSavedCaseStatusListInfo = $this->_caseStatuses->getCompanyCaseStatusListInfo($caseStatusListId);
                    $caseStatusListName         = $arrSavedCaseStatusListInfo['client_status_list_name'];

                    $arrAssignedCaseCategoriesIds = $this->_caseStatuses->getCaseStatusListMappedCategories($caseStatusListId);
                    $arrAssignedCaseStatusesIds   = $this->_caseStatuses->getCaseStatusListMappedStatuses($caseStatusListId);
                }


                $arrCaseCategories = $this->_clients->getCaseCategories()->getCompanyCaseCategories($companyId);
                $arrCaseStatuses   = $this->_caseStatuses->getCompanyCaseStatuses($companyId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'                 => empty($strError),
            'message'                 => $strError,
            'case_categories'         => $arrCaseCategories,
            'assigned_categories_ids' => $arrAssignedCaseCategoriesIds,
            'case_status_list_name'   => $caseStatusListName,
            'case_statuses'           => $arrCaseStatuses,
            'assigned_statuses_ids'   => $arrAssignedCaseStatusesIds,
        );

        return new JsonModel($arrResult);
    }

    public function manageCaseStatusListAction()
    {
        set_time_limit(10 * 60); // 10 minutes, no more
        ini_set('memory_limit', '-1');

        $strError = '';

        try {
            $caseStatusListId                     = Json::decode($this->params()->fromPost('client_status_list_id'), Json::TYPE_ARRAY);
            $caseStatusListName                   = trim(Json::decode($this->params()->fromPost('client_status_list_name', ''), Json::TYPE_ARRAY));
            $caseStatusListAssignedCaseCategories = Json::decode($this->params()->fromPost('assigned_case_categories', ''), Json::TYPE_ARRAY);
            $caseStatusListAssignedCaseStatuses   = Json::decode($this->params()->fromPost('assigned_case_statuses'), Json::TYPE_ARRAY);

            $booNewCaseStatusList = empty($caseStatusListId);
            if (!$booNewCaseStatusList && !$this->_caseStatuses->hasAccessToCaseStatusList($caseStatusListId, false)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !mb_strlen($caseStatusListName)) {
                $strError = $this->_tr->translate('Name is a required field.');
            }

            if (empty($strError)) {
                // No categories can be selected
                if (empty($caseStatusListAssignedCaseCategories)) {
                    $caseStatusListAssignedCaseCategories = array();
                } else {
                    $caseStatusListAssignedCaseCategories = explode(',', $caseStatusListAssignedCaseCategories);
                    foreach ($caseStatusListAssignedCaseCategories as $caseCategoryId) {
                        if (!$this->_caseCategories->hasAccessToCaseCategory($caseCategoryId, true)) {
                            $strError = $this->_tr->translate(
                                sprintf(
                                    'Insufficient access rights to the %s.',
                                    $this->_company->getCurrentCompanyDefaultLabel('categories')
                                )
                            );
                            break;
                        }
                    }
                }
            }

            if (empty($strError) && !empty($caseStatusListAssignedCaseStatuses)) {
                foreach ($caseStatusListAssignedCaseStatuses as $caseStatusId) {
                    if (!$this->_caseStatuses->hasAccessToCaseStatus($caseStatusId, true)) {
                        $strError = $this->_tr->translate(
                            sprintf(
                                'Insufficient access rights to the %s.',
                                $this->_company->getCurrentCompanyDefaultLabel('case_status')
                            )
                        );
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $currentCompanyId = $this->_getCompanyId();
                $defaultCompanyId = $this->_company->getDefaultCompanyId();

                $arrCompaniesIds   = [];
                if ($currentCompanyId == $defaultCompanyId) {
                    // Load the list of all companies only when we try to manage default records
                    $arrCompaniesIds = $this->_company->getAllCompanies(true);
                }

                $savedCaseStatusListName = '';
                if (!$booNewCaseStatusList) {
                    $arrSavedCaseStatusListInfo = $this->_caseStatuses->getCompanyCaseStatusListInfo($caseStatusListId);
                    $savedCaseStatusListName    = $arrSavedCaseStatusListInfo['client_status_list_name'];
                }

                // Update the name if it was changed
                if ($savedCaseStatusListName != $caseStatusListName) {
                    $arrCaseStatusListInfo = [
                        'company_id'                   => $currentCompanyId,
                        'client_status_list_id'        => $caseStatusListId,
                        'client_status_list_parent_id' => null,
                        'client_status_list_name'      => $caseStatusListName,
                    ];

                    $caseStatusListId = $this->_caseStatuses->saveCompanyCaseStatusList($booNewCaseStatusList, $arrCaseStatusListInfo);

                    if ($currentCompanyId == $defaultCompanyId) {
                        $this->_caseStatuses->saveLinkedCompaniesCaseStatusList($booNewCaseStatusList, $caseStatusListId, $arrCaseStatusListInfo, $arrCompaniesIds);
                    }
                }

                if ($currentCompanyId != $defaultCompanyId) {
                    // Save the changes in categories selection (if any)
                    $arrSavedCaseStatusListCategoriesGrouped = [];
                    if (!$booNewCaseStatusList) {
                        $arrSavedCaseStatusListCategories = $this->_caseStatuses->getCaseStatusListMappedCategories($caseStatusListId, false);
                        foreach ($arrSavedCaseStatusListCategories as $arrSavedCaseStatusListCategoryInfo) {
                            $arrSavedCaseStatusListCategoriesGrouped[$arrSavedCaseStatusListCategoryInfo['client_category_id']] = $arrSavedCaseStatusListCategoryInfo;
                        }
                    }

                    foreach ($caseStatusListAssignedCaseCategories as $newCategoryId) {
                        if (!in_array($newCategoryId, array_keys($arrSavedCaseStatusListCategoriesGrouped))) {
                            $this->_caseCategories->updateCategoryInfo(
                                [
                                    'client_status_custom_list_id' => $caseStatusListId
                                ],
                                [
                                    'client_category_id' => $newCategoryId
                                ]
                            );
                        }
                    }

                    foreach ($arrSavedCaseStatusListCategoriesGrouped as $savedCategoryId => $arrSavedCategoryInfo) {
                        if (!in_array($savedCategoryId, $caseStatusListAssignedCaseCategories)) {
                            if (!empty($arrSavedCategoryInfo['client_status_custom_list_id'])) {
                                $this->_caseCategories->updateCategoryInfo(
                                    [
                                        'client_status_custom_list_id' => null
                                    ],
                                    [
                                        'client_category_id' => $savedCategoryId
                                    ]
                                );
                            } else {
                                $this->_caseCategories->updateCategoryInfo(
                                    [
                                        'client_status_list_id' => $arrSavedCategoryInfo['default_client_status_list_id']
                                    ],
                                    [
                                        'client_category_id' => $savedCategoryId
                                    ]
                                );
                            }
                        }
                    }
                }

                $arrCompaniesLists = [];
                if ($currentCompanyId == $defaultCompanyId) {
                    // Load companies lists only when we try to manage default records
                    foreach ($arrCompaniesIds as $companyId) {
                        $arrCompaniesLists[$companyId] = $this->_caseStatuses->getCompanyCaseStatusLists($companyId);
                    }
                }


                // Save the changes in statuses selection (if any)
                $arrSavedCaseStatusListStatuses = [];
                if (!$booNewCaseStatusList) {
                    $arrSavedCaseStatusListStatuses = $this->_caseStatuses->getCaseStatusListMappedStatuses($caseStatusListId);
                }

                // Please note that we check also the order of selected statuses
                if ($arrSavedCaseStatusListStatuses !== $caseStatusListAssignedCaseStatuses) {
                    $this->_caseStatuses->saveCaseStatusListMappingToStatuses($caseStatusListId, $caseStatusListAssignedCaseStatuses);

                    // Save changes for all companies
                    if ($currentCompanyId == $defaultCompanyId) {
                        foreach ($arrCompaniesIds as $companyId) {
                            // Skip companies that don't have case status lists
                            if (empty($arrCompaniesLists[$companyId])) {
                                continue;
                            }

                            // Create a list of custom statuses
                            $arrCompanyNotMappedStatuses = [];
                            $arrCompanyStatuses          = $this->_caseStatuses->getCompanyCaseStatuses($companyId);
                            foreach ($arrCompanyStatuses as $arrCompanyStatusInfo) {
                                if (empty($arrCompanyStatusInfo['client_status_parent_id'])) {
                                    $arrCompanyNotMappedStatuses[] = $arrCompanyStatusInfo['client_status_id'];
                                }
                            }

                            foreach ($arrCompaniesLists[$companyId] as $arrCompanyCaseStatusListInfo) {
                                if ($caseStatusListId == $arrCompanyCaseStatusListInfo['client_status_list_parent_id']) {
                                    $arrCompanyEnabledStatuses = [];
                                    foreach ($caseStatusListAssignedCaseStatuses as $parentStatusId) {
                                        foreach ($arrCompanyStatuses as $arrCompanyStatusInfo) {
                                            if ($arrCompanyStatusInfo['client_status_parent_id'] == $parentStatusId) {
                                                $arrCompanyEnabledStatuses[] = $arrCompanyStatusInfo['client_status_id'];
                                                break;
                                            }
                                        }
                                    }

                                    $this->_caseStatuses->saveCaseStatusListMappingToStatuses($arrCompanyCaseStatusListInfo['client_status_list_id'], $arrCompanyEnabledStatuses, $arrCompanyNotMappedStatuses);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function deleteCaseStatusesListAction()
    {
        $strError = '';

        try {
            $arrCaseStatusListIds = Json::decode($this->params()->fromPost('arrCaseStatusListIds'), Json::TYPE_ARRAY);

            if (!is_array($arrCaseStatusListIds) || empty($arrCaseStatusListIds)) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            if (empty($strError)) {
                foreach ($arrCaseStatusListIds as $caseStatusListId) {
                    if (!$this->_caseStatuses->hasAccessToCaseStatusList($caseStatusListId, false)) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $this->_caseStatuses->deleteCaseStatusLists($arrCaseStatusListIds);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}
