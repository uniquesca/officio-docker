<?php

namespace Applicants\Controller;

use Clients\Service\Members;
use Exception;
use Forms\Service\Pdf;
use Laminas\Filter\StripTags;
use Clients\Service\Clients;
use Clients\Service\ClientsVisaSurvey;
use Officio\Common\Json;
use Officio\BaseController;
use Officio\Common\Service\Encryption;
use Officio\Service\Company;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\Common\Service\Settings;
use Officio\Service\Payment\Stripe;
use Officio\Service\SystemTriggers;
use Tasks\Service\Tasks;

/**
 * Applicants main Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Tasks */
    protected $_tasks;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var Pdf */
    protected $_pdf;

    /** @var ClientsVisaSurvey */
    protected $_applicantVisaSurvey;

    /** @var Stripe */
    protected $_stripe;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_triggers = $services[SystemTriggers::class];
        $this->_pdf = $services[Pdf::class];
        $this->_tasks = $services[Tasks::class];
        $this->_applicantVisaSurvey = $services[ClientsVisaSurvey::class];
        $this->_stripe = $services[Stripe::class];
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
        $strError      = '';
        $arrApplicants = array();

        try {
            $filter       = new StripTags();
            $memberType   = $filter->filter($this->params()->fromPost('memberType'));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && empty($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            if (empty($strError) && !in_array($memberType, array('individual', 'employer'))) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            if (empty($strError)) {
                $arrApplicants = $this->_clients->getApplicants($memberType);

                // For individuals load cases list and use first case name
                if ($memberType == 'individual') {
                    $arrApplicantIds = array();
                    foreach ($arrApplicants as $arrApplicantInfo) {
                        $arrApplicantIds[] = $arrApplicantInfo['user_id'];
                    }
                    $arrCases = $this->_clients->getApplicantsCases($arrApplicantIds);

                    foreach ($arrApplicants as $key => $arrApplicantInfo) {
                        if (!empty($arrCases[$arrApplicantInfo['user_id']]) && strlen($arrCases[$arrApplicantInfo['user_id']][0]['case_name'] ?? '')) {
                            $arrApplicants[$key]['user_name'] .= ' (' . $arrCases[$arrApplicantInfo['user_id']][0]['case_name'] . ')';
                        }
                    }
                }

                // Add "new client" option if needed
                $booAddNew = (int)$this->params()->fromPost('booAddNew');
                if ($booAddNew) {
                    $arrAddNew = array(
                        array(
                            'user_id'        => 0,
                            'user_type'      => $memberType,
                            'user_name'      => $this->_tr->translate('-- a New Client --'),
                            'applicant_id'   => 0,
                            'applicant_name' => ''
                        )
                    );

                    $arrApplicants = array_merge($arrAddNew, $arrApplicants);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrApplicants,
            'count'   => count($arrApplicants),
        );

        return new JsonModel($arrResult);
    }

    public function getCasesListAction()
    {
        ini_set('memory_limit', '-1');

        $strError         = '';
        $clientsCount     = 0;
        $arrClientsParsed = array();

        try {
            $query                   = trim($this->params()->fromPost('query', ''));
            $parentMemberId          = (int)$this->params()->fromPost('parentMemberId');
            $exceptCaseId            = (int)$this->params()->fromPost('exceptCaseId');
            $booLimitCases           = (bool)$this->params()->fromPost('booLimitCases', false);
            $booCategoryMustBeLinked = (bool)$this->params()->fromPost('booCategoryMustBeLinked', false);

            if (!empty($parentMemberId) && !$this->_members->hasCurrentMemberAccessToMember($parentMemberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $arrAllCases = empty($strError) ? $this->_clients->getClientsList() : [];
            if (empty($strError) && !empty($exceptCaseId) && !empty($arrAllCases)) {
                // Get only active cases
                $arrActiveClients = $this->_clients->getActiveClientsList(array_keys($arrAllCases), true);
                if (empty($arrActiveClients)) {
                    $arrAllCases = [];
                } else {
                    $arrAllCases = array_intersect_key($arrAllCases, array_flip($arrActiveClients));
                }
            }

            $arrExceptCases = [];
            if (empty($strError) && !empty($exceptCaseId) && !empty($arrAllCases)) {
                if (!$this->_members->hasCurrentMemberAccessToMember($exceptCaseId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                } else {
                    $arrExceptCases[] = $exceptCaseId;

                    $companyId = $this->_auth->getCurrentUserCompanyId();

                    // Skip already assigned cases
                    $arrSavedExceptCaseIds = $this->_clients->getCasesThatHaveLinkedEmployerCases($companyId);
                    if (!empty($arrSavedExceptCaseIds)) {
                        $arrExceptCases = array_merge($arrExceptCases, $arrSavedExceptCaseIds);
                    }

                    if ($booCategoryMustBeLinked) {
                        // Get this case's employer
                        $employerId   = 0;
                        $arrEmployers = $this->_clients->getParentsForAssignedApplicant($exceptCaseId, $this->_clients->getMemberTypeIdByName('employer'));
                        if (!empty($arrEmployers)) {
                            $employerId = $arrEmployers[0];
                        }

                        // Skip cases that are already assigned to employers
                        $arrEmployerIds = $this->_company->getCompanyMembersIds($companyId, 'employer');
                        if (!empty($employerId)) {
                            unset($arrEmployerIds[array_flip($arrEmployerIds)[$employerId]]);
                        }

                        if (!empty($arrEmployerIds)) {
                            $arrAssignedCasesIds = $this->_clients->getAssignedCases($arrEmployerIds);
                            if (!empty($arrAssignedCasesIds)) {
                                $arrExceptCases = array_merge($arrExceptCases, $arrAssignedCasesIds);
                            }
                        }

                        // Cases must have categories that can be linked
                        $arrCategories = $this->_clients->getCaseCategories()->getCompanyCaseCategories($companyId);

                        $arrCategoriesCanBeLinked = [];
                        foreach ($arrCategories as $arrCategoryInfo) {
                            if ($arrCategoryInfo['client_category_link_to_employer'] == 'Y') {
                                $arrCategoriesCanBeLinked[] = $arrCategoryInfo['client_category_id'];
                            }
                        }

                        if (empty($arrCategoriesCanBeLinked)) {
                            $arrAllCases = [];
                        } else {
                            $categoriesFieldId = $this->_clients->getFields()->getFieldIdByType($companyId, 'categories');
                            if (!empty($categoriesFieldId)) {
                                $arrSavedCasesCategories = $this->_clients->getFields()->getClientsFieldDataValue($categoriesFieldId[0], array_keys($arrAllCases));

                                $arrCasesWithCategoriesThatCanBeLinked = [];
                                foreach ($arrSavedCasesCategories as $arrSavedCasesCategoryInfo) {
                                    if (in_array($arrSavedCasesCategoryInfo['value'], $arrCategoriesCanBeLinked)) {
                                        $arrCasesWithCategoriesThatCanBeLinked[] = $arrSavedCasesCategoryInfo['member_id'];
                                    }
                                }

                                foreach ($arrAllCases as $key => $arrCaseInfo) {
                                    if (!in_array($key, $arrCasesWithCategoriesThatCanBeLinked)) {
                                        $arrExceptCases[] = $key;
                                    }
                                }
                            }
                        }
                    } else {
                        // Ignore cases that have the "Employer Sponsorship Case Type" checkbox UNCHECKED in the case type properties
                        $arrCompanyCaseTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId, false, null, false, false);

                        $arrCompanyCaseTemplatesIgnore = [];
                        foreach ($arrCompanyCaseTemplates as $arrCompanyCaseTemplateInfo) {
                            if ($arrCompanyCaseTemplateInfo['case_template_employer_sponsorship'] == 'N') {
                                $arrCompanyCaseTemplatesIgnore[] = $arrCompanyCaseTemplateInfo['case_template_id'];
                            }
                        }

                        if (!empty($arrCompanyCaseTemplatesIgnore)) {
                            foreach ($arrAllCases as $arrCaseInfo) {
                                if (empty($arrCaseInfo['client_type_id']) || in_array($arrCaseInfo['client_type_id'], $arrCompanyCaseTemplatesIgnore)) {
                                    $arrExceptCases[] = $arrCaseInfo['member_id'];
                                }
                            }
                        }
                    }
                }
            }

            if (empty($strError) && !empty($arrAllCases)) {
                $arrClientsParsed = $this->_clients->getCasesListWithParents($arrAllCases, $parentMemberId, Settings::arrayUnique(array_map('intval', $arrExceptCases)), $query);
                $clientsCount     = count($arrClientsParsed);

                // Return only first 100 cases if needed
                if ($booLimitCases) {
                    $arrClientsParsed = array_slice($arrClientsParsed, 0, 100);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'    => empty($strError),
            'msg'        => $strError,
            'rows'       => $arrClientsParsed,
            'totalCount' => $clientsCount
        );

        return new JsonModel($arrResult);
    }

    public function getAssignedCasesListAction()
    {
        $strError            = '';
        $arrCases            = array();
        $totalCasesCount     = 0;
        $booAllowCreateCases = false;
        $booAllowDeleteCases = false;
        $booAllowEditCases   = false;
        $applicantId         = 0;
        $applicantName       = '';
        $applicantType       = '';

        try {
            $filter      = new StripTags();
            $sort        = $this->params()->fromPost('sort');
            $dir         = $this->params()->fromPost('dir');
            $start       = (int)$this->params()->fromPost('start');
            $limit       = (int)$this->params()->fromPost('limit');
            $applicantId = (int)$this->params()->fromPost('applicantId');

            $params       = array_merge($this->params()->fromPost(), $this->params()->fromQuery());
            $arrAllParams = Settings::filterParamsArray($params, $filter);

            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                // Save applicant details for later usage
                $arrApplicantInfo = $this->_members->getMemberInfo($applicantId);
                $applicantId = $arrApplicantInfo['member_id'];
                $applicantName = $arrApplicantInfo['full_name'];

                // Load parent client type
                $applicantType = $this->_clients->getMemberTypeNameById($arrApplicantInfo['userType']);

                // Load client's assigned cases list
                $caseIdLinkedTo     = Json::decode($this->params()->fromPost('caseIdLinkedTo', 0), Json::TYPE_ARRAY);
                $booOnlyActiveCases = Json::decode($this->params()->fromPost('booOnlyActiveCases', 0), Json::TYPE_ARRAY);
                $companyId          = $this->_auth->getCurrentUserCompanyId();
                list($arrAssignedCases, $totalCasesCount) = $this->_clients->getApplicantAssignedCases($companyId, $applicantId, $booOnlyActiveCases, $caseIdLinkedTo, $start, $limit);

                // Load ALL client's assigned cases list (active and inactive)
                if ($booOnlyActiveCases) {
                    list(, $allCasesCount) = $this->_clients->getApplicantAssignedCases($companyId, $applicantId, false, $caseIdLinkedTo, $start, $limit);
                } else {
                    // Don't do the additional request
                    $allCasesCount = $totalCasesCount;
                }

                // Load cases' parent clients (can be IA, even if the current client is Employer)
                $arrAssignedCasesIds = array();
                foreach ($arrAssignedCases as $arrCaseInfo) {
                    $arrAssignedCasesIds[] = $arrCaseInfo['child_member_id'];
                }
                $arrCasesParents = $this->_clients->getParentsForAssignedApplicants($arrAssignedCasesIds, false, false);

                // Get the list of additional case's fields we need to load
                $arrFieldsToLoad = array();
                if(array_key_exists('arrFieldsToLoad', $arrAllParams)) {
                    $arrFieldsToLoad = Json::decode($arrAllParams['arrFieldsToLoad'], Json::TYPE_ARRAY);
                }
                $arrFieldsToLoad = is_array($arrFieldsToLoad) ? $arrFieldsToLoad : array();

                // Load additional case's fields, if needed
                $arrCasesSavedInfo       = array();
                $booLoadDetailedCaseInfo = $this->params()->fromPost('booLoadDetailedCaseInfo', false);
                if ($booLoadDetailedCaseInfo) {
                    $arrCaseFields       = array('file_status', 'visa_subclass', 'registered_migrant_agent');
                    $arrAccountingFields = array('outstanding_balance_secondary', 'outstanding_balance_primary');
                    $arrColumns          = array();
                    foreach ($arrCaseFields as $caseFieldId) {
                        $arrColumns[] = 'case_' . $caseFieldId;
                    }

                    foreach ($arrAccountingFields as $accountingFieldId) {
                        $arrColumns[] = 'accounting_' . $accountingFieldId;
                    }


                    $arrCasesDetailedInfo = $this->_clients->getCasesStaticInfo($arrAssignedCasesIds);
                    list($strError, $arrSearchResult, ,) = $this->_clients->getSearch()->loadDetailedClientsInfo($arrCasesDetailedInfo, $arrColumns, false, 0, 0, '', '', false);

                    if (empty($strError)) {
                        foreach ($arrSearchResult as $arrData) {
                            foreach ($arrCaseFields as $caseFieldId) {
                                if (isset($arrData['case_' . $caseFieldId])) {
                                    $arrCasesSavedInfo[$arrData['case_id']][$caseFieldId] = $arrData['case_' . $caseFieldId];
                                }
                            }

                            foreach ($arrAccountingFields as $accountingFieldId) {
                                if (isset($arrData['accounting_' . $accountingFieldId]) && isset($arrData['case_id'])) {
                                    $arrCasesSavedInfo[$arrData['case_id']][$accountingFieldId] = $arrData['accounting_' . $accountingFieldId];
                                }
                            }
                        }
                    }
                }

                // Load ids for client fields that we want to load additionally
                $arrApplicantFieldIds   = [];
                $arrInternalContactType = Members::getMemberType('internal_contact');
                if (in_array('parent_DOB', $arrFieldsToLoad)) {
                    $arrApplicantFieldIds['parent_DOB'] = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('DOB', $arrInternalContactType);
                    if (empty($arrApplicantFieldIds['parent_DOB'])) {
                        unset($arrApplicantFieldIds['parent_DOB']);
                    }
                }

                if (in_array('parent_country_of_residence', $arrFieldsToLoad)) {
                    $arrApplicantFieldIds['parent_country_of_residence'] = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('country_of_residence', $arrInternalContactType);
                    if (empty($arrApplicantFieldIds['parent_country_of_residence'])) {
                        unset($arrApplicantFieldIds['parent_country_of_residence']);
                    }
                }

                // Get linked cases
                $arrCasesLinks = [];
                if (!empty($arrAssignedCasesIds)) {
                    $arrCasesLinks = $this->_clients->getCasesLinkedEmployerCases($arrAssignedCasesIds);
                }

                // Generate result
                foreach ($arrAssignedCases as $arrCaseInfo) {
                    $arrCaseInfo = $this->_clients->generateClientName($arrCaseInfo);

                    $arrPreparedCaseInfo = array(
                        'case_id'             => $arrCaseInfo['child_member_id'],
                        'case_first_name'     => $arrCaseInfo['fName'],
                        'case_last_name'      => $arrCaseInfo['lName'],
                        'case_full_name'      => $arrCaseInfo['full_name_with_file_num'],
                        'case_email'          => $arrCaseInfo['emailAddress'],
                        'case_type'           => $arrCaseInfo['client_type_name'] ?? '',
                        'case_type_id'        => $arrCaseInfo['client_type_id'] ?? '',
                        'case_file_number'    => $arrCaseInfo['fileNumber'] ?? '',
                        'case_date_signed_on' => date('Y-m-d H:i:s', $arrCaseInfo['regTime']),
                    );

                    // Return parent IA info, so we can open the correct tab
                    $arrParentInfo = [];
                    foreach ($arrCasesParents as $arrCasesParentInfo) {
                        if ($arrCasesParentInfo['child_member_id'] == $arrCaseInfo['child_member_id']) {
                            if (empty($arrParentInfo)) {
                                $arrParentInfo = $arrCasesParentInfo;

                                $arrPreparedCaseInfo['applicant_id']      = $arrParentInfo['parent_member_id'];
                                $arrPreparedCaseInfo['applicant_type']    = $arrParentInfo['member_type_name'];
                                $arrPreparedCaseInfo['applicant_name']    = $this->_clients->generateApplicantName($arrParentInfo);
                                $arrPreparedCaseInfo['parent_first_name'] = $arrParentInfo['fName'];
                                $arrPreparedCaseInfo['parent_last_name']  = $arrParentInfo['lName'];
                            } elseif ($arrParentInfo['member_type_name'] == 'employer') {
                                $arrPreparedCaseInfo['employer_id']   = $arrParentInfo['parent_member_id'];
                                $arrPreparedCaseInfo['employer_name'] = $this->_clients->generateApplicantName($arrParentInfo);

                                $arrParentInfo = $arrCasesParentInfo;

                                $arrPreparedCaseInfo['applicant_id']      = $arrParentInfo['parent_member_id'];
                                $arrPreparedCaseInfo['applicant_type']    = $arrParentInfo['member_type_name'];
                                $arrPreparedCaseInfo['applicant_name']    = $this->_clients->generateApplicantName($arrParentInfo);
                                $arrPreparedCaseInfo['parent_first_name'] = $arrParentInfo['fName'];
                                $arrPreparedCaseInfo['parent_last_name']  = $arrParentInfo['lName'];
                            } else {
                                $arrParentInfo = $arrCasesParentInfo;

                                $arrPreparedCaseInfo['employer_id']   = $arrParentInfo['parent_member_id'];
                                $arrPreparedCaseInfo['employer_name'] = $this->_clients->generateApplicantName($arrParentInfo);
                            }
                        }
                    }

                    if (!empty($arrPreparedCaseInfo['employer_id']) && isset($arrCasesLinks[$arrCaseInfo['child_member_id']]) && !empty($arrCasesLinks[$arrCaseInfo['child_member_id']]['fileNumber'])) {
                        $arrPreparedCaseInfo['employer_linked_case_file_number'] = $arrCasesLinks[$arrCaseInfo['child_member_id']]['fileNumber'];
                    }

                    // Load additional saved case's parent info
                    foreach ($arrApplicantFieldIds as $applicantColumn => $applicantFieldId) {
                        $arrInternalContacts = $this->_clients->getAssignedContacts($arrPreparedCaseInfo['applicant_id'], true);
                        if (!empty($arrInternalContacts)) {
                            $arrPreparedCaseInfo[$applicantColumn] = $this->_clients->getApplicantFields()->getFieldDataValue($arrInternalContacts[0], $applicantFieldId);
                        }
                    }

                    // Also return additional case's fields
                    if(array_key_exists($arrCaseInfo['child_member_id'], $arrCasesSavedInfo)) {
                        $arrThisCaseSavedInfo = $arrCasesSavedInfo[$arrCaseInfo['child_member_id']];
                        foreach ($arrThisCaseSavedInfo as $fieldId => $fieldVal) {
                            if(in_array($fieldId, $arrFieldsToLoad)) {
                                $arrPreparedCaseInfo[$fieldId] = $fieldVal;
                            }
                        }
                    }

                    $arrCases[] = $arrPreparedCaseInfo;
                }

                // Sort result
                if(count($arrCases) && in_array($sort, array_keys($arrCases[0]))) {
                    foreach ($arrCases as $key => $row) {
                        $arrGrouped[$key]  = strtolower($row[$sort] ?? '');
                    }
                    array_multisort($arrGrouped, $dir == 'DESC' ? SORT_DESC : SORT_ASC, $arrCases);
                }

                // Don't try to group if case id was passed, e.g. for Sponsored Cases grid or for individual client
                if (!empty($arrCasesLinks) && !$booLoadDetailedCaseInfo && $applicantType != 'individual') {
                    $arrTopLevel    = [];
                    $arrSecondLevel = [];
                    foreach ($arrCases as $arrMemberInfo) {
                        if (isset($arrCasesLinks[$arrMemberInfo['case_id']])) {
                            $arrMemberInfo['employer_sub_case'] = true;

                            $arrSecondLevel[$arrCasesLinks[$arrMemberInfo['case_id']]['linkedCaseId']][] = $arrMemberInfo;
                        } else {
                            $arrTopLevel[] = $arrMemberInfo;
                        }
                    }

                    $arrRealSortedData = [];
                    foreach ($arrTopLevel as $arrTopLevelRecord) {
                        $arrRealSortedData[] = $arrTopLevelRecord;
                        if (isset($arrSecondLevel[$arrTopLevelRecord['case_id']])) {
                            foreach ($arrSecondLevel[$arrTopLevelRecord['case_id']] as $arrSecondLevelRecord) {
                                $arrRealSortedData[] = $arrSecondLevelRecord;
                            }
                        }
                    }
                    $arrCases = $arrRealSortedData;
                }

                // Check if we allow creating new cases
                $booAllowEditCases = $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($applicantId);

                if ($this->_acl->isAllowed('clients-profile-new')) {
                    $booEnabledCaseManagement = $this->_company->isCaseManagementEnabledToCompany($companyId) && $booAllowEditCases;
                    $booAllowCreateCases      = ($booEnabledCaseManagement || empty($allCasesCount)) && $booAllowEditCases;
                }

                if ($this->_acl->isAllowed('clients-profile-delete')) {
                    $booAllowDeleteCases = $booAllowEditCases;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'                => empty($strError),
            'applicant_id'           => $applicantId,
            'applicant_name'         => $applicantName,
            'applicant_type'         => $applicantType,
            'msg'                    => $strError,
            'items'                  => $arrCases,
            'count'                  => $totalCasesCount,
            'booAllowCreateCases'    => $booAllowCreateCases,
            'booAllowDeleteCases'    => $booAllowDeleteCases,
            'booAllowUpdateCases'    => $booAllowEditCases,
        );

        return new JsonModel($arrResult);
    }

    public function getTasksListAction()
    {
        $view = new JsonModel();
        $strError = '';
        $arrCaseTasks = array();

        try {
            $arrTasks = $this->_tasks->getMemberTasks(
                'client',
                array(
                    'assigned'    => 'me',
                    'status'      => 'active',
                    'task_is_due' => 'Y'
                ),
                'task_due_on',
                'ASC'
            );

            $arrCasesIds = array();
            foreach ($arrTasks as $arTaskInfo) {
                $arrCasesIds[] = $arTaskInfo['member_id'];
            }
            $arrCasesIds = array_unique($arrCasesIds);

            $arrCasesParents = $this->_clients->getApplicantFields()->getCasesWithParents($arrCasesIds);
            $arrCaseEmployerParents = $this->_clients->getParentsForAssignedApplicants($arrCasesIds);
            $employerTypeId = $this->_clients->getMemberTypeIdByName('employer');

            foreach ($arrTasks as $arTaskInfo) {
                $arrParentInfo = array();
                foreach ($arrCasesParents as $arrCasesParentInfo) {
                    if($arrCasesParentInfo['case_id'] == $arTaskInfo['member_id']) {
                        $arrParentInfo = $arrCasesParentInfo;
                    }
                }

                if(!is_array($arrParentInfo) || !count($arrParentInfo)) {
                    continue;
                }

                $caseEmployerId = $caseEmployerName = null;
                if($arrParentInfo['applicant_type'] != $employerTypeId && array_key_exists($arrParentInfo['case_id'], $arrCaseEmployerParents)) {
                    $caseEmployerId = $arrCaseEmployerParents[$arrParentInfo['case_id']]['parent_member_id'];
                    $caseEmployerName = $this->_clients->generateApplicantName($arrCaseEmployerParents[$arrParentInfo['case_id']]);
                }

                $arrCaseTasks[] = array(
                    'taskId'            => $arTaskInfo['task_id'],
                    'taskName'          => $arTaskInfo['task_subject'],
                    'applicantId'       => $arrParentInfo['applicant_id'],
                    'applicantName'     => $arrParentInfo['applicant_name'],
                    'applicantType'     => $arrParentInfo['applicant_type'],
                    'caseId'            => $arrParentInfo['case_id'],
                    'caseName'          => $arrParentInfo['case_name'],
                    'caseType'          => $arrParentInfo['case_type'],
                    'caseAndClientName' => $arrParentInfo['case_and_applicant_name'],
                    'caseEmployerId'    => $caseEmployerId,
                    'caseEmployerName'  => $caseEmployerName,
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrCaseTasks,
            'count'   => count($arrCaseTasks),
        );

        return $view->setVariables($arrResult);
    }

    public function openLinkAction()
    {
        $view = new ViewModel();
        switch ($this->findParam('id')) {
            case 'EOI_ID':
            case 'EOI_password':
                $view->setVariables(
                    [
                        'url' => 'https://skillselect.gov.au/skillselect/logon/Login.aspx',
                        'pass' => $this->findParam('val2'),
                        'login' => $this->findParam('val1')
                    ]
                );

                $view->setTemplate('applicants/index/open-link-skill-select.phtml');
                break;

            case 'australian_business_number':
            case 'australian_company_number':
            case 'entity_name':
                $view->setVariables(
                    [
                        'url' => 'https://abr.business.gov.au/Index.aspx',
                        'search' => $this->findParam('val1')
                    ]
                );

                $view->setTemplate('applicants/index/open-link-abn.phtml');
                break;

            default:
                $view->setVariable('content', '');
                break;
        }

        return $view;
    }

    public function refreshSettingsAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');

        // Close session for writing - so next requests can be done
        session_write_close();

        $strError    = '';
        $arrSettings = array();

        try {
            $filter   = new StripTags();
            $selector = $filter->filter(Json::decode($this->params()->fromPost('selector'), Json::TYPE_ARRAY));

            switch ($selector) {
                case 'agents':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings['contact_sales_agent'] = $arrSavedSettings['options']['general']['contact_sales_agent'];
                    $arrSettings['agents'] = $arrSavedSettings['options']['general']['agents'];
                    break;

                case 'office':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings = $arrSavedSettings['options']['general']['office'];
                    break;

                case 'users':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings['active_users'] = $arrSavedSettings['options']['general']['active_users'];
                    $arrSettings['staff_responsible_rma'] = $arrSavedSettings['options']['general']['staff_responsible_rma'];
                    $arrSettings['assigned_to'] = $arrSavedSettings['options']['general']['assigned_to'];
                    break;

                case 'visa_office':
                case 'immigration_office':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings = $arrSavedSettings['options']['general'][$selector];
                    break;

                case 'list_of_occupations':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings = $arrSavedSettings['options']['general']['list_of_occupations'];
                    break;

                case 'authorized_agents':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings = $arrSavedSettings['options']['general']['authorized_agents'];
                    break;

                case 'employer_settings':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        false,
                        true
                    );

                    $arrSettings['employee']          = $arrSavedSettings['options']['general']['employee'];
                    $arrSettings['employer_contacts'] = $arrSavedSettings['options']['general']['employer_contacts'];
                    break;

                case 'categories':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings = $arrSavedSettings['options']['general']['categories'];
                    break;

                case 'case_status':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings = $arrSavedSettings['options']['general']['case_statuses'];
                    break;

                case 'accounting':
                    $arrSavedSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true
                    );
                    $arrSettings = $arrSavedSettings['accounting'];
                    break;

                case 'all':
                    $arrSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId(),
                        true,
                        true
                    );
                    break;

                default:
                    $strError = $this->_tr->translate('Incorrect parameters.');
                    break;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'     => empty($strError),
            'arrSettings' => $arrSettings,
        );
        return new JsonModel($arrResult);
    }

    public function submitToGovernmentAction()
    {
        $strError            = '';
        $strMessageSuccess   = '';
        $generatedCaseNumber = '';
        $booUpdateInfo       = false;
        $arrUpdatedInfo      = [];

        try {
            $clientId = (int)Json::decode($this->findParam('clientId'), Json::TYPE_ARRAY);

            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            $companyId         = $this->_auth->getCurrentUserCompanyId();
            $currentMemberId   = $this->_auth->getCurrentUserId();
            $oFields           = $this->_clients->getFields();

            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($clientId) || !$oCompanyDivisions->canCurrentMemberEditClient($clientId) || !$oCompanyDivisions->canCurrentMemberSubmitClientToGovernment())) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Load case and client ids
            $caseId     = 0;
            $clientName = '';
            if (empty($strError)) {
                if ($this->_members->isMemberCaseById($clientId)) {
                    // Get the parent of the case
                    $caseId     = $clientId;
                    $arrParents = $this->_clients->getParentsForAssignedApplicants(array($caseId));
                    $clientId   = $arrParents[$caseId]['parent_member_id'] ?? 0;
                } else {
                    // Get the first assigned case
                    $arrCases = $this->_clients->getAssignedCases($clientId);
                    $caseId   = count($arrCases) ? $arrCases[0] : 0;
                }

                $arrClientInfo = $this->_clients->getClientShortInfo($clientId);
                $clientName    = $arrClientInfo['full_name_with_file_num'] ?? 'Not recognized client with id #' . $clientId;
            }

            if (empty($strError) && (empty($caseId) || empty($clientId))) {
                $strError = $this->_tr->translate('Case was not assigned to the client.');
            }

            if (empty($strError)) {
                // Check if all payments were done if no - do the purchase.
                $arrCompanyAgentSystemAccessPayment = $this->_clients->getAccounting()->getCompanyAgentSystemAccessPayment($caseId);

                if (empty($arrCompanyAgentSystemAccessPayment)) {
                    // Payment wasn't done yet

                    $companyTAId = $this->_clients->getAccounting()->getClientPrimaryCompanyTaId($caseId);
                    if (empty($companyTAId)) {
                        $strError = $this->_tr->translate('Please assign T/A to this client before proceed.');
                    }

                    $paymentAmount                 = 0;
                    $paymentDescription            = '';
                    $systemAccessFeePaidFieldValue = '';
                    if (empty($strError)) {
                        // Check if there is a checkbox and it was checked
                        $systemAccessFeePaidFieldId    = $oFields->getCompanyFieldIdByUniqueFieldId('system_access_fee_paid', $companyId);
                        $systemAccessFeePaidFieldValue = empty($systemAccessFeePaidFieldId) ? '' : $oFields->getFieldDataValue($systemAccessFeePaidFieldId, $caseId);

                        $paymentAmount = $this->_settings->variable_get('price_dm_system_access_fee');

                        if (empty($paymentAmount)) {
                            $strError = $this->_tr->translate('Incorrect amount to be charged.<br>Please contact to support.');
                        }
                    }

                    $feeNotes = '';
                    if (empty($strError) && !empty($systemAccessFeePaidFieldValue) && $systemAccessFeePaidFieldValue === 'on') {
                        $transactionId = 'prepaid'; // must be not null to prevent duplication

                        // Get the description from the field
                        // if it doesn't exist or is empty - use a default label
                        $systemAccessFeePaidDescriptionFieldId = $oFields->getCompanyFieldIdByUniqueFieldId('system_access_fee_paid_description', $companyId);
                        $systemAccessFeePaidAmountFieldId      = $oFields->getCompanyFieldIdByUniqueFieldId('system_access_fee_paid_amount', $companyId);

                        $paymentDescription = empty($systemAccessFeePaidDescriptionFieldId) ? '' : $oFields->getFieldDataValue($systemAccessFeePaidDescriptionFieldId, $caseId);
                        $paymentDescription = empty($paymentDescription) ? 'Prepaid' : $paymentDescription;
                        $paymentAmount      = empty($systemAccessFeePaidAmountFieldId) ? 0 : (double)$oFields->getFieldDataValue($systemAccessFeePaidAmountFieldId, $caseId);
                    } else {
                        // There is no checkbox OR it wasn't checked for the case

                        // Check incoming CC info
                        $filter             = new StripTags();
                        $creditCardName     = trim($filter->filter($this->findParam('cc_name', '')));
                        $creditCardNum      = $filter->filter($this->findParam('cc_num'));
                        $creditCardCVN      = $filter->filter($this->findParam('cc_cvn'));
                        $creditCardExpMonth = $filter->filter($this->findParam('cc_month'));
                        $creditCardExpYear  = $filter->filter($this->findParam('cc_year'));
                        $creditCardExpDate  = $creditCardExpMonth . '/' . $creditCardExpYear;

                        $strError = $this->_clients->getAccounting()->checkCCInfo($companyId, $caseId, $creditCardName, $creditCardNum, $creditCardExpDate, $creditCardCVN, false);

                        if (empty($strError)) {
                            $paymentDescription = $this->_settings->variable_get('description_dm_system_access_fee');

                            if (empty($paymentDescription)) {
                                $strError = $this->_tr->translate('Incorrect description for the System Access Fee payment.<br>Please contact to support.');
                            }
                        }

                        $transactionId = '';
                        if (!empty($this->_config['payment']['stripe']['enabled'])) {
                            // Charge the CC
                            if (empty($strError)) {
                                list ($strError, $transactionId) = $this->_stripe->payWithCard(
                                    $paymentDescription . ' (' . $clientName . ')',
                                    $caseId,
                                    $paymentAmount,
                                    $creditCardNum,
                                    $creditCardExpMonth,
                                    $creditCardExpYear,
                                    $creditCardCVN
                                );
                            }

                            if (empty($strError) && empty($transactionId)) {
                                // Something went wrong
                                $strError = $this->_tr->translate('Internal error. Incorrect transaction id.');
                            }

                            // Check again if that transaction was successful
                            if (empty($strError) && !$this->_stripe->checkTransactionCompletedSuccessfully($transactionId)) {
                                // Something is wrong
                                $strError = $this->_tr->translate('Internal error. Transaction not found.');
                            }

                            $feeNotes = sprintf(
                                $this->_tr->translate('Fee received via CC %s exp %s/%s'),
                                $this->_settings->maskCreditCardNumber($creditCardNum),
                                $creditCardExpMonth,
                                substr(date('Y'), 0, 2) . $creditCardExpYear
                            );
                        } else {
                            try {
                                $transactionId = $this->_db2->insert(
                                    'cc_tmp',
                                    [
                                        'case_id'     => $caseId,
                                        'name'        => $this->_encryption->encode($clientName),
                                        'number'      => $this->_encryption->encode($creditCardNum),
                                        'exp_month'   => $this->_encryption->encode($creditCardExpMonth),
                                        'exp_year'    => $this->_encryption->encode($creditCardExpYear),
                                        'amount'      => $paymentAmount,
                                        'description' => $this->_encryption->encode($paymentDescription)
                                    ]
                                );
                                $transactionId = ''; // We don't have transaction ID from Stripe
                            } catch (Exception $e) {
                                $this->_log->debugErrorToFile(
                                    $e->getFile() . '@' . $e->getLine() . ': ' .
                                    $e->getMessage(),
                                    $e->getTraceAsString()
                                );
                            }
                        }
                    }


                    // Generate all payments
                    $arrPaymentIds = array();
                    if (empty($strError) && !empty($paymentAmount)) {
                        $currentMemberId = $this->_auth->getCurrentUserId();

                        /** @var array $arrPaymentIds */
                        list($strError, $arrPaymentIds) = $this->_clients->getAccounting()->generateCompanyAgentPayments($companyId, $caseId, $companyTAId, $currentMemberId, true);

                        if (empty($strError)) {
                            $gst           = 0;
                            $gstProvinceId = 0;
                            $gstTaxLabel   = '';

                            $paymentId = $this->_clients->getAccounting()->addFee(
                                $companyTAId,
                                $caseId,
                                $paymentAmount,
                                $paymentDescription,
                                'add-fee-received',
                                date('c'),
                                '',
                                $gst,
                                $gstProvinceId,
                                $gstTaxLabel,
                                $feeNotes,
                                $currentMemberId,
                                true,
                                $transactionId
                            );

                            if (empty($paymentId)) {
                                $strError = $this->_tr->translate('Internal error. Payment was not generated.');
                            } else {
                                $arrPaymentIds[] = $paymentId;
                            }
                        }
                    }


                    if (!empty($strError) && count($arrPaymentIds)) {
                        // Delete all created payments
                        $arrErrors     = array();
                        $arrPaymentIds = array_unique($arrPaymentIds);
                        foreach ($arrPaymentIds as $paymentId) {
                            if (!$this->_clients->getAccounting()->deletePayment($paymentId)) {
                                $arrErrors[] = sprintf(
                                    $this->_tr->translate('Payment with id %d was not deleted.'),
                                    $paymentId
                                );
                            }
                        }

                        if (count($arrErrors)) {
                            $strError = 'Internal error.<br>' . $strError . '<br>' . implode('<br>', $arrErrors);
                        }
                    }

                    if (empty($strError)) {
                        $strMessageSuccess = sprintf(
                            $this->_tr->translate('Thank you for your payment.<br>The application for <i>%s</i> was received successfully.'),
                            $clientName
                        );
                    }
                }
            }

            $divisionGroupId              = 0;
            $currentMemberDivisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

            if (empty($strError)) {
                $divisionGroupId = $oCompanyDivisions->getCompanyMainDivisionGroupId($companyId);
                if ($divisionGroupId == $currentMemberDivisionGroupId) {
                    $strError = $this->_tr->translate('You are already in the government group.');
                }
            }

            $arrAssignToDivisions = array();
            $arrDivisionsInMainGroup = $oCompanyDivisions->getDivisionsByGroupId($divisionGroupId, false);
            if (empty($strError)) {
                // Get list of the divisions which client must be assigned to
                foreach ($arrDivisionsInMainGroup as $arrDivisionInfo) {
                    if (isset($arrDivisionInfo['access_assign_to']) && $arrDivisionInfo['access_assign_to'] == 'Y') {
                        $arrAssignToDivisions[] = $arrDivisionInfo['division_id'];
                    }
                }

                if (empty($arrAssignToDivisions)) {
                    $strError = $this->_tr->translate('There are no offices we need to assign client to.');
                }
            }

            if (empty($strError) && $oCompanyDivisions->isClientSubmittedToGovernment($clientId)) {
                $strError = $this->_tr->translate('The client has already been submitted.');
            }

            $arrNewDivisionsToBeAssigned = array();
            if (empty($strError)) {
                $arrClientDivisions = $this->_clients->getApplicantOffices(array($clientId), $divisionGroupId);

                // Check if client was already submitted
                foreach ($arrAssignToDivisions as $arrAssignToDivisionId) {
                    $arrNewDivisionsToBeAssigned[] = $arrAssignToDivisionId;
                }
                foreach ($arrDivisionsInMainGroup as $arrDivisionInfo) {
                    foreach ($arrClientDivisions as $clientDivisionId) {
                        if ($arrDivisionInfo['division_id'] == $clientDivisionId) {
                            if (($arrDivisionInfo['access_owner_can_edit'] == 'N' && $arrDivisionInfo['access_assign_to'] == 'N') || $arrDivisionInfo['access_permanent'] == 'Y') {
                                $arrNewDivisionsToBeAssigned[] = $arrDivisionInfo['division_id'];
                            }
                        }
                    }
                }

                $arrCurrentMemberClientDivisions = $this->_clients->getApplicantOffices(array($clientId), $currentMemberDivisionGroupId);
                $arrNewDivisionsToBeAssigned     = array_merge($arrNewDivisionsToBeAssigned, $arrCurrentMemberClientDivisions);

                // Assign client + case + internal contacts
                list($booSuccess,) = $this->_clients->updateClientsOffices($companyId, array($clientId), $arrNewDivisionsToBeAssigned, null, $divisionGroupId);

                if (!$booSuccess) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }


            // Generate a new case number if it wasn't generated yet
            // And provide info to the UI, so required info will be updated where it is needed
            if (empty($strError)) {
                $oCaseNumber = $this->_clients->getCaseNumber();
                if ($oCaseNumber->isAutomaticTurnedOn($companyId) && $this->_config['site_version']['clients']['generate_case_number_on'] === 'submission') {
                    $arrCaseInfo = $this->_clients->getClientInfoOnly($caseId);
                    if (isset($arrCaseInfo['member_id']) && (!isset($arrCaseInfo['fileNumber']) || !strlen($arrCaseInfo['fileNumber'] ?? ''))) {
                        // Find visa abbreviation, use it during case number generation
                        $subclassAbbreviation = '';
                        if (!empty($subclassFieldVal)) {
                            $arrAllOptions = $this->_clients->getCaseCategories()->getCompanyCaseCategories($companyId);
                            foreach ($arrAllOptions as $arrOptionInfo) {
                                if ($arrOptionInfo['client_category_id'] == $subclassFieldVal) {
                                    $subclassAbbreviation = $arrOptionInfo['client_category_abbreviation'];
                                    break;
                                }
                            }
                        }

                        $boolIsReserved      = false;
                        $intMaxAttempts      = 20;
                        $intAttempt          = 0;
                        $startCaseNumberFrom = '';
                        $strGenerationError  = '';

                        // Get both IA and Employer ids if case is linked to both
                        $individualClientId = 0;
                        $employerClientId   = 0;
                        $arrParents         = $this->_clients->getParentsForAssignedApplicants(array($caseId), false, false);
                        foreach ($arrParents as $parentInfo) {
                            if ($parentInfo['member_type_name'] == 'individual') {
                                $individualClientId = $parentInfo['parent_member_id'];
                            } elseif ($parentInfo['member_type_name'] == 'employer') {
                                $employerClientId = $parentInfo['parent_member_id'];
                            }
                        }

                        while (!$boolIsReserved && ($intAttempt < $intMaxAttempts)) {
                            $intAttempt++;
                            list($strGenerationError, $generatedCaseNumber, $startCaseNumberFrom, $increment) = $oCaseNumber->generateNewCaseNumber($companyId, $individualClientId, $employerClientId, $caseId, $subclassAbbreviation, true, $intAttempt);
                            if (!empty($strGenerationError)) {
                                $this->_log->debugErrorToFile(
                                    sprintf('Could not generate new unique file number. Error: %s. companyId = %s, applicantId = %s, caseId = %s', $strGenerationError, $companyId, $clientId, $caseId),
                                    null,
                                    'case_number'
                                );
                                break;
                            }

                            $boolIsReserved = $oCaseNumber->reserveFileNumber($companyId, $generatedCaseNumber, $increment);
                        }


                        if (empty($strGenerationError)) {
                            if ($generatedCaseNumber) {
                                if (!empty($startCaseNumberFrom)) {
                                    $arrCompanySettings = $oCaseNumber->getCompanyCaseNumberSettings($companyId);

                                    $arrCompanySettings['cn-start-number-from-text'] = $startCaseNumberFrom;
                                    $oCaseNumber->saveCaseNumberSettings($companyId, $arrCompanySettings);
                                }

                                // Update case number for the already created case
                                $arrCaseUpdateInfo = array(
                                    'modified_by' => $currentMemberId,
                                    'modified_on' => date('Y-m-d H:i:s'),
                                    'fileNumber'  => $generatedCaseNumber
                                );
                                $this->_clients->updateClientInfo($caseId, $arrCaseUpdateInfo);

                                // Update XFDF too
                                $memberTypeId = $this->_clients->getMemberTypeByMemberId($clientId);
                                $this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, array('fileNumber' => $generatedCaseNumber), $memberTypeId);

                                // Also, run auto tasks based on the updated client's profile
                                $this->_triggers->triggerProfileUpdate($companyId, $clientId);


                                // Prepare info that will be used to update tabs in the UI
                                $arrParents    = $this->_clients->getParentsForAssignedApplicants(array($caseId), false, false);
                                $applicantId   = $employerId = 0;
                                $applicantName = $applicantUpdatedOn = $applicantUpdatedOnTime = '';
                                $employerName  = $employerUpdatedOn = $employerUpdatedOnTime = '';

                                foreach ($arrParents as $parentInfo) {
                                    if ($parentInfo['member_type_name'] == 'individual') {
                                        $applicantId = $parentInfo['parent_member_id'];

                                        $arrApplicantInfo       = $this->_clients->getClientInfo($applicantId);
                                        $applicantName          = $this->_clients->generateApplicantName($arrApplicantInfo);
                                        $applicantUpdatedOn     = $oFields->generateClientFooter($applicantId, $arrApplicantInfo);
                                        $applicantUpdatedOnTime = empty($arrApplicantInfo['modified_on']) ? '' : $arrApplicantInfo['modified_on'];
                                    } elseif ($parentInfo['member_type_name'] == 'employer') {
                                        $employerId = $parentInfo['parent_member_id'];

                                        $arrEmployerInfo       = $this->_clients->getClientInfo($employerId);
                                        $employerName          = $this->_clients->generateApplicantName($arrEmployerInfo);
                                        $employerUpdatedOn     = $oFields->generateClientFooter($employerId, $arrEmployerInfo);
                                        $employerUpdatedOnTime = empty($arrEmployerInfo['modified_on']) ? '' : $arrEmployerInfo['modified_on'];
                                    }
                                }

                                if (empty($applicantId) && !empty($employerId)) {
                                    $applicantUpdatedOn     = $employerUpdatedOn;
                                    $applicantUpdatedOnTime = $employerUpdatedOnTime;
                                }

                                $arrCaseInfo = $this->_clients->getClientInfo($caseId);


                                // Load linked case's type - will be used to identify the label for link/unlink button and related places
                                $employerLinkCaseId           = $this->_clients->getCaseLinkedEmployerCaseId($caseId);
                                $employerCaseLinkedCaseTypeId = 0;
                                if (!empty($employerLinkCaseId)) {
                                    $arrEmployerLinkCaseInfo      = $this->_clients->getClientInfoOnly($employerLinkCaseId);
                                    $employerCaseLinkedCaseTypeId = $arrEmployerLinkCaseInfo['client_type_id'];
                                }

                                $booUpdateInfo  = true;
                                $arrUpdatedInfo = array(
                                    'caseEmployerId'             => $employerId,
                                    'caseEmployerName'           => $employerName,
                                    'applicantId'                => $applicantId,
                                    'applicantName'              => $applicantName,
                                    'caseId'                     => $caseId,
                                    'caseName'                   => $arrCaseInfo['full_name_with_file_num'],
                                    'caseType'                   => $arrCaseInfo['client_type_id'] ?? 0,
                                    'applicantUpdatedOn'         => $applicantUpdatedOn,
                                    'applicantUpdatedOnTime'     => $applicantUpdatedOnTime,
                                    'applicantOfficeFields'      => [],
                                    'employerCaseLinkedCaseType' => $employerCaseLinkedCaseTypeId,
                                );
                            } else {
                                $this->_log->debugErrorToFile(
                                    sprintf('Could not generate new unique file number - reached maximum number of attempts. companyId = %s, applicantId = %s, caseId = %s', $companyId, $clientId, $caseId),
                                    null,
                                    'case_number'
                                );
                            }
                        }
                    }
                }
            }

            if (empty($strError) && empty($strMessageSuccess)) {
                $strMessageSuccess = sprintf(
                    $this->_tr->translate('The application for <i>%s</i> was received successfully.'),
                    $clientName
                );
            }
        } catch (Exception $e) {
            try {
                // If case file number was reserved, we try to release it
                if ($generatedCaseNumber && !empty($oCaseNumber) && !empty($companyId)) {
                    $oCaseNumber->releaseFileNumber($companyId, $generatedCaseNumber);
                }
            } catch (Exception $e) {
            }

            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'        => empty($strError),
            'message'        => empty($strError) ? $strMessageSuccess : $strError,
            'booUpdateInfo'  => $booUpdateInfo,
            'arrUpdatedInfo' => empty($strError) ? $arrUpdatedInfo : array()
        );

        return new JsonModel($arrResult);
    }

    public function getCompanyAgentPaymentInfoAction()
    {
        $view = new JsonModel();

        $strError        = '';
        $booPayed        = false;
        $currency        = '';
        $systemAccessFee = 0.00;

        try {
            $clientId = (int)Json::decode($this->findParam('clientId'), Json::TYPE_ARRAY);

            $oCompanyDivisions = $this->_company->getCompanyDivisions();

            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($clientId) || !$oCompanyDivisions->canCurrentMemberEditClient($clientId) || !$oCompanyDivisions->canCurrentMemberSubmitClientToGovernment())) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $systemAccessFee = sprintf('%01.2f', $this->_settings->variable_get('price_dm_system_access_fee'));
                if (empty($systemAccessFee)) {
                    $strError = $this->_tr->translate('Incorrect amount to be charged.<br>Please contact to support.');
                }
            }

            // Load case and client ids
            $caseId = 0;
            if (empty($strError)) {
                if ($this->_members->isMemberCaseById($clientId)) {
                    // Get the parent of the case
                    $caseId     = $clientId;
                    $arrParents = $this->_clients->getParentsForAssignedApplicants(array($caseId));
                    $clientId   = $arrParents[$caseId]['parent_member_id'] ?? 0;
                } else {
                    // Get the first assigned case
                    $arrCases = $this->_clients->getAssignedCases($clientId);
                    $caseId   = count($arrCases) ? $arrCases[0] : 0;
                }
            }

            if (empty($strError) && (empty($caseId) || empty($clientId))) {
                $strError = $this->_tr->translate('Case was not assigned to the client.');
            }

            $companyId = $this->_auth->getCurrentUserCompanyId();
            if (empty($strError)) {
                list($strError,) = $this->_clients->getAccounting()->checkAllRequirementsWereMetForSubmitting($companyId, $caseId, $clientId);
            }

            if (empty($strError)) {
                $arrRequiredForms = $this->_clients->getClientDependents()->getMissingRequiredFilesList($caseId);
                if (!empty($arrRequiredForms)) {
                    $strError = $this->_tr->translate('Please upload files for:');
                    foreach ($arrRequiredForms as $dependentName => $arrForms) {
                        $strList = '<ul style="list-style: disc; padding-left: 10px">';
                        foreach ($arrForms as $value) {
                            $strList .= '<li>' . htmlspecialchars($value) . '</li>';
                        }
                        $strList .= '</ul>';

                        $strError .= sprintf(
                            '<div><div style="font-weight: bold; padding-top: 10px">%s</div>%s</div>',
                            $dependentName,
                            $strList
                        );
                    }
                }
            }

            if (empty($strError)) {
                $companyTAId = $this->_clients->getAccounting()->getClientPrimaryCompanyTaId($caseId);
                if (empty($companyTAId)) {
                    $strError = $this->_tr->translate('Please assign T/A to this client before proceed.');
                }
            }

            if (empty($strError)) {
                $currency = $this->_settings->getCurrentCurrency();

                $arrCompanyAgentSystemAccessPayment = $this->_clients->getAccounting()->getCompanyAgentSystemAccessPayment($caseId);

                if (!empty($arrCompanyAgentSystemAccessPayment)) {
                    $booPayed = true;
                } else {
                    // Get field value
                    $fieldId    = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId('system_access_fee_paid', $companyId);
                    $fieldValue = empty($fieldId) ? '' : $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);

                    // Check if there is checkbox and it was checked
                    if (!empty($fieldValue) && $fieldValue === 'on') {
                        $booPayed = true;
                    }
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'         => empty($strError),
            'message'         => $strError,
            'booPayed'        => $booPayed,
            'systemAccessFee' => $systemAccessFee,
            'currency'        => $currency
        );
        return $view->setVariables($arrResult);
    }


    public function getVisaSurveyRecordsAction()
    {
        $view = new JsonModel();
        $strError       = '';
        $arrVisaRecords = array();
        $arrCountries   = array();

        try {
            $caseId = (int)$this->findParam('caseId');

            if (!$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $dependentId = (int)$this->findParam('dependentId');
            $dependentId = empty($dependentId) ? 0 : $dependentId;
            if (empty($strError) && !empty($dependentId) && !$this->_clients->hasCurrentMemberAccessToDependent($dependentId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }


            if (empty($strError)) {
                $arrVisaRecords = $this->_applicantVisaSurvey->getVisaSurveyRecords($caseId, $dependentId);
                $arrCountries   = $this->_applicantVisaSurvey->getCountriesList();
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'   => empty($strError),
            'msg'       => $strError,
            'items'     => $arrVisaRecords,
            'count'     => count($arrVisaRecords),
            'countries' => $arrCountries,
        );

        return $view->setVariables($arrResult);
    }

    public function saveVisaSurveyRecordAction()
    {
        $view = new JsonModel();
        $strError = '';

        try {
            $filter = new StripTags();
            $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

            $caseId = (int)$this->findParam('caseId');
            if (!$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }


            $dependentId = (int)$this->findParam('dependentId');
            $dependentId = empty($dependentId) ? 0 : $dependentId;
            if (empty($strError) && !empty($dependentId) && !$this->_clients->hasCurrentMemberAccessToDependent($dependentId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $visaSurveyId = (int)$this->findParam('visa_survey_id');
            if (empty($strError) && !empty($visaSurveyId)) {
                $arrVisaSurveyRecordInfo = $this->_applicantVisaSurvey->getVisaSurveyRecordInfo($visaSurveyId);
                if (isset($arrVisaSurveyRecordInfo['dependent_id']) && empty($arrVisaSurveyRecordInfo['dependent_id'])) {
                    $arrVisaSurveyRecordInfo['dependent_id'] = 0;
                }

                $booCorrect = false;
                if (isset($arrVisaSurveyRecordInfo['member_id']) && $arrVisaSurveyRecordInfo['member_id'] == $caseId && $arrVisaSurveyRecordInfo['dependent_id'] == $dependentId) {
                    $booCorrect = true;
                }

                if (!$booCorrect) {
                    $strError = $this->_tr->translate('Incorrect incoming info.');
                }
            }

            $visaCountryId = (int)$this->findParam('visa_country_id');
            if (empty($strError) && !in_array($visaCountryId, $this->_applicantVisaSurvey->getCountriesList(true))) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            $visaNumber = $filter->filter(trim($this->findParam('visa_number', '')));
            if (empty($strError) && !strlen($visaNumber)) {
                $strError = $this->_tr->translate('Visa Number is a required field.');
            }

            $visaIssueDate = $filter->filter($this->findParam('visa_issue_date'));
            if (empty($strError)) {
                if (empty($visaIssueDate)) {
                    $strError = $this->_tr->translate('Visa Issue Date is a required field.');
                } else {
                    $visaIssueDate = $this->_settings->reformatDate($visaIssueDate, $dateFormatFull);
                }
            }

            $visaExpiryDate = $filter->filter($this->findParam('visa_expiry_date'));
            if (empty($strError)) {
                if (empty($visaExpiryDate)) {
                    $strError = $this->_tr->translate('Visa Expiry Date is a required field.');
                } else {
                    $visaExpiryDate = $this->_settings->reformatDate($visaExpiryDate, $dateFormatFull);
                }
            }

            if (empty($strError)) {
                $booSuccess = $this->_applicantVisaSurvey->saveVisaSurveyRecord($caseId, $dependentId, $visaSurveyId, $visaCountryId, $visaNumber, $visaIssueDate, $visaExpiryDate);
                if (!$booSuccess) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return $view->setVariables($arrResult);
    }

    public function deleteVisaSurveyRecordAction()
    {
        $view = new JsonModel();
        $strError = '';

        try {
            $caseId = (int)$this->findParam('caseId');
            if (!$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }


            $dependentId = (int)$this->findParam('dependentId');
            $dependentId = empty($dependentId) ? 0 : $dependentId;
            if (empty($strError) && !empty($dependentId) && !$this->_clients->hasCurrentMemberAccessToDependent($dependentId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $visaSurveyId = (int)$this->findParam('visaSurveyId');
            if (empty($strError) && !empty($visaSurveyId)) {
                $arrVisaSurveyRecordInfo = $this->_applicantVisaSurvey->getVisaSurveyRecordInfo($visaSurveyId);
                if (isset($arrVisaSurveyRecordInfo['dependent_id']) && empty($arrVisaSurveyRecordInfo['dependent_id'])) {
                    $arrVisaSurveyRecordInfo['dependent_id'] = 0;
                }

                $booCorrect = false;
                if (isset($arrVisaSurveyRecordInfo['member_id']) && $arrVisaSurveyRecordInfo['member_id'] == $caseId && $arrVisaSurveyRecordInfo['dependent_id'] == $dependentId) {
                    $booCorrect = true;
                }

                if (!$booCorrect) {
                    $strError = $this->_tr->translate('Incorrect incoming info.');
                }
            }

            if (empty($strError) && !$this->_applicantVisaSurvey->deleteVisaSurveyRecord($visaSurveyId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError,
        );

        return $view->setVariables($arrResult);
    }
}