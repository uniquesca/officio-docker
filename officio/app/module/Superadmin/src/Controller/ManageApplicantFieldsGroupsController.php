<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;

/**
 * Manage Applicant IA/Employer fields and groups
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageApplicantFieldsGroupsController extends BaseController
{

    /** @var Clients */
    protected $_clients;
    /** @var Company */
    protected $_company;
    /** @var Roles */
    protected $_roles;
    /** @var Encryption */
    protected $_encryption;
    /** @var StripTags */
    private $_filter;

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_clients    = $services[Clients::class];
        $this->_roles      = $services[Roles::class];
        $this->_encryption = $services[Encryption::class];

        $this->_filter = new StripTags();
    }

    /**
     * The default action - show fields/groups for IA/Employer
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $memberType = $this->_filter->filter($this->params('member_type', ''));

        // Sometimes member_type is passed via GET
        if (!$memberType) {
            $memberType = $this->_filter->filter($this->params()->fromQuery('member_type'));
        }

        $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
        if (empty($memberTypeId)) {
            exit();
        }

        $applicantTypeId = (int)$this->findParam('template_id', 0);

        $memberType = ucfirst($memberType ?? '');
        $view->setVariable('memberType', $memberType);
        $view->setVariable('memberTypeId', $memberTypeId);

        switch (strtolower($memberType)) {
            case 'individual':
            case 'individuals':
                $title = $this->_tr->translate('Individual Client Profile');
                break;

            case 'employer':
            case 'employers':
                $title = $this->_tr->translate('Employer Client profile ');
                break;

            case 'internal_contact':
                $title = $this->_tr->translate('Internal Client Profile');
                break;

            default:
                $title = sprintf($this->_tr->translate('%s Fields Groups'), $memberType);
                break;
        }

        $booSuperadmin = $this->_auth->isCurrentUserSuperadmin();
        if ($booSuperadmin) {
            $title = $this->_tr->translate('Default') . ' ' . $title;
        }

        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        // Load groups and their fields
        if (!$booSuperadmin) {
            $companyId = $this->_auth->getCurrentUserCompanyId();
        } else {
            // Superadmin
            $companyId = (int)$this->findParam('company_id');
            if (empty($companyId)) {
                $companyId = 0;
            }
        }

        // Load fields for this company
        $arrGroupsAndFields = $this->_clients->getApplicantFields()->getAllGroupsAndFields($companyId, $memberTypeId, $applicantTypeId);

        $view->setVariable('arrGroupsAndFields', $arrGroupsAndFields);
        $view->setVariable('companyId', $companyId);
        $view->setVariable('applicantTypeId', $applicantTypeId);
        $view->setVariable('fieldTypesList', $this->_clients->getFieldTypes()->getFieldTypes($memberType));
        $view->setVariable('arrRoles', $this->_roles->getCompanyRoles($companyId, 0));
        $view->setVariable('booIsAuthorisedAgentsManagementEnabled', $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled());

        if ($memberTypeId == $this->_clients->getMemberTypeIdByName('contact')) {
            $this->layout()->setVariable('booShowLeftPanel', false);
        }

        return $view;
    }

    public function applicantTypesAction()
    {
        $title = $this->_tr->translate('Contacts Types');
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $title = $this->_tr->translate('Default') . ' ' . $title;
        }

        $view  = new ViewModel();
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    public function getApplicantTypesAction()
    {
        $view         = new JsonModel();
        $booSuccess   = false;
        $arrTemplates = array();
        $totalCount   = 0;
        try {
            $companyId    = $this->_auth->getCurrentUserCompanyId();
            $arrTemplates = $this->_clients->getApplicantTypes()->getTypes($companyId, false, $this->_clients->getMemberTypeIdByName('contact'));
            $totalCount   = count($arrTemplates);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'items'   => $arrTemplates,
            'count'   => $totalCount,
        );
        return $view->setVariables($arrResult);
    }

    public function addApplicantTypeAction()
    {
        $view       = new JsonModel();
        $strError   = '';
        $booSuccess = false;
        $typeId     = 0;

        try {
            $typeName = trim($this->_filter->filter($this->findParam('applicant_type_name', '')));
            if ($typeName == '') {
                $strError = $this->_tr->translate('Incorrect template name.');
            }

            $typeCopyId = (int)$this->findParam('applicant_type_copy_from');
            if (empty($strError) && !empty($typeCopyId) && !$this->_clients->getApplicantTypes()->hasAccessToType($typeCopyId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $companyId  = $this->_auth->getCurrentUserCompanyId();
                $typeId     = $this->_clients->getApplicantTypes()->addType($companyId, $this->_clients->getMemberTypeIdByName('contact'), $typeName, $typeCopyId);
                $booSuccess = !empty($typeId);

                if ($booSuccess && empty($typeCopyId)) {
                    // TODO: create one group by default
                    // $groupId = $this->_clients->getFields()->createGroup($companyId, 'Not Assigned', $templateId, 'U');
                    // $booSuccess = $groupId > 0;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'           => $booSuccess,
            'applicant_type_id' => $typeId,
            'msg'               => $strError
        );
        return $view->setVariables($arrResult);
    }

    public function updateApplicantTypeAction()
    {
        $view     = new JsonModel();
        $strError = '';

        try {
            $typeId = (int)Json::decode($this->findParam('applicant_type_id'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_clients->getApplicantTypes()->hasAccessToType($typeId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrInfo = $this->_clients->getApplicantTypes()->getTypeInfo($typeId);
                if ($arrInfo['is_system'] == 'Y') {
                    $strError = $this->_tr->translate('This template cannot be changed.');
                }
            }

            $typeName = trim($this->_filter->filter($this->findParam('applicant_type_name', '')));
            if (empty($strError) && $typeName == '') {
                $strError = $this->_tr->translate('Incorrect name.');
            }

            if (empty($strError) && !$this->_clients->getApplicantTypes()->updateType($typeId, $typeName)) {
                $strError = $this->_tr->translate('Internal error.');
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

    public function deleteApplicantTypeAction()
    {
        $view       = new JsonModel();
        $strMessage = '';
        $booSuccess = false;

        try {
            $typeId = (int)Json::decode($this->findParam('applicant_type_id'), Json::TYPE_ARRAY);
            if (empty($strMessage) && !$this->_clients->getApplicantTypes()->hasAccessToType($typeId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strMessage)) {
                $arrClients = $this->_clients->getClientsByApplicantTypeId($typeId);
                if (is_array($arrClients) && count($arrClients)) {
                    $strMessage = $this->_tr->translate('There is assigned at least one record to this template.');
                }
            }


            $companyId = $this->_auth->getCurrentUserCompanyId();
            if (empty($strMessage)) {
                $arrCompanyApplicantTypes = $this->_clients->getApplicantTypes()->getTypes($companyId);

                if (count($arrCompanyApplicantTypes) <= 1) {
                    $strMessage = $this->_tr->translate('It is not possible to delete all templates.');
                }
            }

            if (empty($strMessage)) {
                // TODO: delete related info
                // Delete all groups (and related info) assigned to these templates
                /*
                $arrGroupIds = array();
                $arrGroups = $this->_clients->getFields()->getCompanyGroups($companyId, false, $typeId);
                foreach ($arrGroups as $arrGroupInfo) {
                    $arrGroupIds[] = $arrGroupInfo['group_id'];
                }
                $booSuccess = $this->_clients->getFields()->deleteGroup($arrGroupIds);
                */
                $booSuccess = true;
                if ($booSuccess) {
                    $booSuccess = $this->_clients->getApplicantTypes()->deleteType($typeId);
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'msg'     => $strMessage
        );
        return $view->setVariables($arrResult);
    }

    public function addBlockAction()
    {
        $view     = new JsonModel();
        $strError = '';
        $blockId  = 0;
        $groupId  = 0;
        try {
            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && !is_numeric($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $blockType = $this->_filter->filter(Json::decode($this->findParam('block_type'), Json::TYPE_ARRAY));
            if (empty($strError) && !in_array($blockType, array('contact', 'general'))) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $booIsGroupCollapsed = (bool)Json::decode($this->findParam('group_collapsed'), Json::TYPE_ARRAY);
            $groupName           = trim($this->_filter->filter(Json::decode($this->findParam('group_name', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && empty($groupName)) {
                $strError = $this->_tr->translate('Incorrect group name.');
            }

            $groupColsCount = (int)Json::decode($this->findParam('group_cols_count'), Json::TYPE_ARRAY);
            if (empty($strError) && (!is_numeric($groupColsCount) || $groupColsCount < 1 || $groupColsCount > 5)) {
                $strError = $this->_tr->translate('Incorrect group columns count.');
            }

            if (empty($strError)) {
                $blockId = $this->_clients->getApplicantFields()->createBlock($companyId, $memberTypeId, $blockType);
                if (empty($blockId)) {
                    $strError = $this->_tr->translate('Internal error.');
                } else {
                    $groupId = $this->_clients->getApplicantFields()->createGroup($companyId, $memberTypeId, $blockId, $groupName, $booIsGroupCollapsed, $groupColsCount);
                    if (!$groupId) {
                        $strError = $this->_tr->translate('Internal error.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'    => !empty($strError),
            'message'  => $strError,
            'block_id' => $blockId,
            'group_id' => $groupId
        );
        return $view->setVariables($arrResult);
    }

    public function editBlockAction()
    {
        $view     = new JsonModel();
        $strError = '';
        try {
            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && !is_numeric($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $blockType = $this->_filter->filter(Json::decode($this->findParam('block_type'), Json::TYPE_ARRAY));
            if (empty($strError) && !in_array($blockType, array('contact', 'general'))) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $blockId = Json::decode($this->findParam('block_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrBlockInfo = $this->_clients->getApplicantFields()->getBlockInfo($blockId);
                if (!is_array($arrBlockInfo) || $arrBlockInfo['company_id'] != $companyId) {
                    $strError = $this->_tr->translate('Incorrectly selected block.');
                }
            }

            $booIsBlockRepeatable = Json::decode($this->findParam('block_repeatable'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_clients->getApplicantFields()->updateBlock($companyId, $memberTypeId, $blockId, $booIsBlockRepeatable)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'   => !empty($strError),
            'message' => $strError,
        );
        return $view->setVariables($arrResult);
    }

    public function removeBlockAction()
    {
        $view     = new JsonModel();
        $strError = '';
        try {
            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && !is_numeric($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $blockId      = Json::decode($this->findParam('block_id'), Json::TYPE_ARRAY);
            $arrBlockInfo = $this->_clients->getApplicantFields()->getBlockInfo($blockId);
            if (empty($strError) && (!is_array($arrBlockInfo) || $arrBlockInfo['company_id'] != $companyId || $arrBlockInfo['member_type_id'] != $memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected block.');
            }

            if (empty($strError) && !$this->_clients->getApplicantFields()->deleteBlock($companyId, $memberTypeId, $blockId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'   => !empty($strError),
            'message' => $strError,
        );
        return $view->setVariables($arrResult);
    }

    public function addGroupAction()
    {
        $view     = new JsonModel();
        $strError = '';
        $groupId  = 0;
        try {
            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && !is_numeric($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $blockId      = Json::decode($this->findParam('block_id'), Json::TYPE_ARRAY);
            $arrBlockInfo = $this->_clients->getApplicantFields()->getBlockInfo($blockId);
            if (empty($strError) && (!is_array($arrBlockInfo) || $arrBlockInfo['company_id'] != $companyId || $arrBlockInfo['member_type_id'] != $memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected block.');
            }

            $groupColsCount = Json::decode($this->findParam('group_cols_count'), Json::TYPE_ARRAY);
            if (empty($strError) && (!is_numeric($groupColsCount) || empty($groupColsCount) || $groupColsCount > 5)) {
                $strError = $this->_tr->translate('Incorrect group columns count.');
            }

            $isGroupCollapsed = Json::decode($this->findParam('group_collapsed'), Json::TYPE_ARRAY);
            $groupName        = trim($this->_filter->filter(Json::decode($this->findParam('group_name', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && empty($groupName)) {
                $strError = $this->_tr->translate('Incorrect group name.');
            }

            if (empty($strError)) {
                $groupId = $this->_clients->getApplicantFields()->createGroup($companyId, $memberTypeId, $blockId, $groupName, $isGroupCollapsed, $groupColsCount);
                if (empty($groupId)) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'    => !empty($strError),
            'message'  => empty($strError) ? $this->_tr->translate('Done.') : $strError,
            'group_id' => $groupId
        );
        return $view->setVariables($arrResult);
    }

    public function editGroupAction()
    {
        $view     = new JsonModel();
        $strError = '';
        try {
            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company.');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && !is_numeric($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $blockId      = Json::decode($this->findParam('block_id'), Json::TYPE_ARRAY);
            $arrBlockInfo = $this->_clients->getApplicantFields()->getBlockInfo($blockId);
            if (empty($strError) && (!is_array($arrBlockInfo) || $arrBlockInfo['company_id'] != $companyId || $arrBlockInfo['member_type_id'] != $memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected block.');
            }

            $groupId = Json::decode($this->findParam('group_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrCompanyGroups = $this->_clients->getApplicantFields()->getCompanyGroups($companyId, $memberTypeId);
                $arrGroupIds      = $this->_settings::arrayColumnAsKey('applicant_group_id', $arrCompanyGroups, 'applicant_group_id');

                if (!in_array($groupId, $arrGroupIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected group.');
                }
            }

            $groupColsCount = Json::decode($this->findParam('group_cols_count'), Json::TYPE_ARRAY);
            if (empty($strError) && (!is_numeric($groupColsCount) || empty($groupColsCount) || $groupColsCount > 5)) {
                $strError = $this->_tr->translate('Incorrect group columns count.');
            }

            $isGroupCollapsed = Json::decode($this->findParam('group_collapsed'), Json::TYPE_ARRAY);
            $groupName        = trim($this->_filter->filter(Json::decode($this->findParam('group_name', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && empty($groupName)) {
                $strError = $this->_tr->translate('Incorrect group name.');
            }

            if (empty($strError) && !$this->_clients->getApplicantFields()->updateGroup($memberTypeId, $groupId, $groupName, $isGroupCollapsed, $groupColsCount)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'   => !empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done.') : $strError,
        );
        return $view->setVariables($arrResult);
    }

    public function deleteGroupAction()
    {
        $view = new JsonModel();
        try {
            $companyId      = (int)Json::decode(stripslashes($this->findParam('company_id', '')), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $groupId = Json::decode($this->findParam('group_id'), Json::TYPE_ARRAY);
            if (empty($strError) && !is_numeric($groupId)) {
                $strError = $this->_tr->translate('Incorrectly selected group');
            }

            if (empty($strError) && !$this->_clients->getApplicantFields()->isGroupInCompany($companyId, $groupId)) {
                $strError = $this->_tr->translate('Incorrectly selected group');
            }

            // Check if there are fields in this group
            if (empty($strError) && $this->_clients->getApplicantFields()->hasGroupFields($groupId)) {
                $strError = $this->_tr->translate('There are fields in this group. Please move these fields to other group, save changes and try again.');
            }

            if (empty($strError) && !$this->_clients->getApplicantFields()->deleteGroup($groupId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            "error"   => !empty($strError),
            "message" => !empty($strError) ? $strError : $this->_tr->translate('Group was successfully deleted.')
        );
        return $view->setVariables($arrResult);
    }

    public function getContactFieldsAction()
    {
        $view             = new JsonModel();
        $strError         = '';
        $arrFields        = array();
        $totalFieldsCount = 0;
        try {
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company.');
            }

            $blockId = Json::decode($this->findParam('block_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrBlockInfo = $this->_clients->getApplicantFields()->getBlockInfo($blockId);
                if ((!is_array($arrBlockInfo) || $arrBlockInfo['company_id'] != $companyId)) {
                    $strError = $this->_tr->translate('Incorrectly selected block.');
                }
            }

            if (empty($strError)) {
                $contactTypeId = $this->_clients->getMemberTypeIdByName('internal_contact');
                $arrAllFields  = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $contactTypeId);

                $arrAssignedFields = $this->_clients->getApplicantFields()->getBlockFields($blockId);
                $arrAssignedFields = is_array($arrAssignedFields) ? $arrAssignedFields : array();

                foreach ($arrAllFields as $arrFieldInfo) {
                    $arrFields[] = array(
                        'field_id'                       => $arrFieldInfo['applicant_field_id'],
                        'field_name'                     => $arrFieldInfo['label'],
                        'field_encrypted'                => $arrFieldInfo['encrypted'] == 'Y',
                        'field_required'                 => $arrFieldInfo['required'] == 'Y',
                        'field_disabled'                 => $arrFieldInfo['disabled'] == 'Y',
                        'field_blocked'                  => $arrFieldInfo['blocked'] == 'Y',
                        'field_placed'                   => in_array($arrFieldInfo['applicant_field_id'], $arrAssignedFields),
                        'field_skip_access_requirements' => $arrFieldInfo['skip_access_requirements'] == 'Y',
                        'field_multiple_values'          => $arrFieldInfo['multiple_values'] == 'Y',
                        'field_can_edit_in_gui'          => $arrFieldInfo['can_edit_in_gui'] == 'Y',
                    );
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrFields,
            'count'   => $totalFieldsCount,
        );
        return $view->setVariables($arrResult);
    }

    public function toggleContactFieldsAction()
    {
        $view     = new JsonModel();
        $strError = '';
        try {
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            $arrAllFields   = array();

            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company.');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && !is_numeric($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }

            $blockId = Json::decode($this->findParam('block_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrBlockInfo = $this->_clients->getApplicantFields()->getBlockInfo($blockId);
                if ((!is_array($arrBlockInfo) || $arrBlockInfo['company_id'] != $companyId)) {
                    $strError = $this->_tr->translate('Incorrectly selected block.');
                }
            }

            $groupId = Json::decode($this->findParam('group_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrCompanyGroups = $this->_clients->getApplicantFields()->getCompanyGroups($companyId, $memberTypeId);
                $arrGroupIds      = $this->_settings::arrayColumnAsKey('applicant_group_id', $arrCompanyGroups, 'applicant_group_id');

                if (!in_array($groupId, $arrGroupIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected group.');
                }
            }

            $arrFieldsAdded = Json::decode($this->findParam('fields_added'), Json::TYPE_ARRAY);
            if (empty($strError) && !is_array($arrFieldsAdded)) {
                $strError = $this->_tr->translate('Incorrectly checked fields.');
            }

            $arrFieldsRemoved = Json::decode($this->findParam('fields_removed'), Json::TYPE_ARRAY);
            if (empty($strError) && !is_array($arrFieldsRemoved)) {
                $strError = $this->_tr->translate('Incorrectly unchecked fields.');
            }

            if (empty($strError) && empty($arrFieldsAdded) && empty($arrFieldsRemoved)) {
                $strError = $this->_tr->translate('Please select fields.');
            }

            if (empty($strError)) {
                $contactTypeId  = $this->_clients->getMemberTypeIdByName('internal_contact');
                $arrAllFields   = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $contactTypeId);
                $arrAllFieldIds = $this->_settings::arrayColumnAsKey('applicant_field_id', $arrAllFields, 'applicant_field_id');

                foreach ($arrFieldsAdded as $addedFieldId) {
                    if (!in_array($addedFieldId, $arrAllFieldIds)) {
                        $strError = $this->_tr->translate('Incorrectly checked field.');
                        break;
                    }
                }

                foreach ($arrFieldsRemoved as $removedFieldId) {
                    if (!in_array($removedFieldId, $arrAllFieldIds)) {
                        $strError = $this->_tr->translate('Incorrectly unchecked field.');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                // Unassign specific fields
                $arrGroupIds = $this->_clients->getApplicantFields()->getBlockGroups($blockId);
                if (is_array($arrGroupIds) && count($arrGroupIds) && count($arrFieldsRemoved)) {
                    foreach ($arrFieldsRemoved as $removedFieldId) {
                        $this->_clients->getApplicantFields()->unassignField($removedFieldId, $arrGroupIds, $companyId);
                    }
                }

                // Place new fields into the first column
                if(count($arrFieldsAdded)) {
                    $select = (new Select())
                        ->from('applicant_form_order')
                        ->columns(['field_order' => new Expression('IFNULL(MAX(field_order), 0)')])
                        ->where(['applicant_group_id' => (int)$groupId]);

                    $maxRow = $this->_db2->fetchOne($select);

                    foreach ($arrFieldsAdded as $addedFieldId) {
                        $useFullRow = 'N';
                        foreach ($arrAllFields as $field) {
                            if ($field['applicant_field_id'] == $addedFieldId) {
                                $useFullRow = $field['use_full_row'] ?? 'N';
                            }
                        }
                        $arrValues                       = array();
                        $arrValues['applicant_group_id'] = (int)$groupId;
                        $arrValues['applicant_field_id'] = (int)$addedFieldId;
                        $arrValues['use_full_row']       = $useFullRow;
                        $arrValues['field_order']        = ++$maxRow;

                        $this->_db2->insert('applicant_form_order', $arrValues);
                    }
                }

                $this->_clients->getApplicantFields()->clearCache($companyId, $memberTypeId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'   => !empty($strError),
            'message' => !empty($strError) ? $strError : $this->_tr->translate('Done.')
        );
        return $view->setVariables($arrResult);
    }

    public function getFieldInfoAction()
    {
        $view         = new JsonModel();
        $arrFieldInfo = array();
        $strError     = '';
        try {
            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $updateFieldId = $updateGroupId = 0;
            $fieldId       = Json::decode($this->findParam('field_id'), Json::TYPE_ARRAY);
            if (!empty($fieldId)) {
                if (preg_match('/^field_([\d]{1,})_([\d]{1,})$/i', $fieldId, $regs)) {
                    $updateGroupId = $regs[1];
                    $updateFieldId = $regs[2];
                } else {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                }

                if (empty($strError) && !empty($updateFieldId)) {
                    $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldInfo($updateFieldId, $companyId);
                    if (!is_array($arrFieldInfo) || $companyId != $arrFieldInfo['company_id']) {
                        $arrFieldInfo = array();
                        $strError     = $this->_tr->translate('Internal error.');
                    } else {
                        $arrOrderInfo                 = $this->_clients->getApplicantFields()->getOrderInfo($updateFieldId, $updateGroupId);
                        $arrFieldInfo['use_full_row'] = $arrOrderInfo['use_full_row'];
                    }
                }
            }

            if (empty($strError)) {
                $arrFieldInfo['field_default_access'] = $this->_clients->getApplicantFields()->getFieldDefaultAccessRights($companyId, $updateFieldId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            "error"      => !empty($strError),
            "message"    => !empty($strError) ? $strError : '',
            "field_info" => $arrFieldInfo
        );
        return $view->setVariables($arrResult);
    }

    public function deleteFieldAction()
    {
        $view = new JsonModel();
        try {
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && empty($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrect params.');
            }

            $groupId = Json::decode($this->findParam('group_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrCompanyGroups = $this->_clients->getApplicantFields()->getCompanyGroups($companyId, $memberTypeId);
                $arrGroupIds      = $this->_settings::arrayColumnAsKey('applicant_group_id', $arrCompanyGroups, 'applicant_group_id');

                if (!empty($groupId) && !in_array($groupId, $arrGroupIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected group.');
                }
            }

            $arrFieldInfo = array();
            $fieldId      = Json::decode($this->findParam('field_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldInfo($fieldId, $companyId, Json::TYPE_ARRAY);
                if (!is_array($arrFieldInfo) || $companyId != $arrFieldInfo['company_id']) {
                    $arrFieldInfo = array();
                    $strError     = $this->_tr->translate('Incorrectly selected field.');
                }
            }

            $booDeleted = false;
            if (empty($strError)) {
                // If we try to delete a 'contact' field -
                // We can really delete it only for Contact section
                // Otherwise just remove it from Order/Access tables
                $booDeleteField = true;
                if (!empty($groupId)) {
                    $arrGroupInfo = $this->_clients->getApplicantFields()->getGroupInfoById($groupId);
                    if ($arrGroupInfo['member_type_id'] != $arrFieldInfo['member_type_id']) {
                        $booDeleteField = false;
                    }
                }

                if ($booDeleteField) {
                    $booDeleted = $this->_clients->getApplicantFields()->deleteField($fieldId, $companyId);
                } else {
                    $booDeleted = $this->_clients->getApplicantFields()->unassignField($fieldId, $groupId, $companyId);
                }
            }

            if (empty($strError) && !$booDeleted) {
                $strError = $this->_tr->translate('Incorrectly selected field');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            "error"           => !empty($strError),
            "message"         => !empty($strError) ? $strError : $this->_tr->translate('Field was successfully deleted.'),
            "additional_info" => array()
        );
        return $view->setVariables($arrResult);
    }

    public function saveOrderAction()
    {
        $view = new JsonModel();
        try {
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && empty($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrect params.');
            }

            $applicantTypeId          = (int)$this->findParam('applicant_type_id', 0);
            $arrCompanyApplicantTypes = $this->_clients->getApplicantTypes()->getTypes($companyId, true);
            if (empty($strError) && !empty($applicantTypeId) && !in_array($applicantTypeId, $arrCompanyApplicantTypes)) {
                $strError = $this->_tr->translate('Incorrectly passed applicant type.');
            }


            // Check incoming blocks list
            $arrBlocksOrder = Json::decode($this->findParam('blocks_order'), Json::TYPE_ARRAY);
            if (empty($strError) && !is_array($arrBlocksOrder)) {
                $strError = $this->_tr->translate('Incorrect blocks list.');
            }

            if (empty($strError)) {
                $arrBlocks   = $this->_clients->getApplicantFields()->getCompanyBlocks($companyId, $memberTypeId, $applicantTypeId);
                $arrBlockIds = $this->_settings::arrayColumnAsKey('applicant_block_id', $arrBlocks, 'applicant_block_id');

                foreach ($arrBlocksOrder as $arrBlockOrderInfo) {
                    // Check block id
                    if (!in_array($arrBlockOrderInfo['block_id'], $arrBlockIds)) {
                        $strError = $this->_tr->translate('Incorrectly selected block.');
                        break;
                    }

                    // Check row
                    if (!is_numeric($arrBlockOrderInfo['row'])) {
                        $strError = $this->_tr->translate('Incorrect block order data.');
                        break;
                    }
                }
            }


            // Check incoming fields list
            $arrFieldsOrder = Json::decode($this->findParam('fields_order'), Json::TYPE_ARRAY);
            if (empty($strError) && !is_array($arrFieldsOrder)) {
                $strError = $this->_tr->translate('Incorrect fields list.');
            }

            $arrFieldIds = array();
            $arrGroupIds = array();
            if (empty($strError) && count($arrFieldsOrder)) {
                $arrCompanyFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $memberTypeId, $applicantTypeId);
                $arrFieldIds      = $this->_settings::arrayColumnAsKey('applicant_field_id', $arrCompanyFields, 'applicant_field_id');

                $arrCompanyGroups = $this->_clients->getApplicantFields()->getCompanyGroups($companyId, $memberTypeId, $applicantTypeId);
                $arrGroupIds      = $this->_settings::arrayColumnAsKey('applicant_group_id', $arrCompanyGroups, 'applicant_group_id');

                foreach ($arrFieldsOrder as $fieldOrderInfo) {
                    // Check if passed field id is correct
                    $booCorrectField = false;
                    if (preg_match('/^field_([\d]{1,})_([\d]{1,})$/i', $fieldOrderInfo['field_id'], $regs)) {
                        if (in_array($regs[2], $arrFieldIds)) {
                            $booCorrectField = true;
                        }
                    }

                    if (!$booCorrectField) {
                        $strError = $this->_tr->translate('Incorrect field id.');
                        break;
                    }


                    // Check if passed group id is correct
                    $booCorrectGroup = false;
                    if (preg_match('/^fields_group_([\d]{1,})$/i', $fieldOrderInfo['group_id'], $regs)) {
                        if (in_array($regs[1], $arrGroupIds) || empty($regs[1])) {
                            $booCorrectGroup = true;
                        }
                    }

                    if (!$booCorrectGroup) {
                        $strError = $this->_tr->translate('Incorrect group id.');
                        break;
                    }

                    // Check row/col
                    if (!is_numeric($fieldOrderInfo['field_col']) || !is_numeric($fieldOrderInfo['field_row'])) {
                        $strError = $this->_tr->translate('Incorrect field order data.');
                        break;
                    }
                }
            }


            // Save/update BLOCKS order info
            if (empty($strError) && !empty($arrBlocksOrder) && is_array($arrBlocksOrder)) {
                foreach ($arrBlocksOrder as $arrBlockOrderInfo) {
                    $this->_db2->update(
                        'applicant_form_blocks',
                        ['order' => $arrBlockOrderInfo['row']],
                        ['applicant_block_id' => (int)$arrBlockOrderInfo['block_id']]
                    );
                }
            }

            // Save/update FIELDS order info
            if (empty($strError) && !empty($arrFieldsOrder) && is_array($arrFieldsOrder)) {
                $this->_db2->delete('applicant_form_order', ['applicant_group_id' => $arrGroupIds]);

                $currentGroupId = 0;
                $fieldOrder     = 0;
                foreach ($arrFieldsOrder as $fieldOrderInfo) {
                    $updateFieldId = 0;
                    if (preg_match('/^field_([\d]{1,})_([\d]{1,})$/i', $fieldOrderInfo['field_id'], $regs)) {
                        $updateFieldId = $regs[2];
                    }

                    $updateGroupId = str_replace('fields_group_', '', $fieldOrderInfo['group_id']);
                    if ($currentGroupId != $updateGroupId) {
                        $currentGroupId = $updateGroupId;
                        $fieldOrder     = 0;
                    } else {
                        $fieldOrder++;
                    }

                    if (in_array($updateGroupId, $arrGroupIds) && !empty($updateFieldId) && in_array($updateFieldId, $arrFieldIds)) {
                        $arrValues                       = array();
                        $arrValues['applicant_group_id'] = (int)$updateGroupId;
                        $arrValues['applicant_field_id'] = (int)$updateFieldId;
                        $arrValues['use_full_row']       = $fieldOrderInfo['field_use_full_row'] ? 'Y' : 'N';
                        $arrValues['field_order']        = $fieldOrder;

                        $this->_db2->insert('applicant_form_order', $arrValues);
                    }
                }
            }

            if (empty($strError)) {
                $this->_clients->getApplicantFields()->clearCache($companyId, $memberTypeId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'   => !empty($strError),
            'message' => !empty($strError) ? $strError : $this->_tr->translate('Done.')
        );
        return $view->setVariables($arrResult);
    }

    public function editFieldAction()
    {
        set_time_limit(10 * 60); // 10 minutes
        ini_set('memory_limit', '512M');

        if ($this->getRequest()->isXmlHttpRequest()) {
            $view = new JsonModel();
        } else {
            // Exit, if this is not Ajax Request
            $view = new ViewModel(
                [
                    'content' => null
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }

        $arrAdditionalInfo = array();
        $strError          = '';

        try {
            // Check if user has access to this company
            $companyId      = (int)Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $adminCompanyId = $this->_auth->getCurrentUserCompanyId();
            if (!empty($adminCompanyId) && $companyId != $adminCompanyId) {
                $strError = $this->_tr->translate('Incorrectly selected company');
            }

            $memberType   = $this->_filter->filter(Json::decode($this->findParam('member_type'), Json::TYPE_ARRAY));
            $memberTypeId = $this->_clients->getMemberTypeIdByName($memberType);
            if (empty($strError) && !is_numeric($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected data.');
            }


            // Load and check all incoming parameters
            $groupId       = Json::decode($this->findParam('group_id', ''), Json::TYPE_ARRAY);
            $updateGroupId = str_replace('fields_group_', '', $groupId);

            if (empty($strError) && !empty($groupId) && !is_numeric($updateGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected group');
            }

            if (empty($strError)) {
                if (!empty($updateGroupId)) {
                    $arrGroupInfo        = $this->_clients->getApplicantFields()->getGroupInfoById($updateGroupId);
                    $booIsGroupInCompany = is_array($arrGroupInfo) && $arrGroupInfo['company_id'] == $companyId;
                } else {
                    $booIsGroupInCompany = true;
                }

                if (!$booIsGroupInCompany) {
                    $strError = $this->_tr->translate('Incorrectly selected group [err#2]');
                }
            }

            $updateFieldId = 0;
            if (empty($strError)) {
                $fieldId = Json::decode($this->findParam('field_id'), Json::TYPE_ARRAY);
                if (!empty($fieldId)) {
                    $fieldId = Json::decode($this->findParam('field_id'), Json::TYPE_ARRAY);
                    if (preg_match('/^field_([\d]{1,})_([\d]{1,})$/i', $fieldId, $regs)) {
                        $updateFieldId = $regs[2];
                    } else {
                        $strError = $this->_tr->translate('Incorrectly selected field');
                    }
                }

                if (empty($strError)) {
                    $booInGroup = empty($updateGroupId) ? true : $this->_clients->getApplicantFields()->isFieldInGroup($updateFieldId, $updateGroupId);
                    if (!empty($fieldId) && !$booInGroup) {
                        $strError = $this->_tr->translate('Incorrectly selected field');
                    }
                }
            }

            $arrFieldTypes = $this->_clients->getFieldTypes()->getFieldTypes($memberType);

            $fieldType = Json::decode($this->findParam('field_type'), Json::TYPE_ARRAY);

            $booCorrectFieldType = false;
            $booWithDefaultValue = false;
            $booAllowsEncryption = false;
            $booWithCustomHeight = false;
            foreach ($arrFieldTypes as $fTypeInfo) {
                if ($fTypeInfo['id'] == $fieldType) {
                    $booCorrectFieldType = true;
                    $booAllowsEncryption = $fTypeInfo['booCanBeEncrypted'];
                    $booWithDefaultValue = $fTypeInfo['booWithDefaultValue'];
                    $booWithCustomHeight = $fTypeInfo['booWithCustomHeight'];
                    break;
                }
            }

            if (empty($strError) && !$booCorrectFieldType) {
                $strError = $this->_tr->translate('Incorrect field type');
            }

            $booOfficeField = false;
            if ($fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('division') || $fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('office_multi')) {
                $booOfficeField = true;
            }

            if (empty($strError) && empty($fieldId) && $booOfficeField) {
                $strError = $this->_tr->translate('It is possible to create only one Divisions field');
            }


            $fieldLabel     = $this->_filter->filter(Json::decode($this->findParam('field_label'), Json::TYPE_ARRAY));
            $fieldCompanyId = trim($this->_filter->filter(Json::decode($this->findParam('field_company_id', ''), Json::TYPE_ARRAY)));

            // Remove not allowed chars
            $fieldCompanyId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $fieldCompanyId);

            $fieldMaxLength              = $this->findParam('field_maxlength');
            $fieldOptions                = Json::decode($this->findParam('field_options'), Json::TYPE_ARRAY);
            $fieldEncrypted              = Json::decode($this->findParam('field_encrypted'), Json::TYPE_ARRAY);
            $fieldRequired               = Json::decode($this->findParam('field_required'), Json::TYPE_ARRAY);
            $fieldRequiredForSubmission  = Json::decode($this->findParam('field_required_for_submission'), Json::TYPE_ARRAY);
            $fieldDisabled               = Json::decode($this->findParam('field_disabled'), Json::TYPE_ARRAY);
            $fieldUseFullRow             = Json::decode($this->findParam('field_use_full_row'), Json::TYPE_ARRAY);
            $fieldDefaultValue           = $this->_filter->filter(Json::decode($this->findParam('field_default_value'), Json::TYPE_ARRAY));
            $fieldCustomHeight           = (int)$this->findParam('field_custom_height');
            $fieldSkipAccessRequirements = Json::decode($this->findParam('field_skip_access_requirements'), Json::TYPE_ARRAY);
            $fieldMultipleValues         = Json::decode($this->findParam('field_multiple_values'), Json::TYPE_ARRAY);
            $fieldCanEditInGui           = Json::decode($this->findParam('field_can_edit_in_gui'), Json::TYPE_ARRAY);
            $fieldDefaultAccess          = Json::decode($this->findParam('field_default_access'), Json::TYPE_ARRAY);

            $fieldImageWidth  = (int)$this->findParam('field_image_width');
            $fieldImageHeight = (int)$this->findParam('field_image_height');
            if (empty($strError) && $fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('photo') && ($fieldImageWidth < 1 || $fieldImageHeight < 1)) {
                $strError = $this->_tr->translate('Incorrectly specified image size');
            }

            $arrFieldsInsert                             = array();
            $arrFieldsInsert['member_type_id']           = $memberTypeId;
            $arrFieldsInsert['type']                     = $this->_clients->getFieldTypes()->getStringFieldTypeById($fieldType);
            $arrFieldsInsert['label']                    = $fieldLabel;
            $arrFieldsInsert['encrypted']                = $fieldEncrypted ? 'Y' : 'N';
            $arrFieldsInsert['required']                 = $fieldRequired ? 'Y' : 'N';
            $arrFieldsInsert['required_for_submission']  = $fieldRequiredForSubmission ? 'Y' : 'N';
            $arrFieldsInsert['disabled']                 = $fieldDisabled ? 'Y' : 'N';
            $arrFieldsInsert['skip_access_requirements'] = $fieldSkipAccessRequirements ? 'Y' : 'N';
            $arrFieldsInsert['multiple_values']          = $fieldMultipleValues ? 'Y' : 'N';
            $arrFieldsInsert['can_edit_in_gui']          = $fieldCanEditInGui ? 'Y' : 'N';

            if (empty($fieldMaxLength) || is_numeric($fieldMaxLength)) {
                $arrFieldsInsert['maxlength'] = (int)$fieldMaxLength;
            }

            if ($booWithCustomHeight && (empty($fieldCustomHeight) || is_numeric($fieldCustomHeight))) {
                $arrFieldsInsert['custom_height'] = $fieldCustomHeight;
            }

            // Check if this field company id is correct and unique
            if (empty($strError) && empty($fieldId) && empty($fieldCompanyId)) {
                $strError = $this->_tr->translate('Incorrect field name');
            }

            if (empty($strError) && $fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('applicant_internal_id') && $fieldCompanyId != 'applicant_internal_id') {
                $strError = $this->_tr->translate('Incorrect field name. Create the field with the name "applicant_internal_id"');
            }

            if (empty($strError) && empty($fieldId)) {
                $savedId = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId($fieldCompanyId, $memberTypeId, $companyId);
                if (!empty($savedId)) {
                    $strError = $this->_tr->translate('This field name is already in use. Please enter other field name and try again.');
                }
            }

            $booWasEncrypted = false;
            // Because encrypted fields cannot be used in the advanced searches -
            // check if this field is used in any of the saved searches
            if (empty($strError) && !empty($fieldId)) {
                $arrSavedFieldInfo = $this->_clients->getApplicantFields()->getFieldInfo($updateFieldId, $companyId, true);
                $booWasEncrypted   = $arrSavedFieldInfo['encrypted'] == 'Y';

                if (!$booWasEncrypted && $fieldEncrypted && $this->_clients->getSearch()->isFieldUsedInSearch($companyId, $fieldCompanyId)) {
                    $strError = $this->_tr->translate(
                        'This field is already in use in the advanced searches.<br/>' .
                        'It is not possible to enable Encryption option.<br/><br/>' .
                        'Please remove this field from all saved advanced searches and try again.'
                    );
                }
            }

            $arrFieldDefaultAccess = array();
            if (empty($strError)) {
                $arrRoles = $this->_roles->getCompanyRoles($companyId, 0, false, true);
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
            }

            if (empty($strError)) {
                if (empty($fieldId)) {
                    $arrFieldsInsert['company_id']                = $companyId;
                    $arrFieldsInsert['applicant_field_unique_id'] = $fieldCompanyId;

                    // This is a new field, create it
                    $updateFieldId = $this->_db2->insert('applicant_form_fields', $arrFieldsInsert);


                    // Allow access for this field for company admin
                    if (!empty($updateGroupId)) {
                        $this->_clients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $updateFieldId, $updateGroupId, $arrFieldDefaultAccess);
                    }

                    // Create record in field orders table
                    if (!empty($updateGroupId)) {
                        $query  = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE applicant_group_id = %d)', 'applicant_form_order', $updateGroupId);
                        $maxRow = new Expression($query);

                        $arrOrderInsert = array(
                            'applicant_group_id' => $updateGroupId,
                            'applicant_field_id' => $updateFieldId,
                            'use_full_row'       => $fieldUseFullRow ? 'Y' : 'N',
                            'field_order'        => $maxRow
                        );

                        $this->_db2->insert('applicant_form_order', $arrOrderInsert);
                    }

                    // Now insert new default values
                    $arrFieldsDefaultInsert = array(
                        'applicant_field_id' => $updateFieldId
                    );

                    if ($fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('photo')) {
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

                    if ($booWithDefaultValue) {
                        $arrFieldsDefaultInsert['value'] = $fieldDefaultValue;
                        $arrFieldsDefaultInsert['order'] = 0;
                        $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                    } else {
                        if (!empty($fieldOptions) && is_array($fieldOptions)) {
                            foreach ($fieldOptions as $arrOptionInfo) {
                                $arrFieldsDefaultInsert['value'] = $this->_filter->filter($arrOptionInfo['name']);
                                $arrFieldsDefaultInsert['order'] = $this->_filter->filter($arrOptionInfo['order']);
                                $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                            }
                        }
                    }


                    // Check if this is 'Default' company - create field for all other companies
                    if (empty($companyId)) {
                        // 1. Get all companies list
                        $select = (new Select())
                            ->from('company')
                            ->columns(['company_id'])
                            ->where([(new Where())->notEqualTo('company_id', $this->_company->getDefaultCompanyId())]);

                        $arrCompanies = $this->_db2->fetchAll($select);

                        if (is_array($arrCompanies) && !empty($arrCompanies)) {
                            foreach ($arrCompanies as $companyInfo) {
                                $newFieldCompanyId = $companyInfo['company_id'];

                                // 2. Generate field id
                                $newCompanyFieldId = $arrFieldsInsert['applicant_field_unique_id'];

                                // Check if this field id is unique
                                $booUnique = false;
                                $count     = 0;
                                while (!$booUnique) {
                                    $testFieldId = $newCompanyFieldId;
                                    if (!empty($count)) {
                                        $testFieldId .= '_' . $count;
                                    }

                                    $savedId = $this->_clients->getApplicantFields()->getCompanyFieldIdByUniqueFieldId($testFieldId, $memberTypeId, $newFieldCompanyId);
                                    if (empty($savedId)) {
                                        $booUnique         = true;
                                        $newCompanyFieldId = $testFieldId;
                                    } else {
                                        $count++;
                                    }
                                }


                                // 3. Create new field for company
                                $companyInfoNewFieldInfo                              = $arrFieldsInsert;
                                $companyInfoNewFieldInfo['company_id']                = $newFieldCompanyId;
                                $companyInfoNewFieldInfo['applicant_field_unique_id'] = $newCompanyFieldId;
                                unset($companyInfoNewFieldInfo['applicant_field_id']);

                                $newFieldId = $this->_db2->insert('applicant_form_fields', $companyInfoNewFieldInfo);


                                // 4. Insert default values
                                $arrFieldsDefaultInsert                       = array();
                                $arrFieldsDefaultInsert['applicant_field_id'] = $newFieldId;
                                if ($booWithDefaultValue) {
                                    $arrFieldsDefaultInsert['value'] = $fieldDefaultValue;
                                    $arrFieldsDefaultInsert['order'] = 0;
                                    $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                                } else {
                                    if (!empty($fieldOptions) && is_array($fieldOptions)) {
                                        foreach ($fieldOptions as $arrOptionInfo) {
                                            $arrFieldsDefaultInsert['value'] = $arrOptionInfo['name'];
                                            $arrFieldsDefaultInsert['order'] = $arrOptionInfo['order'];
                                            $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // If field encryption setting was changed - we need to update data, i.e.:
                    // * if data was already encrypted - we need decrypt and save it
                    // * if data wasn't encrypted - we need encrypt and save it
                    if ($booAllowsEncryption && $booWasEncrypted != $fieldEncrypted) {
                        $arrSavedData = $this->_clients->getApplicantFields()->getFieldData($updateFieldId, 0, false);
                        foreach ($arrSavedData as $arrSavedDataRow) {
                            $updatedValue = $booWasEncrypted ?
                                $this->_encryption->decode($arrSavedDataRow['value']) :
                                $this->_encryption->encode($arrSavedDataRow['value']);

                            // Generate 'Where'
                            $arrWhere = array(
                                'applicant_id'       => (int)$arrSavedDataRow['applicant_id'],
                                'applicant_field_id' => (int)$arrSavedDataRow['applicant_field_id']
                            );

                            if (!empty($arrSavedDataRow['row'])) {
                                $arrWhere['row'] = (int)$arrSavedDataRow['row'];
                            }

                            if (!empty($arrSavedDataRow['row_id'])) {
                                $arrWhere['row_id'] = $arrSavedDataRow['row_id'];
                            }

                            $this->_db2->update('applicant_form_data', ['value' => $updatedValue], $arrWhere);
                        }
                    }


                    if ($booOfficeField) {
                        // Update this specific field info

                        // Update field label only
                        $this->_db2->update(
                            'applicant_form_fields',
                            ['label' => $arrFieldsInsert['label']],
                            ['applicant_field_id' => $updateFieldId]
                        );

                        $arrOptionsIds     = array();
                        $oCompanyDivisions = $this->_company->getCompanyDivisions();
                        $divisionGroupId   = $oCompanyDivisions->getCompanyMainDivisionGroupId($companyId);
                        if (!empty($fieldOptions) && is_array($fieldOptions)) {
                            foreach ($fieldOptions as $arrOptionInfo) {
                                $arrOptionsIds[] = $oCompanyDivisions->createUpdateDivision(
                                    $companyId,
                                    $divisionGroupId,
                                    $arrOptionInfo['id'],
                                    $arrOptionInfo['name'],
                                    $arrOptionInfo['order']
                                );
                            }
                        }

                        $oCompanyDivisions->deleteCompanyDivisions($companyId, $divisionGroupId, $arrOptionsIds);

                        // Delete assigned divisions for this company for all company members
                        $arrMembersIds = $this->_company->getCompanyMembersIds($companyId);
                        if (is_array($arrMembersIds) && !empty($arrMembersIds)) {
                            $arrWhere2 = array(
                                'member_id' => $arrMembersIds
                            );

                            if (!empty($arrOptionsIds)) {
                                $arrWhere2[] = (new Where())->notIn('division_id', $arrOptionsIds);
                            }

                            $this->_db2->delete('members_divisions', $arrWhere2);
                        }
                    } else {
                        // Update field
                        $this->_db2->update('applicant_form_fields', $arrFieldsInsert, ['applicant_field_id' => $updateFieldId]);

                        // Update order info
                        $this->_db2->update(
                            'applicant_form_order',
                            [
                                'use_full_row' => $fieldUseFullRow ? 'Y' : 'N'
                            ],
                            [
                                'applicant_field_id' => $updateFieldId,
                                'applicant_group_id' => $updateGroupId
                            ]
                        );

                        if ($memberType == 'internal_contact') {
                            $select = (new Select())
                                ->from('applicant_form_groups')
                                ->columns(['applicant_group_id'])
                                ->where(['company_id' => $companyId]);

                            $arrApplicantFormGroupsIds = $this->_db2->fetchCol($select);

                            if (is_array($arrApplicantFormGroupsIds)) {
                                $this->_db2->update(
                                    'applicant_form_order',
                                    ['use_full_row' => $fieldUseFullRow ? 'Y' : 'N'],
                                    [
                                        'applicant_field_id' => $updateFieldId,
                                        'applicant_group_id' => $arrApplicantFormGroupsIds
                                    ]
                                );
                            }
                        }

                        // Update/Create new default values
                        if ($booWithDefaultValue) {
                            $arrFieldsDefaultInsert['applicant_field_id'] = $updateFieldId;
                            $arrFieldsDefaultInsert['value']              = $fieldDefaultValue;
                            $arrFieldsDefaultInsert['order']              = 0;

                            $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                        } else {
                            // update image field
                            if ($fieldType == $this->_clients->getFieldTypes()->getFieldTypeId('photo')) {
                                $this->_db2->update(
                                    'applicant_form_default',
                                    ['value' => $fieldImageWidth],
                                    [
                                        'applicant_field_id' => $updateFieldId,
                                        'order'              => 0
                                    ]
                                );

                                $this->_db2->update(
                                    'applicant_form_default',
                                    ['value' => $fieldImageHeight],
                                    [
                                        'applicant_field_id' => $updateFieldId,
                                        'order'              => 1
                                    ]
                                );
                            } else {
                                $result = $this->_clients->getApplicantFields()->updateFieldDefaultOptions($updateFieldId, $fieldOptions);
                                if (is_string($result)) {
                                    $strError = $result;
                                }
                            }
                        }
                    }
                }

                if (empty($strError)) {
                    $this->_clients->getApplicantFields()->updateDefaultAccessRights($updateFieldId, $arrFieldDefaultAccess);
                }

                $this->_clients->getApplicantFields()->clearCache($companyId, $memberTypeId);
                if ($memberType == 'internal_contact') {
                    $this->_clients->getApplicantFields()->clearCache($companyId, $this->_clients->getMemberTypeIdByName('individual'));
                    $this->_clients->getApplicantFields()->clearCache($companyId, $this->_clients->getMemberTypeIdByName('employer'));
                }
            }

            $arrAdditionalInfo['field_id']                       = $updateFieldId;
            $arrAdditionalInfo['field_name']                     = $fieldLabel;
            $arrAdditionalInfo['field_encrypted']                = $fieldEncrypted;
            $arrAdditionalInfo['field_required']                 = $fieldRequired;
            $arrAdditionalInfo['field_required_for_submission']  = $fieldRequiredForSubmission;
            $arrAdditionalInfo['field_disabled']                 = $fieldDisabled;
            $arrAdditionalInfo['group_id']                       = $updateGroupId;
            $arrAdditionalInfo['field_skip_access_requirements'] = $fieldSkipAccessRequirements;
            $arrAdditionalInfo['field_multiple_values']          = $fieldMultipleValues;
            $arrAdditionalInfo['field_can_edit_in_gui']          = $fieldCanEditInGui;
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            "error"           => !empty($strError),
            "message"         => !empty($strError) ? $strError : $this->_tr->translate('Information was successfully updated'),
            "additional_info" => empty($strError) ? $arrAdditionalInfo : array()
        );

        return $view->setVariables($arrResult);
    }

    public function manageOptionsAction()
    {
        $strError       = '';
        $booRefreshPage = false;

        try {
            $fieldId    = (int)Json::decode($this->params()->fromPost('field_id'), Json::TYPE_ARRAY);
            $arrOptions = Json::decode($this->params()->fromPost('options_list'), Json::TYPE_ARRAY);

            $oFields = $this->_clients->getApplicantFields();
            if (!$oFields->hasCurrentMemberAccessToFieldById($fieldId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrFieldInfo = $oFields->getFieldInfo($fieldId, 0, true);
                if ($arrFieldInfo['can_edit_in_gui'] !== 'Y') {
                    $strError = $this->_tr->translate('Field does not allow options changing.');
                }
            }

            $arrFormattedOptions = [];
            if (empty($strError)) {
                $arrSavedOptionsIds = [];

                $arrSavedOptions = $oFields->getFieldsOptions($fieldId);
                foreach ($arrSavedOptions as $arrSavedOptionInfo) {
                    $arrSavedOptionsIds[] = $arrSavedOptionInfo['applicant_form_default_id'];
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
                $result = $oFields->updateFieldDefaultOptions($fieldId, $arrFormattedOptions);
                if (is_string($result)) {
                    // Some deleted options are used by clients
                    $strError = $result;
                } else {
                    $booRefreshPage = $result;
                }
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
