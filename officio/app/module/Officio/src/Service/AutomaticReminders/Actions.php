<?php

namespace Officio\Service\AutomaticReminders;

use Clients\Service\Clients;
use Clients\Service\Members;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Json;
use Mailer\Service\Mailer;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Settings;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\SystemTriggers;
use Officio\Service\Users;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;
use Tasks\Service\Tasks;
use Templates\Service\Templates;
use Laminas\Validator\EmailAddress;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Actions extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Members */
    protected $_members;

    /** @var Clients */
    protected $_clients;

    /** @var Users */
    protected $_users;

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var Files */
    protected $_files;

    /** @var Mailer */
    protected $_mailer;

    /** @var AutomaticReminders */
    private $_parent;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var Tasks */
    protected $_tasks;

    public function initAdditionalServices(array $services)
    {
        $this->_company  = $services[Company::class];
        $this->_members  = $services[Members::class];
        $this->_clients  = $services[Clients::class];
        $this->_users    = $services[Users::class];
        $this->_country  = $services[Country::class];
        $this->_files    = $services[Files::class];
        $this->_mailer   = $services[Mailer::class];
        $this->_triggers = $services[SystemTriggers::class];
        $this->_tasks    = $services[Tasks::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Check if current user has access to specific reminder action
     *
     * @param int $reminderActionId
     * @return bool true if user has access
     */
    public function hasAccessToAction($reminderActionId)
    {
        $booHasAccess = false;
        try {
            $arrActionInfo = $this->getAction(0, 0, $reminderActionId);
            if ($this->_auth->isCurrentUserSuperadmin() || (isset($arrActionInfo['company_id']) && $arrActionInfo['company_id'] == $this->_auth->getCurrentUserCompanyId())) {
                $booHasAccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Load saved information of the action
     *
     * @param $companyId
     * @param int $reminderId
     * @param int $actionId
     * @return array
     */
    public function getAction($companyId, $reminderId, $actionId)
    {
        $arrWhere = [];

        $arrWhere['automatic_reminder_action_id'] = (int)$actionId;

        if (!empty($companyId)) {
            $arrWhere['company_id'] = (int)$companyId;
        }

        if (!empty($reminderId)) {
            $arrWhere['automatic_reminder_id'] = (int)$reminderId;
        }

        $select = (new Select())
            ->from('automatic_reminder_actions')
            ->where($arrWhere);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load list of saved actions for specific company
     *
     * @param $companyId
     * @param int $actionType
     * @return array
     */
    public function getCompanyActions($companyId, $actionType = 0)
    {
        $arrWhere = [];

        $arrWhere['company_id'] = (int)$companyId;

        if (!empty($actionType)) {
            $arrWhere['automatic_reminder_action_type_id'] = (int)$actionType;
        }

        $select = (new Select())
            ->from('automatic_reminder_actions')
            ->where($arrWhere);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load detailed info (with additional fields) about automatic reminder
     *
     * @param $companyId
     * @param int $reminderId
     * @param int $actionId
     * @return array
     */
    public function getDetailedActionInfo($companyId, $reminderId, $actionId)
    {
        try {
            $arrSavedActionInfo = empty($actionId) ? array() : $this->getAction($companyId, $reminderId, $actionId);
            $arrActionSettings  = isset($arrSavedActionInfo['automatic_reminder_action_settings']) ? Json::decode($arrSavedActionInfo['automatic_reminder_action_settings'], Json::TYPE_ARRAY) : array();

            /** @var Templates $templates */
            $templates = $this->_serviceContainer->get(Templates::class);
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $arrTemplates = $templates->getTemplates(
                    (int)$this->_files->getFolders()->getDefaultSharedFolderId(),
                    $this->_auth->getCurrentUserId()
                );
                foreach ($arrTemplates as $key => $template) {
                    $arrTemplates[$key]['templateId']   = $template['template_id'];
                    $arrTemplates[$key]['templateName'] = $template['name'];
                }
            } else {
                $arrTemplates = $templates->getTemplatesList(true, 0, false, 'Email');
            }

            // If template was assigned by other admin user (and we don't have access to it)
            // return this template too, so it will be selected in the combo
            if (!empty($arrActionSettings['template_id'])) {
                $booLoadTemplateAdditionally = true;
                foreach ($arrTemplates as $arrTemplateInfo) {
                    if ($arrTemplateInfo['templateId'] == $arrActionSettings['template_id']) {
                        $booLoadTemplateAdditionally = false;
                        break;
                    }
                }

                if ($booLoadTemplateAdditionally) {
                    $arrTemplateInfo = $templates->getTemplate($arrActionSettings['template_id']);
                    if (isset($arrTemplateInfo['template_id'])) {
                        $arrTemplates[] = array(
                            'templateId'   => $arrTemplateInfo['template_id'],
                            'templateName' => $arrTemplateInfo['name'],
                        );
                    }
                }
            }

            $arrMemberTypes    = array();
            $arrSupportedTypes = $this->_clients->getApplicantFields()->getAdvancedSearchTypesList();
            foreach ($arrSupportedTypes as $arrSupportedTypeInfo) {
                if ($arrSupportedTypeInfo['search_for_id'] != 'accounting') {
                    $arrMemberTypes[] = $arrSupportedTypeInfo;
                }
            }

            $actionInfo = array(
                'action_info'     => array(),
                'action_types'    => $this->getActionTypes(),
                'email_templates' => $arrTemplates,
                'member_types'    => $arrMemberTypes,
                'task_subjects'   => $this->getActionTasksSubjects($companyId),
                'assign_to_list'  => $this->_users->getAssignList('reminder'),
            );

            if (!empty($actionId) && isset($arrSavedActionInfo['automatic_reminder_action_id'])) {
                $actionInfo['action_info']['action_type']     = $arrSavedActionInfo['automatic_reminder_action_type_id'];
                $actionInfo['action_info']['action_settings'] = $arrActionSettings;
            }
        } catch (Exception $e) {
            $actionInfo = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $actionInfo;
    }

    /**
     * Load reminders list for current user's company
     *
     * @param $companyId
     * @return array
     */
    public function getActionTasksSubjects($companyId)
    {
        $select = (new Select())
            ->from('automatic_reminder_actions')
            ->columns(['automatic_reminder_action_settings'])
            ->where([
                'company_id'                        => (int)$companyId,
                'automatic_reminder_action_type_id' => 2
            ]);

        $arrActionsSettings = $this->_db2->fetchCol($select);

        $arrSubjects       = array();
        $arrUniqueSubjects = array();
        foreach ($arrActionsSettings as $strSettings) {
            $arrSettings = Json::decode($strSettings, Json::TYPE_ARRAY);
            if (isset($arrSettings['task_subject']) && !in_array($arrSettings['task_subject'], $arrUniqueSubjects)) {
                $arrSubjects[]       = array($arrSettings['task_subject']);
                $arrUniqueSubjects[] = $arrSettings['task_subject'];
            }
        }

        return $arrSubjects;
    }

    /**
     * Load saved information of the action
     *
     * @param $companyId
     * @param int $reminderId
     * @param array $actionIds
     * @param bool $booDeleteOldActions
     * @param bool $booLoadAdditionalData
     * @return array
     */
    public function getReminderActions($companyId, $reminderId, $actionIds = array(), $booDeleteOldActions = false, $booLoadAdditionalData = true)
    {
        try {
            if ($booDeleteOldActions) {
                $this->_db2->delete(
                    'automatic_reminder_actions',
                    [
                        (new Where())->isNull('automatic_reminder_id'),
                        'company_id' => (int)$companyId,
                        (new Where())->lessThan('automatic_reminder_action_create_date', date('Y-m-d')),
                    ]
                );
            }

            $arrWhere = [];

            $arrWhere['a.company_id'] = (int)$companyId;

            if (!empty($reminderId)) {
                $arrWhere['a.automatic_reminder_id'] = (int)$reminderId;
            }

            if (!empty($actionIds)) {
                $arrWhere['a.automatic_reminder_action_id'] = $actionIds;
            }

            $select = (new Select())
                ->from(array('a' => 'automatic_reminder_actions'))
                ->join(array('t' => 'automatic_reminder_action_types'), 't.automatic_reminder_action_type_id = a.automatic_reminder_action_type_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->where($arrWhere);

            $arrReminderActions = $this->_db2->fetchAll($select);

            if ($booLoadAdditionalData) {
                $officeLabel = null;
                $arrAssignTo = null;

                foreach ($arrReminderActions as $key => $reminderAction) {
                    $actionSettings = Json::decode($reminderAction['automatic_reminder_action_settings'], Json::TYPE_ARRAY);

                    switch ($reminderAction['automatic_reminder_action_type_internal_name']) {
                        case 'change_field_value':
                            $memberType   = '';
                            $booCaseField = false;

                            switch ($actionSettings['member_type']) {
                                case 'individual':
                                    $memberType = 'Individuals';
                                    break;

                                case 'employer':
                                    $memberType = 'Employers';
                                    break;

                                case 'case':
                                    $memberType   = 'Cases';
                                    $booCaseField = true;
                                    break;

                                default:
                                    break;
                            }

                            if ($booCaseField) {
                                $fieldName = $this->_clients->getFields()->getFieldName($actionSettings['field_id']);
                            } else {
                                $fieldName = $this->_clients->getApplicantFields()->getFieldName($actionSettings['field_id']);
                            }

                            $arrFieldInfo = $booCaseField ? $this->_clients->getFields()->getFieldInfoById($actionSettings['field_id']) : $this->_clients->getApplicantFields()->getFieldInfo($actionSettings['field_id'], $companyId);

                            if (array_key_exists('text', $actionSettings)) {
                                $arrFieldInfo['value'] = $actionSettings['text'];
                            } elseif (array_key_exists('date', $actionSettings)) {
                                $arrFieldInfo['value'] = $actionSettings['date'];
                            } elseif (array_key_exists('option', $actionSettings)) {
                                $arrFieldInfo['value'] = $actionSettings['option'];
                            }

                            if (!isset($arrFieldInfo['value'])) {
                                $fieldValue = 'UNKNOWN';
                            } else {
                                if (isset($arrFieldInfo['field_type_text_id'])) {
                                    $fieldType = $arrFieldInfo['field_type_text_id'];
                                } else {
                                    $fieldType = is_numeric($arrFieldInfo['type']) ? $this->_clients->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']) : $arrFieldInfo['type'];
                                }

                                if ($fieldType == 'checkbox') {
                                    $fieldValue = $arrFieldInfo['value'] == '1' ? 'Checked' : 'Not Checked';
                                } elseif ($fieldType == 'kskeydid' || $fieldType == 'bcpnp_nomination_certificate_number') {
                                    $fieldValue            = 'generate new';
                                    $strActionReadableName = $reminderAction['automatic_reminder_action_type_name'] . ' for the field ' . $fieldName . ' for ' . $memberType . ' - ' . $fieldValue;
                                    break;
                                } else {
                                    list($fieldValue,) = $this->_clients->getFields()->getFieldReadableValue(
                                        $companyId,
                                        $this->_auth->getCurrentUserDivisionGroupId(),
                                        $arrFieldInfo,
                                        $arrFieldInfo['value'],
                                        !$booCaseField,
                                        false
                                    );
                                }
                            }

                            $strActionReadableName = $reminderAction['automatic_reminder_action_type_name'] . ' for the field ' . $fieldName . ' for ' . $memberType . ' to ' . $fieldValue;
                            break;

                        case 'create_task':
                            $strActionReadableName = $reminderAction['automatic_reminder_action_type_name'] . ': ' . $actionSettings['task_subject'];
                            break;

                        case 'send_email':
                            /** @var Templates $templates */
                            $templates       = $this->_serviceContainer->get(Templates::class);
                            $arrTemplateInfo = $templates->getTemplate($actionSettings['template_id']);
                            $templateName    = isset($arrTemplateInfo['name']) ? $arrTemplateInfo['name'] : 'Unknown';

                            if (isset($actionSettings['to'])) {
                                switch ($actionSettings['to']) {
                                    case 'responsible_staff':
                                        if (is_null($officeLabel)) {
                                            // Load only once
                                            if ($this->_auth->isCurrentUserSuperadmin()) {
                                                $officeLabel = "Agent's Office";
                                            } else {
                                                $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');
                                            }
                                        }

                                        $to = $officeLabel . ' Responsible Staff';
                                        break;

                                    case 'employer':
                                        $to = 'Associated Employer';
                                        break;

                                    case 'client':
                                        $to = 'Client';
                                        break;

                                    default:
                                        $to = '';
                                        if (is_null($arrAssignTo)) {
                                            // Load only once
                                            $arrAssignTo = $this->_users->getAssignList('reminder');
                                        }

                                        foreach ($arrAssignTo as $arrAssignToRecord) {
                                            if ($arrAssignToRecord['assign_to_id'] == $actionSettings['to']) {
                                                $to = $arrAssignToRecord['assign_to_name'];
                                                break;
                                            }
                                        }

                                        if (empty($to)) {
                                            // CANNOT BE HERE
                                            $to = 'Unknown';
                                        }
                                        break;
                                }
                            } else {
                                $to = 'Client';
                            }

                            $strActionReadableName = $reminderAction['automatic_reminder_action_type_name'] . ' to ' . $to . ' using ' . $templateName;
                            break;

                        default:
                            $strActionReadableName = 'Unsupported';
                            break;
                    }

                    $arrReminderActions[$key]['action_text'] = $strActionReadableName;
                    $arrReminderActions[$key]['action_id']   = $reminderAction['automatic_reminder_action_id'];
                    $arrReminderActions[$key]['action_type'] = $reminderAction['automatic_reminder_action_type_name'];
                }
                unset($reminderAction);
            }
        } catch (Exception $e) {
            $arrReminderActions = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrReminderActions;
    }

    /**
     * Load list of actions in readable format for specific reminder
     *
     * @param $companyId
     * @param $reminderId
     * @return string
     */
    public function getReadableReminderActions($companyId, $reminderId)
    {
        $arrActions = $this->getReminderActions($companyId, $reminderId);

        if (empty($arrActions)) {
            $strReadableActions = '<span style="color: red;">There are no defined actions</span>';
        } else {
            $strReadableActions = '';
            foreach ($arrActions as $arrActionInfo) {
                if (!empty($strReadableActions)) {
                    $strReadableActions .= '<br/>';
                }
                $strReadableActions .= $arrActionInfo['action_text'];
            }
        }

        return $strReadableActions;
    }

    /**
     * Load list of action types
     *
     * @return array
     */
    public function getActionTypes()
    {
        $id = 'auto_reminder_action_types';
        if (!($data = $this->_cache->getItem($id))) {
            // Not in cache
            $select = (new Select())
                ->from('automatic_reminder_action_types')
                ->order('automatic_reminder_action_type_name');

            $data = $this->_db2->fetchAll($select);
            $this->_cache->setItem($id, $data);
        }

        return $data;
    }

    /**
     * Load action type id by its internal name
     *
     * @param string $actionTypeTextId
     * @return int action type id
     */
    public function getActionTypeIdByTextId($actionTypeTextId)
    {
        $actionTypeId   = 0;
        $arrActionTypes = $this->getActionTypes();

        foreach ($arrActionTypes as $arrActionTypeInfo) {
            if ($arrActionTypeInfo['automatic_reminder_action_type_internal_name'] == $actionTypeTextId) {
                $actionTypeId = $arrActionTypeInfo['automatic_reminder_action_type_id'];
                break;
            }
        }

        return $actionTypeId;
    }

    /**
     * Delete specific automatic reminder action(s) for specific company
     *
     * @param $companyId
     * @param int|array $actionId
     * @return int
     */
    public function delete($companyId, $actionId)
    {
        return $this->_db2->delete(
            'automatic_reminder_actions',
            [
                'company_id'                   => (int)$companyId,
                'automatic_reminder_action_id' => $actionId,
            ]
        );
    }


    /**
     * Delete reminder actions if they are assigned to specific client (e.g. create task for specific client)
     *
     * @param $memberId
     * @throws Exception
     */
    public function deleteClientActions($memberId)
    {
        if (!empty($memberId) && is_numeric($memberId)) {
            $arrMemberInfo = $this->_members->getMemberInfo($memberId);

            if (!empty($arrMemberInfo['company_id'])) {
                $companyId = $arrMemberInfo['company_id'];

                $arrCompanyActions  = $this->getCompanyActions($companyId, $this->getActionTypeIdByTextId('create_task'));
                $arrActionsToDelete = array();
                foreach ($arrCompanyActions as $arrCompanyActionInfo) {
                    $arrSettings = Json::decode($arrCompanyActionInfo['automatic_reminder_action_settings'], Json::TYPE_ARRAY);

                    if (isset($arrSettings['task_assign_to']) && $arrSettings['task_assign_to'] == $memberId) {
                        $arrActionsToDelete[] = $arrCompanyActionInfo['automatic_reminder_action_id'];
                    }
                }

                if (count($arrActionsToDelete)) {
                    $this->delete($companyId, $arrActionsToDelete);
                }
            }
        }
    }

    /**
     * Create/update + assign action to reminder
     *
     * @param $companyId
     * @param $reminderId
     * @param $actionId
     * @param $actionTypeId
     * @param $arrSettings
     * @return string
     */
    public function save($companyId, $reminderId, $actionId, $actionTypeId, $arrSettings)
    {
        try {
            $arrData = array(
                'automatic_reminder_action_type_id'  => $actionTypeId,
                'automatic_reminder_action_settings' => Json::encode($arrSettings)
            );

            if (empty($actionId)) {
                if (!empty($reminderId)) {
                    $arrData['automatic_reminder_id'] = $reminderId;
                }

                $arrData['company_id']                            = $companyId;
                $arrData['automatic_reminder_action_create_date'] = date('Y-m-d H:i:s');

                $actionId = $this->_db2->insert('automatic_reminder_actions', $arrData);
            } else {
                $this->_db2->update(
                    'automatic_reminder_actions',
                    $arrData,
                    [
                        'company_id'                   => (int)$companyId,
                        'automatic_reminder_id'        => (int)$reminderId,
                        'automatic_reminder_action_id' => (int)$actionId
                    ]
                );
            }
        } catch (Exception $e) {
            $actionId = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $actionId;
    }


    /**
     * Create default automatic reminder actions for specific company
     *
     * @param $fromCompanyId
     * @param int $toCompanyId
     * @param int $defaultReminderId
     * @param int $reminderId
     * @param $arrCompanyDefaultSettings
     * @throws Exception
     */
    public function createDefaultAutomaticReminderActions($fromCompanyId, $toCompanyId, $defaultReminderId, $reminderId, $arrCompanyDefaultSettings)
    {
        $arrDefaultAutomaticReminderActions = $this->getReminderActions($fromCompanyId, $defaultReminderId, array(), false, false);

        $arrMappingClientGroupsAndFields = $arrCompanyDefaultSettings['arrMappingClientGroupsAndFields'];
        $arrMappingCaseGroupsAndFields   = $arrCompanyDefaultSettings['arrMappingCaseGroupsAndFields'];
        $arrMappingRoles                 = $arrCompanyDefaultSettings['arrMappingRoles'];
        $arrMappingDefaultCategories     = $arrCompanyDefaultSettings['arrMappingDefaultCategories'];
        $arrMappingDefaultCaseStatuses   = $arrCompanyDefaultSettings['arrMappingDefaultCaseStatuses'];
        $arrMappingTemplates             = $arrCompanyDefaultSettings['arrMappingTemplates'];

        // Create same actions
        foreach ($arrDefaultAutomaticReminderActions as $arrActionInfo) {
            unset($arrActionInfo['automatic_reminder_action_id']);

            $arrActionInfo['company_id']                            = $toCompanyId;
            $arrActionInfo['automatic_reminder_id']                 = $reminderId;
            $arrActionInfo['automatic_reminder_action_create_date'] = date('Y-m-d');

            $actionSettings = Json::decode($arrActionInfo['automatic_reminder_action_settings'], Json::TYPE_ARRAY);

            $booCreateThisAction = false;

            switch ($arrActionInfo['automatic_reminder_action_type_internal_name']) {
                case 'change_field_value':
                    switch ($actionSettings['member_type']) {
                        case 'employer':
                            $booHasAccessToEmployers = $this->_company->isEmployersModuleEnabledToCompany($toCompanyId);
                            if (!$booHasAccessToEmployers) {
                                break 2;
                            }
                            if (isset($arrMappingClientGroupsAndFields['mappingFields'][$actionSettings['field_id']])) {
                                $actionSettings['field_id'] = $arrMappingClientGroupsAndFields['mappingFields'][$actionSettings['field_id']];
                            } else {
                                break 2;
                            }
                            break;

                        case 'individual':
                            if (isset($arrMappingClientGroupsAndFields['mappingFields'][$actionSettings['field_id']])) {
                                $actionSettings['field_id'] = $arrMappingClientGroupsAndFields['mappingFields'][$actionSettings['field_id']];
                            } else {
                                break 2;
                            }
                            break;

                        case 'case':
                            if (isset($arrMappingCaseGroupsAndFields['mappingFields'][$actionSettings['field_id']])) {
                                $actionSettings['field_id'] = $arrMappingCaseGroupsAndFields['mappingFields'][$actionSettings['field_id']];
                            } else {
                                break 2;
                            }
                            break;

                        default:
                            break;
                    }

                    $booCreateThisAction = true;

                    if (array_key_exists('option', $actionSettings)) {
                        $booCreateThisAction = false;

                        $booCase = $actionSettings['member_type'] == 'case';

                        $fieldType = '';

                        if ($booCase) {
                            $arrFieldInfo = $this->_clients->getFields()->getFieldsInfo(array($actionSettings['field_id']));

                            if (isset($arrFieldInfo[$actionSettings['field_id']])) {
                                $arrFieldInfo = $arrFieldInfo[$actionSettings['field_id']];
                                if (isset($arrFieldInfo['type'])) {
                                    $fieldType = $this->_clients->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']);
                                }
                            }
                        } else {
                            $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldsInfo(array($actionSettings['field_id']));

                            if (isset($arrFieldInfo[$actionSettings['field_id']])) {
                                $arrFieldInfo = $arrFieldInfo[$actionSettings['field_id']];
                                if (isset($arrFieldInfo['type'])) {
                                    $fieldType = $arrFieldInfo['type'];
                                }
                            }
                        }

                        if (empty($fieldType)) {
                            break;
                        }

                        switch ($fieldType) {
                            case 'combo':
                                $arrFieldsMapping = $booCase ? $arrMappingCaseGroupsAndFields['mappingDefaults'] : $arrMappingClientGroupsAndFields['mappingDefaults'];
                                if (isset($arrFieldsMapping[$actionSettings['option']])) {
                                    $actionSettings['option'] = $arrFieldsMapping[$actionSettings['option']];

                                    $booCreateThisAction = true;
                                }
                                break;

                            case 'categories':
                                if (isset($arrMappingDefaultCategories[$actionSettings['option']])) {
                                    $actionSettings['option'] = $arrMappingDefaultCategories[$actionSettings['option']];

                                    $booCreateThisAction = true;
                                }
                                break;

                            case 'case_status':
                                if (isset($arrMappingDefaultCaseStatuses[$actionSettings['option']])) {
                                    $actionSettings['option'] = $arrMappingDefaultCaseStatuses[$actionSettings['option']];

                                    $booCreateThisAction = true;
                                }
                                break;

                            case 'assigned_to':
                                if (preg_match('/^role:(\d+)$/', $actionSettings['option'], $regs)) {
                                    if (isset($arrMappingRoles[$regs[1]])) {
                                        $actionSettings['option'] = 'role:' . $arrMappingRoles[$regs[1]];

                                        $booCreateThisAction = true;
                                    }
                                }
                                break;

                            case 'country':
                                $arrCountries = $this->_country->getCountries(true);
                                if (isset($arrCountries[$actionSettings['option']]) || in_array($actionSettings['option'], $arrCountries)) {
                                    $booCreateThisAction = true;
                                }
                                break;

                            case 'checkbox':
                                $booCreateThisAction = true;
                                break;

                            // These field types are not supported
                            case 'agents':
                            case 'active_users':
                            case 'office':
                            case 'office_multi':
                            case 'staff_responsible_rma':
                            case 'contact_sales_agent':
                            case 'authorized_agents':
                            case 'employer_contacts':
                            default:
                                break;
                        }
                    }
                    break;

                case 'create_task':
                    if (isset($actionSettings['task_assign_to'])) {
                        if (preg_match('/^role:(\d+)$/', $actionSettings['task_assign_to'], $regs)) {
                            if (isset($arrMappingRoles[$regs[1]])) {
                                $actionSettings['task_assign_to'] = 'role:' . $arrMappingRoles[$regs[1]];

                                $booCreateThisAction = true;
                            }
                        } elseif (preg_match('/^assigned:(\d+)$/', $actionSettings['task_assign_to'], $regs) || $actionSettings['task_assign_to'] == 'user:all') {
                            $booCreateThisAction = true;
                        }
                    }
                    break;

                case 'send_email':
                    if (isset($actionSettings['template_id']) && isset($arrMappingTemplates[$actionSettings['template_id']])) {
                        $actionSettings['template_id'] = $arrMappingTemplates[$actionSettings['template_id']];

                        $booCreateThisAction = true;
                    }
                    break;

                default:
                    break;
            }

            if ($booCreateThisAction) {
                unset($arrActionInfo['automatic_reminder_action_type_name'], $arrActionInfo['automatic_reminder_action_type_internal_name']);
                $arrActionInfo['automatic_reminder_action_settings'] = Json::encode($actionSettings);

                $this->_db2->insert('automatic_reminder_actions', $arrActionInfo);
            }
        }
    }

    /**
     * Assign action to specific reminder
     *
     * @param $companyId
     * @param $reminderId
     * @param $arrActionIds
     * @return bool true on success
     */
    public function assignToReminder($companyId, $reminderId, $arrActionIds)
    {
        try {
            if (!empty($arrActionIds)) {
                $this->_db2->update(
                    'automatic_reminder_actions',
                    ['automatic_reminder_id' => (int)$reminderId],
                    [
                        'automatic_reminder_action_id' => $arrActionIds,
                        'company_id'                   => (int)$companyId
                    ]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Process automatic reminder actions
     * if due date is in the future - save the record in the schedule table, so it will be processed later
     *
     * @param $arrReminderInfo
     * @param $triggerTypeTextId
     * @param null|string $taskMessage
     * @return bool
     */
    public function processAutomaticReminderActions($arrReminderInfo, $triggerTypeTextId, $taskMessage = null)
    {
        $strError = '';

        if (empty($arrReminderInfo['member_id'])) {
            $strError = $this->_tr->translate('Incorrectly selected client/case');
        }

        if (empty($strError)) {
            $dueDateTimestamp = empty($arrReminderInfo['calculated_date']) ? time() : strtotime($arrReminderInfo['calculated_date']);

            if ($dueDateTimestamp <= time()) {
                // Pass specific task params (if they will be created)
                $arrTaskInfo = array(
                    'type'           => 'S',
                    'due_on'         => date('Y-m-d H:i:s', $dueDateTimestamp),
                    'auto_task_type' => $this->_parent->getTriggers()->getTriggerTypeIdByTextId($triggerTypeTextId)
                );

                if (!is_null($taskMessage)) {
                    $arrTaskInfo['message'] = $taskMessage;
                }

                $strError = $this->processAutomaticReminderActionsNow($arrReminderInfo, $arrTaskInfo);
            } else {
                $strError = $this->saveAutomaticReminderToBeProcessedLater($arrReminderInfo, $triggerTypeTextId, $dueDateTimestamp, $taskMessage);
            }
        }

        // save/mark automatic reminder as processed for this client
        $booSaveToProcessed = isset($arrReminderInfo['save_to_processed']) ? (bool)$arrReminderInfo['save_to_processed'] : false;
        if (empty($strError) && $booSaveToProcessed) {
            $arrProcessed = array(
                'automatic_reminder_id' => $arrReminderInfo['automatic_reminder_id'],
                'member_id'             => $arrReminderInfo['member_id'],
                'year'                  => date('Y')
            );

            $this->_db2->insert('automatic_reminders_processed', $arrProcessed);
        }

        return empty($strError);
    }

    /**
     * Save in DB reminders that must be processed at specific date
     *
     * @param $arrReminderInfo
     * @param $triggerTypeTextId
     * @param $dueDateTimestamp
     * @param $taskMessage
     * @return string error, empty on success
     */
    public function saveAutomaticReminderToBeProcessedLater($arrReminderInfo, $triggerTypeTextId, $dueDateTimestamp, $taskMessage)
    {
        $strError = '';
        try {
            $arrInsert = array(
                'automatic_reminder_id'                   => $arrReminderInfo['automatic_reminder_id'],
                'automatic_reminder_settings'             => Json::encode($arrReminderInfo),
                'automatic_reminder_trigger_type_id'      => $this->_parent->getTriggers()->getTriggerTypeIdByTextId($triggerTypeTextId),
                'automatic_reminder_schedule_due_on_date' => date('Y-m-d', $dueDateTimestamp),
                'automatic_reminder_schedule_message'     => empty($taskMessage) ? null : $taskMessage
            );

            $this->_db2->insert('automatic_reminder_schedule', $arrInsert);
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Process automatic reminders that were scheduled
     *
     * @return bool true on success
     */
    public function processScheduledReminderActions()
    {
        try {
            $select = (new Select())
                ->from('automatic_reminder_schedule')
                ->where([(new Where())->lessThanOrEqualTo('automatic_reminder_schedule_due_on_date', date('Y-m-d'))]);

            $arrRemindersToProcess = $this->_db2->fetchAll($select);

            $arrReminderIds = array();
            $arrReminders   = array();

            foreach ($arrRemindersToProcess as $arrRemindersToProcessInfo) {
                $arrReminderSettings = Json::decode($arrRemindersToProcessInfo['automatic_reminder_settings'], Json::TYPE_ARRAY);

                $arrReminderSettings['automatic_reminder_trigger_type_id']  = $arrRemindersToProcessInfo['automatic_reminder_trigger_type_id'];
                $arrReminderSettings['automatic_reminder_schedule_message'] = $arrRemindersToProcessInfo['automatic_reminder_schedule_message'];
                $arrReminderSettings['automatic_reminder_schedule_id']      = $arrRemindersToProcessInfo['automatic_reminder_schedule_id'];

                $arrReminders[]   = $arrReminderSettings;
                $arrReminderIds[] = $arrReminderSettings['automatic_reminder_id'];
            }
            // Group/extract ids to load all at once
            $arrReminderIds = array_unique($arrReminderIds);

            // Load conditions for ALL company reminders at once
            $arrRemindersConditions = $this->_parent->getConditions()->getRemindersConditions($arrReminderIds);

            // Group them by reminders
            $arrGroupedConditions = array();
            foreach ($arrRemindersConditions as $arrConditionInfo) {
                $arrGroupedConditions[$arrConditionInfo['automatic_reminder_id']][] = $arrConditionInfo;
            }

            foreach ($arrReminders as $arrReminderInfo) {
                $booAllConditionsAreDue = true;

                if (isset($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']]) && count($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']])) {
                    foreach ($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']] as $arrConditionInfo) {
                        if (in_array($arrConditionInfo['automatic_reminder_condition_type_internal_id'], array('BASED_ON_FIELD', 'CASE_TYPE', 'CASE_HAS_FORM'))) {
                            if (isset($arrReminderInfo['field_id'])) {
                                $arrConditionInfo['field_id'] = $arrReminderInfo['field_id'];
                            }
                            list($booIsDue, ,) = $this->_parent->getConditions()->isConditionDueForMember($arrReminderInfo['company_id'], $arrReminderInfo['member_id'], $arrConditionInfo, $arrReminderInfo['active_clients_only'] == 'Y');

                            // Don't do other checks if at least one condition failed
                            if (!$booIsDue) {
                                $booAllConditionsAreDue = false;
                                break;
                            }
                        }
                    }
                }

                if ($booAllConditionsAreDue) {
                    $arrTaskInfo = array(
                        'type'           => 'S',
                        'due_on'         => date('Y-m-d H:i:s'),
                        'auto_task_type' => $arrReminderInfo['automatic_reminder_trigger_type_id']
                    );

                    if (!empty($arrReminderInfo['automatic_reminder_schedule_message'])) {
                        $arrTaskInfo['message'] = $arrReminderInfo['automatic_reminder_schedule_message'];
                    }

                    $this->processAutomaticReminderActionsNow(
                        $arrReminderInfo,
                        $arrTaskInfo
                    );
                }

                $this->_db2->delete(
                    'automatic_reminder_schedule',
                    ['automatic_reminder_schedule_id' => (int)$arrReminderInfo['automatic_reminder_schedule_id']]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    /**
     * Process all actions assigned to specific auto reminder
     *
     * @param $arrReminderInfo
     * @param $arrTaskSpecificInfo
     * @return string error, empty on success
     */
    public function processAutomaticReminderActionsNow($arrReminderInfo, $arrTaskSpecificInfo)
    {
        $strError = '';

        try {
            $memberId = $arrReminderInfo['member_id'];
            if (empty($memberId)) {
                $strError = $this->_tr->translate('Incorrect client id.');
            }

            $companyId      = 0;
            $companyAdminId = 0;
            $memberTypeId   = 0;

            $arrClientInfo           = array();
            $arrReminderActions      = array();
            $arrAllFieldsChangesData = array();
            if (empty($strError)) {
                $arrClientInfo = $this->_clients->getClientShortInfo($memberId);
                $companyId     = $arrClientInfo['company_id'];

                if (empty($companyId)) {
                    $strError = $this->_tr->translate('Client not found.');
                }

                if (empty($strError)) {
                    $companyAdminId     = $this->_company->getCompanyAdminId($companyId);
                    $memberTypeId       = $this->_clients->getMemberTypeByMemberId($memberId);
                    $arrReminderActions = $this->getReminderActions($companyId, $arrReminderInfo['automatic_reminder_id']);
                }
            }

            if (empty($strError)) {
                foreach ($arrReminderActions as $arrActionInfo) {
                    $actionSettings = Json::decode($arrActionInfo['automatic_reminder_action_settings'], Json::TYPE_ARRAY);

                    switch ($arrActionInfo['automatic_reminder_action_type_internal_name']) {
                        case 'change_field_value':
                            $booActiveOnly = isset($arrReminderInfo['active_clients_only']) && $arrReminderInfo['active_clients_only'] == 'Y';

                            list($strError, $arrFieldChangesData) = $this->_clients->changeFieldValue($memberId, $companyId, $actionSettings, true, $booActiveOnly);
                            foreach ($arrFieldChangesData as $memberId => $arrChanges) {
                                if (isset($arrAllFieldsChangesData[$memberId])) {
                                    $arrAllFieldsChangesData[$memberId]['arrOldData'] = array_merge($arrAllFieldsChangesData[$memberId]['arrOldData'], $arrChanges['arrOldData']);
                                    $arrAllFieldsChangesData[$memberId]['arrNewData'] = array_merge($arrAllFieldsChangesData[$memberId]['arrNewData'], $arrChanges['arrNewData']);
                                } else {
                                    $arrAllFieldsChangesData[$memberId] = $arrChanges;
                                }
                            }
                            break;

                        case 'create_task':
                            // Get case id that task must be created for
                            if (in_array($memberTypeId, Members::getMemberType('case'))) {
                                $caseId = $memberId;
                            } else {
                                // Get last case for each found client
                                $parentMemberId = $memberId;
                                if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                                    $arrParents = $this->_clients->getParentsForAssignedApplicant($memberId);
                                    if (count($arrParents)) {
                                        $parentMemberId = $arrParents[0];
                                    }
                                }

                                $caseId = $this->_clients->getLastAssignedCase($parentMemberId, $arrReminderInfo['active_clients_only'] == 'Y');
                            }

                            if (!empty($caseId)) {
                                $arrTaskInfo = array(
                                    'member_id'      => $caseId,
                                    'author_id'      => $companyAdminId,
                                    'subject'        => $actionSettings['task_subject'],
                                    'message'        => $arrTaskSpecificInfo['message'] ?? $actionSettings['task_message'],
                                    'notify_client'  => 'N',
                                    'deadline'       => '',
                                    'type'           => $arrTaskSpecificInfo['type'],
                                    'due_on'         => $arrTaskSpecificInfo['due_on'],
                                    'number'         => '',
                                    'days'           => '',
                                    'ba'             => '',
                                    'prof'           => '',
                                    'is_due'         => 'Y',
                                    'auto_task_type' => $arrTaskSpecificInfo['auto_task_type'],

                                    'to' => $this->_tasks->getReminderAssignedToMembers($arrReminderInfo['company_id'], $caseId, $actionSettings['task_assign_to']),
                                    'cc' => array(),
                                );

                                $this->_tasks->addTask($arrTaskInfo, true, true);
                            }
                            break;

                        case 'send_email':
                            $arrRecipients        = array();
                            $booSaveEmailToClient = false;

                            if (!isset($actionSettings['to'])) {
                                $actionSettings['to'] = 'client';
                            }

                            switch ($actionSettings['to']) {
                                case 'responsible_staff':
                                    $arrDivisions             = $this->_clients->getMemberDivisions($memberId);
                                    $arrMembersResponsibleFor = $this->_clients->getMembersAssignedToDivisions($arrDivisions);

                                    if (!empty($arrMembersResponsibleFor)) {
                                        foreach ($arrMembersResponsibleFor as $memberResponsibleForId) {
                                            $arrMemberInfo   = $this->_clients->getMemberInfo($memberResponsibleForId);
                                            $arrRecipients[] = $arrMemberInfo['emailAddress'];
                                        }
                                    }
                                    break;

                                case 'employer':
                                    $employerId = 0;
                                    if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                                        $arrIAParents = $this->_clients->getParentsForAssignedApplicant($memberId, 8);
                                        if (count($arrIAParents)) {
                                            $iAId               = $arrIAParents[0];
                                            $arrEmployerParents = $this->_clients->getParentsForAssignedApplicant($iAId, 7);
                                            if (count($arrEmployerParents)) {
                                                $employerId = $arrEmployerParents[0];
                                            }
                                        }
                                    } elseif (in_array($memberTypeId, Members::getMemberType('individual'))) {
                                        $arrEmployerParents = $this->_clients->getParentsForAssignedApplicant($memberId, 7);
                                        if (count($arrEmployerParents)) {
                                            $employerId = $arrEmployerParents[0];
                                        }
                                    } elseif (in_array($memberTypeId, Members::getMemberType('case'))) {
                                        $arrIAParents       = $this->_clients->getParentsForAssignedApplicant($memberId, 8);
                                        $arrEmployerParents = $this->_clients->getParentsForAssignedApplicant($memberId, 7);
                                        if (count($arrIAParents) && count($arrEmployerParents)) {
                                            $employerId = $arrEmployerParents[0];
                                        }
                                    }

                                    if (!empty($employerId)) {
                                        $arrClientInfo        = $this->_clients->getClientShortInfo($employerId);
                                        $arrRecipients[]      = $arrClientInfo['emailAddress'];
                                        $booSaveEmailToClient = true;
                                    }
                                    break;

                                default:
                                    if ($actionSettings['to'] == 'client') {
                                        // For internal contact load parent's info
                                        if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                                            $arrParents = $this->_clients->getParentsForAssignedApplicant($memberId);
                                            if (count($arrParents)) {
                                                $parentMemberId = $arrParents[0];
                                                $arrClientInfo  = $this->_clients->getClientShortInfo($parentMemberId);
                                            }
                                        }

                                        $arrRecipients[]      = $arrClientInfo['emailAddress'];
                                        $booSaveEmailToClient = true;
                                    } else {
                                        // Get case id
                                        if (in_array($memberTypeId, Members::getMemberType('case'))) {
                                            $caseId = $memberId;
                                        } else {
                                            // Get last case for each found client
                                            $parentMemberId = $memberId;
                                            if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                                                $arrParents = $this->_clients->getParentsForAssignedApplicant($memberId);
                                                if (count($arrParents)) {
                                                    $parentMemberId = $arrParents[0];
                                                }
                                            }

                                            $caseId = $this->_clients->getLastAssignedCase($parentMemberId, $arrReminderInfo['active_clients_only'] == 'Y');
                                        }

                                        if (!empty($caseId)) {
                                            $arrMemberIds = $this->_tasks->getReminderAssignedToMembers($arrReminderInfo['company_id'], $caseId, $actionSettings['to']);
                                            if (!empty($arrMemberIds)) {
                                                foreach ($arrMemberIds as $memberIdSendEmailTo) {
                                                    $arrMemberInfo   = $this->_clients->getMemberInfo($memberIdSendEmailTo);
                                                    $arrRecipients[] = $arrMemberInfo['emailAddress'];
                                                }
                                            }
                                        }
                                    }
                                    break;
                            }

                            if (!empty($arrRecipients)) {
                                // Make sure that template record exists
                                /** @var Templates $templates */
                                $templates       = $this->_serviceContainer->get(Templates::class);
                                $arrTemplateInfo = $templates->getTemplate($actionSettings['template_id'], false);
                                if (!empty($arrTemplateInfo)) {
                                    $arrTemplateInfo = $templates->getMessage($actionSettings['template_id'], $memberId);
                                    $arrAttachments  = $templates->parseTemplateAttachments((int)$actionSettings['template_id'], (int)$memberId, true);

                                    $message = "<style type='text/css'>* { font-family: Arial, serif; font-size: 12px; }</style>";
                                    if (!(strcmp($arrTemplateInfo['message'] ?? '', strip_tags($arrTemplateInfo['message'] ?? '')) == 0)) {
                                        $message .= "<div>" . $arrTemplateInfo['message'] . "</div>";
                                    } else {
                                        $message .= "<div>" . nl2br($arrTemplateInfo['message'] ?? '') . "</div>";
                                    }

                                    $arrEmailParams = array(
                                        'from_email'    => $arrTemplateInfo['from'],
                                        'cc'            => $arrTemplateInfo['cc'],
                                        'friendly_name' => '',
                                        'subject'       => $arrTemplateInfo['subject'],
                                        'message'       => $message,
                                        'attached'      => $arrAttachments
                                    );

                                    $arrAdminInfo   = [];
                                    $emailValidator = new EmailAddress();
                                    if (!empty($companyAdminId)) {
                                        $arrAdminInfo = $this->_members->getMemberInfo($companyAdminId, true);
                                        if (!empty($arrAdminInfo['id']) && $arrAdminInfo['out_use_own'] == 'Y') {
                                            // Use admin's email account
                                            $arrEmailParams['from'] = $arrAdminInfo['id'];

                                            // Use email address from the email account if it was not set in the template
                                            if (empty($arrEmailParams['from_email']) && $emailValidator->isValid($arrAdminInfo['email'])) {
                                                $arrEmailParams['from_email'] = $arrAdminInfo['email'];
                                            }
                                        }
                                    }

                                    if (empty($arrEmailParams['from_email'])) {
                                        // If not set in the template and not used from the email account - use company's email address
                                        $arrEmailParams['from_email'] = $this->_clients->getMemberCompanyEmail($memberId);

                                        $booShowSenderName = true;
                                    } else {
                                        $booShowSenderName = false;
                                    }

                                    $arrRecipients = Settings::arrayUnique($arrRecipients);
                                    foreach ($arrRecipients as $emailTo) {
                                        if ($emailValidator->isValid($emailTo)) {
                                            $arrEmailParams['email'] = $emailTo;

                                            list($res, $email) = $this->_mailer->send($arrEmailParams, false, $arrAdminInfo, false, true, $booShowSenderName);

                                            if ($res === true && $booSaveEmailToClient) {
                                                $booLocal     = $this->_company->isCompanyStorageLocationLocal($companyId);
                                                $clientFolder = $this->_files->getClientCorrespondenceFTPFolder($companyId, $memberId, $booLocal);
                                                $this->_mailer->saveRawEmailToClient($email, $arrEmailParams['subject'], 0, $companyId, $memberId, $this->_members->getMemberInfo(), 0, $clientFolder, $booLocal);
                                            }
                                        }
                                    }
                                }
                            }
                            break;

                        default:
                            break;
                    }
                }
            }

            // If all done successfully - log the changes
            if (empty($strError) && count($arrAllFieldsChangesData)) {
                $this->_triggers->triggerFieldBulkChanges($companyId, $arrAllFieldsChangesData, true, $arrReminderInfo['reminder']);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }
}
