<?php

namespace Superadmin\Controller;

use Exception;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;

/**
 * Manage Offices Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageOfficesController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_files      = $services[Files::class];
        $this->_roles      = $services[Roles::class];
        $this->_encryption = $services[Encryption::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $officeLabel       = '';
        $officeLabelPlural = '';

        $arrRoles   = array();
        $arrFolders = array();

        $booAuthorizedAgentsManagementEnabled = false;

        try {
            $officeLabel       = $this->_company->getCurrentCompanyDefaultLabel('office');
            $officeLabelPlural = $this->_company->getCurrentCompanyDefaultLabel('office', true);

            // Load list of roles - so will be possible to automatically allow access to the new office
            $companyId = $this->_auth->getCurrentUserCompanyId();

            $booShowWithoutGroup = $this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
            if (!$booShowWithoutGroup) {
                // Current user is system - don't show
                $arrDivisionGroupInfo = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
                if (isset($arrDivisionGroupInfo['division_group_is_system'])) {
                    $booShowWithoutGroup = $arrDivisionGroupInfo['division_group_is_system'] == 'N';
                }
            }

            $arrAllRoles = $this->_roles->getCompanyRoles(
                $companyId,
                $this->_auth->getCurrentUserDivisionGroupId(),
                $booShowWithoutGroup,
                false,
                array('admin', 'user')
            );

            // Return only specific info, not all
            foreach ($arrAllRoles as $arrAllRolesInfo) {
                $arrRoles[] = array(
                    'role_id'   => $arrAllRolesInfo['role_id'],
                    'role_name' => $arrAllRolesInfo['role_name'],
                    'role_type' => $arrAllRolesInfo['role_type'],
                );
            }

            // Load list of top folders in the Shared Workspace
            $arrTopSharedFolders = $this->_files->getSharedTopFolders($companyId, $this->_company->isCompanyStorageLocationLocal($companyId));
            foreach ($arrTopSharedFolders as $subFolderName) {
                $arrFolders[] = array(
                    'folder_id'   => $this->_encryption->encode($subFolderName),
                    'folder_name' => $subFolderName
                );
            }

            $booAuthorizedAgentsManagementEnabled = $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled() && !$this->_auth->isCurrentUserAuthorizedAgent();
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $this->layout()->setVariable('title', $this->_tr->translate('Manage') . ' ' . $officeLabelPlural);
        $view->setVariable('officeLabel', $officeLabel);
        $view->setVariable('booAuthorizedAgentsManagementEnabled', $booAuthorizedAgentsManagementEnabled);
        $view->setVariable('arrRoles', $arrRoles);
        $view->setVariable('arrFolders', $arrFolders);

        return $view;
    }

    public function getListAction()
    {
        $view = new JsonModel();
        $arrOffices = array();

        try {
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
            if ($this->_company->hasCompanyDivisions($companyId)) {
                $arrOffices = $this->_company->getDivisions($companyId, $divisionGroupId);

                // Sort by order
                $order = array();
                foreach ($arrOffices as $key => $row) {
                    $order[$key] = $row['order'];

                    $arrOffices[$key]['folders_no_access'] = $this->_company->getCompanyDivisions()->getFoldersByAccessToDivision($row['division_id'], '');
                }
                array_multisort($order, SORT_ASC, $arrOffices);
            }

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrOffices,
            'totalCount' => count($arrOffices)
        );

        // Return invoices list
        return $view->setVariables($arrResult);
    }

    public function saveRecordAction()
    {
        $view = new JsonModel();
        $divisionId = 0;
        $strError   = '';

        try {
            $divisionId      = $this->params()->fromPost('division_id');
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
            if (!empty($divisionId) && !$this->_company->getCompanyDivisions()->hasAccessToDivision($divisionId, $divisionGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }

            if (empty($strError)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();

                $maxLength    = 255;
                $filter       = new StripTags();
                $divisionName = $filter->filter(trim($this->params()->fromPost('name', '')));
                $divisionName = strlen($divisionName) > $maxLength ? substr($divisionName, -1 * ($maxLength - 1)) : $divisionName;

                // Comma is not allowed in the name
                $divisionName = str_replace(',', '', $divisionName);

                if (empty($divisionName)) {
                    $strError = $this->_tr->translate('Name is a required field.');
                }


                $arrAssignToRoles = array();
                if (empty($strError) && empty($divisionId)) {
                    // Make sure that current user/admin has access to selected roles
                    $arrAssignToRoles = $this->params()->fromPost('assign_to_roles', '');

                    if (!empty($arrAssignToRoles)) {
                        $arrAssignToRoles = explode(',', $arrAssignToRoles);

                        $booShowWithoutGroup = $this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
                        if (!$booShowWithoutGroup) {
                            // Current user is system - don't show
                            $arrDivisionGroupInfo = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
                            if (isset($arrDivisionGroupInfo['division_group_is_system'])) {
                                $booShowWithoutGroup = $arrDivisionGroupInfo['division_group_is_system'] == 'N';
                            }
                        }

                        $arrAllowedRolesIds = $this->_roles->getCompanyRoles(
                            $companyId,
                            $divisionGroupId,
                            $booShowWithoutGroup,
                            true,
                            array('admin', 'user')
                        );

                        $arrIncorrectIds = array_diff($arrAssignToRoles, $arrAllowedRolesIds);

                        if (!empty($arrIncorrectIds)) {
                            $strError = $this->_tr->translate('Incorrectly selected roles.');
                        }
                    }
                }

                if (empty($strError)) {
                    $booCanOwnerEdit = null;
                    $booCanAssignTo  = null;
                    $booIsPermanent  = null;
                    if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                        $booCanOwnerEdit = $this->params()->fromPost('access_owner_can_edit') == 'on';
                        $booCanAssignTo  = $this->params()->fromPost('access_assign_to') == 'on';
                        $booIsPermanent  = $this->params()->fromPost('access_permanent') == 'on';
                    }

                    $divisionOrder = null;
                    if (empty($divisionId) && $this->_company->hasCompanyDivisions($companyId)) {
                        $arrOffices = $this->_company->getDivisions($companyId, $divisionGroupId);
                        foreach ($arrOffices as $arrOfficeInfo) {
                            $divisionOrder = max($divisionOrder, $arrOfficeInfo['order'] + 1);
                        }
                    }

                    $divisionId = $this->_company->getCompanyDivisions()->createUpdateDivision(
                        $companyId,
                        $divisionGroupId,
                        $divisionId,
                        $divisionName,
                        $divisionOrder,
                        $booCanOwnerEdit,
                        $booCanAssignTo,
                        $booIsPermanent,
                        $arrAssignToRoles
                    );

                    if (!$divisionId) {
                        $strError = $this->_tr->translate('Internal error.');
                    }


                    // Update access rights for the top Shared folders
                    $arrFolderAccess = explode(';', $this->params()->fromPost('folders_access', ''));
                    if (empty($strError)) {
                        $arrNoAccessFolders = array();

                        // Load list of top folders in the Shared Workspace
                        $arrTopSharedFolders = $this->_files->getSharedTopFolders($companyId, $this->_company->isCompanyStorageLocationLocal($companyId));
                        foreach ($arrTopSharedFolders as $subFolderName) {
                            $booFound = false;

                            foreach ($arrFolderAccess as $folderWithAccess) {
                                if ($this->_encryption->decode($folderWithAccess) == $subFolderName) {
                                    $booFound = true;
                                    break;
                                }
                            }

                            // If folder was not "checked" - this means that access to this folder was removed
                            if (!$booFound) {
                                $arrNoAccessFolders[] = $subFolderName;
                            }
                        }

                        $this->_company->getCompanyDivisions()->updateFoldersAccessForDivision($divisionId, $arrNoAccessFolders);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'division_id' => $divisionId,
            'success'     => empty($strError),
            'message'     => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function moveRecordAction()
    {
        $view = new JsonModel();
        $strError = '';

        try {
            $divisionId      = Json::decode($this->params()->fromPost('division_id'), Json::TYPE_ARRAY);
            $booUp           = Json::decode($this->params()->fromPost('direction_up'), Json::TYPE_ARRAY);
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
            if (!$this->_company->getCompanyDivisions()->hasAccessToDivision($divisionId, $divisionGroupId)) {
                $strError = $this->_tr->translate('Incorrectly selected record');
            }

            if (empty($strError) && !$this->_company->hasCompanyDivisions($companyId)) {
                $strError = $this->_tr->translate('There are no records.');
            }

            if (empty($strError)) {
                $arrOffices = $this->_company->getDivisions($companyId, $divisionGroupId);

                // Sort by order
                $order = array();
                foreach ($arrOffices as $key => $row) {
                    $order[$key] = $row['order'];
                }
                array_multisort($order, SORT_ASC, $arrOffices);

                foreach ($arrOffices as $key => $arrOfficeInfo) {
                    if ($arrOfficeInfo['division_id'] == $divisionId) {
                        if ($booUp) {
                            $order = $arrOfficeInfo['order'] - 1;
                            $order = max($order, 0);
                            $this->_company->getCompanyDivisions()->createUpdateDivision($companyId, $divisionGroupId, $divisionId, null, $order);

                            if (isset($arrOffices[$key - 1])) {
                                $this->_company->getCompanyDivisions()->createUpdateDivision($companyId, $divisionGroupId, $arrOffices[$key - 1]['division_id'], null, $order + 1);
                            }
                        } else {
                            $order = $arrOfficeInfo['order'] + 1;
                            $order = max($order, 0);
                            $this->_company->getCompanyDivisions()->createUpdateDivision($companyId, $divisionGroupId, $divisionId, null, $order);

                            if (isset($arrOffices[$key + 1])) {
                                $order = $order - 1;
                                $order = max($order, 0);
                                $this->_company->getCompanyDivisions()->createUpdateDivision($companyId, $divisionGroupId, $arrOffices[$key + 1]['division_id'], null, $order);
                            }
                        }
                        break;
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

        return $view->setVariables($arrResult);
    }

    public function deleteRecordAction()
    {
        $strError   = '';
        $strSuccess = '';

        try {
            $divisionId      = Json::decode($this->params()->fromPost('division_id'), Json::TYPE_ARRAY);
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
            $officeLabel     = $this->_company->getCurrentCompanyDefaultLabel('office');

            if (!$this->_company->getCompanyDivisions()->hasAccessToDivision($divisionId, $divisionGroupId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_company->hasCompanyDivisions($companyId)) {
                $strError = $this->_tr->translate('There are no records.');
            }

            if (empty($strError)) {
                $arrDivisionInfo = $this->_company->getCompanyDivisions()->getDivisionById($divisionId);

                $arrTypesToCheck = array(
                    'client' => 'clients',
                    'user'   => 'users',
                );

                foreach ($arrTypesToCheck as $userType => $label) {
                    $strError = $this->_company->getCompanyDivisions()->canDeleteDivision($divisionId, $userType, $officeLabel, $arrDivisionInfo['name']);
                    if (!empty($strError)) {
                        break;
                    }
                }
            }

            if (empty($strError)) {
                // Check if there are users without assigned divisions (except this one)
                $arrUsersWithoutAssignedDivisions = $this->_company->getCompanyDivisions()->getUsersWithoutDivisions($companyId, $divisionId);
                if (!empty($arrUsersWithoutAssignedDivisions)) {
                    $strError = sprintf(
                        $this->_tr->translate('The following users are only assigned to this %s. Please change to some other %s and try again.<br><br>%s'),
                        $officeLabel,
                        $officeLabel,
                        implode(', ', $arrUsersWithoutAssignedDivisions)
                    );
                }
            }

            if (empty($strError)) {
                if ($this->_company->getCompanyDivisions()->deleteDivisions($divisionId, $divisionGroupId)) {
                    $strSuccess = sprintf(
                        $this->_tr->translate('%s was deleted.'),
                        $officeLabel
                    );
                } else {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $strSuccess : $strError
        );

        return new JsonModel($arrResult);
    }
}
