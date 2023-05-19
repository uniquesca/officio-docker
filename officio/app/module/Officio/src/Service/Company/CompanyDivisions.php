<?php

namespace Officio\Service\Company;

use Clients\Service\Clients;
use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Common\Service\Settings;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyDivisions extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Company */
    protected $_parent;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_roles      = $services[Roles::class];
        $this->_encryption = $services[Encryption::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Check if Authorized Agents Management is enabled
     *
     * @return bool true if enabled, otherwise false
     */
    public function isAuthorizedAgentsManagementEnabled()
    {
        return !empty($this->_config['site_version']['authorised_agents_management_enabled']);
    }


    /**
     * Check if current user can submit client to the Government
     *
     * @return bool true if can submit
     */
    public function canCurrentMemberSubmitClientToGovernment()
    {
        $booCanSubmitToGovernment = false;
        if ($this->isAuthorizedAgentsManagementEnabled()) {
            /** @var Members $oMembers */
            $oMembers = $this->_serviceContainer->get(Members::class);

            $arrDivisionGroupInfo     = $this->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
            $booCanSubmitToGovernment = isset($arrDivisionGroupInfo['division_group_is_system']) && $arrDivisionGroupInfo['division_group_is_system'] != 'Y';

            // Make sure that at least one Agent role is assigned
            if ($booCanSubmitToGovernment) {
                $arrUserRoles = $oMembers->getMemberRoles($this->_auth->getCurrentUserId(), false);

                $booCanSubmitToGovernment = false;
                foreach ($arrUserRoles as $arrUserRole) {
                    if (in_array($arrUserRole['role_name'], array(Roles::$agentUserRoleName, Roles::$agentAdminRoleName, Roles::$agentSubagentRoleName))) {
                        $booCanSubmitToGovernment = true;
                        break;
                    }
                }
            }
        }

        return $booCanSubmitToGovernment;
    }

    /**
     * Check if client was already submitted to the government
     *
     * @param $clientId
     * @return bool
     */
    public function isClientSubmittedToGovernment($clientId)
    {
        $booSubmitted = false;

        try {
            /** @var Clients $clients */
            $clients = $this->_serviceContainer->get(Clients::class);

            $companyId               = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId         = $this->getCompanyMainDivisionGroupId($companyId);
            $arrDivisionsInMainGroup = $this->getDivisionsByGroupId($divisionGroupId, false);

            // Get list of the divisions client must be assigned to
            $arrAssignToDivisions = array();
            $arrReturnedDivisions = array();

            foreach ($arrDivisionsInMainGroup as $arrDivisionInfo) {
                if (isset($arrDivisionInfo['access_assign_to']) && $arrDivisionInfo['access_assign_to'] == 'Y') {
                    $arrAssignToDivisions[] = $arrDivisionInfo['division_id'];
                }

                if (isset($arrDivisionInfo['access_owner_can_edit']) && $arrDivisionInfo['access_owner_can_edit'] == 'Y') {
                    $arrReturnedDivisions[] = $arrDivisionInfo['division_id'];
                }
            }

            if (!empty($arrAssignToDivisions)) {
                $arrClientDivisions = $clients->getApplicantOffices(array($clientId), $divisionGroupId);

                // Check if client was already submitted
                $arrNewDivisionsToBeAssigned = array();
                foreach ($arrAssignToDivisions as $arrAssignToDivisionId) {
                    if (!in_array($arrAssignToDivisionId, $arrClientDivisions)) {
                        $arrNewDivisionsToBeAssigned[] = $arrAssignToDivisionId;
                    }
                }

                $booReturned = false;

                foreach ($arrClientDivisions as $arrClientDivisionId) {
                    if (in_array($arrClientDivisionId, $arrReturnedDivisions)) {
                        $booReturned = true;
                        break;
                    }
                }

                if (empty($arrNewDivisionsToBeAssigned) && !$booReturned) {
                    $booSubmitted = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSubmitted;
    }

    /**
     * Check if current user has access to specific division
     *
     * @param int $divisionId
     * @param null $divisionGroupId
     * @return bool true if has access
     */
    public function hasAccessToDivision($divisionId, $divisionGroupId = null)
    {
        $booHasAccess = false;

        if (!empty($divisionId) && is_numeric($divisionId)) {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } else {
                $divisionGroupId = is_null($divisionGroupId) ? $this->_auth->getCurrentUserDivisionGroupId() : $divisionGroupId;
                $booHasAccess    = in_array($divisionId, $this->getDivisionsByGroupId($divisionGroupId));
            }
        }

        return $booHasAccess;
    }

    /**
     * Check if current user has access to specific division group
     *
     * @param int $divisionGroupId
     * @return bool true if has access
     */
    public function hasAccessToDivisionGroup($divisionGroupId)
    {
        $booHasAccess = false;

        if (!empty($divisionGroupId) && is_numeric($divisionGroupId) && $this->isAuthorizedAgentsManagementEnabled()) {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } else {
                $arrDivisionGroupInfo = $this->getDivisionsGroupInfo($divisionGroupId);
                if (isset($arrDivisionGroupInfo['company_id']) && $this->_auth->getCurrentUserCompanyId() == $arrDivisionGroupInfo['company_id']) {
                    $booHasAccess = true;
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Load division group name for specific user/client
     *
     * @param $memberId
     * @return string
     */
    public function getMemberDivisionGroupName($memberId)
    {
        $strDivisionGroupName = '';

        /** @var Members $members */
        $members = $this->_serviceContainer->get(Members::class);

        $arrClientInfo = $members->getMemberInfo($memberId);
        if (isset($arrClientInfo['division_group_id']) && !empty($arrClientInfo['division_group_id'])) {
            $arrDivisionGroupInfo = $this->getDivisionsGroupInfo($arrClientInfo['division_group_id']);
            if (isset($arrDivisionGroupInfo['division_group_company']) && !empty($arrDivisionGroupInfo['division_group_company'])) {
                $strDivisionGroupName = $arrDivisionGroupInfo['division_group_company'];
            }
        }

        return $strDivisionGroupName;
    }

    /**
     * Load division group name for the current user
     *
     * @return string
     */
    public function getCurrentMemberDivisionGroupName()
    {
        $strDivisionGroupName = '';

        $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
        if (!empty($divisionGroupId)) {
            $arrDivisionGroupInfo = $this->getDivisionsGroupInfo($divisionGroupId);
            if (isset($arrDivisionGroupInfo['division_group_company']) && !empty($arrDivisionGroupInfo['division_group_company'])) {
                $strDivisionGroupName = $arrDivisionGroupInfo['division_group_company'];
            }
        }

        return $strDivisionGroupName;
    }

    /**
     * Check if current member can edit specific client
     *
     * @param int|array $arrMemberIdsToCheck
     * @param null $currentMemberDivisionGroupId
     * @return bool true if current member has access, otherwise false
     */
    public function canCurrentMemberEditClient($arrMemberIdsToCheck, $currentMemberDivisionGroupId = null)
    {
        $booCanEdit = false;

        try {
            // Superadmin can edit all clients
            if ($this->_auth->isCurrentUserSuperadmin()) {
                return true;
            }

            /** @var Clients $oClients */
            $oClients = $this->_serviceContainer->get(Clients::class);

            $arrMemberIdsToCheck = is_array($arrMemberIdsToCheck) ? $arrMemberIdsToCheck : [$arrMemberIdsToCheck];

            // Client can edit only himself
            if ($this->_auth->isCurrentUserClient()) {
                $currentMemberId = $this->_auth->getCurrentUserId();
                $identity        = $this->_auth->getIdentity();

                $memberTypeName = $oClients->getMemberTypeNameById($identity->userType);
                switch ($memberTypeName) {
                    case 'employer':
                        // Employer has access to all assigned cases and to these cases parents (IAs)
                        $arrCases     = $oClients->getAssignedApplicants($currentMemberId);
                        $arrMemberIds = array_merge(array($currentMemberId), $arrCases);

                        list(, $arrParentIds) = $oClients->getParentsForAssignedCases($arrCases);
                        $arrMemberIds = array_merge($arrMemberIds, $arrParentIds);
                        break;

                    default:
                        // All others have access to assigned cases only
                        $arrChildren  = $oClients->getAssignedApplicants($currentMemberId);
                        $arrMemberIds = array_merge(array($currentMemberId), $arrChildren);
                        break;
                }

                foreach ($arrMemberIdsToCheck as $memberId) {
                    $booCanEdit = in_array($memberId, array_unique($arrMemberIds));
                    if (!$booCanEdit) {
                        break;
                    }
                }
            } else {
                // For all other user types check the assigned offices
                foreach ($arrMemberIdsToCheck as $memberId) {
                    // Compare current user and this member group ids
                    $arrMemberInfo      = $oClients->getMemberInfo($memberId);
                    $arrMemberDivisions = $oClients->getMemberDivisions($memberId);

                    $thisMemberDivisionGroupId    = $arrMemberInfo['division_group_id'];
                    $currentMemberDivisionGroupId = is_null($currentMemberDivisionGroupId) ? $this->_auth->getCurrentUserDivisionGroupId() : $currentMemberDivisionGroupId;

                    if ($currentMemberDivisionGroupId == $thisMemberDivisionGroupId) {
                        // That's an owner, check if there are offices from other division group which allow to edit this client
                        $arrGroupDivisions = $this->getDivisionsByGroupId($thisMemberDivisionGroupId);

                        $arrOtherGroupDivisions = array_diff($arrMemberDivisions, $arrGroupDivisions);

                        $arrOtherDivisions = $this->getDivisionsByIds($arrOtherGroupDivisions);
                        if (empty($arrOtherDivisions)) {
                            $booCanEdit = true;
                        } else {
                            foreach ($arrOtherDivisions as $arrOtherDivisionInfo) {
                                if ($arrOtherDivisionInfo['access_owner_can_edit'] == 'Y') {
                                    $booCanEdit = true;
                                    break;
                                }
                            }
                        }
                    } else {
                        // This is not an owner, so we can edit this client only if there are no assigned own divisions with "allow owner to edit"
                        $arrGroupDivisions = $this->getDivisionsByGroupId($currentMemberDivisionGroupId);

                        $arrOtherGroupDivisions = array_intersect($arrMemberDivisions, $arrGroupDivisions);

                        $arrOtherDivisions = $this->getDivisionsByIds($arrOtherGroupDivisions);

                        if (!empty($arrOtherDivisions)) {
                            $booCanEdit = true;
                            foreach ($arrOtherDivisions as $arrOtherDivisionInfo) {
                                if ($arrOtherDivisionInfo['access_owner_can_edit'] == 'Y') {
                                    $booCanEdit = false;
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booCanEdit;
    }

    /**
     * Get main division group id for specific company
     *
     * @param int $companyId
     * @return int
     */
    public function getCompanyMainDivisionGroupId($companyId)
    {
        $select = (new Select())
            ->from('divisions_groups')
            ->columns(['division_group_id'])
            ->where([
                'company_id'               => (int)$companyId,
                'division_group_is_system' => 'Y'
            ]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load information about division group
     *
     * @param int $divisionGroupId
     * @return array
     */
    public function getDivisionsGroupInfo($divisionGroupId)
    {
        $select = (new Select())
            ->from('divisions_groups')
            ->where(['division_group_id' => (int)$divisionGroupId]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Get list of groups of divisions for specific company
     *
     * @param int $companyId
     * @param null $booSystemOnly
     * @return array
     */
    public function getDivisionsGroups($companyId, $booSystemOnly = null)
    {
        $arrWhere = [];
        $arrWhere['company_id'] = (int)$companyId;

        if (!is_null($booSystemOnly)) {
            $arrWhere['division_group_is_system'] = $booSystemOnly ? 'Y' : 'N';
        }

        $select = (new Select())
            ->from('divisions_groups')
            ->where($arrWhere);

        $arrRecords = $this->_db2->fetchAll($select);

        // Generate hash - will be used to register new users for this specific division group
        foreach ($arrRecords as $key => $arrRecordInfo) {
            $arrRecords[$key]['division_group_registration_hash'] = urlencode($this->_encryption->encode($arrRecordInfo['division_group_id']));
        }

        return $arrRecords;
    }

    /**
     * Create/update division group
     *
     * @param $companyId
     * @param int|string $divisionGroupId
     * @param array $arrDivisionGroupInfo
     * @param bool $booCreateUpdateDivision
     * @return int of new/updated group id, empty on error
     */
    public function createUpdateDivisionsGroup($companyId, $divisionGroupId, $arrDivisionGroupInfo, $booCreateUpdateDivision = false)
    {
        try {
            $booSuccess = true;
            $this->_db2->getDriver()->getConnection()->beginTransaction();

            if (empty($divisionGroupId)) {
                $arrDivisionGroupInfo['company_id'] = $companyId;

                $divisionGroupId = $this->_db2->insert('divisions_groups', $arrDivisionGroupInfo);

                if ($booCreateUpdateDivision) {
                    $this->createUpdateDivision($companyId, $divisionGroupId, 0, $arrDivisionGroupInfo['division_group_company'], 0, true);

                    // Create 3 default roles if they were not created yet
                    $roleId = $this->_roles->createRoleIfDoesNotExists($companyId, Roles::$agentAdminRoleName, 'admin');
                    if (empty($roleId)) {
                        $booSuccess = false;
                    }

                    if ($booSuccess) {
                        $roleId = $this->_roles->createRoleIfDoesNotExists($companyId, Roles::$agentUserRoleName, 'user');
                        if (empty($roleId)) {
                            $booSuccess = false;
                        }
                    }

                    if ($booSuccess) {
                        $roleId = $this->_roles->createRoleIfDoesNotExists($companyId, Roles::$agentSubagentRoleName, 'user');
                        if (empty($roleId)) {
                            $booSuccess = false;
                        }
                    }
                }
            } else {
                $this->_db2->update('divisions_groups', $arrDivisionGroupInfo, ['division_group_id' => (int)$divisionGroupId]);

                // Update division's name in relation to the parent group
                // This was done here because it is not possible to edit/change divisions if they are not in the system group
                if ($booCreateUpdateDivision) {
                    $arrDivisions = $this->getDivisionsByGroupId($divisionGroupId);
                    if (empty($arrDivisions)) {
                        $this->createUpdateDivision($companyId, $divisionGroupId, 0, $arrDivisionGroupInfo['division_group_company'], 0);
                    } else {
                        foreach ($arrDivisions as $divisionId) {
                            $this->createUpdateDivision($companyId, $divisionGroupId, $divisionId, $arrDivisionGroupInfo['division_group_company']);
                        }
                    }
                }
            }

            // If all is okay - apply changes
            if ($booSuccess) {
                $this->_db2->getDriver()->getConnection()->commit();
            } else {
                $this->_db2->getDriver()->getConnection()->rollback();
            }
        } catch (Exception $e) {
            $this->_db2->getDriver()->getConnection()->rollback();
            $divisionGroupId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $divisionGroupId;
    }

    /**
     * Update division group status
     *
     * @param int $companyId
     * @param int $divisionGroupId
     * @param string $strStatus (one of 'active','inactive','suspended')
     * @return int updated group id, empty on error
     */
    public function updateDivisionsGroupStatus($companyId, $divisionGroupId, $strStatus)
    {
        try {
            $divisionGroupId = $this->createUpdateDivisionsGroup($companyId, $divisionGroupId, array('division_group_status' => $strStatus));
        } catch (Exception $e) {
            $divisionGroupId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $divisionGroupId;
    }

    /**
     * Assign specific division to the member
     *
     * @param int $memberId
     * @param int|array $arrDivisions
     * @param string $divisionType
     * @return bool true on success, otherwise false
     */
    public function addMemberDivision($memberId, $arrDivisions, $divisionType = 'access_to')
    {
        try {
            $arrDivisions = (array)$arrDivisions;
            foreach ($arrDivisions as $divisionId) {
                $this->_db2->insert(
                    'members_divisions',
                    [
                        'member_id'   => (int)$memberId,
                        'division_id' => (int)$divisionId,
                        'type'        => $divisionType,
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
     * Create or update division
     *
     * @param int $companyId
     * @param int $divisionGroupId
     * @param int|string $divisionId
     * @param string $divisionName
     * @param int $divisionOrder
     * @param null|bool $booCanOwnerEdit
     * @param null $booCanAssignTo
     * @param null $booIsPermanent
     * @param null $arrAssignToRoles
     * @return int created/updated division id, empty on error
     */
    public function createUpdateDivision($companyId, $divisionGroupId, $divisionId, $divisionName = null, $divisionOrder = null, $booCanOwnerEdit = null, $booCanAssignTo = null, $booIsPermanent = null, $arrAssignToRoles = null)
    {

        /** @var Members $members */
        $members = $this->_serviceContainer->get(Members::class);

        try {
            $arrDivisionInfo = array(
                'division_group_id' => empty($divisionGroupId) ? null : $divisionGroupId,
                'company_id'        => $companyId,
            );

            if (!is_null($divisionName)) {
                $arrDivisionInfo['name'] = $divisionName;
            }

            if (!is_null($divisionOrder)) {
                $arrDivisionInfo['order'] = $divisionOrder;
            }

            if (!is_null($booCanOwnerEdit)) {
                $arrDivisionInfo['access_owner_can_edit'] = $booCanOwnerEdit ? 'Y' : 'N';
            }

            if (!is_null($booCanAssignTo)) {
                $arrDivisionInfo['access_assign_to'] = $booCanAssignTo ? 'Y' : 'N';
            }

            if (!is_null($booIsPermanent)) {
                $arrDivisionInfo['access_permanent'] = $booIsPermanent ? 'Y' : 'N';
            }

            if (empty($divisionId)) {
                $divisionId = $this->_db2->insert('divisions', $arrDivisionInfo);

                if (!is_null($arrAssignToRoles)) {
                    // Automatically assign all admins and users that are assigned to the selected roles
                    $arrAdminsIds = empty($arrAssignToRoles) ? array() : $members->getMemberByRoleIds($arrAssignToRoles);
                } else {
                    // Automatically assign all admins from the same group to this new division
                    $arrAdminsIds = $this->_parent->getCompanyMembersIds($companyId, 'admin', false, $divisionGroupId);
                }

                $arrAdminsIds = is_array($arrAdminsIds) ? Settings::arrayUnique($arrAdminsIds) : $arrAdminsIds;
                foreach ($arrAdminsIds as $adminId) {
                    $this->addMemberDivision($adminId, $divisionId);
                }
            } else {
                unset($arrDivisionInfo['division_group_id'], $arrDivisionInfo['company_id']);
                if (array_key_exists('order', $arrDivisionInfo) && is_null($arrDivisionInfo['order'])) {
                    unset($arrDivisionInfo['order']);
                }

                $this->_db2->update('divisions', $arrDivisionInfo, ['division_id' => (int)$divisionId]);
            }
        } catch (Exception $e) {
            $divisionId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $divisionId;
    }

    /**
     * Load list of divisions' ids for specific group
     *
     * @param int $divisionGroupId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getDivisionsByGroupId($divisionGroupId, $booIdsOnly = true)
    {
        $arrResult = array();
        if (!empty($divisionGroupId) && is_numeric($divisionGroupId)) {
            $select = (new Select())
                ->from('divisions')
                ->columns([$booIdsOnly ? 'division_id' : Select::SQL_STAR])
                ->where(['division_group_id' => (int)$divisionGroupId]);

            $arrResult = $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
        }

        return $arrResult;
    }

    /**
     * Load divisions info by their ids
     *
     * @param array $arrDivisionsIds
     * @return array
     */
    public function getDivisionsByIds($arrDivisionsIds)
    {
        $arrResult = array();

        if (!empty($arrDivisionsIds)) {
            $select = (new Select())
                ->from('divisions')
                ->where(['division_id' => $arrDivisionsIds]);

            $arrResult = $this->_db2->fetchAll($select);
        }

        return $arrResult;
    }

    /**
     * Load division info by id
     *
     * @param int $divisionId
     * @return array
     */
    public function getDivisionById($divisionId)
    {
        $select = (new Select())
            ->from('divisions')
            ->where(['division_id' => $divisionId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Delete company divisions for specific group
     *
     * @param $companyId
     * @param $divisionGroupId
     * @param $arrExceptDivisionsIds
     * @return bool
     */
    public function deleteCompanyDivisions($companyId, $divisionGroupId, $arrExceptDivisionsIds)
    {
        try {
            $arrWhere = [
                'company_id' => (int)$companyId
            ];

            if (!empty($divisionGroupId)) {
                $arrWhere['division_group_id'] = (int)$divisionGroupId;
            }

            if (!empty($arrExceptDivisionsIds)) {
                $arrWhere[] = (new Where())->notIn('division_id', $arrExceptDivisionsIds);
            }

            // Delete all not used default options
            $this->_db2->delete('divisions', $arrWhere);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete specific divisions
     *
     * @param array|int $arrDivisionIds
     * @param null|int $divisionGroupId
     * @return bool
     */
    public function deleteDivisions($arrDivisionIds, $divisionGroupId = null)
    {
        try {
            $arrDivisionIds = (array)$arrDivisionIds;
            if (!empty($arrDivisionIds)) {
                $arrWhere = [
                    'division_id' => $arrDivisionIds
                ];

                if (!empty($divisionGroupId)) {
                    $arrWhere['division_group_id'] = (int)$divisionGroupId;
                }

                $this->_db2->delete('divisions', $arrWhere);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load list of member ids for which we want change status
     *
     * @param int $companyId
     * @param int $divisionGroupId
     * @param int $intStatus
     * @return array
     */
    public function getMembersToChangeStatus($companyId, $divisionGroupId, $intStatus)
    {
        try {
            $select = (new Select())
                ->from('members')
                ->columns(['member_id'])
                ->where([
                    'company_id'        => (int)$companyId,
                    'division_group_id' => (int)$divisionGroupId,
                    (new Where())->notIn('status', [0, $intStatus])
                ]);

            $arrIds = $this->_db2->fetchCol($select);
        } catch (Exception $e) {
            $arrIds = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrIds;
    }

    /**
     * Check if specific division can be deleted
     * - this can be done only if there are no assigned clients or users for that division
     * (+ there are some requirements)
     *
     * @param int $divisionId
     * @param string $userType
     * @param string $officeLabel
     * @param string $officeName
     * @return string error message if division cannot be deleted for some reason
     */
    public function canDeleteDivision($divisionId, $userType, $officeLabel, $officeName)
    {
        $strError = '';
        $select = (new Select())
            ->from(array('md' => 'members_divisions'))
            ->join(array('m' => 'members'), 'md.member_id = m.member_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where([
                'm.userType'     => Members::getMemberType($userType),
                'md.division_id' => $divisionId
            ]);

        if ($userType === 'user') {
            // Apply checks only for active users
            $select->where->equalTo('m.status', 1);

            // and which are in the "responsible for" (personal offices) combo
            $select->where->equalTo('md.type', 'responsible_for');
        }

        $arrMembersDivisions = $this->_db2->fetchAll($select);

        if ($userType === 'user') {
            $arrUsers = array();
            foreach ($arrMembersDivisions as $arrMembersDivisionInfo) {
                $arrMemberInfo = Members::generateMemberName($arrMembersDivisionInfo);

                $arrUsers[$arrMembersDivisionInfo['member_id']] = $arrMemberInfo['full_name'];
            }

            if (count($arrUsers)) {
                $strError = Settings::sprintfAssoc(
                    $this->_tr->translate('This %queue is assigned as the Personal %queue of: %users. You can only delete a %queue that is not Personal %queue of an active user.'),
                    array(
                        'queue' => $officeLabel,
                        'users' => implode(', ', $arrUsers),
                    )
                );
            }
        } else {
            if (count($arrMembersDivisions) > 0) {
                $strError = sprintf(
                    $this->_tr->translate('Some clients are still assigned to the %s. You can only delete when %s has no assigned clients.'),
                    $officeLabel,
                    $officeLabel
                );
            }
        }

        if (empty($strError) && $userType === 'user') {
            // Try to check only once
            $select = (new Select())
                ->from('company_questionnaires')
                ->columns(['q_id'])
                ->where(['q_office_id' => $divisionId]);

            $arrQNRsIds = $this->_db2->fetchCol($select);

            if (!empty($arrQNRsIds)) {
                $strError = sprintf(
                    $this->_tr->translate('%s is assigned in a company questionnaire. Please review your questionnaires and change the %s before deleting it.'),
                    $officeName,
                    $officeLabel
                );
            }

            if (empty($strError)) {
                $select = (new Select())
                    ->from('company_prospects_divisions')
                    ->columns(['prospect_id'])
                    ->where(['office_id' => $divisionId]);

                $arrProspectsIds = $this->_db2->fetchCol($select);

                if (!empty($arrProspectsIds)) {
                    $strError = sprintf(
                        $this->_tr->translate('Some prospects are still assigned to the %s.<br>You can only delete when %s has no assigned prospects.'),
                        $officeLabel,
                        $officeLabel
                    );
                }
            }
        }

        return $strError;
    }

    /**
     * Get the list of users for which only a specific division is set in the "Access To" combo
     *
     * @param int $companyId
     * @param int $divisionId
     * @return array
     */
    public function getUsersWithoutDivisions($companyId, $divisionId)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', Select::SQL_STAR, Join::JOIN_LEFT)
            ->where([
                'm.company_id' => (int)$companyId,
                (new Where())->in('m.userType', Members::getMemberType('user')),
                'md.type'      => 'access_to',
            ]);

        $arrMembersDivisions = $this->_db2->fetchAll($select);

        $arrUserNames = array();
        $arrGroupedRecords = array();
        foreach ($arrMembersDivisions as $arrMembersDivisionInfo) {
            if (!isset($arrUserNames[$arrMembersDivisionInfo['member_id']])) {
                $arrMemberInfo = Members::generateMemberName($arrMembersDivisionInfo);

                $arrUserNames[$arrMembersDivisionInfo['member_id']] = $arrMemberInfo['full_name'];
            }

            $arrGroupedRecords[$arrMembersDivisionInfo['member_id']][] = $arrMembersDivisionInfo['division_id'];
        }

        $arrUsers = array();
        foreach ($arrGroupedRecords as $memberId => $arrDivisions) {
            if (count($arrDivisions) === 1 && $arrDivisions[0] == $divisionId) {
                $arrUsers[$memberId] = $arrUserNames[$memberId];
            }
        }

        return $arrUsers;
    }

    public function getSalutations($booIdsOnly = false)
    {
        $arrSalutations = array(
            array(
                'option_id'   => 'mr',
                'option_name' => 'Mr.'
            ),

            array(
                'option_id'   => 'ms',
                'option_name' => 'Ms.'
            ),

            array(
                'option_id'   => 'mrs',
                'option_name' => 'Mrs.'
            ),

            array(
                'option_id'   => 'miss',
                'option_name' => 'Miss'
            ),

            array(
                'option_id'   => 'dr',
                'option_name' => 'Dr.'
            )
        );

        return $booIdsOnly ? Settings::arrayColumn($arrSalutations, 'option_id') : $arrSalutations;
    }

    /**
     * Calculate count of contacts/cases/prospects assigned to each division
     *
     * @param string $panelType
     * @param int $companyId
     * @param array $arrDivisionIds
     * @return array
     */
    public function getClientsCountForDivisions($panelType, $companyId, $arrDivisionIds)
    {
        $arrResult = array();

        if (!empty($arrDivisionIds)) {
            if ($panelType == 'prospects') {
                $select = (new Select())
                    ->from(array('p' => 'company_prospects'))
                    ->columns(array('member_id' => 'prospect_id'))
                    ->join(array('cpd' => 'company_prospects_divisions'), 'cpd.prospect_id = p.prospect_id', ['division_id' => 'office_id'], Select::JOIN_LEFT_OUTER)
                    ->where([
                        'p.company_id' => $companyId,
                        (new Where())
                            ->isNull('cpd.office_id')
                            ->or
                            ->in('cpd.office_id', $arrDivisionIds)
                    ]);
            } else {
                // Please note that we'll filter by offices above and not here
                $select = (new Select())
                    ->from(array('m' => 'members'))
                    ->columns(array('member_id'))
                    ->join(array('md' => 'members_divisions'), new PredicateExpression("md.`member_id` = m.`member_id` AND md.`type` = 'access_to'"), ['division_id'])
                    ->where([
                        'm.company_id' => $companyId,
                        'm.userType'   => $panelType == 'contacts' ? Members::getMemberType('contact') : Members::getMemberType('case')
                    ]);
            }

            $arrResult = $this->_db2->fetchAll($select);
        }


        return $arrResult;
    }


    /**
     * @param int $memberId
     * @param array $folderInfo
     * @return string - empty if no access, RW - full access, R - read only access
     */
    public function getFolderAccess($memberId, $folderInfo)
    {
        $strAccess = $folderInfo['access'];

        if ($this->isAuthorizedAgentsManagementEnabled() && !$this->canCurrentMemberEditClient($memberId)) {
            if ($folderInfo['name'] == 'Additional Documents' || preg_match('/Additional Documents/', $folderInfo['path'])) {
                $strAccess = 'RW';
            } else if (!empty($strAccess)) {
                $strAccess = 'R';
            }
        }

        return $strAccess;
    }

    /**
     * Get access rule to specific folder
     * @param array $arrDefaultFolders - list of default folders
     * @param string $path - folder's path to check access for
     * @param int $memberId
     * @param string $defaultAccess
     * @return string - empty if no access, RW - full access, R - read only access
     */
    public function getFolderPathAndAccess($arrDefaultFolders, $path, $memberId, $defaultAccess = 'RW')
    {
        $strAccess = '';

        // Don't show .images folder and its content
        if (!preg_match('%^(.*)\.images/{0,1}$%', $path)) {
            $strAccess      = $defaultAccess;
            $booFoundAccess = false;

            // Check/load access for default folders
            foreach ($arrDefaultFolders as $folder) {
                if (DIRECTORY_SEPARATOR == '\\') {
                    $folder['path'] = str_replace('\\', '/', $folder['path'] ?? '');
                }

                if ($folder['path'] == $path) {
                    $strAccess      = $this->getFolderAccess($memberId, $folder);
                    $booFoundAccess = true;
                    break;
                }
            }

            // Check/load access for sub folders of default folders
            if (empty($strAccess) && !$booFoundAccess) {
                // Maybe there is access to the parent folder?
                foreach ($arrDefaultFolders as $folder) {
                    if (DIRECTORY_SEPARATOR == '\\') {
                        $folder['path'] = str_replace('\\', '/', $folder['path'] ?? '');
                    }

                    if (substr($path, 0, strlen($folder['path'] ?? '')) == $folder['path']) {
                        $strAccess      = $this->getFolderAccess($memberId, $folder);
                        $booFoundAccess = true;
                        break;
                    }
                }
            }

            // If this is a custom folder (because wasn't identified above) - allow access for logged in users
            // and don't allow access for logged in clients
            if (empty($strAccess) && !$booFoundAccess && !$this->_auth->isCurrentUserClient()) {
                if ($this->isAuthorizedAgentsManagementEnabled() && !$this->canCurrentMemberEditClient($memberId)) {
                    $strAccess = 'R';
                } else {
                    $strAccess = 'RW';
                }
            }
        }

        return $strAccess;
    }

    /**
     * Load list of folders for a specific office(s)/division(s) by a specific access right
     *
     * @param int|array $divisionId
     * @param string $access possible values: RW, R and '' (no access)
     * @return array
     */
    public function getFoldersByAccessToDivision($divisionId, $access)
    {
        $arrFolders = array();

        if (!empty($divisionId)) {
            $select = (new Select())
                ->from(array('a' => 'folder_access_by_division'))
                ->columns(['folder_name'])
                ->where([
                    'a.division_id' => $divisionId,
                    'a.access'      => $access
                ]);

            $arrFolders = $this->_db2->fetchCol($select);
        }

        return $arrFolders;
    }

    /**
     * Update folder access rights for a specific office/division
     *
     * @param int $divisionId
     * @param array $arrNoAccessFolders of "no access" folders for the office
     * @return bool
     */
    public function updateFoldersAccessForDivision($divisionId, $arrNoAccessFolders)
    {
        try {
            $arrWhere = [
                'division_id' => (int)$divisionId,
                'access'      => ''
            ];
            $this->_db2->delete('folder_access_by_division', $arrWhere);

            foreach ($arrNoAccessFolders as $folderName) {
                $this->_db2->insert(
                    'folder_access_by_division',
                    [
                        'division_id' => $divisionId,
                        'folder_name' => $folderName,
                        'access'      => '',
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
     * Rename top level folder in the Shared Docs if it was renamed and this folder was used in the "access rights"
     *
     * @param int $companyId
     * @param string $oldFolderName
     * @param string $newFolderName
     */
    public function renameFoldersBasedOnDivisionAccess($companyId, $oldFolderName, $newFolderName)
    {
        $arrCompanyOfficeIds = $this->_parent->getDivisions($companyId, 0, true);

        if (!empty($arrCompanyOfficeIds)) {
            $this->_db2->update(
                'folder_access_by_division',
                ['folder_name' => $newFolderName],
                [
                    'division_id' => $arrCompanyOfficeIds,
                    'folder_name' => $oldFolderName
                ]
            );
        }
    }

    /**
     * Delete top level folder from the "access rights" list if it was used here
     *
     * @param int $companyId
     * @param string $folderName
     */
    public function deleteFolderBasedOnDivisionAccess($companyId, $folderName)
    {
        $arrCompanyOfficeIds = $this->_parent->getDivisions($companyId, 0, true);

        if (!empty($arrCompanyOfficeIds)) {
            $this->_db2->delete(
                'folder_access_by_division',
                [
                    'division_id' => $arrCompanyOfficeIds,
                    'folder_name' => $folderName
                ]
            );
        }
    }

    /**
     * Load the list of divisions grouped by companies
     *
     * @return array
     */
    public function getAllCompaniesDivisions()
    {
        $arrGroupedOffices = [];

        $select = (new Select())
            ->from('divisions')
            ->columns(['division_id', 'company_id', 'name']);

        $arrOffices = $this->_db2->fetchAll($select);

        foreach ($arrOffices as $arrOfficeInfo) {
            $arrGroupedOffices[$arrOfficeInfo['company_id']][] = $arrOfficeInfo;
        }

        return $arrGroupedOffices;
    }
}
