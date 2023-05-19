<?php

namespace Applicants\Controller;

use Clients\Service\Clients;
use Clients\Service\Clients\Fields;
use Clients\Service\ClientsReferrals;
use Clients\Service\ClientsFileStatusHistory;
use Clients\Service\Members;
use Clients\Service\MembersVevo;
use DateTime;
use DateTimeZone;
use Documents\Service\Documents;
use Exception;
use Files\ImageManager;
use Files\Model\FileInfo;
use Forms\Service\Dominica;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Notes\Service\Notes;
use Officio\BaseController;
use Officio\Common\Service\AccessLogs;
use Officio\Service\AuthHelper;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use Officio\Templates\Model\SystemTemplate;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;
use Templates\Service\Templates;

/**
 * Profile Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ProfileController extends BaseController
{

    /** @var Fields */
    protected $_fields;

    /** @var Company */
    protected $_company;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Clients */
    protected $_clients;

    /** @var CompanyProspects */
    protected $_prospects;

    /** @var Country */
    protected $_country;

    /** @var AutomaticReminders */
    protected $_automaticReminders;

    /** @var MembersVevo */
    protected $_membersVevo;

    /** @var Files */
    protected $_files;

    /** @var Pdf */
    protected $_pdf;

    /** @var Dominica */
    protected $_dominica;

    /** @var Documents */
    protected $_documents;

    /** @var Notes */
    protected $_notes;

    /** @var Templates */
    protected $_templates;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var Encryption */
    protected $_encryption;

    /** @var ClientsReferrals */
    protected $_clientsReferrals;

    /** @var ClientsFileStatusHistory */
    protected $_clientsFileStatusHistory;

    /** @var AccessLogs */
    protected $_accessLogs;

    public function initAdditionalServices(array $services)
    {
        $this->_dominica                 = $services[Dominica::class];
        $this->_company                  = $services[Company::class];
        $this->_clients                  = $services[Clients::class];
        $this->_prospects                = $services[CompanyProspects::class];
        $this->_automaticReminders       = $services[AutomaticReminders::class];
        $this->_authHelper               = $services[AuthHelper::class];
        $this->_country                  = $services[Country::class];
        $this->_membersVevo              = $services[MembersVevo::class];
        $this->_files                    = $services[Files::class];
        $this->_pdf                      = $services[Pdf::class];
        $this->_documents                = $services[Documents::class];
        $this->_notes                    = $services[Notes::class];
        $this->_templates                = $services[Templates::class];
        $this->_systemTemplates          = $services[SystemTemplates::class];
        $this->_triggers                 = $services[SystemTriggers::class];
        $this->_encryption               = $services[Encryption::class];
        $this->_clientsReferrals         = $services[ClientsReferrals::class];
        $this->_clientsFileStatusHistory = $services[ClientsFileStatusHistory::class];
        $this->_accessLogs               = $services[AccessLogs::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        return $view;
    }

    public function loadAction()
    {
        set_time_limit(5 * 60); // 5 minutes, no more
        ini_set('memory_limit', '-1');

        // Close session for writing - so next requests can be done
        session_write_close();

        $caseType                     = 0;
        $caseCategory                 = 0;
        $caseStatus                   = 0;
        $casesCount                   = 0;
        $caseId                       = 0;
        $booCanEdit                   = true;
        $booSubmitted                 = false;
        $strError                     = '';
        $applicantName                = '';
        $caseName                     = '';
        $applicantUpdatedOn           = '';
        $applicantUpdatedOnTime       = '';
        $applicantType                = 0;
        $employerId                   = 0;
        $employerName                 = '';
        $employerCaseLinkedCaseTypeId = 0;
        $arrFields                    = array();
        $arrAdditionalOptions         = array();
        $arrCasesWithParents          = array();
        $arrRowIds                    = array();

        try {
            $applicantId = Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            if (!empty($applicantId) && !$this->_members->hasCurrentMemberAccessToMember($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            $employerId = Json::decode($this->params()->fromPost('caseEmployerId'), Json::TYPE_ARRAY);
            if (empty($strError) && !empty($employerId) && !$this->_members->hasCurrentMemberAccessToMember($employerId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the employer.');
            }


            // Can be passed:
            // 1. Numeric - we need to load/check this case's info
            // 2. 0 - we want to create a new case
            // 3. null - we need to select the first case of the applicant
            $caseId = Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);
            if (empty($strError) && !empty($caseId) && !$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the case.');
            }

            $arrApplicantInfo       = [];
            $arrClientAssignedCases = [];
            if (empty($strError) && !empty($applicantId)) {
                $arrApplicantInfo       = $this->_members->getMemberInfo($applicantId);
                $arrClientAssignedCases = $this->_clients->getAssignedCases($applicantId);

                $casesCount = count($arrClientAssignedCases);
            }

            $caseType     = Json::decode($this->params()->fromPost('caseType'), Json::TYPE_ARRAY);
            $companyId    = $this->_auth->getCurrentUserCompanyId();
            $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
            if (empty($strError) && !empty($caseId) && !empty($caseType)) {
                $arrCompanyCaseTemplateIds = $this->_clients->getCaseTemplates()->getTemplates($companyId, true);
                if (!in_array($caseType, $arrCompanyCaseTemplateIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected') . ' ' . $caseTypeTerm;
                }
            }

            $booLoadCaseInfoOnly = false;
            if (empty($strError)) {
                $oCompanyDivisions         = $this->_company->getCompanyDivisions();
                $booLoadRelatedCaseOptions = false;
                if (empty($applicantId) || ($arrApplicantInfo && count($arrApplicantInfo))) {
                    $applicantName = empty($applicantId) ? 'New Client' : $this->_clients->generateApplicantName($arrApplicantInfo);
                    $memberType    = empty($applicantId) ? $this->_clients->getMemberTypeIdByName('individual') : $arrApplicantInfo['userType'];

                    if (!empty($applicantId)) {
                        $arrClientInfo = $this->_clients->getClientInfoOnly($applicantId);
                        $applicantType = $arrClientInfo['applicant_type_id'] ?? 0;
                    }

                    $booLoadCaseInfoOnly = Json::decode($this->params()->fromPost('booLoadCaseInfoOnly'), Json::TYPE_ARRAY);
                    if (!$booLoadCaseInfoOnly && !empty($applicantId)) {
                        list($arrFields, $arrRowIds) = $this->_clients->getAllApplicantFieldsData($applicantId, $memberType);
                        if (!is_array($arrFields)) {
                            throw new Exception('Applicant fields data is invalid');
                        }
                    }

                    if ($booLoadCaseInfoOnly && is_null($caseId) && in_array($this->_clients->getMemberTypeNameById($memberType), array('case', 'individual')) && !empty($arrClientAssignedCases)) {
                        $caseId = $arrClientAssignedCases[0];
                    }

                    // Get employer id for the case
                    if (!empty($caseId)) {
                        $arrEmployers = $this->_clients->getParentsForAssignedApplicant($caseId, $this->_clients->getMemberTypeIdByName('employer'));
                        if (!empty($arrEmployers)) {
                            $employerId = $arrEmployers[0];
                        }

                        if (!$this->_members->hasCurrentMemberAccessToMember($employerId)) {
                            $employerId = 0;
                        }
                    }

                    if (!empty($employerId)) {
                        $arrEmployerInfo = $this->_members->getMemberInfo($employerId);
                        $employerName    = $this->_clients->generateApplicantName($arrEmployerInfo);
                    }

                    if ($booLoadCaseInfoOnly) {
                        $arrCaseInfo        = array();
                        $booNewCase         = true;
                        $booCaseTypeChanged = false;

                        if (!empty($caseId)) {
                            $arrCaseInfo        = $this->_clients->getClientInfo($caseId);
                            $caseType           = empty($caseType) ? $arrCaseInfo['client_type_id'] : $caseType;
                            $booNewCase         = empty($arrCaseInfo['client_type_id']);
                            $booCaseTypeChanged = $caseType != $arrCaseInfo['client_type_id'];
                        }

                        $arrGroupedClientFields = empty($caseType) ? [] : $this->_clients->getFields()->getGroupedCompanyFields($caseType);
                        foreach ($arrGroupedClientFields as $arrClientGroupInfo) {
                            if (empty($arrClientGroupInfo['fields'])) {
                                continue;
                            }

                            foreach ($arrClientGroupInfo['fields'] as $arrClientFieldInfo) {
                                if ($arrClientFieldInfo['field_type'] == 'related_case_selection') {
                                    $booLoadRelatedCaseOptions = true;
                                    break 2;
                                }
                            }
                        }

                        if (!$booNewCase) {
                            $caseName = $arrCaseInfo['full_name_with_file_num'];

                            $arrCaseData = $this->_clients->getFields()->getClientProfile('edit', $caseId, $caseType);
                            if (!empty($arrCaseData)) {
                                $applicantName          = $arrCaseData['tab_name'];
                                $applicantUpdatedOn     = $arrCaseData['footer'];
                                $applicantUpdatedOnTime = $arrCaseData['last_update_time'];

                                if (array_key_exists('groups', $arrCaseData)) {
                                    $caseTypeFieldTypeId   = $this->_clients->getFieldTypes()->getFieldTypeId('case_type');
                                    $categoriesFieldTypeId = $this->_clients->getFieldTypes()->getFieldTypeId('categories');
                                    $caseStatusFieldTypeId = $this->_clients->getFieldTypes()->getFieldTypeId('case_status');
                                    foreach ($arrCaseData['groups'] as $arrCaseGroupInfo) {
                                        foreach ($arrCaseGroupInfo['fields'] as $arrFieldInfo) {
                                            if (!is_null($arrFieldInfo['value']) && $arrFieldInfo['value'] !== '') {
                                                if ($arrFieldInfo['type'] == $caseTypeFieldTypeId) {
                                                    $arrFieldInfo['value'] = $caseType;
                                                }

                                                $booSkip = false;
                                                if ($arrFieldInfo['type'] == $categoriesFieldTypeId) {
                                                    if ($booCaseTypeChanged) {
                                                        // Reset "category" field if case type was changed
                                                        $booSkip = true;
                                                    } else {
                                                        $caseCategory = $arrFieldInfo['value'];
                                                    }
                                                }

                                                if ($arrFieldInfo['type'] == $caseStatusFieldTypeId) {
                                                    $caseStatus = $arrFieldInfo['value'];
                                                }

                                                if (!$booSkip) {
                                                    $arrFields['field_case_' . $arrFieldInfo['group_id'] . '_' . $arrFieldInfo['field_id']] = array($arrFieldInfo['value']);
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            // Set default values for special fields
                            foreach ($arrGroupedClientFields as $arrClientGroupInfo) {
                                if (!empty($arrClientGroupInfo['fields'])) {
                                    foreach ($arrClientGroupInfo['fields'] as $arrClientFieldInfo) {
                                        if ($arrClientFieldInfo['field_type'] == 'authorized_agents') {
                                            $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array($oCompanyDivisions->getMemberDivisionGroupName($caseId));
                                        }
                                    }
                                }
                            }

                            // Additionally, load assigned dependents list
                            $arrDependents = $this->_clients->getFields()->getDependents(array($caseId));
                            foreach ($arrDependents as $arrDependentData) {
                                foreach ($arrDependentData as $line => $arrDependentInfo) {
                                    foreach ($arrDependentInfo as $fieldId => $fieldVal) {
                                        if (!is_null($fieldVal) && $fieldVal !== '' && !in_array($fieldId, array('member_id', 'line', 'canadian'))) {
                                            if ($line) {
                                                if (!array_key_exists('field_case_dependants_' . $fieldId, $arrFields)) {
                                                    $arrFields['field_case_dependants_' . $fieldId] = array();
                                                }

                                                for ($i = 0; $i < $line; $i++) {
                                                    if (!is_array($arrFields['field_case_dependants_' . $fieldId]) || !array_key_exists($i, $arrFields['field_case_dependants_' . $fieldId])) {
                                                        $arrFields['field_case_dependants_' . $fieldId][$i] = '';
                                                    }
                                                }
                                            }
                                            $arrFields['field_case_dependants_' . $fieldId][$line] = $fieldVal;
                                        }
                                    }
                                }
                            }
                        } else { // Fill specific fields for 'New Case'
                            $applicantUpdatedOn = '&nbsp;';

                            // Load default settings for 'Staff responsible fields'
                            $arrStaffResponsibleOptions = array();
                            if ($this->_company->isRememberDefaultFieldsSettingEnabledForCompany($companyId)) {
                                $arrCookies = $this->_serviceManager->get('Request')->getCookie();

                                $arrStaffResponsibleOptions['registered_migrant_agent'] = $arrCookies['ys-registered_migrant_agent'] ?? '';
                                $arrStaffResponsibleOptions['sales_and_marketing']      = $arrCookies['ys-sales_and_marketing'] ?? '';
                                $arrStaffResponsibleOptions['accounting']               = $arrCookies['ys-accounting'] ?? '';
                                $arrStaffResponsibleOptions['processing']               = $arrCookies['ys-processing'] ?? '';
                                $arrStaffResponsibleOptions                             = str_replace("s:", "", $arrStaffResponsibleOptions);
                            } else {
                                $arrStaffResponsibleOptions['registered_migrant_agent'] = '';
                                $arrStaffResponsibleOptions['sales_and_marketing']      = '';
                                $arrStaffResponsibleOptions['accounting']               = '';
                                $arrStaffResponsibleOptions['processing']               = '';
                            }

                            // Default values for specific fields
                            foreach ($arrGroupedClientFields as $arrClientGroupInfo) {
                                if (!empty($arrClientGroupInfo['fields'])) {
                                    foreach ($arrClientGroupInfo['fields'] as $arrClientFieldInfo) {
                                        switch ($arrClientFieldInfo['field_unique_id']) {
                                            case 'case_type':
                                                $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array($caseType);
                                                break;

                                            case 'date_client_signed':
                                                $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array(date('Y-m-d'));
                                                break;

                                            case 'Client_file_status':
                                                $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array('Active');
                                                break;

                                            case 'registered_migrant_agent':
                                                $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array($arrStaffResponsibleOptions['registered_migrant_agent']);
                                                break;

                                            case 'sales_and_marketing':
                                                $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array($arrStaffResponsibleOptions['sales_and_marketing']);
                                                break;

                                            case 'accounting':
                                                $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array($arrStaffResponsibleOptions['accounting']);
                                                break;

                                            case 'processing':
                                                $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array($arrStaffResponsibleOptions['processing']);
                                                break;

                                            default:
                                                break;
                                        }

                                        if ($arrClientFieldInfo['field_type'] == 'authorized_agents') {
                                            $arrFields['field_case_' . $arrClientGroupInfo['group_id'] . '_' . $arrClientFieldInfo['field_id']] = array($oCompanyDivisions->getCurrentMemberDivisionGroupName());
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($applicantId) && empty($applicantUpdatedOn)) {
                        $arrApplicantInfo       = $this->_clients->getClientsInfo(array($applicantId), false, $arrApplicantInfo['userType']);
                        $applicantUpdatedOn     = $this->_clients->getFields()->generateClientFooter($applicantId, $arrApplicantInfo[0]);
                        $applicantUpdatedOnTime = empty($arrApplicantInfo[0]['modified_on']) ? '' : $arrApplicantInfo[0]['modified_on'];
                    }
                }

                if (!empty($employerId)) {
                    // Search all internal contacts (except of not repeatable) for this employer
                    $arrEmployerGroups   = $this->_clients->getApplicantFields()->getCompanyGroups($companyId, $this->_clients->getMemberTypeIdByName('employer'));
                    $arrEmployerContacts = $this->_clients->getAssignedContacts($employerId);

                    $section           = 'employer_contacts';
                    $arrContactOptions = array();
                    foreach ($arrEmployerContacts as $arrEmployerContactInfo) {
                        $booIsRepeatableContact = false;
                        foreach ($arrEmployerGroups as $arrEmployerGroupInfo) {
                            if ($arrEmployerGroupInfo['applicant_group_id'] == $arrEmployerContactInfo['applicant_group_id'] &&
                                $arrEmployerGroupInfo['contact_block'] == 'Y' &&
                                $arrEmployerGroupInfo['repeatable'] == 'Y') {
                                $booIsRepeatableContact = true;
                                break;
                            }
                        }

                        if ($booIsRepeatableContact) {
                            $arrThisContactOptions = $this->_clients->getApplicantFields()->getEmployerRepeatableFieldsGrouped($section, $arrEmployerContactInfo['child_member_id']);
                            foreach ($arrThisContactOptions as &$arrData) {
                                if (empty($arrData['option_id'])) {
                                    $arrData['option_id'] = $arrEmployerContactInfo['child_member_id'];
                                }
                            }
                            $arrContactOptions = array_merge($arrContactOptions, $arrThisContactOptions);
                        }
                    }
                    $arrAdditionalOptions[$section] = $arrContactOptions;


                    // Load other data, related to this employer only
                    $arrSectionsToLoad = array(
                        'employer_engagements',
                        'employer_legal_entities',
                        'employer_locations',
                        'employer_third_party_representatives'
                    );
                    foreach ($arrSectionsToLoad as $section) {
                        $arrAdditionalOptions[$section] = $this->_clients->getApplicantFields()->getEmployerRepeatableFieldsGrouped($section, $employerId);
                    }
                }

                if (!empty($caseId)) {
                    // Load related cases list only for already created case
                    if ($booLoadRelatedCaseOptions) {
                        $section                        = 'related_case_selection';
                        $arrAdditionalOptions[$section] = $this->_clients->getApplicantFields()->getRelatedCaseOptions($applicantId);
                    }

                    // Get all cases with their parents which have the current case assigned in the related_case_selection field
                    $arrCasesWithParents = $this->_clients->getApplicantFields()->getAssignedRelatedCaseOptions($companyId, $caseId);

                    // Mark this case as viewed
                    $arrParentClients = $this->_clients->getParentsForAssignedApplicant($caseId);
                    $this->_clients->saveLastViewedClient($this->_auth->getCurrentUserId(), $caseId, $arrParentClients);

                    // Load linked case's type - will be used to identify the label for link/unlink button and related places
                    $employerLinkCaseId = $this->_clients->getCaseLinkedEmployerCaseId($caseId);
                    if (!empty($employerLinkCaseId)) {
                        $arrEmployerLinkCaseInfo      = $this->_clients->getClientInfoOnly($employerLinkCaseId);
                        $employerCaseLinkedCaseTypeId = $arrEmployerLinkCaseInfo['client_type_id'];
                    }
                }

                if (!empty($applicantId)) {
                    $booCanEdit   = $oCompanyDivisions->canCurrentMemberEditClient($applicantId);
                    $booSubmitted = $oCompanyDivisions->isClientSubmittedToGovernment($applicantId);

                    // If there are no cases for this client - mark it as viewed
                    if (empty($caseId) && empty($arrClientAssignedCases)) {
                        $this->_clients->saveLastViewedClient($this->_auth->getCurrentUserId(), $applicantId);
                    }
                }

                if (!empty($caseId) && !$booLoadCaseInfoOnly) {
                    $arrLog = array(
                        'log_section'           => 'client',
                        'log_action'            => 'client_or_case_viewed',
                        'log_description'       => 'Case Viewed',
                        'log_company_id'        => $companyId,
                        'log_created_by'        => $this->_auth->getCurrentUserId(),
                        'log_action_applied_to' => $caseId,
                    );
                    $this->_accessLogs->saveLog($arrLog);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'                    => empty($strError),
            'msg'                        => $strError,
            'fields'                     => $arrFields,
            'rowIds'                     => $arrRowIds,
            'applicantName'              => $applicantName,
            'applicantType'              => $applicantType,
            'applicantUpdatedOn'         => $applicantUpdatedOn,
            'applicantUpdatedOnTime'     => $applicantUpdatedOnTime,
            'casesCount'                 => $casesCount,
            'caseId'                     => $caseId,
            'caseType'                   => $caseType,
            'caseCategory'               => $caseCategory,
            'caseStatus'                 => $caseStatus,
            'caseName'                   => $caseName,
            'caseEmployerId'             => $employerId,
            'caseEmployerName'           => $employerName,
            'arrAdditionalOptions'       => $arrAdditionalOptions,
            'arrCasesWithParents'        => $arrCasesWithParents,
            'booCanEdit'                 => $booCanEdit,
            'booSubmitted'               => $booSubmitted,
            'employerCaseLinkedCaseType' => $employerCaseLinkedCaseTypeId,
        );


        return new JsonModel($arrResult);
    }

    public function loadShortInfoAction()
    {
        $strError = '';

        $applicantName = $memberType = '';
        $caseName      = $caseType = '';
        $employerId    = $employerName = '';

        try {
            ini_set('memory_limit', '-1');

            $applicantId = Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $caseId = Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);
            if (empty($strError) && !empty($caseId) && !$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $arrApplicantInfo = array();
            if (empty($strError)) {
                $arrApplicantInfo = $this->_members->getMemberInfo($applicantId);
            }

            if (empty($strError) && ($arrApplicantInfo && count($arrApplicantInfo))) {
                $applicantName = $this->_clients->generateApplicantName($arrApplicantInfo);
                $memberType    = $this->_clients->getMemberTypeNameById($arrApplicantInfo['userType']);

                // Automatically get the first assigned active case for the client
                // Or if there is only one assigned active case
                if (empty($caseId)) {
                    /** @var array $arrAssignedCases */
                    list($arrAssignedCases,) = $this->_clients->getApplicantAssignedCases(
                        $this->_auth->getCurrentUserCompanyId(),
                        $applicantId,
                        true,
                        null,
                        null,
                        null
                    );

                    if (count($arrAssignedCases) && ($this->_auth->isCurrentUserClient() || count($arrAssignedCases) === 1)) {
                        $firstActiveCase = reset($arrAssignedCases);
                        $caseId          = $firstActiveCase['member_id'];
                    }
                }

                if (!empty($caseId)) {
                    $arrCaseInfo = $this->_clients->getClientInfo($caseId);
                    if ($arrCaseInfo && count($arrCaseInfo)) {
                        $caseName = $arrCaseInfo['full_name_with_file_num'];
                        $caseType = $arrCaseInfo['client_type_id'];
                    }
                }

                // Get employer id for the case
                if (!empty($caseId)) {
                    $arrEmployers = $this->_clients->getParentsForAssignedApplicant($caseId, $this->_clients->getMemberTypeIdByName('employer'));
                    if (count($arrEmployers)) {
                        $employerId = $arrEmployers[0];
                    }

                    if (!$this->_members->hasCurrentMemberAccessToMember($employerId)) {
                        $employerId = 0;
                    }
                } elseif ($memberType == 'individual') {
                    $arrAssignedCaseIds = $this->_clients->getAssignedCases($applicantId);
                    $arrParents         = $this->_clients->getParentsForAssignedApplicants($arrAssignedCaseIds, true);
                    $arrEmployerIds     = array();
                    foreach ($arrParents as $arrParentInfo) {
                        $arrEmployerIds[] = $arrParentInfo['parent_member_id'];
                    }
                    $arrEmployerIds = array_unique($arrEmployerIds);
                    if (count($arrEmployerIds) == 1) {
                        $employerId = $arrEmployerIds[0];
                    }
                }

                if (!empty($employerId)) {
                    $arrEmployerInfo = $this->_members->getMemberInfo($employerId);
                    $employerName    = $this->_clients->generateApplicantName($arrEmployerInfo);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,

            'applicantName'    => $applicantName,
            'memberType'       => $memberType,
            'caseId'           => empty($caseId) ? null : $caseId,
            'caseName'         => empty($caseName) ? null : $caseName,
            'caseType'         => empty($caseType) ? null : $caseType,
            'caseEmployerId'   => empty($employerId) ? null : $employerId,
            'caseEmployerName' => empty($employerName) ? null : $employerName,
        );

        return new JsonModel($arrResult);
    }

    public function loadEmployerCasesListAction()
    {
        $strError         = '';
        $arrCasesLinkedTo = array(
            array(
                'case_id'                 => 0,
                'case_and_applicant_name' => '-- All --',
            )
        );

        try {
            $employerId = $this->findParam('employerId', 0);
            $companyId  = $this->_auth->getCurrentUserCompanyId();

            $arrSavedCasesLinkedTo = $this->_clients->getApplicantFields()->getEmployerCaseLinks($companyId, $employerId);
            $arrCasesLinkedTo      = array_merge($arrCasesLinkedTo, $arrSavedCasesLinkedTo);
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrCasesLinkedTo,
            'count'   => count($arrCasesLinkedTo),
        );

        return new JsonModel($arrResult);
    }

    public function saveAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        set_time_limit(5 * 60); // 5 minutes, no more
        ini_set('memory_limit', '-1');

        $arrResult = $this->_clients->createClientAndCaseAtOnce(
            $this->params()->fromPost(),
            $_FILES
        );

        // Result must be wrapped in <textarea> tags because of the issue with result parsing in ExtJs
        $view->setVariable('content', '<textarea>' . Json::encode($arrResult) . '</textarea>');

        return $view;
    }

    public function createCaseAction()
    {
        set_time_limit(5 * 60); // 5 minutes, no more
        ini_set('memory_limit', '-1');

        $strError                  = '';
        $strMessageTitle           = $this->_tr->translate('Error');
        $caseId                    = 0;
        $caseEmployerId            = 0;
        $caseEmployerName          = '';
        $showCaseWasAlreadyCreated = false;

        try {
            $arrParams = $this->params()->fromPost();

            $caseIdLinkedTo = isset($arrParams['caseIdLinkedTo']) ? (int)$arrParams['caseIdLinkedTo'] : 0;

            $applicantId = $arrParams['applicantId'] ?? 0;
            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            $caseEmployerId = $arrParams['caseEmployerId'] ?? 0;
            if (empty($strError) && !empty($caseEmployerId) && !$this->_members->hasCurrentMemberAccessToMember($caseEmployerId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the employer.');
            }

            $companyId = $this->_auth->getCurrentUserCompanyId();

            $totalCasesCount        = 0;
            $arrClientAssignedCases = [];
            if (empty($strError)) {
                // Load the list of cases for this client
                list($arrClientAssignedCases, $totalCasesCount) = $this->_clients->getApplicantAssignedCases($companyId, $applicantId, false, 0, 0, 100000);

                // Don't allow to create a case if user's company subscription is 'lite' and there is at least one created case for the client
                if ($totalCasesCount > 0) {
                    $arrCompanyDetailsInfo = $this->_company->getCompanyDetailsInfo($companyId);
                    if (isset($arrCompanyDetailsInfo['subscription']) && $arrCompanyDetailsInfo['subscription'] == 'lite') {
                        $strMessageTitle = $this->_tr->translate('Upgrade for Case Management Option');
                        $strError        = $this->_tr->translate('Case management option where you can create multiple cases for a client is available in Pro and Ultimate subscriptions.<br><br>Please contact support if you wish to upgrade.');
                    }
                }
            }

            if (empty($strError)) {
                // No cases OR if "link to case" is used-> create -> open this case's tab
                // only 1 case without case type -> show a warning and open this case's tab
                // else -> just show the New Case tab
                if (empty($totalCasesCount) || !empty($caseIdLinkedTo)) {
                    // There are no "new cases" for the client, so create one
                    $currentMemberId = $this->_auth->getCurrentUserId();
                    $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

                    $arrMainClientInfo     = $this->_clients->getMemberInfo($applicantId);
                    $arrAssignedContacts   = $this->_clients->getAssignedContacts($applicantId, true);
                    $arrAssignedContacts[] = $applicantId;

                    $arrNewClientInfo = array(
                        'createdBy'                            => $currentMemberId,
                        'applicantId'                          => $applicantId,
                        'arrParentsIds'                        => array_filter([$applicantId, $caseEmployerId]),
                        'arrParentClientAndInternalContactIds' => array_filter($arrAssignedContacts),
                        'arrMainClientInfo'                    => [
                            'emailAddress' => $arrMainClientInfo['emailAddress']
                        ],
                        'arrParents'                           => [], // We don't want to create or update clients

                        // Case info
                        'case'                                 => array(
                            'members' => array(
                                'company_id'        => $companyId,
                                'division_group_id' => $divisionGroupId,
                                'emailAddress'      => $arrMainClientInfo['emailAddress'],
                                'userType'          => $this->_clients->getMemberTypeIdByName('case'),
                                'regTime'           => time(),
                                'status'            => 1
                            ),

                            'members_divisions' => $this->_clients->getMemberDivisions($applicantId),

                            'clients' => array(
                                'added_by_member_id' => $currentMemberId
                            ),
                        )
                    );

                    $arrCreationResult = $this->_clients->createClient($arrNewClientInfo, $companyId, $divisionGroupId);

                    $strError = $arrCreationResult['strError'];
                    if (empty($strError)) {
                        $caseId = $arrCreationResult['caseId'];

                        // Automatically link to the provided case if needed
                        if (empty($caseEmployerId) && !empty($caseIdLinkedTo)) {
                            list(, $strMessage, $linkedEmployerId) = $this->_clients->linkCaseToCase(true, $caseIdLinkedTo, $caseId, false);
                            if (empty($strMessage) && !empty($linkedEmployerId)) {
                                $caseEmployerId = $linkedEmployerId;
                            }
                        }

                        // Create new client folders
                        $this->_files->mkNewMemberFolders(
                            $caseId,
                            $companyId,
                            $this->_company->isCompanyStorageLocationLocal($companyId)
                        );
                    }
                } else {
                    // At least 1 case was created for this client - check if case type was selected
                    foreach ($arrClientAssignedCases as $arrClientAssignedCaseInfo) {
                        if (!empty($arrClientAssignedCaseInfo['child_member_id']) && empty($arrClientAssignedCaseInfo['client_type_id'])) {
                            $caseId = $arrClientAssignedCaseInfo['child_member_id'];

                            $showCaseWasAlreadyCreated = true;
                            break;
                        }
                    }
                }

                if (!empty($caseEmployerId)) {
                    $arrEmployerInfo  = $this->_clients->getMemberInfo($caseEmployerId);
                    $caseEmployerName = $this->_clients->generateApplicantName($arrEmployerInfo);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'success'                   => empty($strError),
            'message_title'             => $strMessageTitle,
            'message'                   => empty($strError) ? $this->_tr->translate('Done.') : $strError,
            'caseId'                    => $caseId,
            'caseEmployerId'            => $caseEmployerId,
            'caseEmployerName'          => $caseEmployerName,
            'showCaseWasAlreadyCreated' => $showCaseWasAlreadyCreated
        ];

        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError          = '';
        $errorType         = 'error';
        $openApplicantId   = 0;
        $openApplicantName = '';
        $openApplicantType = '';

        try {
            $applicantId  = (int)Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            $booConfirmed = (bool)Json::decode($this->params()->fromPost('confirmed'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Check if this applicant is used somewhere
            if (empty($strError) && $this->_clients->isApplicantUsedInData($applicantId)) {
                $strError = $this->_tr->translate(
                    'The current contact is referenced in one or more cases and cannot be deleted.<br/><br/>' .
                    'You can set its Status to inactive, ' .
                    'if you wish not to see this contact in most of the search results.'
                );
            }

            // Don't allow to delete a client if there are assigned cases to him
            $strMemberType = '';
            if (empty($strError)) {
                $arrApplicantInfo = $this->_members->getMemberInfo($applicantId);
                $strMemberType    = $this->_clients->getMemberTypeNameById($arrApplicantInfo['userType']);
                if ($strMemberType == 'contact') {
                    if (!$booConfirmed) {
                        $arrAssignedProspects = $this->_prospects->getProspectsIdsByAgentId($applicantId);
                        if (count($arrAssignedProspects)) {
                            $strError  = $this->_tr->translate('This contact is linked to one or more prospects. If you delete this contact, the reference to this contact will be deleted from all those prospects. Continue?');
                            $errorType = 'confirmation';
                        }
                    }
                } elseif ($strMemberType != 'case') {
                    $arrAssignedCases = $this->_clients->getAssignedCases($applicantId);
                    if (count($arrAssignedCases)) {
                        $strError = $this->_tr->translate('Please delete all assigned cases and try again.');
                    }
                }
            }

            if (empty($strError)) {
                if ($strMemberType == 'case') {
                    $arrParents = $this->_clients->getParentsForAssignedApplicants([$applicantId]);

                    $booDeleted = $this->_clients->deleteClient($applicantId, true, $this->_automaticReminders->getActions());

                    if ($booDeleted) {
                        if (isset($arrParents[$applicantId]['parent_member_id'])) {
                            $openApplicantId  = $arrParents[$applicantId]['parent_member_id'];
                            $arrApplicantInfo = $this->_members->getMemberInfo($openApplicantId);
                            if ($arrApplicantInfo && count($arrApplicantInfo)) {
                                $openApplicantName = $this->_clients->generateApplicantName($arrApplicantInfo);
                                $openApplicantType = $this->_clients->getMemberTypeNameById($arrApplicantInfo['userType']);
                            } else {
                                $openApplicantId = 0;
                            }
                        }
                    }
                } else {
                    $arrApplicantInfo = $this->_clients->getClientInfo($applicantId);
                    $applicantName    = $this->_clients->generateApplicantName($arrApplicantInfo);
                    $arrMemberNames   = [$applicantName];

                    $arrSubContactIds = $this->_clients->getAssignedApplicants($applicantId, $this->_clients->getMemberTypeIdByName('internal_contact'));
                    $arrAllIds        = array_merge(array($applicantId), $arrSubContactIds);
                    $arrAllIds        = array_unique($arrAllIds);

                    $booDeleted = $this->_members->deleteMember($this->_auth->getCurrentUserCompanyId(), $arrAllIds, $arrMemberNames, $strMemberType);
                }

                if (!$booDeleted) {
                    $strError = $this->_tr->translate('Record was not deleted. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'type'          => $errorType,
            'success'       => empty($strError),
            'msg'           => empty($strError) ? $this->_tr->translate('Done!') : $strError,

            // A client, that we want to open after the case was deleted
            'applicantId'   => $openApplicantId,
            'applicantName' => $openApplicantName,
            'applicantType' => $openApplicantType,
        );

        return new JsonModel($arrResult);
    }

    public function linkCaseToEmployerAction()
    {
        $caseName      = '';
        $caseType      = 0;
        $applicantId   = 0;
        $applicantName = '';
        $employerId    = 0;
        $employerName  = '';

        try {
            $strError                = '';
            $booTransactionStarted   = false;
            $booAssignCaseToEmployer = false;

            $linkTo         = $this->params()->fromPost('linkTo');
            $caseIdLinkFrom = (int)Json::decode($this->params()->fromPost('caseIdLinkFrom'), Json::TYPE_ARRAY);
            $caseIdLinkTo   = (int)Json::decode($this->params()->fromPost('caseIdLinkTo'), Json::TYPE_ARRAY);

            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            if (!$this->_members->hasCurrentMemberAccessToMember($caseIdLinkFrom) || !$oCompanyDivisions->canCurrentMemberEditClient($caseIdLinkFrom)) {
                $strError = $this->_tr->translate('Insufficient access rights to the case.');
            }

            // We can assign this case to Employer or to LMIA case (and maybe to Employer)
            switch ($linkTo) {
                case 'employer':
                    $employerId = (int)Json::decode($this->params()->fromPost('employerId'), Json::TYPE_ARRAY);
                    if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($employerId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to the employer.');
                    }

                    if (empty($strError)) {
                        // Make sure that they have the same employer if both are already assigned
                        $arrParents = $this->_clients->getParentsForAssignedApplicant($caseIdLinkFrom, $this->_clients->getMemberTypeIdByName('employer'));
                        if (empty($arrParents)) {
                            $booAssignCaseToEmployer = true;
                        } else {
                            $strError = $this->_tr->translate('Case is already assigned to the Employer.');
                        }
                    }
                    break;

                case 'lmia-case':
                    if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($caseIdLinkTo) || !$oCompanyDivisions->canCurrentMemberEditClient($caseIdLinkTo))) {
                        $strError = $this->_tr->translate('Insufficient access rights to the selected case.');
                    }

                    if (empty($strError)) {
                        $arrSavedData = $this->_clients->getCasesLinkedEmployerCases(array($caseIdLinkFrom));
                        if (!empty($arrSavedData)) {
                            $strError = $this->_tr->translate('This case is already assigned.');
                        }
                    }

                    if (empty($strError)) {
                        // Check if selected case is assigned to the employer
                        $arrParents = $this->_clients->getParentsForAssignedApplicant($caseIdLinkTo, $this->_clients->getMemberTypeIdByName('employer'));
                        if (!empty($arrParents)) {
                            $employerId = $arrParents[0];
                        }

                        if (empty($employerId)) {
                            $strError = $this->_tr->translate('Selected Case is not assigned to the Employer.');
                        } else {
                            // Make sure that they have the same employer if both are already assigned
                            $arrParents = $this->_clients->getParentsForAssignedApplicant($caseIdLinkFrom, $this->_clients->getMemberTypeIdByName('employer'));
                            if (empty($arrParents)) {
                                // Not assinged
                                $booAssignCaseToEmployer = true;
                            } elseif ($arrParents[0] != $employerId) {
                                $strError = $this->_tr->translate('Cases are already assigned to different Employers.');
                            }
                        }
                    }
                    break;

                default:
                    $strError = $this->_tr->translate('Incorrect incoming params.');
                    break;
            }

            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->beginTransaction();
                $booTransactionStarted = true;

                if ($linkTo == 'lmia-case' && !$this->_clients->linkUnlinkCases($caseIdLinkFrom, $caseIdLinkTo)) {
                    $strError = $this->_tr->translate('Employer case link was not set.');
                }

                if ($booAssignCaseToEmployer) {
                    $arrAssignData = array(
                        'applicant_id' => $employerId,
                        'case_id'      => $caseIdLinkFrom,
                    );

                    if (!$this->_clients->assignCaseToApplicant($arrAssignData)) {
                        $strError = $this->_tr->translate('Internal error.');
                    }

                    if (empty($strError)) {
                        $this->_clients->calculateAndUpdateCaseNumberForEmployer($employerId, $caseIdLinkFrom);
                    }
                }

                if (empty($strError)) {
                    $this->_db2->getDriver()->getConnection()->commit();
                    $booTransactionStarted = false;

                    // Load info about the case + individual + employer -> will be used later
                    $arrClientInfo = $this->_clients->getClientInfo($caseIdLinkFrom);
                    if ($arrClientInfo && count($arrClientInfo)) {
                        $caseName = $arrClientInfo['full_name_with_file_num'];
                        $caseType = $arrClientInfo['client_type_id'];
                    }

                    $arrParents = $this->_clients->getParentsForAssignedApplicant($caseIdLinkFrom, $this->_clients->getMemberTypeIdByName('individual'));
                    if (count($arrParents)) {
                        $applicantId = $arrParents[0];

                        $arrParentInfo = $this->_clients->getClientInfo($applicantId);
                        if ($arrParentInfo && count($arrParentInfo)) {
                            $applicantName = $arrParentInfo['full_name'];
                        }
                    }

                    if (!empty($employerId)) {
                        $arrEmployerInfo = $this->_clients->getClientInfo($employerId);
                        if ($arrEmployerInfo && count($arrEmployerInfo)) {
                            $employerName = $arrEmployerInfo['full_name'];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError) && $booTransactionStarted) {
            $this->_db2->getDriver()->getConnection()->rollback();
        }


        $arrResult = array(
            'success'       => empty($strError),
            'msg'           => empty($strError) ? $this->_tr->translate('Done!') : $strError,
            'applicantId'   => $applicantId,
            'applicantName' => $applicantName,
            'caseName'      => $caseName,
            'caseType'      => $caseType,
            'employerId'    => $employerId,
            'employerName'  => $employerName
        );

        return new JsonModel($arrResult);
    }

    public function linkCaseToCaseAction()
    {
        try {
            $booConfirmation = (bool)Json::decode($this->params()->fromPost('booConfirmation'), Json::TYPE_ARRAY);
            $caseIdLinkFrom  = (int)Json::decode($this->params()->fromPost('caseIdLinkFrom'), Json::TYPE_ARRAY);
            $caseIdLinkTo    = (int)Json::decode($this->params()->fromPost('caseIdLinkTo'), Json::TYPE_ARRAY);

            list($messageType, $strMessage,) = $this->_clients->linkCaseToCase($booConfirmation, $caseIdLinkFrom, $caseIdLinkTo);
        } catch (Exception $e) {
            $messageType = 'error';
            $strMessage  = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'msg_type' => empty($strMessage) ? 'success' : $messageType,
            'msg'      => empty($strMessage) ? $this->_tr->translate('Done!') : $strMessage,
        );

        return new JsonModel($arrResult);
    }

    public function unassignCaseAction()
    {
        $strError              = '';
        $booTransactionStarted = false;

        try {
            $oCompanyDivisions = $this->_company->getCompanyDivisions();

            $applicantId = (int)Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId) || !$oCompanyDivisions->canCurrentMemberEditClient($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the employer.');
            }

            $arrCases = Json::decode($this->params()->fromPost('arrCases'), Json::TYPE_ARRAY);

            $arrCasesUnassign = [];
            if (empty($strError)) {
                if (empty($arrCases) || !is_array($arrCases)) {
                    $strError = $this->_tr->translate('Incorrectly selected case.');
                }

                if (empty($strError)) {
                    foreach ($arrCases as $caseId) {
                        if (!$this->_members->hasCurrentMemberAccessToMember($caseId) || !$oCompanyDivisions->canCurrentMemberEditClient($caseId)) {
                            $strError = $this->_tr->translate('Insufficient access rights to the case.');
                            break;
                        } else {
                            $arrParents = $this->_clients->getParentsForAssignedApplicants(array($caseId), false, false);
                            if (count($arrParents) > 1) {
                                // This case is assigned to Employer and IA, so we can unassign from the employer
                                $arrCasesUnassign[] = $caseId;
                            }
                        }
                    }
                }
            }

            $booIsEmployer = false;
            if (empty($strError)) {
                $arrApplicantInfo = $this->_clients->getMemberInfo($applicantId);
                $memberType       = $this->_clients->getMemberTypeNameById($arrApplicantInfo['userType']);

                $booIsEmployer = $memberType === 'employer';
            }

            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->beginTransaction();
                $booTransactionStarted = true;

                foreach ($arrCases as $caseId) {
                    if (!$this->_clients->linkUnlinkCases($caseId, null)) {
                        $strError = $this->_tr->translate('Internal error.');
                        break;
                    }
                }

                if (!empty($arrCasesUnassign) && !$this->_clients->unassignCasesFromApplicant($applicantId, $arrCasesUnassign)) {
                    $strError = $this->_tr->translate('Internal error.');
                }

                if (empty($strError) && !empty($arrCasesUnassign) && $booIsEmployer) {
                    $this->_clients->updateCaseNumberForEmployer($arrCasesUnassign, null);
                }

                if (empty($strError)) {
                    $this->_db2->getDriver()->getConnection()->commit();
                    $booTransactionStarted = false;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError) && $booTransactionStarted) {
            $this->_db2->getDriver()->getConnection()->rollback();
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function viewImageAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $fieldId  = (int)$this->findParam('id');
        $memberId = (int)$this->findParam('mid');
        $type     = $this->findParam('type');
        $did      = (int)$this->findParam('did');

        if (!empty($type) && empty($fieldId) && $type == 'thumbnail') {
            if ($this->_members->hasCurrentMemberAccessToMember($memberId) && $this->_clients->hasCurrentMemberAccessToDependent($did)) {
                $arrMemberInfo = $this->_members->getMemberInfo($memberId);
                $fileInfo      = $this->_files->getDependentImage($memberId, $arrMemberInfo['company_id'], 'thumbnail', 'thumbnail.png', $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']), $did);
                if ($fileInfo instanceof FileInfo) {
                    if ($fileInfo->local) {
                        return $this->downloadFile($fileInfo->path, $fileInfo->name, $fileInfo->mime, false, false);
                    } else {
                        $url = $this->_files->getCloud()->getFile($fileInfo->path, $fileInfo->name, false, false);
                        if ($url) {
                            return $this->redirect()->toUrl($url);
                        }
                    }
                }
            }
        } elseif (!empty($memberId) && !empty($fieldId) && $this->_members->hasCurrentMemberAccessToMember($memberId)) {
            $arrMemberInfo = $this->_clients->getMemberInfo($memberId);
            $booLocal      = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);

            if ($arrMemberInfo['userType'] == $this->_clients->getMemberTypeIdByName('case')) {
                $fieldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $memberId);
            } else {
                $fieldValue = $this->_clients->getApplicantFields()->getFieldDataValue($memberId, $fieldId);
            }

            if (!empty($fieldValue)) {
                $fileInfo = $this->_files->getClientImage($arrMemberInfo['company_id'], $memberId, $booLocal, 'field-' . $fieldId, $fieldValue);
                if ($fileInfo instanceof FileInfo) {
                    if ($fileInfo->local) {
                        return $this->downloadFile($fileInfo->path, $fileInfo->name, $fileInfo->mime, false, false);
                    } else {
                        $url = $this->_files->getCloud()->getFile($fileInfo->path, $fileInfo->name, false, false);
                        if ($url) {
                            return $this->redirect()->toUrl($url);
                        }
                    }
                }
            }
        }

        return $view;
    }

    public function deleteFileAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $fieldId  = (int)$this->findParam('id');
            $memberId = (int)$this->findParam('mid');
            $type     = $this->findParam('type');
            $did      = $this->findParam('did');

            $memberInfo = $this->_clients->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            if (!empty($memberId) && !empty($fieldId) && $this->_members->hasCurrentMemberAccessToMember($memberId) && $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $arrClientInfo = $this->_clients->getClientShortInfo($memberId);


                if ($arrClientInfo['userType'] == $this->_clients->getMemberTypeIdByName('case')) {
                    $booIsApplicant         = false;
                    $arrMemberAllowedFields = $this->_clients->getFields()->getCaseTemplateFields($arrClientInfo['company_id'], $arrClientInfo['client_type_id']);

                    foreach ($arrMemberAllowedFields as $allowedField) {
                        if ($allowedField['field_id'] == $fieldId) {
                            $this->_db2->delete(
                                'client_form_data',
                                [
                                    'member_id' => $memberId,
                                    'field_id'  => $fieldId
                                ]
                            );
                            break;
                        } elseif (!next($arrMemberAllowedFields)) {
                            $strError = $this->_tr->translate('Insufficient access rights.');
                        }
                    }
                } else {
                    $booIsApplicant         = true;
                    $arrMemberAllowedFields = $this->_clients->getApplicantFields()->getUserAllowedFields();
                    foreach ($arrMemberAllowedFields as $allowedField) {
                        if ($allowedField['applicant_field_id'] == $fieldId) {
                            $this->_db2->delete(
                                'applicant_form_data',
                                [
                                    'applicant_id'       => $memberId,
                                    'applicant_field_id' => $fieldId
                                ]
                            );
                            break;
                        } elseif (!next($arrMemberAllowedFields)) {
                            $strError = $this->_tr->translate('Insufficient access rights.');
                        }
                    }
                }

                if (empty($strError)) {
                    $fileName = 'field-' . $fieldId;
                    if ($type == 'file') {
                        $this->_files->deleteClientFile($companyId, $memberId, $booLocal, $fileName);
                    } else {
                        $this->_files->deleteClientImage($companyId, $memberId, $booLocal, $fileName);

                        // Delete files with name = name + '-original' for profile photo fields
                        if ($type == 'image') {
                            $booLocal                 = $this->_company->isCompanyStorageLocationLocal($arrClientInfo['company_id']);
                            $profilePhotoOriginalPath = $this->_files->getPathToClientImages($arrClientInfo['company_id'], $memberId, $booLocal) . '/' . $fileName . '-original';

                            $booExists = $booLocal
                                ? file_exists($profilePhotoOriginalPath)
                                : $this->_files->getCloud()->checkObjectExists($profilePhotoOriginalPath);
                            if ($booExists) {
                                $this->_files->deleteClientImage($companyId, $memberId, $booLocal, $fileName . '-original');
                            }
                        }
                    }

                    // Log this change
                    $arrAllFieldsData = array(
                        $memberId => array(
                            'booIsApplicant'  => $booIsApplicant,
                            'arrDeletedFiles' => array(
                                array('field_id' => $fieldId)
                            )
                        )
                    );
                    $this->_triggers->triggerFieldBulkChanges($arrClientInfo['company_id'], $arrAllFieldsData);
                }
            }


            if (empty($strError) && !empty($memberId) && isset($did) && $this->_members->hasCurrentMemberAccessToMember($memberId) && $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) && $this->_clients->hasCurrentMemberAccessToDependent($did)) {
                $caseTemplateId = $this->_clients->getClientCurrentTemplateId($memberId);
                if ($this->_clients->getFields()->getAccessToDependants($caseTemplateId) == 'F') {
                    $arrMemberInfo = $this->_clients->getMemberInfo($memberId);
                    $booLocal      = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);
                    $booSuccess    = $this->_files->deleteDependentPhoto($arrMemberInfo['company_id'], $memberId, $did, $booLocal);
                    if ($booSuccess) {
                        $booSuccess = $this->_clients->updateDependents($memberId, $did, array('photo' => null));
                    }
                    if (!$booSuccess) {
                        $strError = 'An error during image deleting. Please try again.';
                    }
                } else {
                    $strError = 'Insufficient access rights.';
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'error'   => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function downloadFileAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $strError = '';

        try {
            $fieldId       = (int)$this->findParam('id');
            $memberId      = (int)$this->findParam('mid');
            $arrClientInfo = $this->_clients->getClientInfo($memberId);
            $companyId     = $arrClientInfo['company_id'];
            $booIsLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);

            if (!empty($memberId) && !empty($fieldId) && $this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $fileName         = 'field-' . $fieldId;
                $filePath         = $this->_files->getPathToClientFiles($companyId, $memberId, $booIsLocal) . '/' . $fileName;
                $originalFilename = '';

                if ($arrClientInfo['userType'] == $this->_clients->getMemberTypeIdByName('case')) {
                    $arrMemberAllowedFields = $this->_clients->getFields()->getCaseTemplateFields($this->_auth->getCurrentUserCompanyId(), $arrClientInfo['client_type_id']);
                    foreach ($arrMemberAllowedFields as $allowedField) {
                        if ($allowedField['field_id'] == $fieldId) {
                            $originalFilename = $this->_clients->getFields()->getFieldDataValue($fieldId, $memberId);
                            break;
                        }
                    }
                } else {
                    $arrMemberAllowedFields = $this->_clients->getApplicantFields()->getUserAllowedFields();
                    foreach ($arrMemberAllowedFields as $allowedField) {
                        if ($allowedField['applicant_field_id'] == $fieldId) {
                            $originalFilename = $this->_clients->getApplicantFields()->getFieldDataValue($memberId, $fieldId);
                            break;
                        }
                    }
                }

                if (empty($originalFilename)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError)) {
                    if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                        return $this->downloadFile($filePath, $originalFilename);
                    } else {
                        $url = $this->_files->getCloud()->getFile($filePath, $originalFilename);
                        if ($url) {
                            return $this->redirect()->toUrl($url);
                        } else {
                            return $this->fileNotFound();
                        }
                    }
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $view->setVariable('content', $strError);
        return $view;
    }

    public function getLoginInfoAction()
    {
        $view = new JsonModel();

        $strError               = '';
        $usernameFieldId        = 0;
        $clientInfo['username'] = '';
        $templates_list         = array();

        try {
            if ($this->getRequest()->isPost()) {
                $filter = new StripTags();

                $memberId = $filter->filter($this->findParam('member_id'));

                if (!is_numeric($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError)) {
                    $clientInfo = $this->_members->getMemberInfo($memberId);

                    $templates_list = $this->_templates->getTemplatesList(true, 0, 'password', 'Email');

                    $usernameFieldId = $this->_clients->getApplicantFields()->getUsernameFieldId($memberId);
                }
            } else {
                $strError = $this->_tr->translate('Incorrectly sent request');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'           => empty($strError),
            'message'           => $strError,
            'username'          => $clientInfo['username'],
            'username_field_id' => $usernameFieldId,
            'templates'         => $templates_list
        );
        return $view->setVariables($arrResult);
    }

    public function updateLoginInfoAction()
    {
        $strError = $message = '';
        $username = $password = '';

        try {
            if ($this->getRequest()->isPost()) {
                $filter = new StripTags();

                $memberId        = $filter->filter($this->params()->fromPost('member_id'));
                $usernameFieldId = $filter->filter(Json::decode($this->params()->fromPost('username_field_id'), Json::TYPE_ARRAY));
                $username        = trim($filter->filter(Json::decode($this->params()->fromPost('username', ''), Json::TYPE_ARRAY)));
                $password        = trim($filter->filter(Json::decode($this->params()->fromPost('password', ''), Json::TYPE_ARRAY)));

                if (!is_numeric($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError) && (!empty($username) && empty($password))) {
                    $strError = $this->_tr->translate('Please specify Case password');
                }

                if (empty($strError) && (empty($username) && !empty($password))) {
                    $strError = $this->_tr->translate('Please specify Case username');
                }

                if (empty($strError) && !empty($username) && $this->_members->isUsernameAlreadyUsed($username, $memberId)) {
                    $strError = $this->_tr->translate('This username is already used, please choose another.');
                }

                if (empty($strError) && !empty($username) && !Fields::validUserName($username)) {
                    $strError = $this->_tr->translate('Incorrect characters in username');
                }

                $arrErrors = array();
                if (empty($strError) && !empty($password) && !$this->_authHelper->isPasswordValid($password, $arrErrors, $username, $memberId)) {
                    $strError = implode('<br/>', $arrErrors);
                }

                if (empty($strError)) {
                    if ($usernameFieldId != $this->_clients->getApplicantFields()->getUsernameFieldId($memberId)) {
                        $strError = $this->_tr->translate('Incorrectly selected username field');
                    }
                }

                $booUsernameUpdated = false;
                if (empty($strError)) {
                    // Check if we'll update username
                    if (!empty($username)) {
                        $booUsernameUpdated = !$this->_members->isUsernameAlreadyUsed($username);
                    }

                    if ($this->_clients->updateApplicantCredentials($memberId, $this->_auth->getCurrentUserCompanyId(), $usernameFieldId, $username, $password)) {
                        //blank username
                        if (empty($username)) {
                            $message = 'The username is set to blank & this client will not be able to login to Officio.';
                        } elseif ($booUsernameUpdated) {
                            $message = 'Username and password were changed!';
                        } else {
                            $message = 'Password was changed!';
                        }

                        // Log this change
                        $arrAllFieldsData = array(
                            $memberId => array(
                                'booIsApplicant'    => true,
                                'arrStaticMessages' => array($message)
                            )
                        );
                        $this->_triggers->triggerFieldBulkChanges($this->_company->getMemberCompanyId($memberId), $arrAllFieldsData, true);
                    } else {
                        $strError = $this->_tr->translate('Internal error. Please try again later.');
                    }
                }
            } else {
                $strError = $this->_tr->translate('Incorrectly sent request');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'                  => empty($strError),
            'message'                  => empty($strError) ? $message : $strError,
            'username'                 => $username,
            'password'                 => empty($password) ? '' : '*******',
            'applicantEncodedPassword' => empty($strError) && !empty($password) ? $this->_encryption->encode($password) : ''
        );
        return new JsonModel($arrResult);
    }

    public function checkEmployerCaseAction()
    {
        $strMessage  = '';
        $messageType = 'error';

        try {
            $applicantId = Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights.');
            }

            $caseId = Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);
            if (empty($strMessage) && !$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strMessage = $this->_tr->translate('Incorrectly selected case.');
            }

            $selectedCaseId = Json::decode($this->params()->fromPost('selectedCaseId'), Json::TYPE_ARRAY);
            if (empty($strMessage) && !$this->_members->hasCurrentMemberAccessToMember($selectedCaseId)) {
                $strMessage = $this->_tr->translate('Incorrectly selected case in the combobox.');
            }

            if (empty($strMessage)) {
                $companyId        = $this->_auth->getCurrentUserCompanyId();
                $arrCaseInfo      = $this->_clients->getClientInfoOnly($selectedCaseId);
                $arrCompanyFields = $this->_clients->getFields()->getAllGroupsAndFields($companyId, $arrCaseInfo['client_type_id'], true);

                // Check if selected case has Nomination Ceiling field in his CaseTemplate
                $nominationCeilingFieldId = 0;
                foreach ($arrCompanyFields as $arrGroupInfo) {
                    foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                        if ($arrFieldInfo['company_field_id'] == 'nomination_ceiling') {
                            $nominationCeilingFieldId = $arrFieldInfo['field_id'];
                            break 2;
                        }
                    }
                }

                // If there is no Nomination Ceiling field - don't show any warning
                if (!empty($nominationCeilingFieldId)) {
                    // Get 'nomination count' field value for selected case
                    $nominatedCount = $this->_clients->getFields()->getFieldDataValue($nominationCeilingFieldId, $selectedCaseId);
                    $nominatedCount = is_numeric($nominatedCount) ? intval($nominatedCount) : null;

                    // Do the check only if something was set in the Nomination Ceiling field
                    if (!is_null($nominatedCount)) {
                        // Check how many times selected case was already assigned
                        $arrCompanyCases   = $this->_clients->getClientsList();
                        $arrCompanyCaseIds = array();
                        foreach ($arrCompanyCases as $arrCompanyCaseInfo) {
                            $arrCompanyCaseIds[] = $arrCompanyCaseInfo['member_id'];
                        }

                        $oldSelectedCaseId = 0;
                        $caseAssignedCount = 0;
                        $arrSavedValues    = $this->_clients->getCasesLinkedEmployerCases($arrCompanyCaseIds);
                        foreach ($arrSavedValues as $masterCaseId => $arrSavedValueInfo) {
                            if ($masterCaseId == $caseId) {
                                $oldSelectedCaseId = $arrSavedValueInfo['linkedCaseId'];
                            }

                            if ($arrSavedValueInfo['linkedCaseId'] == $selectedCaseId) {
                                $caseAssignedCount++;
                            }
                        }

                        if ($oldSelectedCaseId != $selectedCaseId) {
                            $messageType = 'confirmation';
                            if (!empty($nominatedCount) && $caseAssignedCount >= $nominatedCount - 1) {
                                $strMessage = $this->_tr->translate(
                                    'This Sponsorship has only one open Nomination remaining after this application.<br><br>' .
                                    'Do you wish to continue?'
                                );
                            } elseif ($caseAssignedCount >= $nominatedCount) {
                                $strMessage = $this->_tr->translate(
                                    'This Sponsorship has already reached its approved Nomination Ceiling.<br><br>' .
                                    'Do you still wish to continue?'
                                );
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'msg'      => $strMessage,
            'msg_type' => $messageType
        );
        return new JsonModel($arrResult);
    }

    public function generateCaseNumberAction()
    {
        $newCaseNumber         = '';
        $startCaseNumberFrom   = '';
        $subclassMarkInvalidId = '';
        $strError              = '';
        $boolIsReserved        = false;

        try {
            $individualClientId = Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            $employerClientId   = Json::decode($this->params()->fromPost('caseEmployerId'), Json::TYPE_ARRAY);
            $caseId             = Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);
            $caseTemplateId     = Json::decode($this->params()->fromPost('caseType'), Json::TYPE_ARRAY);
            $arrParams          = $this->params()->fromPost();
            $companyId          = $this->_auth->getCurrentUserCompanyId();

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($individualClientId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            if (empty($strError) && !empty($employerClientId) && !$this->_members->hasCurrentMemberAccessToMember($employerClientId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            if (empty($strError) && !empty($caseId) && (!$this->_members->hasCurrentMemberAccessToMember($caseId) || !$oCompanyDivisions->canCurrentMemberEditClient($caseId))) {
                $strError = $this->_tr->translate('Insufficient access rights to the case.');
            }

            if (empty($strError) && empty($employerClientId) && !empty($individualClientId)) {
                // Check if a passed client is employer
                $arrParentInfo = $this->_clients->getMemberInfo($individualClientId);
                if ($arrParentInfo['userType'] == $this->_clients->getMemberTypeIdByName('employer')) {
                    $employerClientId   = $individualClientId;
                    $individualClientId = 0;
                }
            }

            $savedCaseFileNumber = '';
            if (empty($strError)) {
                if (!empty($caseId)) {
                    // Load the already saved case file number
                    $arrSavedCaseInfo    = $this->_clients->getClientInfoOnly($caseId);
                    $savedCaseFileNumber = $arrSavedCaseInfo['fileNumber'] ?? '';
                } else {
                    // Try to use the case file number that was just provided
                    $arrGroupedFields = $this->_clients->getFields()->getGroupedCompanyFields($caseTemplateId);
                    foreach ($arrGroupedFields as $arrGroupInfo) {
                        foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                            if ($arrFieldInfo['field_unique_id'] == 'file_number') {
                                $savedCaseFileNumber = $arrParams['field_case_' . $arrGroupInfo['group_id'] . '_' . $arrFieldInfo['field_id']][0] ?? '';
                                break 2;
                            }
                        }
                    }
                }
            }


            // Generate number
            $intMaxAttempts     = 20;
            $intAttempt         = 0;
            $arrCompanySettings = $this->_clients->getCaseNumber()->getCompanyCaseNumberSettings($companyId);

            while (!$boolIsReserved && !$strError && ($intAttempt < $intMaxAttempts)) {
                $intAttempt++;

                $arrResultGenerateCaseReference = $this->_clients->getCaseNumber()->generateCaseReference($arrParams, $individualClientId, $employerClientId, $caseId, $caseTemplateId, $intAttempt);

                $strError              = $arrResultGenerateCaseReference['strError'];
                $newCaseNumber         = $arrResultGenerateCaseReference['newCaseNumber'];
                $startCaseNumberFrom   = $arrResultGenerateCaseReference['startCaseNumberFrom'];
                $subclassMarkInvalidId = $arrResultGenerateCaseReference['subclassMarkInvalidId'];
                $increment             = $arrResultGenerateCaseReference['increment'];

                if (empty($strError)) {
                    if ($savedCaseFileNumber === $newCaseNumber) {
                        // Don't show an error if the generated case number is the same as already saved
                        $boolIsReserved = true;
                    } else {
                        $booBasedOnCaseType = array_key_exists('cn-global-or-based-on-case-type', $arrCompanySettings) && $arrCompanySettings['cn-global-or-based-on-case-type'] === 'case-type';

                        // do not reserve case number if it is based on the Immigration Program
                        if (!$booBasedOnCaseType) {
                            $boolIsReserved = $this->_clients->getCaseNumber()->reserveFileNumber($companyId, $newCaseNumber, $increment);
                        } else {
                            $boolIsReserved = true;
                        }
                    }
                }
            }

            if ($intAttempt == $intMaxAttempts && !$boolIsReserved && !$strError) {
                $strError = $this->_tr->translate('Could not generate new unique file number - reached maximum number of attempts.');
                $this->_log->debugErrorToFile(
                    sprintf('Could not generate new unique file number - reached maximum number of attempts. companyId = %s, applicantId = %s, caseId = %s', $companyId, $individualClientId, $caseId),
                    null,
                    'case_number'
                );
                $newCaseNumber = '';
            }

            if (empty($strError) && !empty($newCaseNumber) && !empty($startCaseNumberFrom)) {
                $arrCompanySettings['cn-start-number-from-text'] = $startCaseNumberFrom;
                $this->_clients->getCaseNumber()->saveCaseNumberSettings($companyId, $arrCompanySettings);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'success'        => empty($strError),
            'message'        => $strError,
            'newCaseNumber'  => $newCaseNumber,
            'arrErrorFields' => empty($subclassMarkInvalidId) ? [] : [$subclassMarkInvalidId]
        ];

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', Json::encode($arrResult));
        return $view;
    }

    public function releaseCaseNumberAction()
    {
        $strError = '';

        try {
            $companyId  = $this->_auth->getCurrentUserCompanyId();
            $caseNumber = Json::decode($this->params()->fromPost('caseNumber'), Json::TYPE_ARRAY);

            $this->_clients->getCaseNumber()->releaseFileNumber($companyId, $caseNumber);
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return new JsonModel($arrResult);
    }

    public function generateKsKeyAction()
    {
        $strError = '';
        $newKsKey = '';

        try {
            $caseId = Json::decode($this->findParam('caseId'), Json::TYPE_ARRAY);

            if (empty($strError) && !empty($caseId) && (!$this->_members->hasCurrentMemberAccessToMember($caseId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($caseId))) {
                $strError = $this->_tr->translate('Insufficient access rights to the case.');
            }

            if (empty($strError)) {
                $arrResultGenerateKsKey = $this->_clients->generateKsKey($caseId);

                $strError = $arrResultGenerateKsKey['strError'];
                $newKsKey = $arrResultGenerateKsKey['newKsKey'];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'  => empty($strError),
            'message'  => $strError,
            'newKsKey' => $newKsKey
        );

        return new JsonModel($arrResult);
    }

    public function changeMyPasswordAction()
    {
        $view = new JsonModel();

        $booError = false;
        try {
            // collect the data from post
            $filter = new StripTags();

            $oldPassword = $filter->filter(Json::decode($this->findParam('oldPassword'), Json::TYPE_ARRAY));
            $newPassword = $filter->filter(Json::decode($this->findParam('newPassword'), Json::TYPE_ARRAY));

            $arrErrors = array();
            $memberId  = $this->_auth->getCurrentUserId();
            $username  = $this->_auth->getCurrentUserUsername();
            if (empty($oldPassword)) {
                $message  = $this->_tr->translate("Old password cannot be empty.");
                $booError = true;
            } elseif (empty($newPassword)) {
                $message  = $this->_tr->translate("New password cannot be empty.");
                $booError = true;
            } elseif (!$this->_authHelper->isPasswordValid($newPassword, $arrErrors, $username, $memberId)) {
                $message  = implode('<br/>', $arrErrors);
                $booError = true;
            } else {
                $arrMemberInfo = $this->_members->getMemberInfo($memberId);

                if (!$this->_encryption->checkPasswords($oldPassword, $arrMemberInfo['password'])) {
                    $message  = $this->_tr->translate("Sorry, you have entered incorrect old password.");
                    $booError = true;
                } else {
                    $arrMemberInfo['password'] = $newPassword;

                    $this->_members->updateMemberData(
                        $memberId,
                        array(
                            'password'             => $this->_encryption->hashPassword($newPassword),
                            'password_change_date' => time()
                        )
                    );

                    // Send confirmation email to this user
                    $this->_authHelper->triggerPasswordHasBeenChanged($arrMemberInfo);

                    $message = $this->_tr->translate("Password changed successfully.");
                }
            }
        } catch (Exception $e) {
            $booError = true;
            $message  = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $arrResult = array(
            'success' => !$booError,
            'message' => $message
        );
        return $view->setVariables($arrResult);
    }

    public function getVevoInfoAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        set_time_limit(60 * 5); // 5 minutes
        ini_set('memory_limit', '512M');
        session_write_close();

        // We try turn off buffering at all and only respond with correct data
        @ob_end_clean();
        try {
            while (@ob_get_level() > 0) {
                @ob_end_flush();
            }
        } catch (Exception) {
        }
        ob_implicit_flush();

        $content = "<html><body>";
        $content .= str_pad('', 1024);

        $strMessage          = $fileId = '';
        $arrFieldsData       = $arrVevoInfo = array();
        $booEmptyFieldsExist = false;

        try {
            $filter            = new StripTags();
            $clientId          = Json::decode($this->findParam('client_id'), Json::TYPE_ARRAY);
            $memberId          = Json::decode($this->findParam('member_id'), Json::TYPE_ARRAY);
            $countrySuggestion = $filter->filter(Json::decode($this->findParam('countrySuggestion'), Json::TYPE_ARRAY));

            $applicantId = 0;

            if (!$this->_members->hasCurrentMemberAccessToMember($clientId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strMessage)) {
                $booHasAccessToMemberVevoAccount = false;

                $arrMembersFromVevoMappingList = $this->_membersVevo->getMembersFromVevoMappingList($this->_auth->getCurrentUserId());
                foreach ($arrMembersFromVevoMappingList as $memberFromInfo) {
                    if ($memberFromInfo['option_id'] == $memberId) {
                        $booHasAccessToMemberVevoAccount = true;
                        break;
                    }
                }
                if (!$booHasAccessToMemberVevoAccount) {
                    $strMessage = $this->_tr->translate('Incorrectly selected user in the combobox.');
                }
            }

            if (empty($strMessage)) {
                $booHasAccessToMemberVevoAccount = false;

                $arrMembersFromVevoMappingList = $this->_membersVevo->getMembersFromVevoMappingList($this->_auth->getCurrentUserId());
                foreach ($arrMembersFromVevoMappingList as $memberFromInfo) {
                    if ($memberFromInfo['option_id'] == $memberId) {
                        $booHasAccessToMemberVevoAccount = true;
                        break;
                    }
                }
                if (!$booHasAccessToMemberVevoAccount) {
                    $strMessage = $this->_tr->translate('Incorrectly selected user in the combobox.');
                }
            }

            if (empty($strMessage)) {
                $arrMemberInfo = $this->_members->getMemberInfo($clientId);

                if (in_array($arrMemberInfo['userType'], $this->_members::getMemberType('case'))) {
                    $arrParentIds = $this->_clients->getParentsForAssignedApplicant($clientId, $this->_clients->getMemberTypeIdByName('individual'));
                    if (count($arrParentIds)) {
                        $applicantId = $arrParentIds[0];
                    } else {
                        $strMessage = $this->_tr->translate('Incorrectly selected client.');
                    }
                } else {
                    $strMessage = $this->_tr->translate('Incorrectly selected client.');
                }
            }

            if (empty($strMessage)) {
                list($arrAllApplicantFieldsData,) = $this->_clients->getAllApplicantFieldsData($applicantId, $this->_clients->getMemberTypeIdByName('individual'));

                $arrInternalContactType = Members::getMemberType('internal_contact');
                $arrFieldIds            = array(
                    'family_name'     => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('family_name', $arrInternalContactType),
                    'given_names'     => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('given_names', $arrInternalContactType),
                    'DOB'             => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('DOB', $arrInternalContactType),
                    'passport_number' => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('passport_number', $arrInternalContactType)
                );

                foreach ($arrFieldIds as $uniqueFieldId => $fieldId) {
                    $booFound = false;
                    foreach ($arrAllApplicantFieldsData as $key => $arrValue) {
                        preg_match('/.*_[\d]*_([\d]*)/', $key, $arrMatches);
                        if (!empty($arrMatches) && isset($arrMatches[1]) && $fieldId == $arrMatches[1]) {
                            $booFound = true;

                            $arrFieldsData[$uniqueFieldId] = array(
                                'field_name' => $this->_clients->getApplicantFields()->getFieldName($fieldId),
                                'value'      => $arrValue[0]
                            );
                        }
                    }
                    if (!$booFound) {
                        $booEmptyFieldsExist = true;
                        $strMessage          = $this->_tr->translate('Required information was not completed.');

                        $arrFieldsData[$uniqueFieldId] = array(
                            'field_name' => $this->_clients->getApplicantFields()->getFieldName($fieldId),
                            'value'      => ''
                        );
                    }
                }

                $countryOfPassportFieldId = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('country_of_passport', $arrInternalContactType);

                $arrFieldsData['country_of_passport'] = array(
                    'field_name' => $this->_clients->getApplicantFields()->getFieldName($countryOfPassportFieldId),
                    'value'      => $countrySuggestion
                );
            }

            if (empty($strMessage)) {
                list($strMessage, $arrVevoInfo, $fileId) = $this->_membersVevo->getVevoInfo($memberId, $arrFieldsData);
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $arrResult = array(
            'success'          => empty($strMessage),
            'message'          => $strMessage,
            'fields'           => $arrFieldsData,
            'boo_empty_fields' => $booEmptyFieldsExist,
            'vevo_info'        => $arrVevoInfo,
            'file_id'          => $fileId
        );

        $content .= $this->_membersVevo->outputResult($arrResult);

        $content .= "</body></html>";
        $view->setVariable('content', $content);
        return $view;
    }

    public function updateVevoInfoAction()
    {
        $view = new JsonModel();

        $strMessage    = '';
        $arrFieldsData = array();

        try {
            $filter          = new StripTags();
            $clientId        = $this->findParam('client_id');
            $arrUpdateFields = Json::decode($this->findParam('arr_update_fields'), Json::TYPE_ARRAY);

            $applicantId = 0;

            if (!$this->_members->hasCurrentMemberAccessToMember($clientId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($clientId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strMessage)) {
                $arrMemberInfo = $this->_members->getMemberInfo($clientId);

                if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                    $arrParentIds = $this->_clients->getParentsForAssignedApplicant($clientId, $this->_clients->getMemberTypeIdByName('individual'));
                    if (count($arrParentIds)) {
                        $applicantId = $arrParentIds[0];
                    } else {
                        $strMessage = $this->_tr->translate('Incorrectly selected client.');
                    }
                } else {
                    $applicantId = $clientId;
                }
            }

            if (empty($strMessage)) {
                $companyId   = $this->_auth->getCurrentUserCompanyId();
                $memberTypes = array_merge(Members::getMemberType('individual'), Members::getMemberType('internal_contact'));

                foreach ($arrUpdateFields as $updateField) {
                    if ($updateField['field_type'] == 'date') {
                        if (!Settings::isValidDateFormat($updateField['field_value'], 'd M Y')) {
                            $strMessage = $this->_tr->translate('Incorrectly selected date.');
                            break;
                        } else {
                            $updateField['field_value'] = $this->_settings->reformatDate($updateField['field_value'], 'd M Y', 'Y-m-d');
                        }
                    }

                    $fieldId  = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId($updateField['unique_field_id'], $memberTypes);
                    $settings = array(
                        'field_id'                 => $fieldId,
                        $updateField['field_type'] => $filter->filter($updateField['field_value']),
                        'member_type'              => 'individual'
                    );

                    list($strMessage,) = $this->_clients->changeFieldValue($applicantId, $companyId, $settings);

                    if (empty($strMessage)) {
                        list($arrAllApplicantFieldsData,) = $this->_clients->getAllApplicantFieldsData($applicantId, $this->_clients->getMemberTypeIdByName('individual'));

                        foreach ($arrAllApplicantFieldsData as $key => $arrValue) {
                            preg_match('/.*_[\d]*_([\d]*)/', $key, $arrMatches);
                            if (!empty($arrMatches) && isset($arrMatches[1]) && $fieldId == $arrMatches[1]) {
                                if ($updateField['field_type'] == 'date') {
                                    $updateField['field_value'] = $this->_settings->reformatDate($updateField['field_value'], 'Y-m-d', 'd M Y');
                                }
                                $arrFieldsData[] = array(
                                    'full_field_id' => $key,
                                    'value'         => $updateField['field_value']
                                );
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strMessage),
            'message' => $strMessage,
            'fields'  => $arrFieldsData,
        );
        return $view->setVariables($arrResult);
    }

    public function getReferenceFieldViewAction()
    {
        $view = new JsonModel();

        $referenceApplicantId   = 0;
        $referenceApplicantName = '';
        $referenceApplicantType = 0;
        $referenceCaseId        = 0;
        $referenceCaseName      = '';
        $referenceCaseTypeId    = 0;
        $referenceText          = '';
        $value                  = '';
        $booWrongValue          = true;
        $strError               = '';

        try {
            $filter      = new StripTags();
            $applicantId = (int)Json::decode($this->findParam('applicantId'), Json::TYPE_ARRAY);
            $fieldId     = (int)Json::decode($this->findParam('fieldId'), Json::TYPE_ARRAY);
            $value       = $filter->filter(Json::decode($this->findParam('value'), Json::TYPE_ARRAY));

            $booMultipleValues = false;

            if (!empty($applicantId) && (!$this->_members->hasCurrentMemberAccessToMember($applicantId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($applicantId))) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($fieldId)) {
                $strError = $this->_tr->translate('Incorrectly selected field.');
            }

            if (empty($strError)) {
                $arrApplicantInfo = $this->_members->getMemberInfo($applicantId);
                $memberType       = $this->_clients->getMemberTypeNameById($arrApplicantInfo['userType']);
                if ($memberType == 'case') {
                    $arrFieldInfo = $this->_clients->getFields()->getFieldInfo($fieldId, $arrApplicantInfo['company_id']);
                } else {
                    $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldInfo($fieldId, $arrApplicantInfo['company_id']);
                }

                if (empty($arrFieldInfo) || !isset($arrFieldInfo['multiple_values'])) {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                } else {
                    $booMultipleValues = $arrFieldInfo['multiple_values'] == 'Y';
                }
            }

            if (empty($strError)) {
                $arrReference = $this->_clients->getFields()->prepareReferenceField($value, $booMultipleValues);
                $arrReference = $arrReference[0];

                $referenceApplicantId   = $arrReference['applicantId'];
                $referenceApplicantName = $arrReference['applicantName'];
                $referenceApplicantType = $arrReference['applicantType'];
                $referenceCaseId        = $arrReference['caseId'];
                $referenceCaseName      = $arrReference['caseName'];
                $referenceCaseTypeId    = $arrReference['caseType'];
                $referenceText          = $arrReference['reference'];
                $value                  = $arrReference['value'];
                $booWrongValue          = $arrReference['booWrongValue'];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'       => empty($strError),
            'message'       => $strError,
            'applicantId'   => $referenceApplicantId,
            'applicantName' => $referenceApplicantName,
            'applicantType' => $referenceApplicantType,
            'caseId'        => $referenceCaseId,
            'caseName'      => $referenceCaseName,
            'caseType'      => $referenceCaseTypeId,
            'reference'     => $referenceText,
            'value'         => $value,
            'booWrongValue' => $booWrongValue
        );
        return $view->setVariables($arrResult);
    }

    public function getProfileImageAction()
    {
        try {
            $memberId    = $this->params()->fromQuery('mid');
            $dependentId = $this->params()->fromQuery('did');
            $fieldId     = $this->params()->fromQuery('id');

            $booLocal  = $this->_auth->isCurrentUserCompanyStorageLocal();
            $companyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($dependentId) && empty($fieldId)) {
                if ($this->_members->hasCurrentMemberAccessToMember($memberId) && $this->_clients->hasCurrentMemberAccessToDependent($dependentId)) {
                    $arrDependentInfo = $this->_clients->getDependentInfo($dependentId);
                    $dependentsPath   = $this->_files->getCompanyDependantsPath($companyId, $booLocal);

                    $filePath = $dependentsPath . '/' . $memberId . '/' . $dependentId . '/original';
                    if ($booLocal) {
                        return $this->downloadFile($filePath, $arrDependentInfo['photo'], '', false, false);
                    } else {
                        $url = $this->_files->getCloud()->getFile($filePath, $arrDependentInfo['photo'], false, false);
                        if ($url) {
                            return $this->redirect()->toUrl($url);
                        } else {
                            return $this->fileNotFound();
                        }
                    }
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            } elseif ($this->_members->hasCurrentMemberAccessToMember($memberId) && !empty($fieldId)) {
                $filePath         = $this->_files->getPathToClientImages($companyId, $memberId, $booLocal) . '/field-' . $fieldId;
                $filePathOriginal = $filePath . '-original';

                if ($booLocal ? is_file($filePathOriginal) : $this->_files->getCloud()->checkObjectExists($filePathOriginal)) {
                    $filePath = $filePathOriginal;
                }

                $arrClientInfo = $this->_clients->getClientInfo($memberId);
                if ($arrClientInfo['userType'] == $this->_clients->getMemberTypeIdByName('case')) {
                    $fieldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $memberId);
                } else {
                    $fieldValue = $this->_clients->getApplicantFields()->getFieldDataValue($memberId, $fieldId);
                }

                if ($booLocal) {
                    return $this->downloadFile($filePath, $fieldValue, '', false, false);
                } else {
                    $url = $this->_files->getCloud()->getFile($filePath, $fieldValue, false, false);
                    if ($url) {
                        return $this->redirect()->toUrl($url);
                    } else {
                        return $this->fileNotFound();
                    }
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strError);

        return $view;
    }

    public function getVevoCountrySuggestionsAction()
    {
        $view = new JsonModel();

        $arrSuggestions              = array();
        $countryOfPassportFieldValue = '';
        $strError                    = '';
        $booCorrectValue             = false;

        try {
            $applicantId = (int)Json::decode($this->findParam('applicantId'), Json::TYPE_ARRAY);

            if (!empty($applicantId) && (!$this->_members->hasCurrentMemberAccessToMember($applicantId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($applicantId))) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrMemberInfo = $this->_members->getMemberInfo($applicantId);

                if (!in_array($arrMemberInfo['userType'], Members::getMemberType('individual'))) {
                    $strError = $this->_tr->translate('Incorrectly selected client.');
                }
            }

            if (empty($strError)) {
                list($arrAllApplicantFieldsData,) = $this->_clients->getAllApplicantFieldsData($applicantId, $this->_clients->getMemberTypeIdByName('individual'));
                $arrInternalContactType   = Members::getMemberType('internal_contact');
                $countryOfPassportFieldId = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('country_of_passport', $arrInternalContactType);

                foreach ($arrAllApplicantFieldsData as $key => $arrValue) {
                    preg_match('/.*_[\d]*_([\d]*)/', $key, $arrMatches);
                    if (!empty($arrMatches) && isset($arrMatches[1]) && $countryOfPassportFieldId == $arrMatches[1]) {
                        $countryOfPassportFieldValue = $arrValue[0];
                    }
                }

                if ($this->_country->getCountryIdByName($countryOfPassportFieldValue, 'vevo')) {
                    $booCorrectValue = true;
                } else {
                    $arrSuggestions = $this->_membersVevo->getVevoCountiesSuggestionsList($countryOfPassportFieldValue);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'            => empty($strError),
            'message'            => $strError,
            'countrySuggestions' => $arrSuggestions,
            'countryFieldValue'  => $countryOfPassportFieldValue,
            'booCorrectValue'    => $booCorrectValue
        );
        return $view->setVariables($arrResult);
    }

    /**
     * Load "Certificate of Naturalisation" (CON) html template, process it for the specific client
     * @return false|ViewModel
     */
    public function generateConAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $strResult = '';

        try {
            $applicantId = 0;
            $caseId      = Json::decode($this->findParam('client_id'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strResult = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strResult)) {
                $arrMemberInfo = $this->_members->getMemberInfo($caseId);

                if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                    $arrParentIds = $this->_clients->getParentsForAssignedApplicant($caseId, $this->_clients->getMemberTypeIdByName('individual'));
                    if (count($arrParentIds)) {
                        $applicantId = $arrParentIds[0];
                    } else {
                        $strResult = $this->_tr->translate('Incorrectly selected client.');
                    }
                } else {
                    $strResult = $this->_tr->translate('Incorrectly selected client.');
                }
            }

            if (empty($strResult)) {
                $arrInternalContactType = Members::getMemberType('internal_contact');
                $arrFieldIds            = array(
                    'fName'          => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId($this->_clients->getFields()->getStaticColumnNameByFieldId('fName'), $arrInternalContactType),
                    'lName'          => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId($this->_clients->getFields()->getStaticColumnNameByFieldId('lName'), $arrInternalContactType),
                    'address_1'      => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('address_1', $arrInternalContactType),
                    'address_2'      => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('address_2', $arrInternalContactType),
                    'city'           => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('city', $arrInternalContactType),
                    'state'          => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('state', $arrInternalContactType),
                    'country'        => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('country', $arrInternalContactType),
                    'zip_code'       => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('zip_code', $arrInternalContactType),
                    'occupation'     => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('occupation', $arrInternalContactType),
                    'place_of_birth' => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('country_of_birth', $arrInternalContactType),
                    'sex'            => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('sex', $arrInternalContactType),
                    'date_of_birth'  => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('DOB', $arrInternalContactType),
                    'marital_status' => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('relationship_status', $arrInternalContactType),
                    'name_of_spouse' => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('name_of_spouse', $arrInternalContactType),
                    'photo'          => $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('photo', $arrInternalContactType)
                );

                $companyId = $this->_auth->getCurrentUserCompanyId();
                $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);
                list($arrAllApplicantFieldsData,) = $this->_clients->getAllApplicantFieldsData($applicantId, $this->_clients->getMemberTypeIdByName('individual'));

                $arrMainApplicantData = array();
                foreach ($arrFieldIds as $uniqueFieldId => $fieldId) {
                    $arrMainApplicantData[$uniqueFieldId] = '';

                    foreach ($arrAllApplicantFieldsData as $key => $arrValue) {
                        preg_match('/.*_([\d]*)_([\d]*)/', $key, $arrMatches);
                        if (!empty($arrMatches) && isset($arrMatches[2]) && $fieldId == $arrMatches[2]) {
                            switch ($uniqueFieldId) {
                                case 'photo':
                                    // @Note: remove or revert (Temporary don't show)
                                    $internalContactId = 0;
                                    // $internalContactId = $this->_clients->getAssignedContact($applicantId, $arrMatches[1]);

                                    if (!empty($internalContactId)) {
                                        $filePath         = $this->_files->getPathToClientImages($companyId, $internalContactId, $booLocal) . '/field-' . $fieldId;
                                        $filePathOriginal = $filePath . '-original';

                                        if ($booLocal ? is_file($filePathOriginal) : $this->_files->getCloud()->checkObjectExists($filePathOriginal)) {
                                            $filePath = $filePathOriginal;
                                        }

                                        if ($booLocal ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath)) {
                                            $pathToTemp = $filePath . '-con';

                                            if ($booLocal) {
                                                $this->_files->copyFile($filePath, $pathToTemp, $booLocal);
                                            } else {
                                                $pathToTemp = $this->_files->getCloud()->downloadFileContent($filePath);
                                            }

                                            if (is_file($pathToTemp)) {
                                                if ($filePath == $filePathOriginal) {
                                                    $imageConfig['source_image'] = $pathToTemp;
                                                    $imageConfig['width']        = 132;
                                                    $imageConfig['height']       = 170;
                                                    $imageManager                = new ImageManager($this->_tr, $imageConfig);

                                                    if (!$imageManager->resize()) {
                                                        throw new Exception($imageManager->display_errors());
                                                    }
                                                }

                                                // This path will be used later, during pdf file generation
                                                $arrMainApplicantData['photo_path'] = $this->_encryption->encode($pathToTemp);

                                                // Embed and show this image directly to the html
                                                $type                                 = pathinfo($pathToTemp, PATHINFO_EXTENSION);
                                                $data                                 = file_get_contents($pathToTemp);
                                                $base64                               = 'data:image/' . $type . ';base64,' . base64_encode($data);
                                                $arrMainApplicantData[$uniqueFieldId] = "<img src='$base64' alt='Photo' width='200px' />";
                                            }
                                        }
                                    }
                                    break;

                                case 'date_of_birth':
                                    $arrMainApplicantData[$uniqueFieldId] = strtoupper(date('F d, Y', strtotime($arrValue[0])));
                                    break;

                                default:
                                    $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldInfo($fieldId, $companyId);
                                    list($readableValue,) = $this->_clients->getFields()->getFieldReadableValue(
                                        $companyId,
                                        $this->_auth->getCurrentUserDivisionGroupId(),
                                        $arrFieldInfo,
                                        $arrValue[0],
                                        true,
                                        true,
                                        true,
                                        true,
                                        true,
                                        true
                                    );
                                    $arrMainApplicantData[$uniqueFieldId] = strtoupper($readableValue);
                                    break;
                            }
                            break;
                        }
                    }
                }

                $dependantsPath = $this->_files->getCompanyDependantsPath($companyId, $booLocal);

                $originalTemplate = SystemTemplate::loadOne(['title' => 'Certificate of Naturalisation (for HTML preview)']);
                if (empty($originalTemplate->system_template_id)) {
                    return false;
                }
                $strOriginalTemplate = $this->_systemTemplates::fixEncoding($originalTemplate->template);

                // Use spouse's details if that specific field wasn't found or no data was saved in that field
                $arrDependents = $this->_clients->getFields()->getDependents(array($caseId), false);
                if (empty($arrMainApplicantData['name_of_spouse'])) {
                    foreach ($arrDependents as $arrDependentInfo) {
                        if ($arrDependentInfo['relationship'] == 'spouse') {
                            $arrMainApplicantData['name_of_spouse'] = strtoupper(trim($arrDependentInfo['fName'] . ' ' . $arrDependentInfo['lName']));

                            // Always set 'married' if there is spouse and no data was saved in the "relationship_status" field
                            if (empty($arrMainApplicantData['marital_status'])) {
                                $arrMainApplicantData['marital_status'] = 'MARRIED';
                            }
                            break;
                        }
                    }
                }

                if (empty($arrMainApplicantData['marital_status'])) {
                    $arrMainApplicantData['marital_status'] = 'N/A';
                }

                if (empty($arrMainApplicantData['name_of_spouse'])) {
                    $arrMainApplicantData['name_of_spouse'] = 'N/A';
                }

                $pageNumber = 1;

                // If there are previously generated CON numbers, we use them
                $conNumbers = $this->_dominica->getCaseConNumbers($companyId, $caseId);

                $arrMainApplicantData['page_number']           = $pageNumber;
                $arrMainApplicantData['main_applicant_name']   = strtoupper(trim($arrMainApplicantData['fName'] . ' ' . $arrMainApplicantData['lName']));
                $arrMainApplicantData['main_applicant_name_2'] = strtoupper(trim($arrMainApplicantData['fName'] . ' ' . $arrMainApplicantData['lName']));
                $arrMainApplicantData['main_applicant_name_3'] = strtoupper(trim($arrMainApplicantData['fName'] . ' ' . $arrMainApplicantData['lName']));
                $arrMainApplicantData['con_number']            = $conNumbers['main_applicant'] ?? '';
                $arrMainApplicantData['dependent_id']          = 'main_applicant';

                $arrMainApplicantData['self_name'] = '';
                if (!empty($arrMainApplicantData['sex'])) {
                    $arrMainApplicantData['self_name'] = $arrMainApplicantData['sex'] === 'MALE' ? 'himself' : 'herself';
                }

                // Generate one address filed from several fields
                $arrAddressLines  = array();
                $arrAddressFields = array(
                    array('address_1', 'address_2'),
                    array('city', 'state'),
                    array('country', 'zip_code'),
                );
                foreach ($arrAddressFields as $arrAddressFieldsPerLine) {
                    $arrAddressLine = array();
                    foreach ($arrAddressFieldsPerLine as $fieldId) {
                        if (isset($arrMainApplicantData[$fieldId]) && !empty($arrMainApplicantData[$fieldId])) {
                            $arrAddressLine[] = $arrMainApplicantData[$fieldId];
                        }
                    }

                    if (count($arrAddressLine)) {
                        $arrAddressLines[] = trim(implode(', ', $arrAddressLine));
                    }
                }
                $arrMainApplicantData['address'] = implode(PHP_EOL, $arrAddressLines);

                $replacements   = $this->_pdf->getTemplateReplacements($arrMainApplicantData);
                $arrHtmlPages[] = $this->_systemTemplates->processText($strOriginalTemplate, $replacements);


                // Generate a separate page for each dependent
                foreach ($arrDependents as $arrDependentInfo) {
                    $dependentName = strtoupper(trim($arrDependentInfo['fName'] . ' ' . $arrDependentInfo['lName']));

                    // Use dependent's address if 'Address is same as the main applicant's address' checkbox is not checked
                    if ($arrDependentInfo['main_applicant_address_is_the_same'] == 'Y') {
                        $address = $arrMainApplicantData['address'];
                    } else {
                        $address = $arrDependentInfo['address'];
                    }

                    $pageNumber++;
                    $arrFieldsData                          = array();
                    $arrFieldsData['main_applicant_name']   = $dependentName;
                    $arrFieldsData['main_applicant_name_2'] = $dependentName;
                    $arrFieldsData['main_applicant_name_3'] = $dependentName;
                    $arrFieldsData['address']               = $address;
                    $arrFieldsData['occupation']            = strtoupper(trim($arrDependentInfo['profession'] ?? ''));
                    $arrFieldsData['place_of_birth']        = strtoupper(trim($arrDependentInfo['place_of_birth']) ?? '');
                    $arrFieldsData['date_of_birth']         = strtoupper(date('F d, Y', strtotime($arrDependentInfo['DOB'])));
                    $arrFieldsData['marital_status']        = strtoupper($this->_clients->getFields()->getDependentFieldOptionLabel('marital_status', $arrDependentInfo['marital_status']) ?? '');
                    $arrFieldsData['sex']                   = isset($arrDependentInfo['sex']) ? strtoupper($this->_clients->getFields()->getDependentFieldOptionLabel('sex', $arrDependentInfo['sex'])) : '';
                    $arrFieldsData['name_of_spouse']        = strtoupper(trim($arrDependentInfo['spouse_name']) ?? '');
                    $arrFieldsData['page_number']           = $pageNumber;
                    $arrFieldsData['con_number']            = $conNumbers[$arrDependentInfo['dependent_id']] ?? '';
                    $arrFieldsData['dependent_id']          = $arrDependentInfo['dependent_id'] ?? '';

                    $arrFieldsData['self_name'] = '';
                    if (!empty($arrFieldsData['sex'])) {
                        $arrFieldsData['self_name'] = $arrFieldsData['sex'] == 'MALE' ? 'himself' : 'herself';
                    }

                    // TODO: remove or revert back (Temporary don't show)
                    $arrDependentInfo['photo'] = '';
                    if (!empty($arrDependentInfo['photo'])) {
                        $filePath = $dependantsPath . '/' . $caseId . '/' . $arrDependentInfo['dependent_id'] . '/original';
                        if ($booLocal ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath)) {
                            $pathToTemp = $filePath . '-con';

                            if ($booLocal) {
                                $this->_files->copyFile($filePath, $pathToTemp, $booLocal);
                            } else {
                                $pathToTemp = $this->_files->getCloud()->downloadFileContent($filePath);
                            }

                            if (is_file($pathToTemp)) {
                                $imageConfig['source_image'] = $pathToTemp;
                                $imageConfig['width']        = 132;
                                $imageConfig['height']       = 170;
                                $imageManager                = new ImageManager($this->_tr, $imageConfig);

                                if (!$imageManager->resize()) {
                                    throw new Exception($imageManager->display_errors());
                                }

                                // This path will be used later, during pdf file generation
                                $arrFieldsData['photo_path'] = $this->_encryption->encode($pathToTemp);

                                // Embed and show this image directly to the html
                                $type                   = pathinfo($pathToTemp, PATHINFO_EXTENSION);
                                $data                   = file_get_contents($pathToTemp);
                                $base64                 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                                $arrFieldsData['photo'] = "<img src='$base64' alt='Photo' width='200px' />";
                            }
                        }
                    }

                    switch ($arrDependentInfo['relationship']) {
                        case 'spouse':
                            // For spouse use several fields from the main applicant
                            $arrFieldsData['name_of_spouse'] = $arrMainApplicantData['main_applicant_name'];
                            $arrFieldsData['marital_status'] = 'MARRIED';
                            break;

                        case 'child':
                        default:
                            if (!empty($arrDependentInfo['DOB'])) {
                                $birthDate   = new DateTime($arrDependentInfo['DOB'] . 'T00:00:00');
                                $currentDate = new DateTime();

                                if ($birthDate->diff($currentDate)->y < 18) {
                                    $arrFieldsData['occupation']          = empty($arrFieldsData['occupation']) ? 'STUDENT' : $arrFieldsData['occupation'];
                                    $arrFieldsData['marital_status']      = 'N/A';
                                    $arrFieldsData['name_of_spouse']      = empty($arrFieldsData['name_of_spouse']) ? 'N/A' : $arrFieldsData['name_of_spouse'];
                                    $arrFieldsData['self_name']           = $dependentName;
                                    $arrFieldsData['main_applicant_name'] = $arrMainApplicantData['main_applicant_name'];
                                }
                            }
                            break;
                    }

                    $replacements   = $this->_pdf->getTemplateReplacements($arrFieldsData);
                    $arrHtmlPages[] = $this->_systemTemplates->processText($strOriginalTemplate, $replacements);
                }

                // Make sure that this form is returned - it is used to collect all fields and submit them to generate a pdf file
                $strResult = '<form id="generated_con_form"><input type="hidden" name="pages_count" value="' . count($arrHtmlPages) . '" >' . implode('', $arrHtmlPages) . '</form>';
            }
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $view->setVariable('content', $strResult);
        return $view;
    }

    public function exportConAction()
    {
        $strError   = '';
        $strResult  = '';
        $booAsDraft = false;

        try {
            $arrParams = $this->findParams();

            $booAsDraft = (bool)Json::decode($this->findParam('is_draft'), Json::TYPE_ARRAY);

            $caseId = Json::decode($this->findParam('client_id'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $memberInfo = $this->_clients->getMemberInfo($caseId);
            $companyId  = $memberInfo['company_id'];
            $isLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);

            if (empty($strError) && (!isset($arrParams['pages_count']) || !is_numeric($arrParams['pages_count']) || $arrParams['pages_count'] <= 0 || $arrParams['pages_count'] > 100)) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            if (empty($strError)) {
                $filter = new StripTags();
                foreach ($arrParams as $key => $val) {
                    $arrParams[$key] = nl2br(substr($filter->filter($val) ?? '', 0, 1024));
                }

                $folderPath   = $this->_files->getClientCorrespondenceFTPFolder($companyId, $caseId, $isLocal);
                $folderAccess = $this->_clients->checkClientFolderAccess($caseId, $folderPath);

                $arrDependents    = $this->_clients->getFields()->getDependents(array($caseId), false);
                $arrDependentsIds = array_map(
                    function ($n) {
                        return (int)$n['dependent_id'];
                    },
                    $arrDependents
                );
                $arrConNumbers    = $this->_dominica->commitConNumbers($companyId, $caseId, $arrDependentsIds, $booAsDraft);

                $result = $this->_pdf->generatePdfCon($companyId, $caseId, $isLocal, $arrParams, $booAsDraft, $folderAccess, $arrConNumbers);
                if ($result instanceof FileInfo) {
                    return $this->downloadFile($result->path, $result->name, $result->mime, false, true, true);
                } else {
                    list($strError, $filePath) = $result;

                    if ($booAsDraft) {
                        $templateId = '';
                        if (empty($strError)) {
                            // Try to preselect the correct template
                            $arrTemplates = $this->_templates->getTemplatesList(true, 0, false, 'Email');
                            foreach ($arrTemplates as $arrTemplateInfo) {
                                if ($arrTemplateInfo['templateName'] === 'CON Draft') {
                                    $templateId = $arrTemplateInfo['templateId'];
                                    break;
                                }
                            }

                            if (empty($templateId)) {
                                $templateId = $this->_templates->getDefaultTemplateId();
                            }
                        }

                        $arrResult = array(
                            'success'     => empty($strError),
                            'msg'         => $strError,

                            // Required info to attach this file in the email dialog
                            'file_id'     => $this->_encryption->encode($filePath . '#' . $caseId),
                            'file_size'   => $this->_files->formatFileSize(filesize($filePath)),
                            'template_id' => $templateId
                        );

                        $strResult = Json::encode($arrResult);
                    } else {
                        $strResult = $strError;
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');

            if ($booAsDraft) {
                $arrResult = array(
                    'success' => false,
                    'msg'     => $strError,
                );

                $strResult = Json::encode($arrResult);
            } else {
                $strResult = $strError;
            }

            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strResult);
        return $view;
    }

    public function upgradeSubscriptionPlanAction()
    {
        $strError          = '';
        $strSuccessMessage = '';
        $freeClientsCount  = 0;
        $subscriptionName  = '';

        try {
            $companyId             = $this->_auth->getCurrentUserCompanyId();
            $arrDetailsCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);

            if ($arrDetailsCompanyInfo['subscription'] != 'starter') {
                $strError = $this->_tr->translate('Your current plan allows you to add clients.');
            }

            if (empty($strError)) {
                list($strError, $strEmailSentTo, $freeClientsCount, $subscriptionName) = $this->_company->getCompanySubscriptions()->upgradeSubscriptionPlan($companyId);
                $strSuccessMessage = sprintf($this->_tr->translate('Thank you for upgrading your subscription. A copy of the invoice was emailed to: %s'), $strEmailSentTo);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'            => empty($strError),
            'message'            => empty($strError) ? $strSuccessMessage : $strError,
            'free_clients_count' => $freeClientsCount,
            'subscription_name'  => $subscriptionName,
        );

        return new JsonModel($arrResult);
    }

    public function getLetterTemplatesByTypeAction()
    {
        $view = new JsonModel();

        $strError     = '';
        $arrTemplates = array();

        try {
            $templateType = Json::decode($this->findParam('templateType'), Json::TYPE_ARRAY);

            switch ($templateType) {
                case 'comfort_letter':
                    $config = $this->_config;
                    if (empty($config['site_version']['custom_templates_settings']['comfort_letter']['enabled'])) {
                        $strError = $this->_tr->translate('Comfort Letter Templates generation is turned off in the config.');
                    }

                    $arrConfigTemplates = array();
                    if (empty($strError)) {
                        if (!empty($config['site_version']['custom_templates_settings']['comfort_letter']['templates'])) {
                            $arrConfigTemplates = $config['site_version']['custom_templates_settings']['comfort_letter']['templates'];
                        }

                        if (empty($arrConfigTemplates)) {
                            $strError = $this->_tr->translate('Comfort Letter Templates were not set in the config.');
                        }
                    }

                    if (empty($strError)) {
                        $arrLetterTemplates = $this->_templates->getTemplatesList(true, 0, false, 'Letter');

                        $arrUsedTemplates = array();
                        foreach ($arrLetterTemplates as $arrLetterTemplateInfo) {
                            if (in_array($arrLetterTemplateInfo['templateName'], $arrConfigTemplates) && !in_array($arrLetterTemplateInfo['templateName'], $arrUsedTemplates)) {
                                $arrTemplates[]     = $arrLetterTemplateInfo;
                                $arrUsedTemplates[] = $arrLetterTemplateInfo['templateName'];
                            }
                        }

                        if (empty($arrTemplates)) {
                            $strError = $this->_tr->translate('Comfort Letter Templates were not created.<br><br>Such letter template(s) can be created:<br> * ') . implode('<br> * ', $arrConfigTemplates);
                        }
                    }
                    break;

                default:
                    $strError = $this->_tr->translate('Incorrect template type.');
                    break;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'msg'        => $strError,
            'rows'       => $arrTemplates,
            'totalCount' => count($arrTemplates)
        );
        return $view->setVariables($arrResult);
    }

    public function generatePdfLetterAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $caseId = Json::decode($this->findParam('caseId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $templateId = Json::decode($this->findParam('templateId'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_templates->hasAccessToTemplate($templateId)) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            $templateType = Json::decode($this->findParam('templateType'), Json::TYPE_ARRAY);
            if (empty($strError) && $templateType != 'comfort_letter') {
                $strError = $this->_tr->translate('Incorrect template type.');
            }

            // Load/use main parent of the case (IA or Employer), not all (IA + Employer)
            $arrCaseParents = $this->_clients->getParentsForAssignedApplicants(array($caseId));
            $caseParentId   = isset($arrCaseParents[$caseId]) ? $arrCaseParents[$caseId]['parent_member_id'] : 0;

            if (empty($caseParentId)) {
                $strError = $this->_tr->translate('Case is not assigned.');
            }

            if (empty($strError)) {
                $currentCompanyId = $this->_auth->getCurrentUserCompanyId();
                $booLocal         = $this->_company->isCompanyStorageLocationLocal($currentCompanyId);
                $strError         = $this->_templates->generateComfortLetterPdf($currentCompanyId, $caseId, $caseParentId, $templateId, $booLocal);
                if (empty($strError)) {
                    $this->_notes->updateNote(0, $caseId, 'Comfort Letter Issued.', true);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );
        return $view->setVariables($arrResult);
    }

    public function getAssignedClientReferralsAction()
    {
        $strError                  = '';
        $arrSavedReferrals         = array();
        $arrCompensationAgreements = array();

        try {
            $clientId = Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($clientId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrSavedReferrals = $this->_clientsReferrals->getClientReferralsRecords($clientId);

                $arrClientsIds = [];
                foreach ($arrSavedReferrals as $arrSavedReferralInfo) {
                    if (!empty($arrSavedReferralInfo['client_id'])) {
                        $arrClientsIds[] = $arrSavedReferralInfo['client_id'];
                    }
                }

                $arrGroupedClientsInfo = [];
                if (!empty($arrClientsIds)) {
                    $arrClientsInfo = $this->_clients->getClientsInfo($arrClientsIds);
                    foreach ($arrClientsInfo as $arrClientInfo) {
                        $arrGroupedClientsInfo[$arrClientInfo['member_id']] = $arrClientInfo;
                    }
                }

                foreach ($arrSavedReferrals as $key => $arrSavedReferralInfo) {
                    $clientId = $arrSavedReferralInfo['client_id'];
                    if (!empty($clientId)) {
                        $arrSavedReferrals[$key]['referral_client_id']        = $clientId;
                        $arrSavedReferrals[$key]['referral_client_type']      = 'client';
                        $arrSavedReferrals[$key]['referral_client_real_type'] = $this->_clients->getMemberTypeNameById($arrGroupedClientsInfo[$clientId]['userType']);
                        $arrSavedReferrals[$key]['referral_client_name']      = $arrGroupedClientsInfo[$clientId]['full_name_with_file_num'];
                    } else {
                        $arrSavedReferrals[$key]['referral_client_id']   = $arrSavedReferralInfo['prospect_id'];
                        $arrSavedReferrals[$key]['referral_client_type'] = 'prospect';
                        $arrSavedReferrals[$key]['referral_client_name'] = $this->_prospects->generateProspectName(['fName' => $arrSavedReferralInfo['prospect_first_name'], 'lName' => $arrSavedReferralInfo['prospect_last_name']]);
                    }

                    unset($arrSavedReferrals[$key]['client_id'], $arrSavedReferrals[$key]['prospect_id']);
                }

                // Load the list of all already saved Compensation Agreements
                $arrSavedCompensationAgreements = $this->_clientsReferrals->getCompanyCompensationAgreements($this->_auth->getCurrentUserCompanyId());

                // Convert for extjs
                foreach ($arrSavedCompensationAgreements as $savedCompensationAgreement) {
                    $arrCompensationAgreements[] = [$savedCompensationAgreement];
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'                   => empty($strError),
            'msg'                       => $strError,
            'items'                     => $arrSavedReferrals,
            'count'                     => count($arrSavedReferrals),
            'arrCompensationAgreements' => $arrCompensationAgreements,
        );

        return new JsonModel($arrResult);
    }

    public function getClientReferralsAction()
    {
        $query        = '';
        $strError     = '';
        $arrReferrals = array();

        try {
            $applicantId = Json::decode($this->params()->fromPost('applicantId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            $query = trim($this->params()->fromPost('query', ''));
            if (empty($strError) && !strlen($query)) {
                $strError = $this->_tr->translate('Please type the name.');
            }

            if (empty($strError)) {
                // Get/filter Prospects
                $arrCompanyProspects = $this->_prospects->getProspectsList('prospects', 0, 10, 'all-prospects', $query);
                foreach ($arrCompanyProspects['rows'] as $arrCompanyProspectInfo) {
                    $arrReferrals[] = [
                        'clientId'       => $arrCompanyProspectInfo['prospect_id'],
                        'clientType'     => 'prospect',
                        'clientFullName' => $this->_prospects->generateProspectName($arrCompanyProspectInfo),
                    ];
                }

                // Get/filter clients
                list($arrApplicants,) = $this->_clients->getMembersList(
                    $this->_auth->getCurrentUserCompanyId(),
                    $this->_auth->getCurrentUserDivisionGroupId(),
                    [
                        'filter_first_name' => $query,
                        'filter_last_name'  => $query,
                    ],
                    '',
                    '',
                    0,
                    10,
                    'individual_employer_internal_contact',
                    false
                );
                foreach ($arrApplicants as $arrApplicantInfo) {
                    $arrApplicantInfo = $this->_clients->generateClientName($arrApplicantInfo);
                    $arrReferrals[]   = [
                        'clientId'       => $arrApplicantInfo['member_id'],
                        'clientType'     => 'client',
                        'clientFullName' => $arrApplicantInfo['full_name_with_file_num'],
                    ];
                }

                // Sort!
                $arrAllNames = array();
                foreach ($arrReferrals as $key => $row) {
                    $arrAllNames[$key] = mb_strtolower($row['clientFullName']);
                }
                array_multisort($arrAllNames, SORT_ASC, $arrReferrals);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'msg'        => $strError,
            'query'      => $query,
            'rows'       => $arrReferrals,
            'totalCount' => count($arrReferrals)
        );

        return new JsonModel($arrResult);
    }

    public function saveClientReferralAction()
    {
        $strError = '';

        try {
            $applicantId = (int)$this->params()->fromPost('applicant_id');
            if (!$this->_members->hasCurrentMemberAccessToMember($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            $referralId = (int)$this->params()->fromPost('referral_id');
            if (empty($strError) && !empty($referralId)) {
                $arrSavedReferrals = $this->_clientsReferrals->getClientReferralsByIds([$referralId]);
                foreach ($arrSavedReferrals as $arrSavedReferralInfo) {
                    if (!empty($arrSavedReferralInfo['client_id'])) {
                        $booHasAccess = $this->_members->hasCurrentMemberAccessToMember($arrSavedReferralInfo['client_id']);
                    } else {
                        $booHasAccess = $this->_prospects->allowAccessToProspect($arrSavedReferralInfo['prospect_id']);
                    }

                    if (!$booHasAccess) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                        break;
                    }
                }
            }

            $clientType = $this->params()->fromPost('referral_client_type');
            if (empty($strError) && !in_array($clientType, ['client', 'prospect'])) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            $clientId = (int)$this->params()->fromPost('referral_client_id');
            if (empty($strError)) {
                if (empty($clientId)) {
                    $strError = $this->_tr->translate('Please select a client or a prospect.');
                } else {
                    if ($clientType == 'client') {
                        $booHasAccess = $this->_members->hasCurrentMemberAccessToMember($clientId);
                    } else {
                        $booHasAccess = $this->_prospects->allowAccessToProspect($clientId);
                    }

                    if (!$booHasAccess) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }
            }

            if (empty($strError)) {
                $arrSavedReferrals = $this->_clientsReferrals->getClientReferralsRecords($applicantId);

                $booShowWarning = false;
                foreach ($arrSavedReferrals as $arrSavedReferralInfo) {
                    if ($referralId == $arrSavedReferralInfo['referral_id']) {
                        continue;
                    }

                    if ($clientType == 'client') {
                        if ($arrSavedReferralInfo['client_id'] == $clientId) {
                            $booShowWarning = true;
                            break;
                        }
                    } elseif ($arrSavedReferralInfo['prospect_id'] == $clientId) {
                        $booShowWarning = true;
                        break;
                    }
                }

                if ($booShowWarning) {
                    if ($clientType == 'client') {
                        $strError = $this->_tr->translate('This client is already assigned.');
                    } else {
                        $strError = $this->_tr->translate('This prospect is already assigned.');
                    }
                }
            }

            $filter                  = new StripTags();
            $compensationArrangement = trim($filter->filter($this->params()->fromPost('referral_compensation_arrangement', '')));
            if (empty($strError) && !strlen($compensationArrangement)) {
                $strError = $this->_tr->translate('Compensation Agreement is a required field');
            }

            $isPaid = (bool)$this->params()->fromPost('referral_is_paid');

            if (empty($strError)) {
                $arrReferralInfo = [
                    'member_id'                         => $applicantId,
                    'client_id'                         => $clientId,
                    'prospect_id'                       => $clientId,
                    'referral_compensation_arrangement' => $compensationArrangement,
                    'referral_is_paid'                  => $isPaid ? 'Y' : 'N',
                ];

                if ($clientType == 'client') {
                    $arrReferralInfo['prospect_id'] = null;
                } else {
                    $arrReferralInfo['client_id'] = null;
                }

                $this->_clientsReferrals->saveClientReferral($referralId, $arrReferralInfo);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError,
        );

        return new JsonModel($arrResult);
    }

    public function removeClientReferralsAction()
    {
        $strError = '';

        try {
            $arrClientReferrals = Json::decode($this->params()->fromPost('arrClientReferrals'), Json::TYPE_ARRAY);
            if (!is_array($arrClientReferrals) || empty($arrClientReferrals)) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            } else {
                $arrSavedReferrals = $this->_clientsReferrals->getClientReferralsByIds($arrClientReferrals);
                foreach ($arrSavedReferrals as $arrSavedReferralInfo) {
                    if (!empty($arrSavedReferralInfo['client_id'])) {
                        $booHasAccess = $this->_members->hasCurrentMemberAccessToMember($arrSavedReferralInfo['client_id']);
                    } else {
                        $booHasAccess = $this->_prospects->allowAccessToProspect($arrSavedReferralInfo['prospect_id']);
                    }

                    if (!$booHasAccess) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                        break;
                    }
                }
            }

            if (empty($strError) && !$this->_clientsReferrals->removeClientReferrals($arrClientReferrals)) {
                $strError = $this->_tr->translate('Internal error. Please try again later.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'msg'     => empty($strError) ? $this->_tr->translate('Done!') : $strError,
        );

        return new JsonModel($arrResult);
    }

    public function getCaseFileStatusHistoryAction()
    {
        $strError   = '';
        $arrHistory = [];

        try {
            $clientId = Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($clientId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $now = date('Y-m-d H:i:s');

                $arrHistory = $this->_clientsFileStatusHistory->getClientFileStatusHistory($clientId);
                foreach ($arrHistory as $key => $arrRecord) {
                    $arrHistory[$key]['history_client_status_name']  = empty($arrRecord['history_current_client_status_name']) ? $arrRecord['history_client_status_name'] : $arrRecord['history_current_client_status_name'];
                    $arrHistory[$key]['history_client_status_name']  = empty($arrRecord['history_client_status_name']) ? $this->_tr->translate('Not selected') : $arrRecord['history_client_status_name'];
                    $arrHistory[$key]['history_checked_user_name']   = empty($arrRecord['history_checked_current_user_name']) ? $arrRecord['history_checked_user_name'] : $arrRecord['history_checked_current_user_name'];
                    $arrHistory[$key]['history_unchecked_user_name'] = '';
                    if (!empty($arrRecord['history_unchecked_date'])) {
                        $arrHistory[$key]['history_unchecked_user_name'] = empty($arrRecord['history_unchecked_current_user_name']) ? $arrRecord['history_unchecked_user_name'] : $arrRecord['history_unchecked_current_user_name'];
                    }

                    unset($arrHistory[$key]['history_current_client_status_name']);
                    unset($arrHistory[$key]['history_checked_current_user_name']);
                    unset($arrHistory[$key]['history_unchecked_current_user_name']);

                    // Calculate days count
                    $date1 = new DateTime($arrRecord['history_checked_date']);
                    $date2 = new DateTime(empty($arrRecord['history_unchecked_date']) ? $now : $arrRecord['history_unchecked_date']);

                    // Apply time zone when format the date
                    $tz = $this->_auth->getCurrentMemberTimezone();
                    if ($tz instanceof DateTimeZone) {
                        $date1->setTimezone($tz);
                        $date2->setTimezone($tz);

                        $arrHistory[$key]['history_checked_date'] = $date1->format('Y-m-d H:i:s');
                        if (!empty($arrRecord['history_unchecked_date'])) {
                            $arrHistory[$key]['history_unchecked_date'] = $date2->format('Y-m-d H:i:s');
                        }
                    }

                    // This date will be shown in the grid
                    $arrHistory[$key]['history_changed_on_date'] = $arrHistory[$key]['history_checked_date'];
                    if (!empty($arrRecord['history_unchecked_date'])) {
                        $arrHistory[$key]['history_changed_on_date'] = $arrHistory[$key]['history_unchecked_date'];
                    }

                    $days = $date2->diff($date1)->format('%a');
                    if (empty($days)) {
                        $diff  = $date2->getTimestamp() - $date1->getTimestamp();
                        $hours = round($diff / (60 * 60));

                        $arrHistory[$key]['history_days'] = $hours . ' ' . $this->_tr->translatePlural('hour', 'hours', $hours);
                    } else {
                        $days = round((int)$days);

                        $arrHistory[$key]['history_days'] = $days . ' ' . $this->_tr->translatePlural('day', 'days', $days);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'items'   => $arrHistory,
            'count'   => count($arrHistory),
            'success' => empty($strError),
            'msg'     => empty($strError) ? '' : $strError,
        );

        return new JsonModel($arrResult);
    }

    public function getCaseDependentsListAction()
    {
        $strError  = '';
        $strResult = '';

        try {
            $caseId = Json::decode($this->params()->fromPost('case_id'));
            if (empty($strError) && !empty($caseId) && !$this->_clients->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the case.');
            }

            $arrDependents = array();
            $oFields = $this->_clients->getFields();
            if (empty($strError)) {
                $arrDependents = $oFields->getDependents(array($caseId), false);

                if (empty($arrDependents)) {
                    $strError = $this->_tr->translate('There are no dependents for this case.');
                }
            }

            if (empty($strError)) {
                $arrDependentFields = $oFields->getDependantFields();
                $arrShowFields      = $this->_config['site_version']['dependants']['export_or_tooltip_fields'];

                $strResult = '<table><tr>';
                foreach ($arrDependentFields as $arrDependentFieldInfo) {
                    if (!in_array($arrDependentFieldInfo['field_id'], $arrShowFields)) {
                        continue;
                    }

                    $strResult .= '<th>' . $arrDependentFieldInfo['field_name'] . '</th>';
                }
                $strResult .= '</tr>';

                foreach ($arrDependents as $arrDependentInfo) {
                    $strResult .= '<tr>';
                    foreach ($arrDependentFields as $arrDependentFieldInfo) {
                        if (!in_array($arrDependentFieldInfo['field_id'], $arrShowFields)) {
                            continue;
                        }

                        if (isset($arrDependentInfo[$arrDependentFieldInfo['field_id']])) {
                            $value = $oFields->getDependentFieldReadableValue($arrDependentFieldInfo, $arrDependentInfo[$arrDependentFieldInfo['field_id']], '');
                        } else {
                            $value = '';
                        }
                        $strResult .= '<td>' . $value . '</td>';
                    }

                    $strResult .= '</tr>';
                }

                $strResult .= '</table>';
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel(['content' => empty($strError) ? $strResult : $strError]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }
}
