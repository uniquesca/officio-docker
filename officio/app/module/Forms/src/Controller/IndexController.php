<?php

namespace Forms\Controller;

use Clients\Service\Clients;
use DOMDocument;
use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\Validator\File\Extension;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\AccessLogs;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\SystemTriggers;

/**
 * Forms Index Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{
    /** @var Files */
    private $_files;

    /** @var Company */
    protected $_company;

    /** @var Pdf */
    private $_pdf;

    /** @var Clients */
    protected $_clients;

    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var Forms */
    protected $_forms;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_clients    = $services[Clients::class];
        $this->_files      = $services[Files::class];
        $this->_pdf        = $services[Pdf::class];
        $this->_accessLogs = $services[AccessLogs::class];
        $this->_triggers   = $services[SystemTriggers::class];
        $this->_forms      = $services[Forms::class];
        $this->_encryption = $services[Encryption::class];
    }


    public function indexAction()
    {
    }

    public function searchAction()
    {
        $filter = new StripTags();

        $errMsg = '';
        $booFormatFiles = false;

        $search_form = trim($filter->filter(Json::decode($this->params()->fromPost('search_form', ''), Json::TYPE_ARRAY)));
        if (empty($errMsg) && empty($search_form)) {
            $errMsg = $this->_tr->translate('Please enter a keyword to search for');
        }

        $version = $filter->filter(trim(Json::decode($this->params()->fromPost('version', ''), Json::TYPE_ARRAY)));
        if (empty($errMsg) && !in_array($version, array('all', 'latest'))) {
            $errMsg = $this->_tr->translate('Incorrectly selected form version');
        }

        $format = $filter->filter(trim(Json::decode($this->params()->fromPost('format', ''), Json::TYPE_ARRAY)));
        if (empty($errMsg) && $format == 'files') {
            $booFormatFiles = true;
        }


        // Run search
        $result = '';
        if (empty($errMsg)) {
            $arrFoundForms = $this->_forms->getFormVersion()->searchFormByName($search_form, $version);

            $count = @count($arrFoundForms);
            if (!$booFormatFiles) {
                $strCount = empty($count) ? '' : sprintf(' <span style="font-weight: normal; color: #666655;">(found %d form%s)</span>', $count, $count == 1 ? '' : 's');
                $result   = sprintf('<div style="padding-top: 5px; font-weight: bold;">Search Result%s:</div>', $strCount);

                $currentFormId = '';
                if (is_array($arrFoundForms) && !empty($arrFoundForms)) {
                    foreach ($arrFoundForms as $arrFormInfo) {
                        $id   = 'pform' . $arrFormInfo['form_version_id'];
                        $name = $arrFormInfo['file_name'];
                        if ($version == 'all') {
                            $name .= ' (' . $this->_settings->formatDate($arrFormInfo['version_date']) . ')';

                            if ($currentFormId != $arrFormInfo['form_id']) {
                                $result        .= "<div style='padding: 5px;'></div>";
                                $currentFormId = $arrFormInfo['form_id'];
                            }
                        }


                        $result .= "<div style='padding: 3px;'>";
                        $result .= "<input id='$id' class='pform' type='checkbox' style='vertical-align: top;'/>";
                        $result .= "<label for='$id' style='padding: 5px; vertical-align: top;'>$name</label>";
                        $result .= "</div>";
                    }
                } else {
                    $result .= "<div style='color: #FF800F;'>No form name matched <i>'$search_form'</i></div>";
                }
            } else {
                $arrFormattedForms = array();
                foreach ($arrFoundForms as $assignedFormInfo) {
                    $arrFormattedForms[] = array(
                        'form_version_id' => (int)$assignedFormInfo['form_version_id'],
                        'form_type'       => $assignedFormInfo['form_type'],
                        'file_name'       => $assignedFormInfo['file_name'],
                        'date_uploaded'   => $assignedFormInfo['uploaded_date'],
                        'date_version'    => $assignedFormInfo['version_date'],
                        'size'            => $assignedFormInfo['size'],
                        'has_pdf'         => $this->_forms->getFormVersion()->isFormVersionPdf($assignedFormInfo['form_version_id']),
                        'has_html'        => $this->_forms->getFormVersion()->isFormVersionHtml($assignedFormInfo['form_version_id']),
                        'has_xod'         => $this->_forms->getFormVersion()->isFormVersionXod($assignedFormInfo['form_version_id'])
                    );
                }

                $result = array('rows' => $arrFormattedForms, 'totalCount' => $count);
            }
        }


        if (!$booFormatFiles) {
            $result = array(
                'success'       => empty($errMsg),
                'message'       => $errMsg,
                'search_result' => $result
            );
        }

        return new JsonModel($result);
    }

    public function editAliasAction()
    {
        $view = new JsonModel();

        $errMsg = '';

        $memberId = (int)$this->findParam('member_id', 0);
        if (!$this->_clients->isAlowedClient($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
            $errMsg = $this->_tr->translate('Insufficient access rights');
        }

        $assignedFormId = (int)Json::decode($this->findParam('client_form_id', 0), Json::TYPE_ARRAY);
        if (empty($errMsg) && empty($assignedFormId)) {
            $errMsg = $this->_tr->translate('Incorrectly selected form');
        }

        if (empty($errMsg)) {
            $filter = new StripTags();

            $newAlias = trim($filter->filter(Json::decode($this->findParam('alias', ''), Json::TYPE_ARRAY)));

            $this->_forms->getFormAssigned()->updateAliasForAssignedForm($newAlias, $assignedFormId, $memberId);
        }

        return $view->setVariables(array('success' => empty($errMsg), 'message' => $errMsg));
    }

    public function listAction()
    {
        $arrResult = array(
            'rows'           => array(),
            'totalCount'     => 0,
            'booLocked'      => true,
            'hasOfficioForm' => false
        );

        try {
            $memberId = (int)$this->params()->fromPost('member_id', 0);

            if (!empty($memberId) && $this->_clients->isAlowedClient($memberId)) {
                $filter = new StripTags();
                $sort   = $filter->filter($this->params()->fromPost('sort'));
                $dir    = $filter->filter($this->params()->fromPost('dir'));
                $start  = (int)$this->params()->fromPost('start', 0);
                $limit  = (int)$this->params()->fromPost('limit', 100);

                $arrResult = $this->_clients->getClientFormsList($memberId, $sort, $dir, $start, $limit);
                $arrResult['hasOfficioForm'] = count($this->_clients->getOfficioFormAssigned($memberId)) > 0;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($arrResult);
    }


    public function deleteAction()
    {
        $strError = '';

        try {
            // Client cannot delete pdf form
            if (empty($strError) && $this->_auth->isCurrentUserClient()) {
                $strError = $this->_tr->translate('Cannot delete assigned pdf form');
            }

            /** @var array $arrFormIds */
            $arrFormIds = Json::decode($this->params()->fromPost('arr_form_id', '[]'), Json::TYPE_ARRAY);
            if (empty($strError) && (!is_array($arrFormIds) || !count($arrFormIds))) {
                $strError = $this->_tr->translate('Nothing to delete.');
            }

            // Check if this user has access to these forms
            $arrRolesNamesToDelete = array();
            if (empty($strError)) {
                $booHasAccess = false;

                // If all ids are correct - check access to each form
                $arrCorrectFormIds = $this->_pdf->filterFormIds($arrFormIds);
                if ($arrCorrectFormIds && (count($arrCorrectFormIds) == count($arrFormIds))) {
                    foreach ($arrCorrectFormIds as $formId) {
                        $arrAssignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($formId);
                        if (!$this->_clients->isAlowedClient($arrAssignedFormInfo['client_member_id'])) {
                            $booHasAccess = false;
                            break;
                        } else {
                            $booHasAccess = true;

                            $arrFamilyMemberNames = $this->_clients->getFamilyMemberNamesForTheClient($arrAssignedFormInfo['client_member_id'], $arrAssignedFormInfo['family_member_id']);

                            $arrRolesNamesToDelete[] = sprintf(
                                '%s%s (%s)',
                                empty($arrFamilyMemberNames['lName']) && empty($arrFamilyMemberNames['fName']) ? '' : '(' . trim($arrFamilyMemberNames['lName'] . ' ' . $arrFamilyMemberNames['fName']) . ') ',
                                $arrAssignedFormInfo['file_name'],
                                date('Y-m-d', strtotime($arrAssignedFormInfo['version_date']))
                            );
                        }
                    }
                }

                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Incorrectly selected forms');
                }
            }

            $memberId = 0;
            if (empty($strError)) {
                $memberId = (int)Json::decode($this->params()->fromPost('member_id', 0), Json::TYPE_ARRAY);

                if (empty($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strError)) {
                // Get all revisions for selected forms
                // and delete files/records in DB
                $arrFormVersions = $this->_forms->getFormRevision()->getAssignedFormRevisions($arrFormIds, false, true);
                foreach ($arrFormVersions as $arrRevisionInfo) {
                    $this->_forms->getFormRevision()->deleteRevision(
                        $arrRevisionInfo['client_member_id'],
                        $arrRevisionInfo['client_member_id']
                    );
                }

                $this->_db2->delete('form_assigned', ['form_assigned_id' => $arrFormIds]);

                // Save to the log
                // For <user> forms were deleted: Form 1 (date), Form 2 (date) by <admin>
                $strLogOfficeDescription = sprintf(
                    'For {2} %s %s by {1}',
                    count($arrFormIds) == 1 ? 'form was' : 'forms were',
                    sprintf('deleted: %s', implode(', ', $arrRolesNamesToDelete))
                );

                $arrLog = array(
                    'log_section'           => 'user',
                    'log_action'            => 'form_deleted',
                    'log_description'       => $strLogOfficeDescription,
                    'log_company_id'        => $this->_auth->getCurrentUserCompanyId(),
                    'log_created_by'        => $this->_auth->getCurrentUserId(),
                    'log_action_applied_to' => $memberId,
                );
                $this->_accessLogs->saveLog($arrLog);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal Error. Please contact to web site support.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );
        return new JsonModel($arrResult);
    }


    public function completeAction()
    {
        $view = new JsonModel();

        try {
            $errMsg = '';
            /** @var array $arrAssignedFormIds */
            $arrAssignedFormIds = Json::decode($this->findParam('arr_form_id', '[]'), Json::TYPE_ARRAY);
            if (!is_array($arrAssignedFormIds)) {
                $errMsg = $this->_tr->translate('Incorrectly selected forms');
            }

            // Check if this user has access to these forms
            if (empty($errMsg)) {
                $booHasAccess      = true;
                $arrCorrectFormIds = $this->_pdf->filterFormIds($arrAssignedFormIds);
                // If all ids are correct - check access to each form
                if ($arrCorrectFormIds && (count($arrCorrectFormIds) == count($arrAssignedFormIds))) {
                    /** @var array $arrMemberIds */
                    $arrMemberIds = $this->_forms->getFormAssigned()->getFormMemberIdById($arrCorrectFormIds);
                    if (count($arrMemberIds)) {
                        foreach ($arrMemberIds as $memberId) {
                            if (!$this->_clients->isAlowedClient($memberId)) {
                                $booHasAccess = false;
                                break;
                            }
                        }
                    }
                } else {
                    $booHasAccess = false;
                }

                if (!$booHasAccess) {
                    $errMsg = $this->_tr->translate('Incorrectly selected forms');
                }
            }

            // Mark forms as completed
            if (empty($errMsg) && is_array($arrAssignedFormIds) && count($arrAssignedFormIds)) {
                $memberId    = $this->_auth->getCurrentUserId();
                $companyId   = $this->_auth->getCurrentUserCompanyId();
                $booIsClient = $this->_auth->isCurrentUserClient();

                foreach ($arrAssignedFormIds as $formId) {
                    $arrAssignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($formId);

                    if (empty($errMsg) && !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($arrAssignedFormInfo['client_member_id'])) {
                        $errMsg = $this->_tr->translate('Insufficient access rights.');
                        break;
                    }

                    // Insert new records in db
                    $data = array(
                        'completed_date'   => date('Y-m-d H:i:s'),
                        'last_update_date' => date('Y-m-d H:i:s'),
                        'updated_by'       => $memberId
                    );

                    $this->_db2->update('form_assigned', $data, ['form_assigned_id' => $formId]);

                    //TRIGGER: When a client mark a form as Complete
                    if ($booIsClient) {
                        $this->_triggers->triggerFormComplete($companyId, $arrAssignedFormInfo['client_member_id']);
                    }
                }
            }
        } catch (Exception $e) {
            $errMsg = $this->_tr->translate('Internal Error. Please contact to web site support.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return json result
        $arrResult = array(
            'success' => empty($errMsg),
            'message' => $errMsg
        );
        return $view->setVariables($arrResult);
    }


    public function finalizeAction()
    {
        $view = new JsonModel();

        $errMsg = '';

        $member_id          = (int)$this->findParam('member_id', 0);
        $booFinalizeReplace = $this->findParam('booFinalizeReplace', 0);

        if (!$this->_clients->isAlowedClient($member_id)) {
            $errMsg = $this->_tr->translate('Incorrectly selected case.');
        }

        if (!$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($member_id)) {
            $errMsg = $this->_tr->translate('Insufficient access rights.');
        }

        /** @var array $arrFormIds */
        $arrFormIds = Json::decode($this->findParam('arr_form_id', '[]'), Json::TYPE_ARRAY);
        if (!is_array($arrFormIds)) {
            $errMsg = $this->_tr->translate('Incorrectly selected forms.');
        }

        // Check if current user has access to these forms
        if (empty($errMsg)) {
            $booHasAccess      = true;
            $arrCorrectFormIds = $this->_pdf->filterFormIds($arrFormIds);
            // If all ids are correct - check access to each form
            if ($arrCorrectFormIds && (count($arrCorrectFormIds) == count($arrFormIds))) {
                /** @var array $arrMemberIds */
                $arrMemberIds = $this->_forms->getFormAssigned()->getFormMemberIdById($arrCorrectFormIds);
                if (count($arrMemberIds)) {
                    foreach ($arrMemberIds as $memberId) {
                        if (!$this->_clients->isAlowedClient($memberId)) {
                            $booHasAccess = false;
                            break;
                        }
                    }
                }
            } else {
                $booHasAccess = false;
            }

            if (!$booHasAccess) {
                $errMsg = $this->_tr->translate('Incorrectly selected forms');
            }
        }

        // Mark forms as finalized
        if (empty($errMsg)) {
            // Select only not finalized forms
            $select = (new Select())
                ->from(array('a' => 'form_assigned'))
                ->columns(['form_assigned_id', 'updated_by', 'family_member_id', 'finalized_date', 'use_revision'])
                ->join(array('v' => 'form_version'), 'a.form_version_id = v.form_version_id', array('file_path', 'file_name'), Select::JOIN_LEFT_OUTER)
                ->where(['a.form_assigned_id' => $arrFormIds]);

            $arrAssignedFormsInfo = $this->_db2->fetchAll($select);

            if (is_array($arrAssignedFormsInfo) && count($arrAssignedFormsInfo) > 0) {
                $arrAssignedFormInfo   = array();
                $arrNewCompleteFormIds = array();
                foreach ($arrAssignedFormsInfo as $assignedFormInfo) {
                    $arrNewCompleteFormIds[]                                    = $assignedFormInfo['form_assigned_id'];
                    $arrAssignedFormInfo[$assignedFormInfo['form_assigned_id']] = $assignedFormInfo;
                }

                $userId = $this->_auth->getCurrentUserId();

                // Insert new records in db
                foreach ($arrNewCompleteFormIds as $formId) {
                    $data = array(
                        'finalized_date'   => date('c'),
                        'updated_by'       => $userId,
                        'last_update_date' => date('c')
                    );

                    $this->_db2->update('form_assigned', $data, ['form_assigned_id' => $formId]);


                    // Now we need to create a 'flatten' version of pdf with data
                    // And save it to 'Finalized' folder in client documents
                    $companyId = $this->_company->getMemberCompanyId($member_id);

                    $arrFamilyMemberNames = $this->_clients->getFamilyMemberNamesForTheClient($member_id, $arrAssignedFormInfo[$formId]['family_member_id']);

                    if ($booFinalizeReplace && !is_null($arrAssignedFormInfo[$formId]['finalized_date'])) {
                        $resBooFinalizeReplace = true;
                    } else {
                        $resBooFinalizeReplace = false;
                    }


                    // Create 'flatten' pdf
                    $arrFileInfo = array(
                        'formId'             => $formId,
                        'authorId'           => $userId,
                        'memberId'           => $member_id,
                        'familyMemberId'     => $arrAssignedFormInfo[$formId]['family_member_id'],
                        'fName'              => $arrFamilyMemberNames['fName'],
                        'lName'              => $arrFamilyMemberNames['lName'],
                        'companyId'          => $companyId,
                        'fileName'           => $arrAssignedFormInfo[$formId]['file_name'],
                        'booFinalizeReplace' => $resBooFinalizeReplace,
                        'useRevision'        => $arrAssignedFormInfo[$formId]['use_revision'],
                        'filePath'           => $arrAssignedFormInfo[$formId]['file_path']
                    );

                    $result = $this->_pdf->createFinalizedVersion($arrFileInfo);

                    if (!$result) {
                        // Revert changes
                        $data = array(
                            'finalized_date'   => null,
                            'updated_by'       => $arrAssignedFormInfo[$formId]['updated_by'],
                            'last_update_date' => date('c')
                        );

                        $this->_db2->update('form_assigned', $data, ['form_assigned_id' => $formId]);

                        $errMsg = $this->_tr->translate('Cannot create flatten pdf');
                        break;
                    }
                }
            }
        }

        // Return json result
        $booSuccess = empty($errMsg);
        $arrResult  = array('success' => $booSuccess, 'message' => $errMsg);
        return $view->setVariables($arrResult);
    }


    /**
     * Assign form, you can assign 1 or more pdf to the client (to different family members)
     * so this is when the staff knows this client has to have these 10 forms assigned he does that on one screen
     * New Form, is when he wants to assign a single form to that client and see it open to start editing it
     * so New Form = Assign one form + Automatically open it
     *
     * So difference is only on client's side
     * But we need to have two controllers to restrict access if needed
     *
     */
    public function assignAction()
    {
        $filter         = new StripTags();
        $memberId       = (int)$this->params()->fromPost('member_id', 0);
        $familyMemberId = Json::decode($this->params()->fromPost('family_member_id'), Json::TYPE_ARRAY);
        $arrForms       = Json::decode($this->params()->fromPost('forms'), Json::TYPE_ARRAY);
        $other          = $filter->filter(Json::decode($this->params()->fromPost('other'), Json::TYPE_ARRAY));

        $strError     = '';
        $arrFormsInfo = array();
        if (!$this->_clients->isAlowedClient($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
            $strError = $this->_tr->translate('Insufficient access rights');
        }

        if (empty($strError)) {
            $arrCreationResult = $this->_forms->getFormAssigned()->assignFormToCase(
                $memberId,
                $familyMemberId,
                $arrForms,
                $other,
                $this->_auth->getCurrentUserId()
            );

            $strError     = $arrCreationResult['msg'];
            $arrFormsInfo = $arrCreationResult['forms_info'];
        }

        // Return json result
        $arrResult = array(
            'success'      => empty($strError),
            'message'      => $strError,
            'arrFormsInfo' => $arrFormsInfo
        );

        return new JsonModel($arrResult);
    }


    public function getQuestionnaireSettings()
    {
        $rootDir  = realpath(__DIR__ . '/../../../..');
        $jsonPath = "$rootDir/public/config-json/list-questionnaire-au-settings.json";
        return file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : null;
    }


    /**
     * Assign the Officio forms to a client case.
     */
    public function assignOfficioFormAction()
    {
        $memberId       = (int)$this->params()->fromPost('member_id', 0);
        $strFormType    = $this->params()->fromPost('form_questionnaire_type', '');
        $strFormSetting = '';
        $arrForms       = [];
        $strError       = '';
        $arrFormsInfo   = array();

        if (!$this->_clients->isAlowedClient($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
            $strError = $this->_tr->translate('Insufficient access rights');
        }

        $formSelect = (new Select())
            ->from(['fv' => 'form_version'])
            ->columns(['form_version_id'])
            ->where(['fv.form_type' => 'officio-form']);

        $arrFormVersion = $this->_db2->fetchAll($formSelect);
        foreach ($arrFormVersion as $item) {
            $arrForms[] = 'pform' . $item['form_version_id'];
        }

        $jsonSetting = $this->getQuestionnaireSettings();
        if ($jsonSetting && $strFormType && isset($jsonSetting['form-setting']) && isset($jsonSetting['form-setting'][$strFormType])) {
            $strFormSetting = json_encode($jsonSetting['form-setting'][$strFormType]);
        }

        if (empty($strError) && count($arrFormVersion)) {
            $arrCreationResult = $this->_forms->getFormAssigned()->assignFormToCase(
                $memberId,
                'main_applicant',
                $arrForms,
                '',
                $this->_auth->getCurrentUserId(),
                $strFormSetting
            );

            $strError     = $arrCreationResult['msg'];
            $arrFormsInfo = $arrCreationResult['forms_info'];
        }

        // Return json result
        $arrResult = array(
            'success'      => empty($strError),
            'message'      => $strError,
            'arrFormsInfo' => $arrFormsInfo
        );

        return new JsonModel($arrResult);
    }


    public function loadFormSettingAction()
    {
        $formId       = (int)$this->params()->fromPost('client_form_id', 0);
        $formSettings = json_decode($this->_forms->getFormAssigned()->getFormSettingById($formId));
        $jsonSetting  = $this->getQuestionnaireSettings();
        $formTemplate = $jsonSetting['setting-template'] ?? [];
        return new JsonModel([
            "form_settings" => $formSettings,
            "form_template" => $formTemplate
        ]);
    }


    public function loadSettingAction()
    {
        $jsonSetting = $this->getQuestionnaireSettings();
        return new JsonModel($jsonSetting);
    }


    public function saveFormSettingAction()
    {
        $formId      = (int)$this->params()->fromPost('client_form_id', 0);
        $arrSettings = Json::decode($this->params()->fromPost('settings'), Json::TYPE_ARRAY);
        $jsonSetting = $this->getQuestionnaireSettings();
        $formSetting = $jsonSetting['form-setting'] ?? [];
        $newSettings = [];

        $clientSeeting = json_decode($this->_forms->getFormAssigned()->getFormSettingById($formId), true);

        foreach ($formSetting[$clientSeeting['form_type']] as $name => $value) {
            $newSettings[$name] = strpos($name, 'show-') === 0 ? ($arrSettings[$name] ?? null) === 'on' : $value;
        }

        $this->_forms->getFormAssigned()->updateFormSettings(json_encode($newSettings), $formId);

        return new JsonModel([
            'success' => true
        ]);
    }


    /**
     * Load family members list for specific client
     *
     */
    public function getFamilyMembersAction()
    {
        $view = new JsonModel();

        $member_id = (int)$this->findParam('member_id', 0);

        // Get family members list for this client
        // (if current user has access to this client)
        $arrFamilyMembers = array();
        if ($this->_clients->isAlowedClient($member_id)) {
            $arrFamilyMembers = $this->_clients->getFamilyMembersForClient($member_id, true);
        }

        // Prepare result
        $arrResult = array('rows' => $arrFamilyMembers, 'totalCount' => count($arrFamilyMembers));

        // Return json result
        return $view->setVariables($arrResult);
    }


    public function lockAndUnlockAction()
    {
        $errMsg      = '';
        $booIsLocked = true;

        $memberId = (int)$this->params()->fromPost('member_id', 0);
        if ($this->_clients->isAlowedClient($memberId) && $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
            $this->_db2->update(
                'clients',
                ['forms_locked' => new Expression('!forms_locked')],
                ['member_id' => $memberId]
            );

            $select = (new Select())
                ->from('clients')
                ->columns(['forms_locked'])
                ->where(['member_id' => $memberId]);

            $booIsLocked = $this->_db2->fetchOne($select);
        } else {
            $errMsg = $this->_tr->translate('Insufficient access rights');
        }

        // Return json result
        $arrResult = array(
            'success' => empty($errMsg),
            'message' => $errMsg,
            'locked'  => (bool)$booIsLocked
        );

        return new JsonModel($arrResult);
    }


    public function loadSettingsAction()
    {
        $view = new JsonModel();

        $errMsg      = '';
        $arrSettings = array();

        $member_id = (int)$this->findParam('member_id', 0);
        if ($this->_clients->isAlowedClient($member_id)) {
            $select = (new Select())
                ->from('clients')
                ->columns(['forms_locked'])
                ->where(['member_id' => $member_id]);

            $arrSettings['booFormsLocked'] = (bool)$this->_db2->fetchOne($select);
        } else {
            $errMsg = $this->_tr->translate('Insufficient access rights');
        }

        // Return json result
        $booSuccess = empty($errMsg);
        $arrResult  = array('success' => $booSuccess, 'message' => $errMsg, 'arrSettings' => $arrSettings);
        return $view->setVariables($arrResult);
    }

    /**
     * Return pdf form by assigned pdf id
     * Note: Anyone who has access to this function can open a pdf form
     *
     */
    public function openVersionPdfAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        try {
            $formVersionId = (int)$this->findParam('version_id');
            list($realPath, $fileName) = $this->_forms->getFormVersion()->getPdfFilePathByVersionId($formVersionId);
            if (!empty($realPath)) {
                return $this->downloadFile($realPath, $fileName, 'application/pdf', true, false);
            } else {
                $strError = $this->_tr->translate('Incorrect path to the file');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $strError);

        return $view;
    }


    /**
     * Return pdf by assigned pdf id
     *
     */
    public function openAssignedPdfAction()
    {
        $view = new ViewModel();

        try {
            $strError = '';

            $formId               = (int)$this->findParam('pdfid');
            $booMergeXfdf         = $this->findParam('merge', 0);
            $booDownload          = $this->findParam('download', 0);
            $booUseLatestRevision = (bool)$this->findParam('latest', false);

            // Get assigned form info by id
            $formAssigned     = $this->_forms->getFormAssigned();
            $assignedFormInfo = $formAssigned->getAssignedFormInfo($formId, true);

            $memberId         = $assignedFormInfo['client_member_id'];
            $family_member_id = $assignedFormInfo['family_member_id'];

            if (!$this->_clients->isAlowedClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                // Check which version of the form we need to use
                $formVersionId = $assignedFormInfo['form_version_id'];
                if ($booUseLatestRevision) {
                    // Get the latest version of the form
                    $arrLatestVersionInfo = $this->_forms->getFormVersion()->getLatestFormVersionInfo($formVersionId);
                    if (is_array($arrLatestVersionInfo) && !empty($arrLatestVersionInfo['form_version_id'])) {
                        // Use it for output
                        $formVersionId = $arrLatestVersionInfo['form_version_id'];

                        // Also update it for the current client
                        $formAssigned->updateAssignedFormVersion($formId, $formVersionId);
                    }
                }

                if ($booMergeXfdf) {
                    $fileName      = $this->_pdf::getXfdfFileName($family_member_id, $formId);
                    $pathToXfdfDir = $this->_files->getClientXFDFFTPFolder($memberId);
                    $pathToXfdf    = $pathToXfdfDir . '/' . $fileName;

                    // Update xfdf (our settings)
                    if (file_exists($pathToXfdf)) {
                        $pathToAnnotation = $this->_pdf->getAnnotationPath($pathToXfdfDir, $formId);

                        // Check if annotations are enabled for the company
                        $arrMemberInfo         = $this->_members->getMemberInfo($memberId);
                        $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($arrMemberInfo['company_id']);

                        $xml = $this->_pdf->readXfdfFromFile($pathToXfdf, $pathToAnnotation, $booAnnotationsEnabled);
                        if ($xml === false) {
                            // Not in xml format, return empty doc
                            $emptyXfdf = $this->_pdf->getEmptyXfdf();
                            $oXml      = simplexml_load_string($emptyXfdf);
                        } else {
                            $oXml = $xml;
                        }
                    } else {
                        $emptyXfdf = $this->_pdf->getEmptyXfdf();
                        $oXml      = simplexml_load_string($emptyXfdf);
                    }

                    // Check if this client is locked
                    $booClient = $this->_auth->isCurrentUserClient();
                    $booLocked = $this->_clients->isLockedClient($memberId);
                    if ($booClient && $booLocked) {
                        $xfdfLoadedCode = 2;
                    } else {
                        $xfdfLoadedCode = 1;
                    }

                    // $timeStamp = strtotime($assignedFormInfo['version_date']);
                    // $formVersion = 'Form Version Date: ';
                    // $formVersion .= ($timeStamp === false) ? 'Unknown' : date('Y-m-d', $timeStamp);
                    $this->_pdf->updateFieldInXfdf('server_form_version', '', $oXml);
                    $this->_pdf->updateFieldInXfdf('server_url', $this->layout()->getVariable('baseUrl') . '/forms/sync#FDF', $oXml);
                    $this->_pdf->updateFieldInXfdf('server_assigned_id', $formId, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_xfdf_loaded', $xfdfLoadedCode, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_locked_form', ($booClient && $booLocked) ? 1 : 0, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_time_stamp', $assignedFormInfo['last_update_date'], $oXml);

                    // Update xfdf
                    $thisXfdfCreationResult = $this->_pdf->saveXfdf($pathToXfdf, $oXml->asXML());

                    if ($thisXfdfCreationResult == Pdf::XFDF_SAVED_CORRECTLY) {
                        $arrFormInfo = $this->_forms->getFormVersion()->getFormVersionInfo($formVersionId);

                        $pdfFileName = $arrFormInfo['file_path'];
                        $pathToPdf   = $this->_config['directory']['pdfpath_physical'] . '/' . $pdfFileName;

                        $pathToMergedFile = $this->_config['directory']['pdf_temp'] . '/' . uniqid(rand() . time(), true) . '.pdf';

                        $booResult = $this->_pdf->createFlattenPdf($pathToPdf, $pathToXfdf, $pathToMergedFile, false);

                        if ($booResult && file_exists($pathToMergedFile)) {
                            // Generate file name
                            $arrFamilyMembers = array_merge(
                                $this->_clients->getFamilyMembersForClient($memberId),
                                $this->_clients->getFamilyMembersForClient($memberId, true)
                            );
                            $outputFileName   = $this->_pdf->generateFileNameFromAssignedFormInfo($assignedFormInfo, $arrFormInfo, $arrFamilyMembers);
                            return $this->downloadFile($pathToMergedFile, $outputFileName . '.pdf', 'application/pdf', !$booMergeXfdf, $booDownload);
                        } else {
                            $strError = $this->_tr->translate('Incorrect path to the file.');
                        }
                    } else {
                        $strError = $this->_tr->translate('Error during xfdf creation.');
                    }
                } else {
                    list($realPath, $fileName) = $this->_forms->getFormVersion()->getPdfFilePathByVersionId($formVersionId);
                    if (!empty($realPath)) {
                        return $this->downloadFile($realPath, $fileName, 'application/pdf', true, false);
                    } else {
                        $strError = $this->_tr->translate('Incorrect path to the file');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strError);

        return $view;
    }

    public function openXdpAction()
    {
        $view = new ViewModel();

        try {
            $strError = '';
            $formId   = (int)$this->findParam('pdfid');

            // Get assigned form info by id
            $formAssigned     = $this->_forms->getFormAssigned();
            $assignedFormInfo = $formAssigned->getAssignedFormInfo($formId);

            // Return xdp for specific member id
            $member_id        = $assignedFormInfo['client_member_id'];
            $family_member_id = $assignedFormInfo['family_member_id'];
            if ($this->_clients->isAlowedClient($member_id)) {
                $realPath = $this->_files->getClientXdpFilePath($member_id, $family_member_id, $formId);

                // Find xdp file if it doesn't exists - return a blank one
                if (file_exists($realPath)) {
                    $dom = new DOMDocument();
                    $dom->load($realPath);
                    $xdpContent = $dom->saveXML();
                } else {
                    $xdpContent = $this->_pdf->getEmptyXdp();
                }

                $oXml = simplexml_load_string($xdpContent);

                // Update path to pdf form
                $oXml = $this->_pdf->updatePDFUrlInXDPFile($oXml, $this->layout()->getVariable('baseUrl'), $formId);

                // Update custom pdf fields
                $arrUpdateFields = array(
                    'OfficioErrorCode'    => 0,
                    'OfficioErrorMessage' => '',
                    'OfficioPDFFormId'    => $formId,
                    'OfficioSubmitURL'    => $this->layout()->getVariable('baseUrl') . '/forms/sync/save-xdp'
                );
                $oXml = $this->_pdf->updateFieldInXDP($oXml, $arrUpdateFields);

                // Output XDP file
                return $this->file($oXml->saveXML(), 'test.xdp', 'application/vnd.adobe.xdp+xml', false, false);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strError);

        return $view;
    }

    public function openAssignedXfdfAction()
    {
        // Disable layout and view
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $formId      = (int)$this->findParam('pdfid');
        $booUseMerge = $this->findParam('merge', 0);

        // Get assigned form info by id
        $formAssigned     = $this->_forms->getFormAssigned();
        $assignedFormInfo = $formAssigned->getAssignedFormInfo($formId);

        // Return xfdf for specific member id
        $memberId       = $assignedFormInfo['client_member_id'];
        $familyMemberId = $assignedFormInfo['family_member_id'];
        if ($this->_clients->isAlowedClient($memberId)) {
            $fileName = $this->_pdf::getXfdfFileName($familyMemberId, $formId);

            $pathToXfdfDir = $this->_files->getClientXFDFFTPFolder($memberId);
            $realPath      = $pathToXfdfDir . '/' . $fileName;

            $oXml = null;
            if (file_exists($realPath)) {
                $pathToAnnotation = $this->_pdf->getAnnotationPath($pathToXfdfDir, $formId);

                // Check if annotations are enabled for the company
                $arrMemberInfo         = $this->_members->getMemberInfo($memberId);
                $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($arrMemberInfo['company_id']);

                $xml = $this->_pdf->readXfdfFromFile($realPath, $pathToAnnotation, $booAnnotationsEnabled);
                if ($xml !== false) {
                    $oXml = $xml;
                }
            }

            if (is_null($oXml)) {
                $emptyXfdf = $this->_pdf->getEmptyXfdf();
                $oXml      = simplexml_load_string($emptyXfdf);
            }

            // Check if this client is locked
            $booClient = $this->_auth->isCurrentUserClient();
            $booLocked = $this->_clients->isLockedClient($memberId);
            if ($booClient && $booLocked) {
                $xfdfLoadedCode = 2;
            } else {
                $xfdfLoadedCode = 1;
            }

            if ($booUseMerge) {
                $submitUrl = $this->layout()->getVariable('baseUrl') . '/forms/sync/index?merge=1';
            } else {
                $submitUrl = $this->layout()->getVariable('baseUrl') . '/forms/sync#FDF';
            }

            $this->_pdf->updateFieldInXfdf('server_form_version', '', $oXml);
            $this->_pdf->updateFieldInXfdf('server_url', $submitUrl, $oXml);
            $this->_pdf->updateFieldInXfdf('server_assigned_id', $formId, $oXml);
            $this->_pdf->updateFieldInXfdf('server_xfdf_loaded', $xfdfLoadedCode, $oXml);
            $this->_pdf->updateFieldInXfdf('server_time_stamp', $assignedFormInfo['last_update_date'], $oXml);
            $this->_pdf->updateFieldInXfdf('server_locked_form', ($booClient && $booLocked) ? 1 : 0, $oXml);
            $this->_pdf->updateFieldInXfdf('server_user_name', $this->_members->getCurrentMemberName(true), $oXml);
            $this->_pdf->updateFieldInXfdf('server_is_admin', $booClient ? '0' : '1', $oXml);

            $booWithPdf = $this->findParam('withpdfurl', 0);
            if ($booWithPdf) {
                // Show server confirmation text
                $codeResult = (int)$this->findParam('code_result');
                $this->_pdf->updateFieldInXfdf('server_confirmation', $this->_pdf->getCodeResultById($codeResult), $oXml);

                // <f href="http://foo.com/bar.pdf"/>
                $pdfUrl   = $this->layout()->getVariable('baseUrl') . '/forms/index/open-assigned-pdf?pdfid=' . $formId;
                $urlField = $oXml->addChild('f');
                $urlField->addAttribute('href', $pdfUrl);
            }

            $strParsedResult = preg_replace('%<value>(\r|\n|\r\n)</value>%', '<value></value>', $oXml->asXML());
            return $this->file($strParsedResult, $fileName, 'application/vnd.adobe.xfdf', false, false);
        } else {
            $view->setVariable('content', 'Insufficient access rights');
        }

        return $view;
    }

    public function openXodAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $formVersionId = 0;
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $formVersionId = (int)$this->findParam('version_id', 0);
            }

            if (empty($formVersionId)) {
                $formId = (int)$this->findParam('pdfid');
                // Get assigned form info by id
                $formAssigned     = $this->_forms->getFormAssigned();
                $assignedFormInfo = $formAssigned->getAssignedFormInfo($formId);

                $memberId      = $assignedFormInfo['client_member_id'];
                $formVersionId = $assignedFormInfo['form_version_id'];

                if (!$this->_clients->isAlowedClient($memberId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError)) {
                    $booUseLatestRevision = (bool)$this->findParam('latest');
                    if ($booUseLatestRevision) {
                        // Get the latest version of the form
                        $arrLatestVersionInfo = $this->_forms->getFormVersion()->getLatestFormVersionInfo($formVersionId);
                        if (is_array($arrLatestVersionInfo) && !empty($arrLatestVersionInfo['form_version_id'])) {
                            // Use it for output
                            $formVersionId = $arrLatestVersionInfo['form_version_id'];

                            // Also update it for the current client
                            $formAssigned->updateAssignedFormVersion($formId, $formVersionId);
                        }
                    }
                }
            }

            if (empty($strError)) {
                $arrFormVersionInfo = $this->_forms->getFormVersion()->getFormVersionInfo($formVersionId);
                if (isset($arrFormVersionInfo['file_path'])) {
                    $realPath = $this->_files->getConvertedXodFormPath($arrFormVersionInfo['file_path']);
                    if (!empty($realPath) && file_exists($realPath)) {
                        return $this->downloadFile($realPath, 'form.xod', '', true);
                    } else {
                        $this->getResponse()->setStatusCode(404);
                        $strError = $this->_tr->translate('Internal error.');
                    }
                } else {
                    $this->getResponse()->setStatusCode(404);
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $strError);

        return $view;
    }

    public function openPdfAndXfdfAction()
    {
        $view = new ViewModel();

        $filter = new StripTags();
        $formId = (int)$this->findParam('pdfid');

        // Get assigned form info by id
        $formAssigned     = $this->_forms->getFormAssigned();
        $assignedFormInfo = $formAssigned->getAssignedFormInfo($formId);
        $member_id        = $assignedFormInfo['client_member_id'];

        $clients_id   = $assignedFormInfo['client_member_id'];
        $clients_info = $this->_clients->getClientInfo($clients_id);

        $select = (new Select())
            ->from('form_version')
            ->columns(['file_name'])
            ->where(['form_version_id' => $assignedFormInfo['form_version_id']]);

        $arrResult = $this->_db2->fetchCol($select);

        if (count($arrResult) > 0) {
            $formFileName = $arrResult[0];
        } else {
            $formFileName = '';
        }

        $view->setVariable('formFileName', $formFileName);
        $view->setVariable('clients_info', $clients_info);

        if ($this->_clients->isAlowedClient($member_id)) {
            $formId   = (int)$this->findParam('pdfid');
            $formName = $filter->filter($this->findParam('file'));

            $booMergeXfdf = $this->findParam('merge', 0);

            if ($booMergeXfdf) {
                $this->layout()->setVariable(
                    'frameUrl',
                    $this->layout()->getVariable('baseUrl') . '/forms/index/open-assigned-pdf?pdfid=' . $formId . '&merge=1&file=' . $formName
                );
            } else {
                $this->layout()->setVariable(
                    'frameUrl',
                    $this->layout()->getVariable('baseUrl') . '/forms/index/open-assigned-pdf?pdfid=' . $formId . '&file=' . $formName .
                    '#FDF=' .
                    $this->layout()->getVariable('baseUrl') . '/forms/index/open-assigned-xfdf?pdfid=' . $formId
                );
            }
        } else {
            $view->setTemplate('layout/plain');
            $view->setTerminal(true);
            $view->setVariables(
                [
                    'content' => 'Insufficient access rights'
                ],
                true
            );
        }

        return $view;
    }

    public function openEmbedPdfAction()
    {
        $filter   = new StripTags();
        $formId   = (int)$this->findParam('pdfid');
        $formName = $filter->filter($this->findParam('file'));
        $this->layout()->setVariable(
            'embedUrl',
            $this->layout()->getVariable('baseUrl') . '/forms/index/open-assigned-pdf?pdfid=' . $formId . '&file=' . $formName .
            '#FDF=' .
            $this->layout()->getVariable('baseUrl') . '/forms/index/open-assigned-xfdf?pdfid=' . $formId
        );
    }

    public function printAction()
    {
        $view = new ViewModel();

        try {
            $memberId = (int)$this->findParam('member_id');
            $formId   = (int)$this->findParam('pdfid');

            $userId           = $this->_auth->getCurrentUserId();
            $companyId        = $this->_company->getMemberCompanyId($memberId);
            $arrFamilyMembers = $this->_clients->getFamilyMembersForClient($memberId);

            if (empty($strError) && !$this->_clients->isAlowedClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            $arrFormsFormatted = [
                $formId => 'read-only'
            ];

            // Check if this user has access to these forms
            if (empty($strError)) {
                // Check if current user has access to these forms
                $arrFormIds        = array_keys($arrFormsFormatted);
                $booHasAccess      = true;
                $arrCorrectFormIds = $this->_pdf->filterFormIds($arrFormIds);
                // If all ids are correct - check access to each form
                if ($arrCorrectFormIds && (count($arrCorrectFormIds) == count($arrFormIds))) {
                    /** @var array $arrMemberIds */
                    $arrMemberIds = $this->_forms->getFormAssigned()->getFormMemberIdById($arrCorrectFormIds);
                    if (count($arrMemberIds)) {
                        foreach ($arrMemberIds as $memberId) {
                            if (!$this->_clients->isAlowedClient($memberId)) {
                                $booHasAccess = false;
                                break;
                            }
                        }
                    }
                } else {
                    $booHasAccess = false;
                }

                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Incorrectly selected forms');
                }
            }

            if (empty($strError)) {
                $arrResult = $this->_pdf->createPDF(
                    $companyId,
                    $memberId,
                    $userId,
                    $arrFormsFormatted,
                    $arrFamilyMembers
                );
                if (empty($arrResult['error'])) {
                    $files = $arrResult['files'];
                    if (!is_array($files) || !count($files) || !file_exists($files[0]['file'])) {
                        $strError = $this->_tr->translate('Cannot print pdf');
                    } else {
                        return $this->downloadFile($files[0]['file'], $files[0]['filename'], 'application/pdf', false, false);
                    }
                } else {
                    $strError = $arrResult['error'];
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        $view->setVariable('content', $strError);

        return $view;
    }


    public function emailAction()
    {
        $strError = '';
        $arrFiles = array();

        try {
            // IMPORTANT (OR ELSE INFINITE LOOP) - close current sessions or the next page will wait FOREVER for a "write lock".
            session_write_close();
            set_time_limit(5 * 60); // 5 minutes, no more

            $memberId = (int)$this->params()->fromPost('memberId');
            $arrForms = Json::decode($this->params()->fromPost('arrPdf'), Json::TYPE_ARRAY);

            // Generate pdf files (merge with xfdf)
            $userId           = $this->_auth->getCurrentUserId();
            $companyId        = $this->_company->getMemberCompanyId($memberId);
            $arrFamilyMembers = $this->_clients->getFamilyMembersForClient($memberId);

            if (!$this->_clients->isAlowedClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            $arrFormsFormatted = array();
            if (empty($strError)) {
                if (is_array($arrForms) && count($arrForms)) {
                    foreach ($arrForms as $arrFormInfo) {
                        if (!isset($arrFormInfo['pdfId']) || !isset($arrFormInfo['mode']) || !in_array($arrFormInfo['mode'], array('read-only', 'fillable'))) {
                            $strError = $this->_tr->translate('Incorrectly selected forms');
                            break;
                        }

                        $arrFormsFormatted[$arrFormInfo['pdfId']] = $arrFormInfo['mode'];
                    }
                }
            }

            // Check if this user has access to these forms
            if (empty($strError)) {
                // Check if current user has access to these forms
                $arrFormIds        = array_keys($arrFormsFormatted);
                $booHasAccess      = true;
                $arrCorrectFormIds = $this->_pdf->filterFormIds($arrFormIds);
                // If all ids are correct - check access to each form
                if ($arrCorrectFormIds && (count($arrCorrectFormIds) == count($arrFormIds))) {
                    /** @var array $arrMemberIds */
                    $arrMemberIds = $this->_forms->getFormAssigned()->getFormMemberIdById($arrCorrectFormIds);
                    if (count($arrMemberIds)) {
                        foreach ($arrMemberIds as $memberId) {
                            if (!$this->_clients->isAlowedClient($memberId)) {
                                $booHasAccess = false;
                                break;
                            }
                        }
                    }
                } else {
                    $booHasAccess = false;
                }

                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Incorrectly selected forms');
                }
            }

            $arrResult = [];
            if (empty($strError)) {
                $arrResult = $this->_pdf->createPDF($companyId, $memberId, $userId, $arrFormsFormatted, $arrFamilyMembers);
                $strError  = $arrResult['error'];
            }

            if (empty($strError)) {
                $arrFiles = $arrResult['files'];
                if (!count($arrFiles) || !file_exists($arrFiles[0]['file'])) {
                    $strError = $this->_tr->translate('Cannot attach pdf form(s)');
                } else {
                    $member_info          = $this->_members->getMemberInfo($memberId);
                    $arrFiles[0]['email'] = $member_info['emailAddress'];

                    //encode file path
                    foreach ($arrFiles as &$file) {
                        if (file_exists($file['file'])) {
                            $file['filesize'] = $this->_files->formatFileSize(filesize($file['file']));
                            $file['file']     = $this->_encryption->encode($file['file']);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error' => $strError,
            'files' => $arrFiles
        );

        return new JsonModel($arrResult);
    }


    public function openPdfPrintAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $errMsg = '';

        $member_id = (int)$this->findParam('member_id', 0);
        if (!$this->_clients->isAlowedClient($member_id)) {
            $errMsg = $this->_tr->translate('Insufficient access rights');
        }


        $arrFormIds[] = (int)$this->findParam('pdfid', 0);
        // Check if current user has access to this form
        if (empty($errMsg)) {
            $booHasAccess      = true;
            $arrCorrectFormIds = $this->_pdf->filterFormIds($arrFormIds);
            // If all ids are correct - check access to each form
            if ($arrCorrectFormIds && (count($arrCorrectFormIds) == count($arrFormIds))) {
                /** @var array $arrMemberIds */
                $arrMemberIds = $this->_forms->getFormAssigned()->getFormMemberIdById($arrCorrectFormIds);
                if (count($arrMemberIds)) {
                    foreach ($arrMemberIds as $memberId) {
                        if (!$this->_clients->isAlowedClient($memberId)) {
                            $booHasAccess = false;
                            break;
                        }
                    }
                }
            } else {
                $booHasAccess = false;
            }

            if (!$booHasAccess) {
                $errMsg = $this->_tr->translate('Incorrectly selected forms');
            }
        }


        if (empty($errMsg)) {
            // Select only not finalized forms
            $select = (new Select())
                ->from(array('a' => 'form_assigned'))
                ->columns(['form_assigned_id', 'updated_by', 'family_member_id', 'use_revision', 'last_update_date'])
                ->join(array('v' => 'form_version'), 'a.form_version_id = v.form_version_id', array('file_path', 'file_name'), Select::JOIN_LEFT_OUTER)
                ->where(['a.form_assigned_id' => $arrFormIds]);

            $arrAssignedFormsInfo = $this->_db2->fetchAll($select);

            if (is_array($arrAssignedFormsInfo) && count($arrAssignedFormsInfo) > 0) {
                $arrAssignedFormInfo   = array();
                $arrNewCompleteFormIds = array();
                foreach ($arrAssignedFormsInfo as $assignedFormInfo) {
                    $arrNewCompleteFormIds[] = $assignedFormInfo['form_assigned_id'];
                    $arrAssignedFormInfo[$assignedFormInfo['form_assigned_id']] = $assignedFormInfo;
                }

                $userId           = $this->_auth->getCurrentUserId();
                $companyId        = $this->_company->getMemberCompanyId($member_id);
                $arrFamilyMembers = $this->_clients->getFamilyMembersForClient($member_id);

                // Insert new records in db
                foreach ($arrNewCompleteFormIds as $formId) {
                    // Now we need to create a 'flatten' version of pdf with data
                    // And save it to 'Finalized' folder in client documents
                    $fName = $lName = '';
                    foreach ($arrFamilyMembers as $familyMemberInfo) {
                        if (preg_match('/^other\d{0,}$/', $arrAssignedFormInfo[$formId]['family_member_id'])) {
                            $lName = 'Other';
                            break;
                        } elseif ($familyMemberInfo['id'] == $arrAssignedFormInfo[$formId]['family_member_id']) {
                            $fName = $familyMemberInfo['fName'];
                            $lName = $familyMemberInfo['lName'];

                            break;
                        }
                    }


                    // Create 'flatten' pdf
                    $arrFileInfo  = array(
                        'formId'         => $formId,
                        'authorId'       => $userId,
                        'memberId'       => $member_id,
                        'familyMemberId' => $arrAssignedFormInfo[$formId]['family_member_id'],
                        'fName'          => $fName,
                        'lName'          => $lName,
                        'companyId'      => $companyId,

                        'useRevision'    => $arrAssignedFormInfo[$formId]['use_revision'],
                        'filePath'       => $arrAssignedFormInfo[$formId]['file_path'],
                        'lastUpdateDate' => $arrAssignedFormInfo[$formId]['last_update_date']
                    );
                    $printPdfPath = $this->_pdf->createPrintVersion($arrFileInfo);

                    if (!$printPdfPath) {
                        return $this->downloadFile($printPdfPath, 'print.pdf', 'application/pdf');
                    }
                    $errMsg = $this->_tr->translate('Cannot print pdf');
                }
            }
        }

        $viewVariables = array();
        if (!empty($errMsg)) {
            $viewVariables['content'] = $errMsg;
        } else {
            $viewVariables['content'] = null;
        }
        $view->setVariables($viewVariables, true);

        return $view;
    }

    public function uploadRevisionAction()
    {
        $view = new JsonModel();

        $strError = '';

        $this->_db2->getDriver()->getConnection()->beginTransaction();
        try {
            // Check incoming params
            $formId = $this->findParam('form_id', 0);
            if (!is_numeric($formId) || $formId < 0) {
                $strError = $this->_tr->translate('Incorrectly selected form');
            }

            // Check if current user has access to this form
            $memberId = 0;
            if (empty($strError)) {
                $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($formId);
                $memberId         = $assignedFormInfo['client_member_id'];
                if (empty($memberId) || !$this->_clients->isAlowedClient($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            // Check the file
            if (empty($strError) && !array_key_exists('form-revision', $_FILES)) {
                $strError = $this->_tr->translate('File was attached incorrectly');
            }

            if (empty($strError)) {
                $upload = new Extension('pdf');
                if (!$upload->isValid($_FILES['form-revision'])) {
                    $strError = $this->_tr->translate('Unsupported file format');
                }
            }

            // All is correct?
            // Create new DB record and place file in correct directory
            if (empty($strError)) {
                $arrRevisions   = $this->_forms->getFormRevision()->getAssignedFormRevisions($formId);
                $countRevisions = count($arrRevisions);

                $revisionNumber = 1;
                if ($countRevisions) {
                    $arrLastRevisionInfo = $arrRevisions[count($arrRevisions) - 1];
                    $revisionNumber      = $arrLastRevisionInfo['form_revision_number'] + 1;
                }

                // Create new record in db
                $currentMemberId       = $this->_auth->getCurrentUserId();
                $arrNewRevisionDetails = array(
                    'form_assigned_id'     => $formId,
                    'form_revision_number' => $revisionNumber,
                    'uploaded_by'          => $currentMemberId,
                    'uploaded_on'          => date('c')
                );
                $revisionId            = $this->_forms->getFormRevision()->createNewFormVersion($arrNewRevisionDetails);


                // Update 'last updated on/by' fields
                $data = array(
                    'updated_by'       => $currentMemberId,
                    'last_update_date' => date('c')
                );
                $this->_db2->update('form_assigned', $data, ['form_assigned_id' => $formId]);


                // Place file in correct place
                $pathToNewPdf = $this->_files->getClientBarcodedPDFFilePath($memberId, $revisionId);

                $booSuccess = move_uploaded_file($_FILES['form-revision']['tmp_name'], $pathToNewPdf);
                if ($booSuccess) {
                    $this->_db2->getDriver()->getConnection()->commit();

                    // Leave only X revisions. Older revisions will be deleted
                    $maxRevisionsCount = 3;
                    if ($countRevisions >= $maxRevisionsCount) {
                        for ($i = 0; $i <= ($countRevisions - $maxRevisionsCount); $i++) {
                            $this->_forms->getFormRevision()->deleteRevision($memberId, $arrRevisions[$i]['form_revision_id']);
                        }
                    }
                } else {
                    $this->_db2->getDriver()->getConnection()->rollback();
                    $strError = $this->_tr->translate('File was not saved on server. Please contact to web site support.');
                }
            }
        } catch (Exception $e) {
            $this->_db2->getDriver()->getConnection()->rollback();
            $strError = $this->_tr->translate('Internal Error. Please contact to web site support.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function downloadRevisionAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $errMsg = '';

        // Check incoming params
        $formId = $this->findParam('pdfid', 0);
        if (!is_numeric($formId) || $formId < 0) {
            $errMsg = $this->_tr->translate('Incorrectly selected form');
        }

        // Check if current user has access to this form
        $memberId         = 0;
        $assignedFormInfo = array();
        if (empty($errMsg)) {
            $formAssigned     = $this->_forms->getFormAssigned();
            $assignedFormInfo = $formAssigned->getAssignedFormInfo($formId, true);
            $memberId         = $assignedFormInfo['client_member_id'];
            if (!$this->_clients->isAlowedClient($memberId)) {
                $errMsg = $this->_tr->translate('Insufficient access rights');
            }
        }

        // Check if revision is correct
        $revisionId      = (int)$this->findParam('revision', 0);
        $arrRevisionInfo = array();
        if (empty($errMsg)) {
            if (empty($revisionId)) {
                $arrRevisions = $this->_forms->getFormRevision()->getAssignedFormRevisions($formId, false, true);
                if (count($arrRevisions)) {
                    $arrRevisionInfo = $arrRevisions[count($arrRevisions) - 1];
                    $revisionId      = $arrRevisionInfo['form_revision_id'];
                }
            } else {
                $arrRevisionInfo = $this->_forms->getFormRevision()->loadRevisionInfo($formId, $revisionId);
            }


            if (!empty($revisionId) && (!is_array($arrRevisionInfo) || !count($arrRevisionInfo))) {
                $errMsg = $this->_tr->translate('Incorrectly selected revision');
            }
        }

        if (empty($errMsg)) {
            // Load all other required info about the form
            $arrFormInfo = $this->_forms->getFormVersion()->getFormVersionInfo($assignedFormInfo['form_version_id']);

            // Generate file name
            $arrFamilyMembers = array_merge(
                $this->_clients->getFamilyMembersForClient($memberId),
                $this->_clients->getFamilyMembersForClient($memberId, true)
            );
            $outputFileName   = $this->_pdf->generateFileNameFromAssignedFormInfo($assignedFormInfo, $arrFormInfo, $arrFamilyMembers);

            if (empty($revisionId)) {
                // Load blank pdf form
                $pathToPdf      = $this->_config['directory']['pdfpath_physical'] . '/' . $arrFormInfo['file_path'];
                $outputFileName .= sprintf(' (Revision %d)', 0);
            } else {
                // Load already saved pdf form
                $pathToPdf      = $this->_files->getClientBarcodedPDFFilePath($memberId, $revisionId);
                $outputFileName .= sprintf(' (Revision %d)', $arrRevisionInfo['form_revision_number']);
            }

            // Output the file
            if (!empty($pathToPdf) && file_exists($pathToPdf)) {
                return $this->downloadFile($pathToPdf, $outputFileName . '.pdf', 'application/pdf');
            } else {
                $errMsg = $this->_tr->translate('File does not exists');
            }
        }

        if (!empty($errMsg)) {
            $viewVariables['content'] = $errMsg;
        } else {
            $viewVariables['content'] = null;
        }
        $view->setVariables($viewVariables, true);

        return $view;
    }

    public function editFormSettingsAction()
    {
        $view = new JsonModel();

        $errMsg = '';

        $memberId = (int)$this->findParam('member_id', 0);
        if (!$this->_clients->isAlowedClient($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
            $errMsg = $this->_tr->translate('Insufficient access rights');
        }

        $assignedFormId = (int)Json::decode($this->findParam('client_form_id', 0), Json::TYPE_ARRAY);
        if (empty($errMsg) && empty($assignedFormId)) {
            $errMsg = $this->_tr->translate('Incorrectly selected form');
        }

        if (empty($errMsg)) {
            $filter = new StripTags();

            $settings = trim($filter->filter(Json::decode($this->findParam('settings', ''), Json::TYPE_ARRAY)));

            $this->_forms->getFormAssigned()->updateFormSettings($settings, $assignedFormId);
        }

        return $view->setVariables(array('success' => empty($errMsg), 'message' => $errMsg));
    }
}
