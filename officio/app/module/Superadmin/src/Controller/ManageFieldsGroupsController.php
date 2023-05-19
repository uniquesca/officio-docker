<?php

/**
 * Manage Fields Groups Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Clients\Service\Clients\CaseCategories;
use Clients\Service\Clients\CaseStatuses;
use Clients\Service\Clients\Fields;
use Exception;
use Forms\Service\Forms;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Settings;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\ConditionalFields;
use Officio\Service\Roles;
use Templates\Service\Templates;
use Laminas\Db\Sql\Expression;

class ManageFieldsGroupsController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Roles */
    protected $_roles;

    /** @var Templates */
    protected $_templates;

    /** @var CaseStatuses */
    protected $_caseStatuses;

    /** @var CaseCategories */
    protected $_caseCategories;

    /** @var ConditionalFields */
    private $_conditionalFields;

    /** @var Fields */
    private $_fields;

    /** @var Forms */
    protected $_forms;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company           = $services[Company::class];
        $this->_clients           = $services[Clients::class];
        $this->_roles             = $services[Roles::class];
        $this->_templates         = $services[Templates::class];
        $this->_forms             = $services[Forms::class];
        $this->_encryption        = $services[Encryption::class];
        $this->_conditionalFields = $services[ConditionalFields::class];
        $this->_caseStatuses      = $this->_clients->getCaseStatuses();
        $this->_caseCategories    = $this->_clients->getCaseCategories();
        $this->_fields            = $this->_clients->getFields();
    }

    private function _loadCompanies($booPlease = false)
    {
        $where = [
            'c.status' => 1
        ];

        // Show only one company for admin, because
        // he is related to only one company
        $companyId = $this->_auth->getCurrentUserCompanyId();
        if (!empty($companyId)) {
            $where['c.company_id'] = $companyId;
        }

        $select = (new Select())
            ->from(['c' => 'company'])
            ->columns(['company_id', 'companyName'])
            ->where($where);

        $arrCompanies = $this->_db2->fetchAll($select);

        $arrResult = array();
        if ($booPlease) {
            $arrResult[0] = $this->_tr->translate('--- DEFAULT ---');
        }

        if (is_array($arrCompanies) && count($arrCompanies) > 0) {
            foreach ($arrCompanies as $company) {
                $arrResult[$company['company_id']] = $company['companyName'];
            }
        }

        return $arrResult;
    }

    /**
     * The default action - show fields/groups for specific template
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $caseTemplateId = intval($this->params()->fromQuery('template_id'));
        $view->setVariable('caseTemplateId', $caseTemplateId);

        $templateName = '';
        if (!$this->_clients->getCaseTemplates()->hasAccessToTemplate($caseTemplateId)) {
            $view->setTemplate('layout/plain');
            $view->setVariables(
                [
                    'content' => $this->_tr->translate('Incorrectly selected template.')
                ]
            );
        } else {
            // Load groups and their fields
            $booPlease = false;

            if (!$this->_auth->isCurrentUserSuperadmin()) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            } else {
                // Superadmin
                $booPlease = true;
                $companyId = $this->params()->fromQuery('company_id');
                if (empty($companyId)) {
                    $companyId = 0;
                }
            }

            $arrCaseTypeInfo = $this->_clients->getCaseTemplates()->getTemplateInfo($caseTemplateId);
            $templateName    = $arrCaseTypeInfo['client_type_name'];

            // Load fields for this company
            $arrGroupsAndFields = $this->_fields->getAllGroupsAndFields($companyId, $caseTemplateId);

            // Get readable conditions, so we'll show them in tooltips
            $view->setVariable('arrReadableConditions', $this->_conditionalFields->getCaseTypeReadableConditions($this->_fields, $companyId, $caseTemplateId));

            $view->setVariable('arrCompanies', $this->_loadCompanies($booPlease));
            $view->setVariable('arrGroupsAndFields', $arrGroupsAndFields);
            $view->setVariable('arrFieldsInfo', array('company_id' => $companyId));
            $view->setVariable('booHideGroups', false);
            $view->setVariable('fieldTypesList', $this->_clients->getFieldTypes()->getFieldTypes());
            $view->setVariable('arrRoles', $this->_roles->getCompanyRoles($companyId, 0, false, false, ['admin', 'user', 'individual_client', 'employer_client']));
            $view->setVariable('booIsAuthorisedAgentsManagementEnabled', $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled());
            $view->setVariable('booCreatedFromDefaultTemplate', $this->_clients->getCaseTemplates()->isCreatedFromDefaultTemplate($caseTemplateId));
        }

        $title = trim($templateName . ' ' . $this->_tr->translate('Fields Groups'));
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    public function getFieldInfoAction()
    {
        $strError     = '';
        $arrFieldInfo = array();

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrect incoming params');
            }

            $companyId = Json::decode($this->params()->fromPost('company_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                // Check if user has access to this company
                $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
                if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                    $strError = $this->_tr->translate('Incorrectly selected company');
                }
            }

            $caseTemplateId = intval($this->params()->fromPost('template_id'));
            if (empty($strError) && !$this->_clients->getCaseTemplates()->hasAccessToTemplate($caseTemplateId)) {
                $strError = $this->_tr->translate('Incorrectly selected case template');
            }


            $fieldId       = Json::decode($this->params()->fromPost('field_id', ''), Json::TYPE_ARRAY);
            $updateFieldId = 0;
            if (empty($strError) && !empty($fieldId)) {
                $updateFieldId = str_replace('field_', '', $fieldId);
                $arrFieldInfo  = $this->_fields->getFieldInfo($updateFieldId, $companyId, $caseTemplateId);

                if (empty($arrFieldInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                }
            }

            if (empty($strError)) {
                $arrFieldInfo['field_default_access'] = $this->_fields->getFieldDefaultAccessRights($companyId, $updateFieldId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'           => !empty($strError),
            'message'         => empty($strError) ? $this->_tr->translate('Information was successfully loaded') : $strError,
            'additional_info' => empty($strError) ? $arrFieldInfo : []
        );

        return new JsonModel($arrResult);
    }


    public function editFieldAction()
    {
        set_time_limit(10 * 60); // 10 min
        ini_set('memory_limit', '512M');

        $strError               = '';
        $strConfirmationMessage = '';
        $arrAdditionalInfo      = array();

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                exit();
            }

            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->params()->fromPost('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }


            // Load and check all incoming parameters
            $filter        = new StripTags();
            $groupId       = $filter->filter(Json::decode($this->params()->fromPost('group_id', ''), Json::TYPE_ARRAY));
            $updateGroupId = str_replace('fields_group_', '', $groupId);

            if (empty($strError) && !empty($groupId) && !is_numeric($updateGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected group');
            }


            $booIsGroupInCompany = $this->_fields->isGroupInCompany($companyId, $updateGroupId, false);
            if (empty($strError) && !$booIsGroupInCompany) {
                $strError = $this->_tr->translate('Incorrectly selected group [err#2]');
            }

            $fieldId       = $filter->filter(Json::decode($this->params()->fromPost('field_id', ''), Json::TYPE_ARRAY));
            $updateFieldId = str_replace('field_', '', $fieldId);

            if (empty($strError) && !empty($fieldId) && !$this->_fields->isFieldInGroup($updateFieldId, $updateGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected field');
            }

            $fieldType        = (int)Json::decode($this->params()->fromPost('field_type'), Json::TYPE_ARRAY);
            $arrFieldTypeInfo = [];
            if (empty($strError)) {
                $arrFieldTypeInfo = $this->_clients->getFieldTypes()->getFieldTypeInfoById($fieldType);
                if (empty($arrFieldTypeInfo)) {
                    $strError = $this->_tr->translate('Incorrect field type');
                }
            }

            if (empty($strError) && empty($fieldId) && $fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('division')) {
                $strError = $this->_tr->translate('It is possible to create only one Divisions field');
            }


            $fieldLabel = trim($filter->filter(Json::decode($this->params()->fromPost('field_label', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && !mb_strlen($fieldLabel)) {
                $strError = $this->_tr->translate('Field Label is a required field.');
            }

            $fieldCompanyId = $filter->filter(trim(Json::decode($this->params()->fromPost('field_company_id', ''), Json::TYPE_ARRAY)));
            // Remove not allowed chars
            $fieldCompanyId = preg_replace('/[^a-zA-Z\d_\-]/', '', $fieldCompanyId);

            $fieldMaxLength              = $this->params()->fromPost('field_maxlength');
            $fieldOptions                = Json::decode($this->params()->fromPost('field_options'), Json::TYPE_ARRAY);
            $booFieldIsEncrypted         = (bool)Json::decode($this->params()->fromPost('field_encrypted'), Json::TYPE_ARRAY);
            $fieldSkipAccessRequirements = Json::decode($this->params()->fromPost('field_skip_access_requirements'), Json::TYPE_ARRAY);
            $fieldSyncWithDefault        = empty($fieldId) ? 'No' : Json::decode($this->params()->fromPost('field_sync_with_default'), Json::TYPE_ARRAY);
            $fieldMultipleValues         = Json::decode($this->params()->fromPost('field_multiple_values'), Json::TYPE_ARRAY);
            $fieldCanEditInGui           = Json::decode($this->params()->fromPost('field_can_edit_in_gui'), Json::TYPE_ARRAY);
            $fieldRequired               = Json::decode($this->params()->fromPost('field_required'), Json::TYPE_ARRAY);
            $fieldRequiredForSubmission  = Json::decode($this->params()->fromPost('field_required_for_submission'), Json::TYPE_ARRAY);
            $fieldDisabled               = Json::decode($this->params()->fromPost('field_disabled'), Json::TYPE_ARRAY);
            $fieldUseFullRow             = Json::decode($this->params()->fromPost('field_use_full_row'), Json::TYPE_ARRAY);
            $fieldDefaultValue           = $filter->filter(Json::decode($this->params()->fromPost('field_default_value'), Json::TYPE_ARRAY));
            $fieldCustomHeight           = (int)$this->params()->fromPost('field_custom_height');
            $fieldMinValue               = $this->params()->fromPost('field_min_value');
            $fieldMaxValue               = $this->params()->fromPost('field_max_value');
            $fieldDefaultAccess          = Json::decode($this->params()->fromPost('field_default_access'), Json::TYPE_ARRAY);

            $fieldImageWidth  = (int)$this->params()->fromPost('field_image_width');
            $fieldImageHeight = (int)$this->params()->fromPost('field_image_height');
            if (empty($strError) && $arrFieldTypeInfo['booWithImageSettings']) {
                if (($fieldImageWidth < 1 || $fieldImageHeight < 1)) {
                    $strError = $this->_tr->translate('Incorrectly specified image size');
                } else {
                    $fieldOptions = array(
                        array(
                            'name'  => $fieldImageWidth,
                            'order' => 0
                        ),
                        array(
                            'name'  => $fieldImageHeight,
                            'order' => 1
                        )
                    );
                }
            }

            if (empty($strError) && $arrFieldTypeInfo['booWithDefaultValue']) {
                $fieldOptions = [];
                if ($fieldDefaultValue !== '') {
                    $fieldOptions = array(
                        array(
                            'name'  => $fieldDefaultValue,
                            'order' => 0
                        )
                    );
                }
            }

            $arrFieldInfo                             = array();
            $arrFieldInfo['company_id']               = $companyId;
            $arrFieldInfo['company_field_id']         = $fieldCompanyId;
            $arrFieldInfo['type']                     = $fieldType;
            $arrFieldInfo['label']                    = $fieldLabel;
            $arrFieldInfo['encrypted']                = $booFieldIsEncrypted ? 'Y' : 'N';
            $arrFieldInfo['skip_access_requirements'] = $fieldSkipAccessRequirements ? 'Y' : 'N';
            $arrFieldInfo['sync_with_default']        = in_array($fieldSyncWithDefault, array('Yes', 'No', 'Label')) ? $fieldSyncWithDefault : 'No';
            $arrFieldInfo['multiple_values']          = $fieldMultipleValues ? 'Y' : 'N';
            $arrFieldInfo['can_edit_in_gui']          = $fieldCanEditInGui ? 'Y' : 'N';
            $arrFieldInfo['required']                 = $fieldRequired ? 'Y' : 'N';
            $arrFieldInfo['required_for_submission']  = $fieldRequiredForSubmission ? 'Y' : 'N';
            $arrFieldInfo['disabled']                 = $fieldDisabled ? 'Y' : 'N';
            $arrFieldInfo['use_full_row']             = $fieldUseFullRow;


            // Because encrypted fields cannot be used in the advanced searches -
            // check if this field is used in any of the saved searches
            $booFieldWasEncrypted = false;
            if (empty($strError) && !empty($fieldId)) {
                $arrSavedFieldInfo = $this->_fields->getFieldInfo($updateFieldId, $companyId, 0);

                if (empty($arrSavedFieldInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                } else {
                    $booFieldWasEncrypted = $arrSavedFieldInfo['encrypted'] == 'Y';

                    if (!$booFieldWasEncrypted && $booFieldIsEncrypted && $this->_clients->getSearch()->isFieldUsedInSearch($companyId, $fieldCompanyId)) {
                        $strError = $this->_tr->translate(
                            'This field is already in use in the advanced searches.<br/>' .
                            'It is not possible to enable Encryption option.<br/><br/>' .
                            'Please remove this field from all saved advanced searches and try again.'
                        );
                    }
                }
            }

            $arrFieldDefaultAccess = array();
            if (empty($strError)) {
                $arrRoles = $this->_roles->getCompanyRoles($companyId, 0, false, true, ['admin', 'user', 'individual_client', 'employer_client']);
                foreach ($fieldDefaultAccess as $roleFullId => $access) {
                    if (!in_array($access, array('', 'R', 'F'))) {
                        $strError = $this->_tr->translate("Incorrectly defined role's access level");
                    } elseif (preg_match('/role_(\d+)_default_access/', $roleFullId, $regs)) {
                        if (!empty($regs[1]) && !in_array($regs[1], $arrRoles)) {
                            $strError = $this->_tr->translate('Incorrectly defined role');
                        } else {
                            $arrFieldDefaultAccess[$regs[1]] = $access;
                        }
                    } else {
                        $strError = $this->_tr->translate('Incorrectly defined role');
                    }

                    if (!empty($strError)) {
                        break;
                    }
                }

                if (empty($strError)) {
                    ksort($arrFieldDefaultAccess);
                }
            }

            if (empty($fieldMaxLength) || is_numeric($fieldMaxLength)) {
                $arrFieldInfo['maxlength'] = $fieldMaxLength;
            }

            if (empty($strError) && $arrFieldTypeInfo['booWithCustomHeight'] && !empty($fieldCustomHeight)) {
                $arrFieldInfo['custom_height'] = $fieldCustomHeight;
            }

            if (empty($strError) && is_numeric($fieldMinValue) && is_numeric($fieldMaxValue)) {
                if ((int)$fieldMinValue > (int)$fieldMaxValue) {
                    $strError = $this->_tr->translate('Min value must be less than or equal to max value.');
                }
            }

            if ($arrFieldTypeInfo['booAutoCalcField'] && is_numeric($fieldMinValue)) {
                $arrFieldInfo['min_value'] = $fieldMinValue;
            } else {
                $arrFieldInfo['min_value'] = null;
            }

            if ($arrFieldTypeInfo['booAutoCalcField'] && is_numeric($fieldMaxValue)) {
                $arrFieldInfo['max_value'] = $fieldMaxValue;
            } else {
                $arrFieldInfo['max_value'] = null;
            }

            if (empty($strError) && $fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('case_internal_id') && $fieldCompanyId != 'case_internal_id') {
                $strError = $this->_tr->translate('Incorrect field name. Create the field with the name "case_internal_id"');
            }

            if (empty($strError) && $fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('applicant_internal_id') && $fieldCompanyId != 'applicant_internal_id') {
                $strError = $this->_tr->translate('Incorrect field name. Create the field with the name "applicant_internal_id"');
            }

            $arrAllFieldsInfoWithUniqueFieldId = [];
            if (empty($strError) && empty($fieldId)) {
                // Check if this field company id is correct and unique
                if (empty($fieldCompanyId)) {
                    $strError = $this->_tr->translate('Incorrect field name');
                }

                $savedId = $this->_fields->getCompanyFieldIdByUniqueFieldId($fieldCompanyId, $companyId);
                if (empty($strError) && !empty($savedId)) {
                    $strError = $this->_tr->translate('This field name is already in use. Please enter other field name and try again.');
                }

                if (empty($strError) && $companyId == $this->_company->getDefaultCompanyId()) {
                    $arrAllFieldsInfoWithUniqueFieldId = $this->_fields->getAllFieldsInfoByUniqueFieldId($fieldCompanyId);
                    if (!empty($arrAllFieldsInfoWithUniqueFieldId)) {
                        $arrFieldsWithSameFieldId           = array();
                        $booSameFieldId                     = false;
                        $booSameFieldIdWithAnotherFieldType = false;

                        foreach ($arrAllFieldsInfoWithUniqueFieldId as $fieldInfo) {
                            $fieldTypeName              = $this->_clients->getFieldTypes()->getStringFieldTypeById($fieldInfo['type']);
                            $arrFieldsWithSameFieldId[] = 'Company: ' . $fieldInfo['companyName'] . '; field type: ' . $fieldTypeName . '; label: ' . $fieldInfo['label'];
                            if ($fieldInfo['type'] != $fieldType) {
                                $booSameFieldIdWithAnotherFieldType = true;
                            } elseif (!empty($fieldInfo['parent_field_id']) || $fieldInfo['sync_with_default'] !== 'No') {
                                $booSameFieldId = true;
                            }
                        }

                        $prefix        = count($arrFieldsWithSameFieldId) > 1 ? '*' : '';
                        $strFieldsList = '</br>';
                        foreach ($arrFieldsWithSameFieldId as $str) {
                            $strFieldsList .= $prefix . ' ' . $str . '</br>';
                        }

                        if ($booSameFieldIdWithAnotherFieldType) {
                            $strError = $this->_tr->translate('This field name for another field type is already in use by one of the companies.<br>Please enter other field name and try again.') . $strFieldsList;
                        } elseif ($booSameFieldId) {
                            $strError = $this->_tr->translate('This field name is already in use by one of the companies (that is already linked to the default field OR sync is turned on). Please enter other field name and try again.') . $strFieldsList;
                        }
                    }
                }
            }

            if (empty($strError)) {
                list($strError, $updateFieldId) = $this->_fields->saveField($updateGroupId, $updateFieldId, $arrFieldInfo, $arrFieldTypeInfo, $fieldOptions, $booFieldWasEncrypted, $booFieldIsEncrypted, $arrFieldDefaultAccess, $arrAllFieldsInfoWithUniqueFieldId);

                if (empty($strError)) {
                    if (empty($fieldId)) {
                        $arrAdditionalInfo['group_id']        = $updateGroupId;
                        $arrAdditionalInfo['field_id']        = $updateFieldId;
                        $arrAdditionalInfo['field_name']      = $fieldLabel;
                        $arrAdditionalInfo['field_encrypted'] = $booFieldIsEncrypted;
                        $arrAdditionalInfo['field_required']  = $fieldRequired;
                        $arrAdditionalInfo['field_disabled']  = $fieldDisabled;
                    }

                    $strConfirmationMessage = empty($fieldId) ? $this->_tr->translate('Field was successfully created') : $this->_tr->translate('Field was successfully updated');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'         => empty($strError),
            'message'         => empty($strError) ? $strConfirmationMessage : $strError,
            'additional_info' => $arrAdditionalInfo,
        );
        return new JsonModel($arrResult);
    }

    public function deleteFieldAction()
    {
        $strError = '';

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrect incoming info');
            }

            $companyId      = Json::decode($this->params()->fromPost('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (empty($strError) && !empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $fieldId = Json::decode($this->params()->fromPost('field_id', ''), Json::TYPE_ARRAY);
            $fieldId = str_replace('field_', '', $fieldId);
            if (empty($strError) && !$this->_fields->hasCurrentMemberAccessToFieldById($fieldId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrFieldsToDelete = [$fieldId];
                if ($companyId == $this->_company->getDefaultCompanyId()) {
                    $arrAllCompaniesFields = $this->_fields->getFieldsByParentId($fieldId, true);

                    $arrFieldsToDelete = array_merge($arrFieldsToDelete, $arrAllCompaniesFields);
                }

                if (!$this->_fields->deleteField($arrFieldsToDelete)) {
                    $strError = $this->_tr->translate('Internal error.');
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

    public function deleteGroupAction()
    {
        $strError = '';

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrect incoming info');
            }

            $companyId      = Json::decode($this->params()->fromPost('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (empty($strError) && !empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $groupId       = Json::decode($this->params()->fromPost('group_id', ''), Json::TYPE_ARRAY);
            $deleteGroupId = str_replace('fields_group_', '', $groupId);
            if (empty($strError) && !empty($groupId) && !is_numeric($deleteGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected group');
            }

            if (empty($strError) && !$this->_fields->isGroupInCompany($companyId, $deleteGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected group');
            }

            if (empty($strError)) {
                // Check if there are fields in this group
                $select = (new Select())
                    ->from('client_form_order')
                    ->columns(['fields_count' => new Expression('COUNT(*)')])
                    ->where(['group_id' => $deleteGroupId]);

                $totalFields = $this->_db2->fetchOne($select);

                if ($totalFields != 0) {
                    // There are fields in this group
                    $strError = $this->_tr->translate('There are fields in this group. Please move these fields to other group, save changes and try again.');
                } else {
                    $this->_fields->deleteGroup($deleteGroupId);
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

    public function ajaxAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit();
        }

        set_time_limit(10 * 60); // 10 min
        ini_set('memory_limit', '-1');

        $strError              = '';
        $strSuccess            = $this->_tr->translate('Information was successfully updated.');
        $arrAdditionalInfo     = array();
        $booTransactionStarted = false;

        try {
            $companyId         = $this->_auth->getCurrentUserCompanyId();
            $booDefaultCompany = $companyId == $this->_company->getDefaultCompanyId();
            $caseTemplateId    = $this->params()->fromPost('template_id');

            $arrCompanyCaseTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId, true);
            if (!is_array($arrCompanyCaseTemplates) || !in_array($caseTemplateId, $arrCompanyCaseTemplates)) {
                $strError = $this->_tr->translate('Incorrectly selected case template.');
            }

            $action = Json::decode(stripslashes($this->params()->fromPost('doAction', '')), Json::TYPE_ARRAY);
            if (empty($strError) && !in_array($action, array('update_order', 'create_update_group'))) {
                $strError = $this->_tr->translate('Incorrect action.');
            }

            $arrAssignedGroupIds = $arrAllGroupIds = array();
            if (empty($strError)) {
                $arrCompanyGroups = $this->_fields->getCompanyGroups($companyId, false, $caseTemplateId);

                foreach ($arrCompanyGroups as $arrCompanyGroupInfo) {
                    $arrAllGroupIds[] = $arrCompanyGroupInfo['group_id'];
                    if ($arrCompanyGroupInfo['assigned'] != 'U') {
                        $arrAssignedGroupIds[] = $arrCompanyGroupInfo['group_id'];
                    }
                }
            }


            if (empty($strError)) {
                switch ($action) {
                    case 'update_order':
                        $arrCompanyFields = $this->_fields->getCompanyFields($companyId);
                        $arrFieldIds      = $this->_settings::arrayColumnAsKey('field_id', $arrCompanyFields, 'field_id');

                        $this->_db2->getDriver()->getConnection()->beginTransaction();
                        $booTransactionStarted = true;

                        // Save/update groups order info
                        $arrReceivedGroupsOrders = Json::decode(stripslashes($this->params()->fromPost('groups_order', '')), Json::TYPE_ARRAY);
                        if (!empty($arrReceivedGroupsOrders)) {
                            $arrGroupsOrder = array();
                            parse_str($arrReceivedGroupsOrders, $arrGroupsOrder);
                            if (is_array($arrGroupsOrder) && count($arrGroupsOrder) > 0) {
                                foreach ($arrGroupsOrder['fields_group'] as $order => $groupId) {
                                    if (in_array($groupId, $arrAssignedGroupIds)) {
                                        $this->_fields->updateGroupInfo($groupId, array('order' => $order));

                                        if ($booDefaultCompany) {
                                            $this->_fields->updateGroupInfo($groupId, array('order' => $order), true);
                                        }
                                    }
                                }
                            }
                        }

                        // Save/update fields order info
                        $arrFieldsOrder = Json::decode(stripslashes($this->params()->fromPost('fields_order', '')), Json::TYPE_ARRAY);
                        if (!empty($arrFieldsOrder) && is_array($arrFieldsOrder) && count($arrAllGroupIds) && count($arrFieldIds)) {
                            // Sort incoming data (by groups)
                            $arrGroupedOrder = array();
                            foreach ($arrFieldsOrder as $fieldOrderInfo) {
                                $updateFieldId = str_replace('field_', '', $fieldOrderInfo['field_id'] ?? '');
                                $updateGroupId = str_replace('fields_group_', '', $fieldOrderInfo['group_id'] ?? '');

                                if (in_array($updateGroupId, $arrAllGroupIds) && in_array($updateFieldId, $arrFieldIds) &&
                                    (!array_key_exists($updateGroupId, $arrGroupedOrder) || !array_key_exists($updateFieldId, $arrGroupedOrder[$updateGroupId]))) {
                                    $arrGroupedOrder[$updateGroupId][$updateFieldId] = array(
                                        'field_use_full_row' => $fieldOrderInfo['field_use_full_row'],
                                        'field_id'           => $updateFieldId
                                    );
                                }
                            }

                            $arrChangedGroups      = [];
                            $arrSavedGroupedOrders = $this->_fields->getFieldsOrderInGroups(array_keys($arrGroupedOrder));
                            foreach ($arrSavedGroupedOrders as $savedGroupId => $arrSavedOrderInfo) {
                                if (!isset($arrGroupedOrder[$savedGroupId])) {
                                    $arrChangedGroups[] = $savedGroupId;
                                    continue;
                                }

                                $arrSavedGroupFields = array_keys($arrSavedOrderInfo);
                                $arrNewGroupFields   = array_keys($arrGroupedOrder[$savedGroupId]);
                                if (!empty(array_diff($arrSavedGroupFields, $arrNewGroupFields)) || !empty(array_diff($arrNewGroupFields, $arrSavedGroupFields))) {
                                    $arrChangedGroups[] = $savedGroupId;
                                }
                            }

                            foreach ($arrGroupedOrder as $newGroupId => $arrNewOrderInfo) {
                                if (!isset($arrSavedGroupedOrders[$newGroupId])) {
                                    $arrChangedGroups[] = $newGroupId;
                                    continue;
                                }

                                $arrNewGroupFields   = array_keys($arrNewOrderInfo);
                                $arrSavedGroupFields = array_keys($arrSavedGroupedOrders[$newGroupId]);
                                if (!empty(array_diff($arrSavedGroupFields, $arrNewGroupFields)) || !empty(array_diff($arrNewGroupFields, $arrSavedGroupFields))) {
                                    $arrChangedGroups[] = $newGroupId;
                                }
                            }
                            $arrChangedGroups = Settings::arrayUnique($arrChangedGroups);


                            $arrValues      = array();
                            $currentGroupId = 0;
                            $fieldOrder     = 0;
                            $platform       = $this->_db2->getPlatform();
                            foreach ($arrGroupedOrder as $groupId => $arrFields) {
                                $arrLinkedGroupIds = [];
                                if ($booDefaultCompany) {
                                    $arrSavedLinkedGroups = $this->_fields->getGroupsByParentId($groupId);
                                    foreach ($arrSavedLinkedGroups as $arrSavedLinkedGroupInfo) {
                                        $arrLinkedGroupIds[$arrSavedLinkedGroupInfo['company_id']] = $arrSavedLinkedGroupInfo['group_id'];
                                    }
                                }


                                foreach ($arrFields as $arrFieldOrderInfo) {
                                    if ($currentGroupId != $groupId) {
                                        $currentGroupId = $groupId;
                                        $fieldOrder     = 0;
                                    } else {
                                        $fieldOrder++;
                                    }

                                    $arrValues[] = sprintf(
                                        '(%d, %d, %s, %d)',
                                        $groupId,
                                        $arrFieldOrderInfo['field_id'],
                                        $platform->quoteValue($arrFieldOrderInfo['field_use_full_row'] ? 'Y' : 'N'),
                                        $fieldOrder
                                    );

                                    if ($booDefaultCompany) {
                                        $arrFieldsByParentId = $this->_fields->getFieldsByParentId($arrFieldOrderInfo['field_id']);
                                        foreach ($arrFieldsByParentId as $arrFieldInfo) {
                                            if (isset($arrLinkedGroupIds[$arrFieldInfo['company_id']])) {
                                                $linkedGroupId = $arrLinkedGroupIds[$arrFieldInfo['company_id']];

                                                $arrValues[] = sprintf(
                                                    '(%d, %d, %s, %d)',
                                                    $linkedGroupId,
                                                    $arrFieldInfo['field_id'],
                                                    $platform->quoteValue($arrFieldOrderInfo['field_use_full_row'] ? 'Y' : 'N'),
                                                    $fieldOrder
                                                );

                                                $arrAllGroupIds[] = $linkedGroupId;
                                            }
                                        }
                                    }
                                }
                            }

                            // Delete all records before creating new ones
                            $this->_db2->delete('client_form_order', ['group_id' => $arrAllGroupIds]);

                            if (count($arrValues)) {
                                $sql = sprintf("INSERT INTO client_form_order (`group_id`, `field_id`, `use_full_row`, `field_order`) VALUES %s", implode(',' . PHP_EOL, $arrValues));
                                $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
                            }

                            $this->_db2->getDriver()->getConnection()->commit();
                            $booTransactionStarted = false;

                            // Update access only if there were some changes
                            if (!empty($arrChangedGroups)) {
                                $this->_fields->updateGroupAccessFromFields($companyId, $arrChangedGroups);
                                if ($booDefaultCompany) {
                                    $arrParentGroups = $this->_fields->getGroupsByParentId($arrChangedGroups);

                                    $arrParentGroupsGroupedByCompanies = [];
                                    foreach ($arrParentGroups as $arrParentGroup) {
                                        $arrParentGroupsGroupedByCompanies[$arrParentGroup['company_id']][] = $arrParentGroup['group_id'];
                                    }

                                    foreach ($arrParentGroupsGroupedByCompanies as $groupCompanyId => $arrCompanyChangedGroups) {
                                        $this->_fields->updateGroupAccessFromFields($groupCompanyId, Settings::arrayUnique($arrCompanyChangedGroups));
                                    }
                                }
                            }
                        }
                        break;

                    case 'create_update_group':
                        $filter              = new StripTags();
                        $groupId             = Json::decode(stripslashes($this->params()->fromPost('group_id', '')), Json::TYPE_ARRAY);
                        $groupColsCount      = Json::decode(stripslashes($this->params()->fromPost('group_cols_count', '')), Json::TYPE_ARRAY);
                        $updateGroupId       = str_replace('fields_group_', '', $groupId);
                        $groupName           = $filter->filter(trim(Json::decode(stripslashes($this->params()->fromPost('group_name', '')), Json::TYPE_ARRAY)));
                        $isGroupCollapsed    = Json::decode($this->params()->fromPost('group_collapsed'), Json::TYPE_ARRAY);
                        $isGroupTitleVisible = Json::decode($this->params()->fromPost('group_show_title'), Json::TYPE_ARRAY);

                        if (!empty($groupId) && !in_array($updateGroupId, $arrAssignedGroupIds)) {
                            $strError = $this->_tr->translate('Incorrectly selected group');
                        }

                        if (empty($strError) && empty($groupName)) {
                            $strError = $this->_tr->translate('Incorrect group name');
                        }

                        if (empty($strError) && (!is_numeric($groupColsCount) || $groupColsCount < 1 || $groupColsCount > 5)) {
                            $strError = $this->_tr->translate('Incorrect group columns count');
                        }

                        if (empty($strError)) {
                            $this->_db2->getDriver()->getConnection()->beginTransaction();
                            $booTransactionStarted = true;

                            if (empty($updateGroupId)) {
                                // Create new group
                                $groupId = $this->_fields->createGroup($companyId, $groupName, $groupColsCount, $caseTemplateId, $isGroupCollapsed, $isGroupTitleVisible);
                                if ($groupId) {
                                    $arrAdditionalInfo['new_group_id']   = $groupId;
                                    $arrAdditionalInfo['new_group_name'] = $groupName;
                                    $strSuccess                          = $this->_tr->translate('Group was successfully created.');
                                } else {
                                    $strError = $this->_tr->translate('Internal error.');
                                }
                            } elseif ($this->_fields->updateGroup($companyId, $updateGroupId, $groupName, $groupColsCount, $isGroupCollapsed, $isGroupTitleVisible)) {
                                $arrAdditionalInfo['new_group_id']   = $updateGroupId;
                                $arrAdditionalInfo['new_group_name'] = $groupName;
                                $strSuccess                          = $this->_tr->translate('Group was successfully updated.');
                            } else {
                                $strError = $this->_tr->translate('Internal error.');
                            }
                        }

                        break;
                }
            }

            if (empty($strError) && $booTransactionStarted) {
                $this->_db2->getDriver()->getConnection()->commit();
                $booTransactionStarted = false;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError) && $booTransactionStarted) {
            $this->_db2->getDriver()->getConnection()->rollback();
        }

        $arrResult = array(
            'error'           => !empty($strError),
            'message'         => empty($strError) ? $strSuccess : $strError,
            'additional_info' => $arrAdditionalInfo
        );

        return new JsonModel($arrResult);
    }


    public function templatesAction()
    {
        $view = new ViewModel();

        $title = $this->_company->getCurrentCompanyDefaultLabel('case_type', true);
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $title = $this->_tr->translate('Default') . ' ' . $title;
        }

        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);
        $view->setVariable('arrCaseTemplateTypes', $this->_clients->getCaseTemplates()->getAllTemplatesTypes());
        $view->setVariable('arrCaseEmailTemplates', $this->_templates->getTemplatesList(true, 0, false, 'Email'));
        $view->setVariable('arrCaseStatusLists', $this->_caseStatuses->getCompanyCaseStatusLists($this->_auth->getCurrentUserCompanyId()));

        $arrFormVersions          = $this->_forms->getFormVersion()->searchFormByName('', 'all');
        $arrFormVersionsFormatted = array();
        foreach ($arrFormVersions as $arrFormVersionInfo) {
            $arrFormVersionsFormatted[] = array(
                $arrFormVersionInfo['form_version_id'],
                $arrFormVersionInfo['file_name'] . ' (' . $this->_settings->formatDate($arrFormVersionInfo['version_date']) . ')'
            );
        }
        $view->setVariable('arrCaseFormVersions', $arrFormVersionsFormatted);
        $view->setVariable('booIsNotDefaultCompany', $this->_auth->getCurrentUserCompanyId() != $this->_company->getDefaultCompanyId());
        $view->setVariable('caseTypeFieldLabelSingular', $this->_company->getCurrentCompanyDefaultLabel('case_type'));
        $view->setVariable('caseTypeFieldLabelPlural', $this->_company->getCurrentCompanyDefaultLabel('case_type', true));
        $view->setVariable('statusesFieldLabelSingular', $this->_company->getCurrentCompanyDefaultLabel('case_status'));
        $view->setVariable('categoriesFieldLabelSingular', $this->_company->getCurrentCompanyDefaultLabel('categories'));
        $view->setVariable('categoriesFieldLabelPlural', $this->_company->getCurrentCompanyDefaultLabel('categories', true));

        return $view;
    }

    public function getCasesTemplatesAction()
    {
        $booSuccess   = false;
        $arrTemplates = array();
        $totalCount   = 0;

        try {
            $companyId    = $this->_auth->getCurrentUserCompanyId();
            $arrTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId);

            $defaultCompanyId    = $this->_company->getDefaultCompanyId();
            $dateFormatFull      = $this->_settings->variableGet('dateFormatFull');
            $arrDefaultTemplates = $this->_clients->getCaseTemplates()->getTemplates($defaultCompanyId, true);
            foreach ($arrTemplates as $key => $arrTemplateInfo) {
                // A default/custom type
                if ($companyId == $defaultCompanyId) {
                    $booDefault          = empty($arrTemplateInfo['case_template_parent_id']);
                    $booCanAllFieldsEdit = true;
                } else {
                    $booDefault          = in_array($arrTemplateInfo['case_template_parent_id'], $arrDefaultTemplates);
                    $booCanAllFieldsEdit = !$booDefault;
                }
                $arrTemplates[$key]['case_template_default']             = $booDefault ? 'Y' : 'N';
                $arrTemplates[$key]['case_template_can_all_fields_edit'] = $booCanAllFieldsEdit;
                $arrTemplates[$key]['case_template_categories']          = $this->_clients->getCaseCategories()->getCaseCategoriesMappingForCaseType($arrTemplateInfo['case_template_id'], false);

                // Generate a tooltip
                $tooltip = '';
                if (!$booDefault) {
                    if (!empty($arrTemplateInfo['case_template_parent_id'])) {
                        $arrParentTemplateInfo = $this->_clients->getCaseTemplates()->getTemplateInfo($arrTemplateInfo['case_template_parent_id']);
                        $parentTemplateName    = $arrParentTemplateInfo['client_type_name'];
                    } else {
                        $parentTemplateName = $this->_tr->translate('Blank template');
                    }

                    $tooltip .= sprintf(
                        $this->_tr->translate('Copy of: %s'),
                        $parentTemplateName
                    );
                }

                if (!empty($tooltip)) {
                    $tooltip .= '<br>';
                }

                $tooltip .= sprintf(
                    $this->_tr->translate('Created on: %s'),
                    date($dateFormatFull, strtotime($arrTemplateInfo['case_template_created_on']))
                );

                $arrTemplates[$key]['case_template_default_tooltip'] = $tooltip;
            }

            $totalCount = count($arrTemplates);
            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'items'   => $arrTemplates,
            'count'   => $totalCount,
        );

        return new JsonModel($arrResult);
    }

    public function addCasesTemplateAction()
    {
        set_time_limit(10 * 60); // 10 min
        ini_set('memory_limit', '-1');

        $strError              = '';
        $templateId            = 0;
        $booTransactionStarted = false;

        try {
            $filter       = new StripTags();
            $templateName = trim($filter->filter($this->params()->fromPost('case_template_name', '')));
            if ($templateName == '') {
                $strError = $this->_tr->translate('Incorrect template name.');
            }

            $templateCopyId = $this->params()->fromPost('case_template_copy_from');
            if (empty($strError) && !empty($templateCopyId) && !$this->_clients->getCaseTemplates()->hasAccessToTemplate($templateCopyId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $templateFormVersionId  = $this->params()->fromPost('case_form_version_id', '');
            $templateFormVersionIds = explode(";", $templateFormVersionId);
            foreach ($templateFormVersionIds as $templateFormVersionId) {
                if (empty($strError) && !empty($templateFormVersionId) && !$this->_forms->getFormVersion()->formVersionExists($templateFormVersionId)) {
                    $strError = $this->_tr->translate('Insufficient access rights [form].');
                    break;
                }
            }

            $templateEmailTemplateId = $this->params()->fromPost('case_email_template_id');
            if (empty($strError) && !empty($templateEmailTemplateId) && !$this->_templates->hasAccessToTemplate($templateEmailTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights [email template].');
            }

            $templateCaseStatusListId = $this->params()->fromPost('case_template_client_status_list_id');
            if (empty($strError)) {
                if (empty($templateCaseStatusListId)) {
                    $strError = $this->_tr->translate(
                        sprintf(
                            'Default %s List is a required field.',
                            $this->_company->getCurrentCompanyDefaultLabel('case_status')
                        )
                    );
                } elseif (!$this->_caseStatuses->hasAccessToCaseStatusList($templateCaseStatusListId, true)) {
                    $strError = $this->_tr->translate('Insufficient access rights [case status workflow].');
                }
            }

            $booCaseTemplateNeedsIA             = !is_null($this->params()->fromPost('case_template_needs_ia'));
            $booCaseTemplateEmployerSponsorship = !is_null($this->params()->fromPost('case_template_employer_sponsorship'));
            $booCaseTemplateHidden              = !is_null($this->params()->fromPost('case_template_hidden'));

            $currentCompanyId  = $this->_auth->getCurrentUserCompanyId();
            $booDefaultCompany = $currentCompanyId == $this->_company->getDefaultCompanyId();

            if ($booDefaultCompany) {
                $booCaseTemplateHiddenForCompany = null;
            } else {
                $booCaseTemplateHiddenForCompany = !is_null($this->params()->fromPost('case_template_hidden_for_company'));
            }

            $companyVisibleCaseTemplatesCount = $this->_clients->getCaseTemplates()->getCompanyVisibleCaseTemplatesCount($currentCompanyId);
            if (empty($strError) && ($companyVisibleCaseTemplatesCount < 1) && ($booCaseTemplateHidden || $booCaseTemplateHiddenForCompany === true)) {
                $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                $strError     = $this->_tr->translate('At least one ' . $caseTypeTerm . ' must be visible.');
            }

            $arrTemplateTypes = $this->params()->fromPost('case_template_type');
            if (empty($strError) && !is_array($arrTemplateTypes) || !count($arrTemplateTypes)) {
                $strError = $this->_tr->translate('Please select template type.');
            }

            if (empty($strError)) {
                $arrIds = $this->_clients->getCaseTemplates()->getAllTemplatesTypes(true);
                foreach ($arrTemplateTypes as $templateType) {
                    if (!in_array($templateType, $arrIds)) {
                        $strError = $this->_tr->translate('Incorrect template type.');
                        break;
                    }
                }
            }

            $templateCaseReferenceAs = trim($filter->filter($this->params()->fromPost('case_template_case_reference_as', '')));
            if (empty($strError)) {
                $individualTypeId = $this->_clients->getMemberTypeIdByName('individual');
                $employerTypeId   = $this->_clients->getMemberTypeIdByName('employer');

                if (in_array($employerTypeId, $arrTemplateTypes) && !in_array($individualTypeId, $arrTemplateTypes)) {
                    // This field is required for Emploer type only
                    if (!mb_strlen($templateCaseReferenceAs)) {
                        $strError = $this->_tr->translate('Incorrect "Case Referenced as".');
                    }
                } else {
                    $templateCaseReferenceAs = '';
                }
            }

            $arrAssignedCategories = [];
            if (empty($strError)) {
                $arrAssignedCategories = Json::decode($this->params()->fromPost('case_template_categories'));
                foreach ($arrAssignedCategories as $key => $arrCategoryInfo) {
                    $arrCategoryInfo = (array)$arrCategoryInfo;
                    if (!mb_strlen($arrCategoryInfo['client_category_name'])) {
                        $strError = $this->_tr->translate('Category name is a required field.');
                    }

                    if (empty($strError) && !$this->_caseStatuses->hasAccessToCaseStatusList($arrCategoryInfo['client_category_assigned_list_id'], true)) {
                        $strError = $this->_tr->translate("Insufficient access rights [category's case status workflow].");
                    }

                    if (empty($strError) && !in_array($arrCategoryInfo['client_category_link_to_employer'], ['Y', 'N'])) {
                        $strError = $this->_tr->translate('Category Link to Employer is a required field.');
                    }

                    if (!empty($strError)) {
                        break;
                    }

                    $arrAssignedCategories[$key] = $arrCategoryInfo;
                }
            }

            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->beginTransaction();
                $booTransactionStarted = true;

                list($templateId, $arrGroupsMapping) = $this->_clients->getCaseTemplates()->addTemplate(
                    $currentCompanyId,
                    $templateName,
                    $templateCopyId,
                    $templateFormVersionIds,
                    $templateEmailTemplateId,
                    $templateCaseStatusListId,
                    $templateCaseReferenceAs,
                    $booCaseTemplateNeedsIA,
                    $booCaseTemplateEmployerSponsorship,
                    $arrTemplateTypes,
                    $booDefaultCompany ? null : $templateCopyId,
                    $booCaseTemplateHidden,
                    $booCaseTemplateHiddenForCompany
                );

                if (empty($templateId)) {
                    $strError = $this->_tr->translate('Template was not created.');
                }

                if (empty($strError) && empty($templateCopyId)) {
                    $topGroupId = $this->_fields->createGroup($currentCompanyId, 'Top group', 3, $templateId, false, false);
                    if (empty($topGroupId)) {
                        $strError = $this->_tr->translate('Top Group was not created.');
                    }

                    if (empty($strError)) {
                        // Find and place the "Case Type" field in this top group
                        $caseTypeFieldInfo = $this->_fields->getCompanyFieldInfoByUniqueFieldId('case_type', $currentCompanyId);
                        if (!empty($caseTypeFieldInfo)) {
                            $this->_fields->placeFieldInGroup($topGroupId, $caseTypeFieldInfo['field_id'], false, 0);
                            $this->_fields->updateGroupAccessFromFields($currentCompanyId, [$topGroupId]);
                        }
                    }

                    if (empty($strError)) {
                        $unassignedGroupId = $this->_fields->createGroup($currentCompanyId, 'Not Assigned', 3, $templateId, true, true, 'U');

                        if (empty($unassignedGroupId)) {
                            $strError = $this->_tr->translate('Not Assigned Group was not created.');
                        }
                    }
                }

                $arrCreatedCategories = [];
                if (empty($strError)) {
                    foreach ($arrAssignedCategories as $order => $arrCategoryInfo) {
                        // Create/update case category details
                        $arrCaseCategoryInfo = [
                            'company_id'                       => $currentCompanyId,
                            'client_type_id'                   => $templateId,
                            'client_category_id'               => $arrCategoryInfo['client_category_id'],
                            'client_status_list_id'            => $arrCategoryInfo['client_category_assigned_list_id'],
                            'client_category_parent_id'        => null,
                            'client_category_name'             => $arrCategoryInfo['client_category_name'],
                            'client_category_abbreviation'     => $arrCategoryInfo['client_category_abbreviation'],
                            'client_category_link_to_employer' => $arrCategoryInfo['client_category_link_to_employer'],
                            'client_category_order'            => $order,
                        ];

                        $arrCaseCategoryInfo['client_category_created_id'] = $this->_caseCategories->saveCompanyCaseCategory(true, $arrCaseCategoryInfo);

                        $arrCreatedCategories[] = $arrCaseCategoryInfo;
                    }
                }

                // Automatically create copies to all companies if this is a default Immigration Program
                if (empty($strError) && $booDefaultCompany) {
                    $arrAllCompaniesIds = $this->_company->getAllCompanies(true);

                    foreach ($arrAllCompaniesIds as $companyId) {
                        $companyCaseStatusListId = $this->_caseStatuses->getCompanyCaseStatusListIdByCompanyAndParentListId($companyId, $templateCaseStatusListId);

                        list($companyTemplateId,) = $this->_clients->getCaseTemplates()->addTemplate(
                            $companyId,
                            $templateName,
                            $templateId,
                            $templateFormVersionIds,
                            0, // don't use the email template
                            $companyCaseStatusListId,
                            $templateCaseReferenceAs,
                            $booCaseTemplateNeedsIA,
                            $booCaseTemplateEmployerSponsorship,
                            $arrTemplateTypes,
                            $templateId,
                            $booCaseTemplateHidden,
                            $booCaseTemplateHiddenForCompany,
                            true
                        );

                        if (!empty($companyTemplateId) && !empty($arrCreatedCategories)) {
                            foreach ($arrCreatedCategories as $arrCreatedUpdatedCategoryInfo) {
                                $arrCreatedUpdatedCategoryInfo['company_id']                = $companyId;
                                $arrCreatedUpdatedCategoryInfo['client_type_id']            = $companyTemplateId;
                                $arrCreatedUpdatedCategoryInfo['client_status_list_id']     = $this->_caseStatuses->getCompanyCaseStatusListIdByCompanyAndParentListId($companyId, $arrCreatedUpdatedCategoryInfo['client_status_list_id']);
                                $arrCreatedUpdatedCategoryInfo['client_category_parent_id'] = $arrCreatedUpdatedCategoryInfo['client_category_created_id'];
                                unset($arrCreatedUpdatedCategoryInfo['client_category_created_id']);

                                $this->_caseCategories->saveCompanyCaseCategory(true, $arrCreatedUpdatedCategoryInfo);
                            }
                        }
                    }
                }
            }

            if (empty($strError) && $booTransactionStarted) {
                $this->_db2->getDriver()->getConnection()->commit();
                $booTransactionStarted = false;
            }

            if (empty($strError) && !empty($templateCopyId)) {
                // Create a copy of fields/groups conditions
                $arrSavedConditions = $this->_conditionalFields->getGroupedConditionalFields($templateCopyId);

                $arrCachedFieldsInfo = [];
                foreach ($arrSavedConditions as $arrSavedConditionRecords) {
                    foreach ($arrSavedConditionRecords as $fieldId => $arrFieldConditions) {
                        // Load only once for the same field
                        if (!isset($arrCachedFieldsInfo[$fieldId])) {
                            $arrCachedFieldsInfo[$fieldId] = $this->_fields->getFieldInfo($fieldId, $currentCompanyId, $templateCopyId);
                        }
                        $arrFieldInfo = $arrCachedFieldsInfo[$fieldId];

                        $arrGroupedChanges = [];
                        foreach ($arrFieldConditions as $fieldOptionValue => $arrHiddenGroupsFields) {
                            // Find the label for this option
                            $fieldOptionLabel = '';
                            if (isset($arrFieldInfo['default_val'])) {
                                foreach ($arrFieldInfo['default_val'] as $arrOptionInfo) {
                                    if ($arrOptionInfo[0] == $fieldOptionValue) {
                                        $fieldOptionLabel = $arrOptionInfo[1];
                                        break;
                                    }
                                }
                            }

                            // Use just created groups
                            $arrHideGroups = [];
                            if (isset($arrHiddenGroupsFields['hide_groups']) && !empty($arrGroupsMapping)) {
                                foreach ($arrHiddenGroupsFields['hide_groups'] as $hideGroupId) {
                                    if (isset($arrGroupsMapping[$hideGroupId])) {
                                        $arrHideGroups[] = $arrGroupsMapping[$hideGroupId];
                                    }
                                }
                            }

                            // Collect all these conditions for this field
                            $arrGroupedChanges[] = [
                                'condition_id'       => 0,
                                'field_option_value' => $fieldOptionValue,
                                'field_option_label' => $fieldOptionLabel,
                                'hidden_groups'      => $arrHideGroups,
                                'hidden_fields'      => $arrHiddenGroupsFields['hide_fields'] ?? [],
                            ];
                        }

                        if (!empty($arrGroupedChanges)) {
                            $this->_conditionalFields->saveConditionsInBatch(
                                $this->_company,
                                $this->_clients,
                                $this->_fields,
                                $currentCompanyId,
                                $templateId,
                                $arrFieldInfo,
                                $arrGroupedChanges
                            );
                        }
                    }
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
            'success'          => empty($strError),
            'case_template_id' => $templateId,
            'msg'              => $strError
        );

        return new JsonModel($arrResult);
    }

    public function updateCasesTemplateAction()
    {
        set_time_limit(10 * 60); // 10 min
        ini_set('memory_limit', '-1');

        $strError              = '';
        $booTransactionStarted = false;

        try {
            $filter              = new StripTags();
            $currentCompanyId    = $this->_auth->getCurrentUserCompanyId();
            $defaultCompanyId    = $this->_company->getDefaultCompanyId();
            $booIsDefaultCompany = $currentCompanyId == $defaultCompanyId;

            $templateId = (int)Json::decode($this->params()->fromPost('case_template_id'), Json::TYPE_ARRAY);
            if (!$this->_clients->getCaseTemplates()->hasAccessToTemplate($templateId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $booCanUpdateMainSettings = false;
            if (empty($strError)) {
                // A default/custom type
                if ($booIsDefaultCompany) {
                    $booCanUpdateMainSettings = true;
                } else {
                    $arrCaseTypeInfo          = $this->_clients->getCaseTemplates()->getTemplateInfo($templateId);
                    $arrDefaultTemplates      = $this->_clients->getCaseTemplates()->getTemplates($defaultCompanyId, true);
                    $booCanUpdateMainSettings = !in_array($arrCaseTypeInfo['parent_client_type_id'], $arrDefaultTemplates);
                }
            }


            $templateName = null;
            if (empty($strError) && $booCanUpdateMainSettings) {
                $templateName = trim($filter->filter($this->params()->fromPost('case_template_name', '')));
                if ($templateName == '') {
                    $strError = $this->_tr->translate('Incorrect template name.');
                }
            }

            $arrTemplateTypes        = null;
            $templateCaseReferenceAs = null;
            if (empty($strError) && $booCanUpdateMainSettings) {
                $arrTemplateTypes = $this->params()->fromPost('case_template_type');
                if (!is_array($arrTemplateTypes) || !count($arrTemplateTypes)) {
                    $strError = $this->_tr->translate('Please select a template type.');
                }

                if (empty($strError)) {
                    $arrIds = $this->_clients->getCaseTemplates()->getAllTemplatesTypes(true);
                    foreach ($arrTemplateTypes as $templateType) {
                        if (!in_array($templateType, $arrIds)) {
                            $strError = $this->_tr->translate('Incorrect template type.');
                            break;
                        }
                    }
                }

                if (empty($strError)) {
                    $individualTypeId        = $this->_clients->getMemberTypeIdByName('individual');
                    $employerTypeId          = $this->_clients->getMemberTypeIdByName('employer');
                    $templateCaseReferenceAs = trim($filter->filter($this->params()->fromPost('case_template_case_reference_as', '')));

                    if (in_array($employerTypeId, $arrTemplateTypes) && !in_array($individualTypeId, $arrTemplateTypes)) {
                        // This field is required for Emploer type only
                        if (!mb_strlen($templateCaseReferenceAs)) {
                            $strError = $this->_tr->translate('Incorrect "Case Referenced as".');
                        }
                    } else {
                        $templateCaseReferenceAs = '';
                    }
                }
            }


            $templateFormVersionIds = null;
            if (empty($strError) && $booCanUpdateMainSettings) {
                $templateFormVersionId  = $this->params()->fromPost('case_form_version_id', '');
                $templateFormVersionIds = explode(";", $templateFormVersionId);
                foreach ($templateFormVersionIds as $templateFormVersionId) {
                    if (empty($strError) && !empty($templateFormVersionId) && !$this->_forms->getFormVersion()->formVersionExists($templateFormVersionId)) {
                        $strError = $this->_tr->translate('Insufficient access rights [form].');
                        break;
                    }
                }
            }

            $templateEmailTemplateId = $this->params()->fromPost('case_email_template_id');
            if (empty($strError) && !empty($templateEmailTemplateId) && !$this->_templates->hasAccessToTemplate($templateEmailTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights [email template].');
            }

            $templateCaseStatusListId = null;
            if (empty($strError) && $booCanUpdateMainSettings) {
                $templateCaseStatusListId = $this->params()->fromPost('case_template_client_status_list_id');
                if (empty($templateCaseStatusListId)) {
                    $strError = $this->_tr->translate(
                        sprintf(
                            'Default %s List is a required field.',
                            $this->_company->getCurrentCompanyDefaultLabel('case_status')
                        )
                    );
                } elseif (!$this->_caseStatuses->hasAccessToCaseStatusList($templateCaseStatusListId, true)) {
                    $strError = $this->_tr->translate('Insufficient access rights [case status workflow].');
                }
            }

            if ($booIsDefaultCompany) {
                $booCaseTemplateHiddenForCompany = null;
            } else {
                $booCaseTemplateHiddenForCompany = !is_null($this->params()->fromPost('case_template_hidden_for_company'));
            }

            $booCaseTemplateNeedsIA             = null;
            $booCaseTemplateEmployerSponsorship = null;
            $booCaseTemplateHidden              = null;
            if (empty($strError) && $booCanUpdateMainSettings) {
                $booCaseTemplateNeedsIA             = !is_null($this->params()->fromPost('case_template_needs_ia'));
                $booCaseTemplateEmployerSponsorship = !is_null($this->params()->fromPost('case_template_employer_sponsorship'));
                $booCaseTemplateHidden              = !is_null($this->params()->fromPost('case_template_hidden'));

                $companyVisibleCaseTemplatesCount = $this->_clients->getCaseTemplates()->getCompanyVisibleCaseTemplatesCount($currentCompanyId);
                if (($companyVisibleCaseTemplatesCount <= 1) && ($booCaseTemplateHidden || $booCaseTemplateHiddenForCompany === true)) {
                    $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                    $strError     = $this->_tr->translate('At least one ' . $caseTypeTerm . ' must be visible.');
                }
            }

            $arrAssignedCategories = [];
            if (empty($strError) && $booCanUpdateMainSettings) {
                $arrAssignedCategories = Json::decode($this->params()->fromPost('case_template_categories'));
                foreach ($arrAssignedCategories as $key => $arrCategoryInfo) {
                    $arrCategoryInfo = (array)$arrCategoryInfo;
                    if (!empty($arrCategoryInfo['client_category_id']) && !$this->_caseCategories->hasAccessToCaseCategory($arrCategoryInfo['client_category_id'], true)) {
                        $strError = $this->_tr->translate('Insufficient access rights [case category].');
                    }

                    if (empty($strError) && !mb_strlen($arrCategoryInfo['client_category_name'])) {
                        $strError = $this->_tr->translate('Category name is a required field.');
                    }

                    if (empty($strError) && !$this->_caseStatuses->hasAccessToCaseStatusList($arrCategoryInfo['client_category_assigned_list_id'], true)) {
                        $strError = $this->_tr->translate("Insufficient access rights [category's case status workflow].");
                    }

                    if (empty($strError) && !in_array($arrCategoryInfo['client_category_link_to_employer'], ['Y', 'N'])) {
                        $strError = $this->_tr->translate('Category Link to Employer is a required field.');
                    }

                    if (!empty($strError)) {
                        break;
                    }

                    $arrAssignedCategories[$key] = $arrCategoryInfo;
                }
            }

            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->beginTransaction();
                $booTransactionStarted = true;

                if (!$this->_clients->getCaseTemplates()->updateTemplate(
                    $templateId,
                    $templateName,
                    $templateFormVersionIds,
                    $templateEmailTemplateId,
                    $templateCaseStatusListId,
                    $templateCaseReferenceAs,
                    $booCaseTemplateNeedsIA,
                    $booCaseTemplateEmployerSponsorship,
                    $arrTemplateTypes,
                    $booCaseTemplateHidden,
                    $booCaseTemplateHiddenForCompany
                )) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }


            $arrAssignedCategoriesIds           = [];
            $arrCreatedUpdatedCategories        = [];
            $arrUpdatedCategoriesWithSimpleInfo = [];
            if (empty($strError) && $booCanUpdateMainSettings) {
                foreach ($arrAssignedCategories as $order => $arrCategoryInfo) {
                    $booUpdateCaseCategory    = true;
                    $booNewCaseCategory       = empty($arrCategoryInfo['client_category_id']);
                    $clientStatusCustomListId = null;
                    if (!$booNewCaseCategory) {
                        $arrSavedCategoryInfo = $this->_caseCategories->getCompanyCaseCategoryInfo($arrCategoryInfo['client_category_id']);

                        if (!$booIsDefaultCompany) {
                            $clientStatusCustomListId = $arrSavedCategoryInfo['client_status_custom_list_id'];
                            if (empty($clientStatusCustomListId)) {
                                $booStatusListChanged = $arrSavedCategoryInfo['client_status_list_id'] != $arrCategoryInfo['client_category_assigned_list_id'];
                            } else {
                                $booStatusListChanged = $arrSavedCategoryInfo['client_status_custom_list_id'] != $arrCategoryInfo['client_category_assigned_list_id'];
                            }
                        } else {
                            $booStatusListChanged = $arrSavedCategoryInfo['client_status_list_id'] != $arrCategoryInfo['client_category_assigned_list_id'];
                        }

                        if ($arrSavedCategoryInfo['client_category_name'] == $arrCategoryInfo['client_category_name'] &&
                            $arrSavedCategoryInfo['client_category_abbreviation'] == $arrCategoryInfo['client_category_abbreviation'] &&
                            $arrSavedCategoryInfo['client_category_link_to_employer'] == $arrCategoryInfo['client_category_link_to_employer'] &&
                            !$booStatusListChanged &&
                            $arrSavedCategoryInfo['client_category_order'] == $order) {
                            // If nothing was changed - don't update case category details
                            $booUpdateCaseCategory = false;
                        }

                        $booCaseCategoryListChanged = $arrSavedCategoryInfo['client_status_list_id'] != $arrCategoryInfo['client_category_assigned_list_id'];
                    } else {
                        $booCaseCategoryListChanged = true;
                    }

                    if ($booUpdateCaseCategory) {
                        // Create/update case category details
                        $arrCaseCategoryInfo = [
                            'company_id'                       => $currentCompanyId,
                            'client_type_id'                   => $templateId,
                            'client_category_id'               => $arrCategoryInfo['client_category_id'],
                            'client_status_list_id'            => $arrCategoryInfo['client_category_assigned_list_id'],
                            'client_category_parent_id'        => null,
                            'client_category_name'             => $arrCategoryInfo['client_category_name'],
                            'client_category_abbreviation'     => $arrCategoryInfo['client_category_abbreviation'],
                            'client_category_link_to_employer' => $arrCategoryInfo['client_category_link_to_employer'],
                            'client_category_order'            => $order,
                        ];

                        if (!$booIsDefaultCompany) {
                            if ($clientStatusCustomListId == $arrCategoryInfo['client_category_assigned_list_id']) {
                                // Don't try to update the status list - it is a custom one and wasn't changed
                                unset($arrCaseCategoryInfo['client_status_list_id']);
                            } else {
                                // A custom list we need to reset
                                $arrCaseCategoryInfo['client_status_custom_list_id'] = null;
                            }
                        }

                        $arrCaseCategoryInfo['client_category_created_id'] = $this->_caseCategories->saveCompanyCaseCategory($booNewCaseCategory, $arrCaseCategoryInfo);

                        if ($booCaseCategoryListChanged) {
                            // We'll create/update this info for each case template / category
                            $arrCreatedUpdatedCategories[] = $arrCaseCategoryInfo;
                        } else {
                            // We'll update this info with 1 query only
                            $arrUpdatedCategoriesWithSimpleInfo[] = $arrCaseCategoryInfo;
                        }

                        $arrAssignedCategoriesIds[] = $arrCaseCategoryInfo['client_category_created_id'];
                    } else {
                        $arrAssignedCategoriesIds[] = $arrCategoryInfo['client_category_id'];
                    }
                }

                // Check which case categories were deleted
                $arrDeletedCaseCategoryIds = [];
                $arrSavedCompanyCategories = $this->_caseCategories->getCompanyCaseCategories($currentCompanyId);
                foreach ($arrSavedCompanyCategories as $arrSavedCompanyCategoryInfo) {
                    if ($arrSavedCompanyCategoryInfo['client_type_id'] == $templateId && !in_array($arrSavedCompanyCategoryInfo['client_category_id'], $arrAssignedCategoriesIds)) {
                        $arrDeletedCaseCategoryIds[] = $arrSavedCompanyCategoryInfo['client_category_id'];
                    }
                }

                if (!empty($arrDeletedCaseCategoryIds)) {
                    // Temporary disabled, can be enabled if needed (+ we need to check if deleted categories are used/saved)
                    $strError = $this->_tr->translate('Temporary disabled.');
                    // $this->_caseCategories->deleteCaseCategories($arrDeletedCaseCategoryIds);
                }
            }

            // Automatically create copies to all companies if this is a default Immigration Program
            if (empty($strError) && $booIsDefaultCompany) {
                $arrCaseTemplates = $this->_clients->getCaseTemplates()->getCaseTemplatesByParentId($templateId);

                $arrGroupedListsIds = [];
                foreach ($arrCaseTemplates as $arrTemplateInfo) {
                    // Load id only once for each company
                    if (!isset($arrGroupedListsIds[$arrTemplateInfo['company_id']])) {
                        $arrGroupedListsIds[$arrTemplateInfo['company_id']] = $this->_caseStatuses->getCompanyCaseStatusListIdByCompanyAndParentListId($arrTemplateInfo['company_id'], $templateCaseStatusListId);
                    }

                    $this->_clients->getCaseTemplates()->updateTemplate(
                        $arrTemplateInfo['client_type_id'],
                        $templateName,
                        $templateFormVersionIds,
                        null, // don't udpate the template
                        $arrGroupedListsIds[$arrTemplateInfo['company_id']],
                        $templateCaseReferenceAs,
                        $booCaseTemplateNeedsIA,
                        $booCaseTemplateEmployerSponsorship,
                        $arrTemplateTypes,
                        $booCaseTemplateHidden,
                        $booCaseTemplateHiddenForCompany
                    );

                    if (!empty($arrCreatedUpdatedCategories) && $booCanUpdateMainSettings) {
                        foreach ($arrCreatedUpdatedCategories as $arrCreatedUpdatedCategoryInfo) {
                            $booNewCaseCategory = empty($arrCreatedUpdatedCategoryInfo['client_category_id']);
                            if ($booNewCaseCategory) {
                                $caseCategoryId = $arrCreatedUpdatedCategoryInfo['client_category_created_id'];
                                unset($arrCreatedUpdatedCategoryInfo['client_category_created_id']);
                            } else {
                                $caseCategoryId = $arrCreatedUpdatedCategoryInfo['client_category_id'];
                            }

                            $arrCreatedUpdatedCategoryInfo['company_id']                = $arrTemplateInfo['company_id'];
                            $arrCreatedUpdatedCategoryInfo['client_type_id']            = $arrTemplateInfo['client_type_id'];
                            $arrCreatedUpdatedCategoryInfo['client_status_list_id']     = $this->_caseStatuses->getCompanyCaseStatusListIdByCompanyAndParentListId($arrTemplateInfo['company_id'], $arrCreatedUpdatedCategoryInfo['client_status_list_id']);
                            $arrCreatedUpdatedCategoryInfo['client_category_parent_id'] = $caseCategoryId;

                            $this->_caseCategories->saveCompanyCaseCategory($booNewCaseCategory, $arrCreatedUpdatedCategoryInfo);
                        }
                    }
                }

                // Update simple info for case category with 1 query
                if (!empty($arrUpdatedCategoriesWithSimpleInfo) && $booCanUpdateMainSettings) {
                    foreach ($arrUpdatedCategoriesWithSimpleInfo as $arrUpdatedCategoryWithSimpleInfo) {
                        $arrUpdateData = [
                            'client_category_name'             => $arrUpdatedCategoryWithSimpleInfo['client_category_name'],
                            'client_category_abbreviation'     => $arrUpdatedCategoryWithSimpleInfo['client_category_abbreviation'],
                            'client_category_link_to_employer' => $arrUpdatedCategoryWithSimpleInfo['client_category_link_to_employer'],
                            'client_category_order'            => $arrUpdatedCategoryWithSimpleInfo['client_category_order']
                        ];

                        $this->_caseCategories->updateCategoryByParentId($arrUpdatedCategoryWithSimpleInfo['client_category_id'], $arrUpdateData);
                    }
                }
            }

            if (empty($strError) && $booTransactionStarted) {
                $this->_db2->getDriver()->getConnection()->commit();
                $booTransactionStarted = false;
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

    public function deleteCasesTemplateAction()
    {
        $strError = '';

        try {
            $templateId = (int)Json::decode($this->params()->fromPost('case_template_id'), Json::TYPE_ARRAY);
            if (!$this->_clients->getCaseTemplates()->hasAccessToTemplate($templateId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrClients = $this->_clients->getClientsByCaseTemplateId($templateId);
                if (is_array($arrClients) && count($arrClients)) {
                    $strError = $this->_tr->translate('There is assigned at least one client to this template.');
                }
            }

            $companyId = $this->_auth->getCurrentUserCompanyId();
            if (empty($strError)) {
                $arrCompanyTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId);

                if (count($arrCompanyTemplates) <= 1) {
                    $strError = $this->_tr->translate('It is not possible to delete all templates.');
                }
            }

            if (empty($strError)) {
                $strError = $this->_tr->translate('Temporary disabled.');
            }

            if (empty($strError)) {
                // Delete all groups (and related info) assigned to these templates
                $arrGroupIds = array();
                $arrGroups   = $this->_fields->getCompanyGroups($companyId, false, $templateId);
                foreach ($arrGroups as $arrGroupInfo) {
                    $arrGroupIds[] = $arrGroupInfo['group_id'];
                }

                if ($this->_fields->deleteGroup($arrGroupIds)) {
                    if (!$this->_clients->getCaseTemplates()->deleteTemplate($templateId)) {
                        $strError = $this->_tr->translate('Failed to delete the record.');
                    }
                } else {
                    $strError = $this->_tr->translate('Groups were not deleted.');
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
        return new JsonModel($arrResult);
    }

    public function manageOptionsAction()
    {
        $strError       = '';
        $booRefreshPage = false;

        try {
            $fieldId    = (int)Json::decode($this->params()->fromPost('field_id'), Json::TYPE_ARRAY);
            $arrOptions = Json::decode($this->params()->fromPost('options_list'), Json::TYPE_ARRAY);

            if (!$this->_fields->hasCurrentMemberAccessToFieldById($fieldId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrFieldInfo = $this->_fields->getFieldInfoById($fieldId);
                if ($arrFieldInfo['can_edit_in_gui'] !== 'Y') {
                    $strError = $this->_tr->translate('Field does not allow options changing.');
                }
            }

            $arrFormattedOptions = [];
            if (empty($strError)) {
                $arrSavedOptionsIds = [];

                $arrSavedOptions = $this->_fields->getFieldOptions($fieldId);
                foreach ($arrSavedOptions as $arrSavedOptionInfo) {
                    $arrSavedOptionsIds[] = $arrSavedOptionInfo['option_id'];
                }

                foreach ($arrOptions as $arrOptionInfo) {
                    if (!empty($arrOptionInfo['option_id']) && !in_array($arrOptionInfo['option_id'], $arrSavedOptionsIds)) {
                        $strError = $this->_tr->translate('Insufficient access rights to the option');
                        break;
                    } else {
                        $arrFormattedOptions[] = [
                            'id'    => $arrOptionInfo['option_id'],
                            'name'  => $arrOptionInfo['option_name'],
                            'order' => $arrOptionInfo['option_order'],
                        ];
                    }
                }
            }

            if (empty($strError)) {
                $booRefreshPage = $this->_fields->updateFieldDefaultOptions(false, $fieldId, $arrFormattedOptions);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? '' : $strError,
            'refresh' => $booRefreshPage,
        );

        return new JsonModel($arrResult);
    }
}
