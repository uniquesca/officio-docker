<?php

namespace Clients\Service;

use DateTimeZone;
use Exception;
use Files\Service\Files;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Email\Models\MailAccount;
use Officio\Common\Service\AccessLogs;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\Roles;
use Officio\Service\SystemTriggers;
use Officio\Common\SubServiceOwner;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Members extends SubServiceOwner
{
    public static $membersPerPage = 25;

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var Files */
    protected $_files;

    /** @var EventManager */
    protected $_eventManager;

    /** @var Roles */
    protected $_roles;

    /** @var SystemTriggers */
    protected $_systemTriggers;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company        = $services[Company::class];
        $this->_country        = $services[Country::class];
        $this->_files          = $services[Files::class];
        $this->_roles          = $services[Roles::class];
        $this->_systemTriggers = $services[SystemTriggers::class];
        $this->_encryption     = $services[Encryption::class];
    }

    public function setEventManager(EventManagerInterface $eventManager)
    {
        $eventManager->setIdentifiers(
            [
                __CLASS__,
                get_class($this)
            ]
        );
        $this->_eventManager = $eventManager;
    }

    public function getEventManager()
    {
        if (!$this->_eventManager) {
            $this->setEventManager(new EventManager());
        }
        return $this->_eventManager;
    }

    /**
     * Retrieves list of member IDs belonging to a company(-ies)
     * @param $companyId
     * @return array
     */
    public function getCompanyMemberIds($companyId)
    {
        if (!is_array($companyId)) {
            $companyId = array($companyId);
        }

        // Collect company members
        $select = (new Select())
            ->from('members')
            ->columns(['member_id'])
            ->where(['company_id' => $companyId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load time zone for specific user
     * Try to load in such order:
     * 1. From default mail account
     * 2. User settings
     * 3. Company settings
     * 4. Config file
     *
     * @param array $arrUserInfo
     * @return bool|DateTimeZone
     */
    public function getMemberTimezone($arrUserInfo)
    {
        $tz = false;

        // Get time zone from the default mail account
        $arrMailAccount = MailAccount::getDefaultAccount($arrUserInfo['member_id']);
        if (isset($arrMailAccount['timezone']) && !empty($arrMailAccount['timezone'])) {
            $tz = $arrMailAccount['timezone'];
        }

        // If there is no default mail account - use time zone from User's settings
        if (empty($tz) && isset($arrUserInfo['timeZone']) && !empty($arrUserInfo['timeZone'])) {
            $arrTimeZones = array_keys($this->_settings->getWebmailTimeZones(false));
            if (array_key_exists($arrUserInfo['timeZone'], $arrTimeZones)) {
                $tz = $arrTimeZones[$arrUserInfo['timeZone']];
            }
        }

        // Still no time zone - try to load/use from the company settings
        if (empty($tz)) {
            $arrCompanyInfo = $this->_company->getCompanyInfo($arrUserInfo['company_id']);

            $tz = $arrCompanyInfo['companyTimeZone'] ?? $tz;
        }

        // Still no time zone - use from the config
        if (empty($tz)) {
            $tz = $this->_config['translator']['timezone'];
        }

        try {
            $tz = new DateTimeZone($tz);
        } catch (Exception $e) {
            $tz = false;
        }

        return $tz;
    }

    public function isMemberClient($userType)
    {
        $arrClientTypeIds = array_merge(
            self::getMemberType('client'),
            self::getMemberType('employer'),
            self::getMemberType('individual'),
            self::getMemberType('internal_contact'),
            self::getMemberType('contact')
        );

        return in_array($userType, $arrClientTypeIds);
    }

    public function isMemberClientById($memberId)
    {
        $select = (new Select())
            ->from('members')
            ->columns(['count' => new Expression('COUNT(*)')])
            ->where(
                [
                    'userType'  => self::getMemberType('client'),
                    'member_id' => (int)$memberId
                ]
            );

        return (int)$this->_db2->fetchOne($select) > 0;
    }

    public function isMemberCaseById($memberId)
    {
        $select = (new Select())
            ->from('members')
            ->columns(['count' => new Expression('COUNT(*)')])
            ->where(
                [
                    'userType'  => self::getMemberType('case'),
                    'member_id' => (int)$memberId
                ]
            );

        return (int)$this->_db2->fetchOne($select) > 0;
    }

    public function isMemberAdmin($userType)
    {
        $arrClientTypeId = self::getMemberType('admin');

        return (is_array($arrClientTypeId) && in_array($userType, $arrClientTypeId));
    }

    public function isMemberSuperAdmin($userType)
    {
        $arrClientTypeId = self::getMemberType('superadmin');

        return (is_array($arrClientTypeId) && in_array($userType, $arrClientTypeId));
    }

    public function getCurrentMemberName($booNameOnly = false)
    {
        if (!$this->_auth->getCurrentUserId()) {
            return '';
        }

        if ($this->_auth->isCurrentUserSuperadminMaskedAsAdmin()) {
            $memberName = $this->_auth->getAdminNameLoggedAsSuperAdmin();
            if (!$booNameOnly) {
                $memberName .= ' (as admin)';
            }
        } else {
            $memberInfo = $this->getMemberInfo();
            if ($booNameOnly) {
                $memberName = $memberInfo['full_name'];
            } else {
                $memberName = empty($memberInfo['fName']) ? $memberInfo['lName'] : $memberInfo['fName'];
            }
        }

        return $memberName;
    }

    /**
     * Generate member query
     * @NOTE required members as m and members_divisions as md tables!
     *
     * @param int|null $memberId
     * @param bool $booCheckForActiveUsers
     * @return array [Where, bool]
     */
    public function getMemberStructureQuery($memberId = null, $booCheckForActiveUsers = true)
    {
        $booAdmin = false;
        if (!isset($memberId)) {
            // Current member
            $identity        = $this->_auth->getIdentity();
            $companyId       = $identity->company_id;
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
            $arrDivisions    = $identity->divisions;
            if (!empty($divisionGroupId) && $this->_auth->isCurrentUserAdmin()) {
                $arrDivisions = false;

                // For Admins try to filter by offices only if Authorized Agents Management is enabled
                if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $arrDivisions = $this->_company->getCompanyDivisions()->getDivisionsByGroupId($divisionGroupId);
                    $arrDivisions = empty($arrDivisions) ? false : $arrDivisions;
                }

                $booAdmin = true;
            }
        } else {
            // Another member
            $arrMemberInfo   = $this->getMemberInfo($memberId);
            $companyId       = $arrMemberInfo['company_id'];
            $divisionGroupId = $arrMemberInfo['division_group_id'];
            if ($this->isMemberAdmin($arrMemberInfo['userType']) || $this->isMemberSuperAdmin($arrMemberInfo['userType'])) {
                // Admin
                $arrDivisions = false;

                // For Admins try to filter by offices only if Authorized Agents Management is enabled
                if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $arrDivisions = $this->_company->getCompanyDivisions()->getDivisionsByGroupId($divisionGroupId);
                    $arrDivisions = empty($arrDivisions) ? false : $arrDivisions;
                }

                $booAdmin = true;
            } else {
                // User or client
                $arrDivisions = $this->getMemberDivisions($memberId);
                $arrDivisions = empty($arrDivisions) ? [] : $arrDivisions;
            }
        }

        $arrQueryParams = new Where();
        $arrQueryParams->equalTo('m.company_id', (int)$companyId);

        $booUseDivisionsTable       = true;
        $booFilterByDivisionGroupId = true;
        if ($arrDivisions === false) {
            // This is company admin, has access to all divisions
            $booUseDivisionsTable = false;
        } elseif (is_array($arrDivisions) && count($arrDivisions)) {
            // This is user with access to specific divisions only
            if (!empty($divisionGroupId) && $booAdmin) {
                $arrQueryParams
                    ->nest()
                    ->equalTo('m.division_group_id', (int)$divisionGroupId)
                    ->or
                    ->in('md.division_id', array_map('intval', $arrDivisions))
                    ->unnest();

                $booFilterByDivisionGroupId = false;
            } else {
                $arrQueryParams->in('md.division_id', array_map('intval', $arrDivisions));
            }
            $arrQueryParams->equalTo('md.type', 'access_to');
        } else {
            // This is a user without assigned divisions
            // So this user cannot access to any office in the company
            $arrQueryParams->in('md.division_id', ['0']);
        }

        if (!empty($divisionGroupId) && $booFilterByDivisionGroupId) {
            $arrQueryParams->equalTo('m.division_group_id', (int)$divisionGroupId);
        }

        if ($booCheckForActiveUsers) {
            $arrQueryParams->equalTo('m.status', 1);
        }

        return [$arrQueryParams, $booUseDivisionsTable];
    }

    /**
     * Check if user/prospect with provided username exists
     * (except of user with $memberId AND/OR prospect)
     *
     * @param  $username
     * @param int $memberId
     * @param int $prospectId
     * @return bool true if user exists
     */
    public function isUsernameAlreadyUsed($username, $memberId = 0, $prospectId = 0)
    {
        $select = (new Select())
            ->from('members')
            ->columns(['member_id'])
            ->where(
                [
                    'username' => trim($username ?? '')
                ]
            );

        if (!empty($memberId)) {
            $select->where->notEqualTo('member_id', (int)$memberId);
        }

        $memberIdWithUsername = $this->_db2->fetchOne($select);

        $select = (new Select())
            ->from('prospects')
            ->columns(['prospect_id'])
            ->where(
                [
                    'admin_username' => trim($username ?? '')
                ]
            );

        if (!empty($prospectId)) {
            $select->where->notEqualTo('prospect_id', (int)$prospectId);
        }

        $prospectIdWithUsername = $this->_db2->fetchOne($select);

        return !empty($memberIdWithUsername) || !empty($prospectIdWithUsername);
    }

    /**
     * Save last viewed case/client by a specific user
     *
     * @param int $memberId
     * @param int $viewMemberId
     * @param array $arrMembersIdsToRemove
     * @return void
     */
    public function saveLastViewedClient($memberId, $viewMemberId, $arrMembersIdsToRemove = [])
    {
        $select = (new Select())
            ->from('members_last_access')
            ->columns(['count' => new Expression('COUNT(*)')])
            ->where(
                [
                    'member_id'      => (int)$memberId,
                    'view_member_id' => (int)$viewMemberId
                ]
            );

        $count = (int)$this->_db2->fetchOne($select);

        if (empty($count)) {
            $this->_db2->insert(
                'members_last_access',
                [
                    'member_id'      => (int)$memberId,
                    'view_member_id' => (int)$viewMemberId,
                    'access_date'    => date('c')
                ]
            );
        } else {
            $this->_db2->update(
                'members_last_access',
                [
                    'access_date' => date('c')
                ],
                [
                    'member_id'      => (int)$memberId,
                    'view_member_id' => (int)$viewMemberId
                ]
            );
        }

        if (!empty($arrMembersIdsToRemove)) {
            $this->_db2->delete(
                'members_last_access',
                [
                    'member_id' => (int)$memberId,
                    'view_member_id' => array_map('intval', $arrMembersIdsToRemove)
                ]
            );
        }
    }

    /**
     * Load ids of cases (last accessed by the same user or by the same company users) that the user has access to
     *
     * @param int $lastX
     * @param string $for last4me or last4all
     * @return array
     */
    public function getLastViewedClients($lastX = 50, $for = 'last4me')
    {
        $arrCasesIds = array();

        try {
            $memberId = $this->_auth->getCurrentUserId();

            $searchFor          = $this->getMemberTypeIdByName('case');
            $arrCasesIdsGrouped = $this->getMembersWhichICanAccess($searchFor);
            $arrCasesIdsGrouped = array_map('intval', $arrCasesIdsGrouped);

            if (!empty($arrCasesIdsGrouped)) {
                if ($for == 'last4me') {
                    $select = (new Select())
                        ->from(array('mla' => 'members_last_access'))
                        ->columns(['view_member_id'])
                        ->where(
                            [
                                'mla.member_id'      => $memberId,
                                'mla.view_member_id' => $arrCasesIdsGrouped
                            ]
                        )
                        ->order('mla.access_date DESC')
                        ->limit($lastX);

                    $arrCasesIds = $this->_db2->fetchCol($select);
                } elseif ($for == 'last4all') {
                    $arrAllowedUsers = $this->getAdminsAndUsersWhichUserCanAccess($memberId);

                    if (!empty($arrAllowedUsers)) {
                        $subSelect = (new Select())
                            ->from(array('mla' => 'members_last_access'))
                            ->columns(array('view_member_id', 'access_date' => new Expression('MAX(access_date)')))
                            ->where(['mla.member_id' => array_unique($arrAllowedUsers)])
                            ->group('mla.view_member_id');

                        $select = (new Select())
                            ->from(array('x' => $subSelect))
                            ->columns(['view_member_id'])
                            ->where(['x.view_member_id' => $arrCasesIdsGrouped])
                            ->order('x.access_date DESC')
                            ->limit($lastX);

                        $arrCasesIds = $this->_db2->fetchCol($select);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrCasesIds;
    }

    /**
     * Load list of admins and users that specific user can access (in the same divisions, etc.)
     *
     * @param int $memberId
     * @return array
     */
    public function getAdminsAndUsersWhichUserCanAccess($memberId)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(['member_id'])
            ->where(
                [
                    'm.userType' => self::getMemberType('admin_and_user')
                ]
            );

        list($structureQuery, $booUseDivisionsTable) = $this->getMemberStructureQuery($memberId);

        if ($booUseDivisionsTable) {
            $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
        }

        if (!empty($structureQuery)) {
            $select->where([$structureQuery]);
        }

        return $this->_db2->fetchCol($select);
    }

    /**
     * Generate User's full name
     *
     * @param array $arrClientInfo
     * @return array
     */
    public static function generateMemberName($arrClientInfo)
    {
        $arrClientInfo = is_array($arrClientInfo) ? $arrClientInfo : array();
        $arrClientInfo['full_name'] = array_key_exists('fName', $arrClientInfo) || array_key_exists('lName', $arrClientInfo) ? trim($arrClientInfo['fName'] . ' ' . $arrClientInfo['lName']) : '';

        return $arrClientInfo;
    }

    /**
     * Generate "full_name" and "update_full_name" fields
     *
     * @param array $arrClientInfo
     * @return array
     */
    public function generateUpdateMemberName($arrClientInfo)
    {
        $arrClientInfo                     = static::generateMemberName($arrClientInfo);
        $arrClientInfo['update_full_name'] = array_key_exists('update_fName', $arrClientInfo) || array_key_exists('update_lName', $arrClientInfo) ? trim($arrClientInfo['update_fName'] . ' ' . $arrClientInfo['update_lName']) : '';

        return $arrClientInfo;
    }

    /**
     * Load member information
     *
     * @param int|string $memberId
     * @param bool $booWithSMTP
     * @param bool $booWithPasswordRetrievals
     *
     * @return array|false with member info or false if something is wrong
     */
    public function getMemberInfo($memberId = 0, $booWithSMTP = false, $booWithPasswordRetrievals = false)
    {
        if (!is_numeric($memberId)) {
            return false;
        }

        $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->where([
                (new Where())
                    ->equalTo('m.member_id', $memberId)
            ]);

        if ($booWithSMTP) {
            $select->join(array('smtp' => 'eml_accounts'), new PredicateExpression('m.member_id = smtp.member_id AND smtp.is_default = "Y"'), Select::SQL_STAR, Select::JOIN_LEFT);
        }

        if ($booWithPasswordRetrievals) {
            $select->join(array('pr' => 'members_password_retrievals'), new PredicateExpression('m.member_id = pr.member_id AND pr.expiration >=' . time()), 'hash', Select::JOIN_LEFT);
        }

        $arrMemberInfo = $this->_db2->fetchRow($select);
        if (is_array($arrMemberInfo) && count($arrMemberInfo)) {
            $arrMemberInfo = static::generateMemberName($arrMemberInfo);
        }

        return $arrMemberInfo;
    }

    /**
     * Load simple information for specific members
     *
     * @param $arrMemberIds
     * @return array
     */
    public function getMembersSimpleInfo($arrMemberIds)
    {
        $arrResult = array();
        if (is_array($arrMemberIds) && count($arrMemberIds)) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->join(array('r' => 'members_relations'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT)
                ->join(array('m2' => 'members'), 'r.parent_member_id = m2.member_id', array('parentUserType' => 'userType'), Select::JOIN_LEFT)
                ->where(['m.member_id' => $arrMemberIds]);

            $arrResult = $this->_db2->fetchAssoc($select);
        }

        return $arrResult;
    }


    /**
     * Load members list
     *
     * @param array $arrMemberIds
     * @param bool $booAllUsers true to load 'all users' option
     * @param string $strUserType user type
     * @return array
     */
    public function getMembersInfo($arrMemberIds, $booAllUsers, $strUserType = 'admin_and_user')
    {
        $arrResult = array();
        if (!is_array($arrMemberIds) || !count($arrMemberIds)) {
            return $arrResult;
        }

        $select = (new Select())
            ->from(array('m' => 'members'))
            ->where(
                [
                    'm.member_id' => $arrMemberIds,
                    'm.userType' => self::getMemberType($strUserType)
                ]
            )
            ->order(array('m.fName', 'm.lName'));

        $arrMembers = $this->_db2->fetchAll($select);

        foreach ($arrMembers as $arrMemberInfo) {
            $arrMemberInfo = static::generateMemberName($arrMemberInfo);
            $arrResult[]   = array(
                $arrMemberInfo['member_id'],
                $arrMemberInfo['full_name'],
                $arrMemberInfo['company_id'],
                $arrMemberInfo['status']
            );
        }

        if ($booAllUsers) {
            // Use same name in js
            array_unshift($arrResult, array(0, 'All Users'));
        }


        return $arrResult;
    }

    /**
     * Load sorted members by first/last name from all companies (except of the default company)
     *
     * @param string $strUsersType
     * @param bool $booActiveOnly
     * @return array
     */
    public function getAllCompaniesMembersIds($strUsersType = '', $booActiveOnly = false)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->where([
                (new Where())->notEqualTo('company_id ', $this->_company->getDefaultCompanyId())
            ])
            ->order(array('m.fName', 'm.lName'));

        if ($booActiveOnly) {
            $select->where(['m.status' => 1]);
        }

        if (!empty($strUsersType)) {
            $arrTypes = Members::getMemberType($strUsersType);
            if (!empty($arrTypes)) {
                $select->where(['userType' => $arrTypes]);
            }
        }

        $arrMembers = $this->_db2->fetchAll($select);

        $arrResult = array();
        foreach ($arrMembers as $arrMemberInfo) {
            $arrMemberInfo = static::generateMemberName($arrMemberInfo);
            $arrResult[]   = array(
                $arrMemberInfo['member_id'],
                $arrMemberInfo['full_name'],
                $arrMemberInfo['company_id'],
                $arrMemberInfo['status']
            );
        }

        return $arrResult;
    }

    /**
     * Load simple member info by the provided username
     *
     * @param string $username
     * @return array|false Array with member info or false if member not found
     */
    public function getMemberSimpleInfoByUsername($username)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->where(['username' => $username]);

        $arrMemberInfo = $this->_db2->fetchRow($select);

        return $arrMemberInfo ? static::generateMemberName($arrMemberInfo) : false;
    }

    /**
     * Load member information by username
     *
     * @param string $username
     * @param bool $booSuperadmin
     *
     * @return array member info
     */
    public function getMemberInfoByUsername($username, $booSuperadmin = false)
    {
        try {
            // User/client and his/her company must be active
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->where([
                    'm.username'      => $username,
                    'm.login_enabled' => 'Y',
                    'm.status'        => 1
                ]);

            if ($booSuperadmin) {
                // Only active user/client can log in
                $arrAllowedTypes = Members::getMemberType('superadmin');
            } else {
                $select->join(array('c' => 'company'), 'c.company_id = m.company_id', array('company_status' => 'Status'), Select::JOIN_LEFT_OUTER)
                    ->having('company_status', array(1, 2));
                // Only active user/client can log in, not superadmin
                $arrAllowedTypes = Members::getMemberType('not_superadmin');
            }
            $select->where(['m.userType' => $arrAllowedTypes]);

            $arrMemberInfo = $this->_db2->fetchRow($select);
            unset($arrMemberInfo['company_status']);
        } catch (Exception $e) {
            $arrMemberInfo = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMemberInfo;
    }

    /**
     * Load member information by the provided IDIR
     *
     * @param string $idir
     * @param bool $booSuperadmin
     *
     * @return array member info
     */
    public function getMemberInfoByIdir($idir, $booSuperadmin = false)
    {
        try {
            // User/client and his/her company must be active
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->where([
                    'm.oauth_idir'    => $idir,
                    'm.login_enabled' => 'Y',
                    'm.status'        => 1
                ]);

            if ($booSuperadmin) {
                // Only active user/client can log in
                $arrAllowedTypes = Members::getMemberType('superadmin');
            } else {
                $select->join(array('c' => 'company'), 'c.company_id = m.company_id', array('company_status' => 'Status'), Select::JOIN_LEFT_OUTER)
                    ->having('company_status', array(1, 2));
                // Only active user/client can log in, not superadmin
                $arrAllowedTypes = Members::getMemberType('not_superadmin');
            }
            $select->where(['m.userType' => $arrAllowedTypes]);

            $arrMemberInfo = $this->_db2->fetchRow($select);
            unset($arrMemberInfo['company_status']);
        } catch (Exception $e) {
            $arrMemberInfo = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMemberInfo;
    }


    /**
     * Check if selected member has access to mail
     *
     * @param int $memberId
     * @return bool has access or not
     */
    public function hasMemberAccessToMail($memberId)
    {
        return $this->_acl->isMemberAllowed($memberId, 'mail-view');
    }


    /**
     * Load smtp settings for specific member
     *
     * @param array $arrMemberIds
     * @return array smtp settings
     */
    public function getMemberSmtpSettings($arrMemberIds = array())
    {
        $arrResult = array();
        if (is_array($arrMemberIds) && !empty($arrMemberIds)) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array('emailAddress'))
                ->join(array('smtp' => 'eml_accounts'), new PredicateExpression('m.member_id = smtp.member_id AND is_default= "Y"'), Select::SQL_STAR, Select::JOIN_LEFT)
                ->where(['m.member_id' => $arrMemberIds]);

            $arrResult = $this->_db2->fetchRow($select);
        }

        return is_array($arrResult) ? $arrResult : array();
    }


    /**
     * Update user smtp settings
     * i.e. create email account and update members table
     *
     * @param array $arrUpdateInfo
     * @return bool true on success, false otherwise
     */
    public function updateMemberSMTPSettings($arrUpdateInfo)
    {
        $booResult = false;

        $memberId = $arrUpdateInfo['member_id'];
        unset($arrUpdateInfo['member_id']);

        // Update info if member id is correct
        if (!empty($memberId) && is_numeric($memberId)) {
            // Load smtp settings for this member
            $arrMemberInfo = $this->getMemberSmtpSettings(array($memberId));

            if (empty($arrMemberInfo['id'])) {
                // Create new account
                $booResult = MailAccount::createAccount($memberId, $arrUpdateInfo);
            } else {
                // Update members table
                if (array_key_exists('emailAddress', $arrMemberInfo) && array_key_exists('email', $arrUpdateInfo) && $arrUpdateInfo['email'] != $arrMemberInfo['emailAddress']) {
                    $this->_db2->update(
                        'members',
                        ['emailAddress' => $arrUpdateInfo['email']],
                        ['member_id' => $memberId]
                    );
                }
                $booResult = true;
            }
        }

        return $booResult;
    }

    public function getMembersWhichICanAccess($memberTypeId = null, $currentMemberId = null)
    {
        if (!isset($currentMemberId)) {
            $currentMemberId = $this->_auth->getCurrentUserId();
            $booClient       = $this->_auth->isCurrentUserClient();
            $identity        = $this->_auth->getIdentity();
            $memberTypeName  = $this->getMemberTypeNameById($identity->userType);
        } else {
            $arrMemberInfo = $this->getMemberInfo($currentMemberId);
            $booClient = $this->isMemberClient($arrMemberInfo['userType']);
            $memberTypeName = $this->getMemberTypeNameById($arrMemberInfo['userType']);
        }

        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(['member_id']);

        // If this is a client(IA/Employer/Contact/Case)
        if ($booClient) {
            $clients = (!$this instanceof Clients)
                ? $this->_serviceContainer->get(Clients::class)
                : $this;
            switch ($memberTypeName) {
                case 'employer':
                    // Employer has access to all assigned cases and to these cases parents (IAs)
                    $arrCases = $clients->getAssignedApplicants($currentMemberId);
                    $arrMemberIds = array_merge(array($currentMemberId), $arrCases);

                    list(, $arrParentIds) = $clients->getParentsForAssignedCases($arrCases);
                    $arrMemberIds = array_merge($arrMemberIds, $arrParentIds);
                    break;

                default:
                    // All others have access to assigned cases only
                    $arrChildren  = $clients->getAssignedApplicants($currentMemberId);
                    $arrMemberIds = array_merge(array($currentMemberId), $arrChildren);
                    break;
            }

            $select->where(['m.member_id' => array_unique($arrMemberIds)]);
        }

        // Check members access to another members
        list($oWhere, $booUseDivisionsTable) = $this->getMemberStructureQuery($currentMemberId, false);

        if ($booUseDivisionsTable) {
            $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
        }

        if (!empty($oWhere)) {
            $select->where([$oWhere]);
        }

        if (!is_null($memberTypeId)) {
            $select->where(['m.userType' => $memberTypeId]);
        }

        $arrMemberIds = $this->_db2->fetchCol($select);
        $arrMemberIds = array_map('intval', $arrMemberIds);

        return Settings::arrayUnique(array_merge($arrMemberIds, array((int)$currentMemberId)));
    }

    /**
     * Check if member has access to another member
     *
     * @param int $memberId
     * @param int $checkMemberId
     * @return bool has access
     */
    public function hasMemberAccessToMember($memberId, $checkMemberId)
    {
        $booHasAccess = false;
        try {
            $arrMemberInfo = $this->getMemberInfo($memberId);
            if ($this->isMemberSuperAdmin($arrMemberInfo['userType'])) {
                $booHasAccess = true;
            } else {
                $arrMemberIds = $this->getMembersWhichICanAccess(null, $memberId);
                if (is_array($arrMemberIds) && in_array($checkMemberId, $arrMemberIds)) {
                    $booHasAccess = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Check if current member has access to specific member(s)
     *
     * @param int|array $arrCheckMemberIds
     * @return bool has access
     */
    public function hasCurrentMemberAccessToMember($arrCheckMemberIds)
    {
        $booHasAccess = false;
        try {
            if (!empty($arrCheckMemberIds) && (is_numeric($arrCheckMemberIds) || is_array($arrCheckMemberIds))) {
                // Prevent additional checks if id is incorrect
                if ($this->_auth->isCurrentUserSuperadmin()) {
                    $booHasAccess = true;
                } else {
                    $arrMemberIds = $this->getMembersWhichICanAccess();
                    if (!empty($arrMemberIds)) {
                        $arrCheckMemberIds = is_array($arrCheckMemberIds) ? $arrCheckMemberIds : [$arrCheckMemberIds];
                        $arrCheckMemberIds = Settings::arrayUnique($arrCheckMemberIds);

                        foreach ($arrCheckMemberIds as $checkMemberId) {
                            if (!in_array($checkMemberId, $arrMemberIds)) {
                                $booHasAccess = false;
                                break;
                            } else {
                                $booHasAccess = true;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Check if current member has access to specific company
     *
     * @param int $checkCompanyId
     * @return bool has access
     */
    public function hasCurrentMemberAccessToCompany($checkCompanyId)
    {
        $booHasAccess = false;
        // Get current user company id
        $currentMemberCompanyId = $this->_auth->getCurrentUserCompanyId();

        // Check if is superadmin or user is in same company
        if ($checkCompanyId == $currentMemberCompanyId || $this->_auth->isCurrentUserSuperadmin()) {
            $booHasAccess = true;
        }

        return $booHasAccess;
    }

    /**
     * Load user type by member id
     * @param int $memberId
     * @return string user type
     */
    public function getMemberTypeByMemberId($memberId = 0)
    {
        $memberId = (!$memberId ? $this->_auth->getCurrentUserId() : $memberId);

        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(['userType'])
            ->where([(new Where())->equalTo('m.member_id', (int)$memberId)]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load assigned member roles list
     *
     * @param int $memberId
     * @param bool $booIdOnly (true to load only roles ids)
     * @return array result
     */
    public function getMemberRoles($memberId = 0, $booIdOnly = true)
    {
        $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
        $select   = (new Select())
            ->from(array('mr' => 'members_roles'))
            ->columns(['role_id'])
            ->join(array('r' => 'acl_roles'), 'r.role_id = mr.role_id', $booIdOnly ? array() : array('role_parent_id', 'role_type', 'role_name'), Select::JOIN_LEFT)
            ->where(['mr.member_id' => (int)$memberId]);

        return $booIdOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Search member ids by specific role ids
     *
     * @param array $arrRoleIds
     * @return array
     */
    public function getMemberByRoleIds($arrRoleIds)
    {
        $arrMemberIds = array();

        if (is_array($arrRoleIds) && count($arrRoleIds)) {
            $select = (new Select())
                ->from(array('m' => 'members_roles'))
                ->columns(['member_id'])
                ->where(['m.role_id' => $arrRoleIds]);

            $arrMemberIds = $this->_db2->fetchCol($select);
        }

        return $arrMemberIds;
    }

    /**
     * Load role type by its id
     *
     * @param int $roleId
     * @return string role type
     */
    public function getRoleTypeById($roleId)
    {
        $select = (new Select())
            ->from(array('r' => 'acl_roles'))
            ->columns(['role_type'])
            ->where(['r.role_id' => (int)$roleId]);

        return $this->_db2->fetchOne($select);
    }

    public function getRoleIdByRoleType($type, $companyId = null)
    {
        $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
        $select = (new Select())
            ->quantifier(Select::QUANTIFIER_DISTINCT)
            ->from(array('r' => 'acl_roles'))
            ->columns(['role_id'])
            ->where(
                [
                    'r.role_type' => $type,
                    'r.company_id' => (int)$companyId
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    public function getUsertypeIdByRoleType($strRoleType)
    {
        $arrUserTypes = $this->getUserTypes(false);

        $userTypeId = false;
        if (is_array($arrUserTypes) && !empty($arrUserTypes)) {
            foreach ($arrUserTypes as $row) {
                if ($row['role_type'] == $strRoleType) {
                    $userTypeId = $row['usertype_id'];
                    break;
                }
            }
        }

        return $userTypeId;
    }

    public function getUserTypes($booAsKeyVal = true)
    {
        $cacheId = 'usertypes';
        $cacheTagId = 'tagUserTypes';
        if (!($arrUserTypes = $this->_cache->getItem($cacheId))) {
            // Not in cache
            $select = (new Select())
                ->from(array('t' => 'usertypes'));

            $arrUserTypes = $this->_db2->fetchAll($select);

            $this->_cache->setItem($cacheId, $arrUserTypes);
            if ($this->_cache instanceof TaggableInterface) {
                $this->_cache->setTags($cacheId, array($cacheTagId));
            }
        }

        $arrResult = array();
        if ($booAsKeyVal && is_array($arrUserTypes) && !empty($arrUserTypes)) {
            foreach ($arrUserTypes as $arrUserTypeInfo) {
                $arrResult[$arrUserTypeInfo['usertype_id']] = $arrUserTypeInfo['role_type'];
            }
        } else {
            $arrResult = $arrUserTypes;
        }

        return $arrResult;
    }


    public function getTextRolesByIds($arrRoleIds)
    {
        $select = (new Select())
            ->from(array('r' => 'acl_roles'))
            ->columns(['role_parent_id'])
            ->where(['r.role_id' => $arrRoleIds]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load the list of roles for a current user's company
     *
     * @param bool $booOnlyVisible
     * @param bool $booIdsOnly
     * @return array
     */
    public function getRoles($booOnlyVisible = true, $booIdsOnly = false)
    {
        $select = (new Select())
            ->from(array('r' => 'acl_roles'))
            ->columns([$booIdsOnly ? 'role_id' : Select::SQL_STAR])
            ->where(['company_id' => $this->_auth->getCurrentUserCompanyId()]);


        if ($booOnlyVisible) {
            $select->where->equalTo('role_visible',  1);
        }

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load superadmin roles list
     * @param $booWithPleaseSelect
     *
     * @return array
     */
    public function getSuperAdminRoles($booWithPleaseSelect = false)
    {
        $arrRoles = array();
        if ($booWithPleaseSelect) {
            $arrRoles[0] = '-- Please Select --';
        }

        $arrSuperadminRoles = $this->getRoles(false);
        foreach ($arrSuperadminRoles as $arrSuperadminRoleInfo) {
            if ($arrSuperadminRoleInfo['role_type'] == 'superadmin') {
                $arrRoles[$arrSuperadminRoleInfo['role_id']] = $arrSuperadminRoleInfo['role_name'];
            }
        }

        asort($arrRoles);

        return $arrRoles;
    }


    /**
     * Get Maximum role type for several roles
     * @param array $arrRolesInfo
     * @return string role type
     */
    public function getUserTypeByRoles($arrRolesInfo)
    {
        $strUserType = 'guest';

        if (is_array($arrRolesInfo)) {
            foreach ($arrRolesInfo as $roleInfo) {
                switch ($roleInfo['role_type']) {
                    case 'superadmin':
                        $strUserType = 'superadmin';
                        break;

                    case 'crmuser':
                        $strUserType = 'crmuser';
                        break;

                    case 'admin':
                        if ($strUserType != 'superadmin') {
                            $strUserType = 'admin';
                        }
                        break;

                    case 'user':
                        if ($strUserType != 'superadmin' && $strUserType != 'admin') {
                            $strUserType = 'user';
                        }
                        break;

                    case 'client':
                        if ($strUserType != 'superadmin' && $strUserType != 'admin' && $strUserType != 'user') {
                            $strUserType = 'client';
                        }
                        break;

                    default:
                        $strUserType = 'guest';
                        break;
                }
            }
        }

        return $strUserType;
    }

    public function getUserTypeByRolesIds($arrRolesIds)
    {
        $arrRoles = array();

        if (is_array($arrRolesIds) && !empty($arrRolesIds)) {
            $select = (new Select())
                ->from(array('r' => 'acl_roles'))
                ->where(['role_id' => $arrRolesIds]);

            $arrRoles = $this->_db2->fetchAll($select);
        }

        $arrTypes = self::getMemberType($this->getUserTypeByRoles($arrRoles));

        return $arrTypes[0];
    }

    /**
     * Load member type id(s) by string name
     *
     * @param string $type
     * @return array
     */
    public static function getMemberType($type)
    {
        switch ($type) {
            case 'superadmin':
                $arrTypes = array(1);
                break;

            case 'admin':
                $arrTypes = array(2);
                break;

            case 'superadmin_admin':
                $arrTypes = array(1, 2);
                break;

            case 'case':
                $arrTypes = array(3);
                break;

            case 'client':
                $arrTypes = array(3, 7, 8, 10);
                break;

            case 'user':
                $arrTypes = array(4, 5);
                break;

            case 'staff':
                $arrTypes = array(4);
                break;

            case 'agent':
                $arrTypes = array(5);
                break;

            case 'crm_user':
                $arrTypes = array(6);
                break;

            case 'employer':
                $arrTypes = array(7);
                break;

            case 'individual':
                $arrTypes = array(8);
                break;

            case 'internal_contact':
                $arrTypes = array(9);
                break;

            case 'individual_employer_internal_contact':
                $arrTypes = array(7, 8, 9);
                break;

            case 'contact':
                $arrTypes = array(10);
                break;

            case 'client_agent':
                $arrTypes = array(3, 5, 7, 8, 10);
                break;

            case 'not_superadmin':
                $arrTypes = array(2, 3, 4, 5, 7, 8, 10);
                break;

            case 'not_admin':
                $arrTypes = array(3, 4, 5);
                break;

            case 'admin_and_user':
                $arrTypes = array(2, 4, 5);
                break;

            case 'admin_and_staff':
                $arrTypes = array(2, 4);
                break;

            default:
                $arrTypes = array(0);
                break;
        }

        return $arrTypes;
    }

    public function updateMemberData($memberId, $arrUpdate)
    {
        try {
            $this->_db2->update(
                'members',
                $arrUpdate,
                ['member_id' => $memberId]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function updateLastLogin($memberId)
    {
        $lastLogin = time();

        $this->_db2->update(
            'members',
            ['lastLogin' => $lastLogin],
            ['member_id' => $memberId]
        );

        return $lastLogin;
    }

    /**
     * Update roles list for specific member
     * Note: delete only not used roles, not all assigned and insert only new roles
     *
     * @param int $memberId
     * @param array $arrMemberRolesAfterUpdate
     * @param bool $booSaveToLog
     * @return bool result
     */
    public function updateMemberRoles($memberId, $arrMemberRolesAfterUpdate, $booSaveToLog = true)
    {
        try {
            $arrRolesToLeave = $arrRolesToDelete = $arrRolesToAdd = array();

            $companyId = $this->_company->getMemberCompanyId($memberId);
            $arrCompanyRoles = $this->_company->getCompanyRoles($companyId);

            // Load roles list saved for the member
            $arrMemberRolesSaved = $this->getMemberRoles($memberId, false);
            $arrMemberRolesBeforeUpdate = array();
            foreach ($arrMemberRolesSaved as $arrRoleInfo) {
                $arrMemberRolesBeforeUpdate[] = $arrRoleInfo['role_id'];
            }

            // Check which roles must be added/removed
            $arrWhereDelete = array();
            if (is_array($arrMemberRolesBeforeUpdate) && !empty($arrMemberRolesBeforeUpdate)) {
                $arrRolesToLeave = array_intersect($arrMemberRolesBeforeUpdate, $arrMemberRolesAfterUpdate);
                $arrRolesToDelete = array_diff($arrMemberRolesBeforeUpdate, $arrMemberRolesAfterUpdate);

                $arrWhereDelete['member_id'] = $memberId;
                if (!empty($arrRolesToLeave)) {
                    $arrWhereDelete[] = (new Where())->notIn('role_id', $arrRolesToLeave);
                }

                $this->_db2->delete('members_roles', $arrWhereDelete);
            }

            if (is_array($arrMemberRolesAfterUpdate) && !empty($arrMemberRolesAfterUpdate)) {
                foreach ($arrMemberRolesAfterUpdate as $roleId) {
                    if (!in_array($roleId, $arrRolesToLeave)) {
                        $this->_db2->insert(
                            'members_roles',
                            [
                                'role_id'   => $roleId,
                                'member_id' => $memberId
                            ]
                        );
                        $arrRolesToAdd[] = $roleId;
                    }
                }
            }

            // Log this action
            if ($booSaveToLog && (count($arrRolesToDelete) || count($arrRolesToAdd))) {
                $arrRoleActions = array();

                if (count($arrRolesToAdd)) {
                    $arrRolesNamesToAdd = array();
                    foreach ($arrCompanyRoles as $arrRoleInfo) {
                        if (in_array($arrRoleInfo['role_id'], $arrRolesToAdd)) {
                            $arrRolesNamesToAdd[] = $arrRoleInfo['role_name'];
                        }
                    }
                    $arrRoleActions[] = sprintf('added: %s', implode(', ', $arrRolesNamesToAdd));
                }

                if (count($arrRolesToDelete)) {
                    $arrRolesNamesToDelete = array();
                    foreach ($arrCompanyRoles as $arrRoleInfo) {
                        if (in_array($arrRoleInfo['role_id'], $arrRolesToDelete)) {
                            $arrRolesNamesToDelete[] = $arrRoleInfo['role_name'];
                        }
                    }
                    $arrRoleActions[] = sprintf('removed: %s', implode(', ', $arrRolesNamesToDelete));
                }

                // For <user> roles were added: Role1, Role2, removed: Role3, Role4 by <admin>
                $strLogRoleDescription = sprintf(
                    'For {2} %s %s by {1}',
                    count($arrRolesToDelete) + count($arrRolesToAdd) == 1 ? 'role was' : 'roles were',
                    implode(' and ', $arrRoleActions)
                );
                $arrLog = array(
                    'log_section'           => 'user',
                    'log_action'            => 'role_change',
                    'log_description'       => $strLogRoleDescription,
                    'log_created_by'        => $this->_auth->getCurrentUserId(),
                    'log_action_applied_to' => $memberId,
                );

                /** @var AccessLogs $oAccessLogs */
                $oAccessLogs = $this->_serviceContainer->get(AccessLogs::class);
                $oAccessLogs->saveLog($arrLog);
            }

            $booResult = true;
        } catch (Exception $e) {
            $booResult = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booResult;
    }

    /**
     * Load divisions list for current member
     *
     * @param bool $booIdsOnly
     * @param int $companyId
     * @param null $divisionGroupId
     * @return array with divisions list
     */
    public function getDivisions($booIdsOnly = false, $companyId = null, $divisionGroupId = null)
    {
        // Check if current member has divisions
        $is_divisions = $this->_auth->isCurrentUserDivision();
        if (is_array($is_divisions) && empty($is_divisions)) {
            return array();
        }

        // Load divisions for current member
        $companyId       = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
        $divisionGroupId = is_null($divisionGroupId) ? $this->_auth->getCurrentUserDivisionGroupId() : $divisionGroupId;
        $divisions       = $this->_auth->getCurrentUserDivisions();

        if ($is_divisions && !empty($divisions)) {
            $select = (new Select())
                ->quantifier(Select::QUANTIFIER_DISTINCT)
                ->from(array('d' => 'divisions'))
                ->columns($booIdsOnly ? array('division_id') : array('division_id', 'name'))
                ->join(array('md' => 'members_divisions'), 'md.division_id = d.division_id', [], Select::JOIN_LEFT)
                ->join(array('m' => 'members'), 'm.member_id = md.member_id', [], Select::JOIN_LEFT)
                ->where(
                    [
                        'md.division_id' => $divisions,
                        'm.company_id' => $companyId
                    ]
                )
                ->order('d.order');

            $result = $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
        } else {
            $result = $this->_company->getDivisions($companyId, $divisionGroupId, $booIdsOnly);
        }

        return $result;
    }

    /**
     * Load divisions list for specific member
     *
     * @param int $memberId
     * @param string $type
     * @return array
     */
    public function getMemberDivisions($memberId, $type = 'access_to')
    {
        $select = (new Select())
            ->from('members_divisions')
            ->columns(['division_id'])
            ->where(
                [
                    'member_id' => (int)$memberId,
                    'type'      => $type
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load a list of members (can be filtered via the $userType) assigned to provided divisions by a specific "assigned to" type
     *
     * @param array $arrDivisionIds
     * @param string $divisionType
     * @param string $userType
     * @return array
     */
    public function getMembersAssignedToDivisions($arrDivisionIds, $divisionType = 'responsible_for', $userType = '')
    {
        if (!is_array($arrDivisionIds) || empty($arrDivisionIds)) {
            return array();
        }

        $select = (new Select())
            ->from(['md' => 'members_divisions'])
            ->columns(['member_id'])
            ->where(['md.division_id' => $arrDivisionIds]);

        if (!empty($divisionType)) {
            $select->where->equalTo('md.type', $divisionType);
        }

        if (!empty($userType)) {
            $select->join(array('m' => 'members'), 'md.member_id = m.member_id', []);
            $select->where(['m.userType' => Members::getMemberType($userType)]);
        }

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load divisions list for specific array of members
     * @param array $arrMemberIds
     * @return array
     */
    public function getMembersDivisions($arrMemberIds)
    {
        if (!is_array($arrMemberIds) || empty($arrMemberIds)) {
            return array();
        }

        $select = (new Select())
            ->from('members_divisions')
            ->columns(array('member_id', 'division_id'))
            ->where(
                [
                    'member_id' => $arrMemberIds,
                    'type'      => 'access_to'
                ]
            );

        return $this->_db2->fetchAll($select);
    }

    /**
     * Get divisions detailed info assigned to a specific member
     * @param $memberId
     * @return array
     */
    public function getMemberDivisionsInfo($memberId)
    {
        $select = (new Select())
            ->from(array('d' => 'divisions'))
            ->join(array('md' => 'members_divisions'), 'md.division_id = d.division_id', Select::SQL_STAR, Select::JOIN_LEFT)
            ->where(
                [
                    'md.member_id' => (int)$memberId,
                    'md.type'      => 'access_to'
                ]
            )
            ->order(array('order ASC', 'name'));

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load division info by id
     * @param int $divisionId
     * @return array
     */
    public function getDivisionInfo($divisionId)
    {
        $select = (new Select())
            ->from('divisions')
            ->where(['division_id' => (int)$divisionId]);

        return $this->_db2->fetchRow($select);
    }

    // Returns the first available calendar date
    public function getCalendarStartDate($booMySQLDateFormat = false)
    {
        if ($this->_auth->isCurrentUserAdmin()) {
            $time = mktime(0, 0, 0, 1, 1, 2000);
        } else {
            $time = strtotime('-' . $this->_settings->variable_get('transactionViewPeriodMonths') . ' months');
        }

        if ($booMySQLDateFormat) {
            $format = 'Y-m-d';
        } else {
            $format = $this->_settings->variable_get('dateFormatShort');
        }

        return date($format, $time);
    }

    public function getCompanyAdminRoleId($companyId)
    {
        $companyAdminId = $this->_company->getCompanyAdminId($companyId);

        return $this->getMemberRoles($companyAdminId);
    }

    public function getMembersForProspectsMatching()
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(array('member_id', 'company_id', 'fName', 'lName', 'emailAddress'))
            ->join(array('c' => 'company'), 'c.company_id = m.company_id', 'companyName')
            ->where(
                [
                    (new Where())->greaterThan('m.company_id', 0)
                ]
            )
            ->where(
                [
                    'm.userType' => self::getMemberType('admin_and_staff'),
                    'c.Status'   => 1
                ]
            );

        return $this->_db2->fetchAll($select);
    }

    public function getMembersByCompanyAndType($companyId, $strUserType)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(array('member_id'))
            ->where(
                [
                    'm.userType'   => self::getMemberType($strUserType),
                    'm.status'     => 1,
                    'm.company_id' => $companyId
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    public function getCommaSeparatedMemberNames($ids)
    {
        $arrNames = array();
        if (is_array($ids) && count($ids)) {
            $select = (new Select())
                ->from('members')
                ->columns(['fullName' => new Expression('CONCAT(fName, " ", lName)')])
                ->where(['member_id' => $ids]);

            $arrNames = $this->_db2->fetchCol($select);
        }

        return implode(', ', $arrNames);
    }


    /**
     * Delete client(s) (IA/Individual/Contact/Internal contact) or user(s)/admin(s)/superadmin(s)
     * Save this action to the log
     *
     * @param int $companyId - id of the company
     * @param array $arrMemberIds - array of ids of members that we want to delete
     * @param array $arrMemberNames - array of names of members that we want to delete, if not provided - load them
     * @param string $strMemberType - type of the member(s) that we want to delete
     * @param bool $booSaveInLog - true to save to the Events log
     * @return bool true on success, false on error
     */
    public function deleteMember($companyId, $arrMemberIds, $arrMemberNames = [], $strMemberType = '', $booSaveInLog = true)
    {
        $booSuccess = false;

        try {
            if (is_array($arrMemberIds) && count($arrMemberIds)) {
                if ($booSaveInLog && empty($arrMemberNames)) {
                    $arrMembersInfo = $this->getMembersInfo($arrMemberIds, false, $strMemberType);

                    $arrMemberNames = array();
                    foreach ($arrMembersInfo as $arrMemberInfo) {
                        if (in_array($arrMemberInfo[0], $arrMemberIds)) {
                            $name = trim($arrMemberInfo[1] ?? '');
                            // If name is empty (e.g. case was deleted and there was no file number set) - save the id
                            if (empty($name)) {
                                $name = 'record id #' . $arrMemberInfo[0];
                            }

                            $arrMemberNames[] = $name;
                        }
                    }
                }

                // Also delete client or user info for this member
                $this->_db2->delete('form_default', ['updated_by' => $arrMemberIds]);

                // Clear Applicant data
                $this->_db2->delete(
                    'members_relations',
                    [
                        (new Where())
                            ->nest()
                            ->in('parent_member_id', $arrMemberIds)
                            ->or
                            ->in('child_member_id', $arrMemberIds)
                            ->unnest()
                    ]
                );

                $this->_db2->delete('applicant_form_data', ['applicant_id' => $arrMemberIds]);

                // TODO Move this to TimeTracker
                $this->_db2->delete(
                    'time_tracker',
                    [
                        (new Where())
                            ->nest()
                            ->in('track_member_id', $arrMemberIds)
                            ->or
                            ->in('track_posted_by_member_id', $arrMemberIds)
                            ->unnest()
                    ]
                );

                // Clear main data
                $arrMainTables = array(
                    'members_divisions',
                    'members_last_access',
                    'users',
                    'default_searches',
                    'client_form_data',
                    'clients',
                    'u_tasks',
                    'u_notes',
                    'u_payment'
                );
                foreach ($arrMainTables as $table) {
                    $this->_db2->delete($table, ['member_id' => $arrMemberIds]);
                }

                $this->_systemTriggers->triggerMemberDeleted($companyId, $arrMemberIds);

                // Delete main record
                $this->_db2->delete('members', ['member_id' => $arrMemberIds]);

                if ($booSaveInLog) {
                    // Log this action
                    $arrLog = array(
                        'log_section'     => 'user',
                        'log_action'      => 'delete',
                        'log_description' => count($arrMemberNames) == 1 ? sprintf('{1} deleted %s ' . $strMemberType, $arrMemberNames[0]) : sprintf('{1} deleted such ' . $strMemberType . 's: %s', implode(', ', $arrMemberNames)),
                        'log_company_id'  => $companyId,
                        'log_created_by'  => $this->_auth->getCurrentUserId(),
                    );

                    /** @var AccessLogs $accessLogs */
                    $accessLogs = $this->_serviceContainer->get(AccessLogs::class);
                    $accessLogs->saveLog($arrLog);
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Get info about superadmin user that can be used in API calls
     *
     * @return array
     */
    public function getActiveSuperadminForAPI()
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->where(
                [
                    'm.company_id' => 0,
                    'm.status'     => 1,
                    'm.userType'   => self::getMemberType('superadmin')
                ]
            );

        return $this->_db2->fetchRow($select);
    }

    /**
     * Update member(s) status
     *
     * @param array|int $arrMemberIds
     * @param int $companyId
     * @param int $actionDoneByMemberId
     * @param bool $intStatus true to enable, false to disable
     * @return bool true on success, otherwise false
     */
    public function toggleMemberStatus($arrMemberIds, $companyId, $actionDoneByMemberId, $intStatus)
    {
        $booSuccess = false;

        try {
            $arrMemberIds = (array)$arrMemberIds;
            if (count($arrMemberIds)) {
                switch ($intStatus) {
                    case 0:
                        $logMessage = '{2} was deactivated by {1}';
                        $arrToUpdate = array(
                            'status' => $intStatus,
                            'disabled_timestamp' => time()
                        );
                        break;

                    case 1:
                        $logMessage = '{2} was activated by {1}';
                        $arrToUpdate = array(
                            'status' => $intStatus
                        );
                        break;

                    case 2:
                        $logMessage = '{2} was suspended by {1}';
                        $arrToUpdate = array(
                            'status' => $intStatus,
                            'disabled_timestamp' => time()
                        );
                        break;

                    default:
                        $logMessage = '';
                        $arrToUpdate = array();
                        break;
                }

                if (!empty($arrToUpdate)) {
                    $this->_db2->update(
                        'members',
                        $arrToUpdate,
                        ['member_id' => $arrMemberIds]
                    );

                    /** @var AccessLogs $oAccessLogs */
                    $oAccessLogs = $this->_serviceContainer->get(AccessLogs::class);
                    foreach ($arrMemberIds as $memberId) {
                        $arrLog = array(
                            'log_section' => 'user',
                            'log_action' => 'status_change',
                            'log_description' => $logMessage,
                            'log_company_id' => $companyId,
                            'log_created_by' => $actionDoneByMemberId,
                            'log_action_applied_to' => $memberId,
                        );
                        $oAccessLogs->saveLog($arrLog);
                    }

                    $booSuccess = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if email address can be changed for specific member
     *
     * @param $memberId
     * @return bool
     */
    public function canUpdateMemberEmailAddress($memberId)
    {
        $booCanChangeEmail = false;
        if (!$this->isMemberClientById($memberId)) {
            $arrAccounts = MailAccount::getAccounts($memberId);
            $booHasMailAccounts = (count($arrAccounts) > 0);

            $booCanChangeEmail = !$this->hasMemberAccessToMail($memberId) || !$booHasMailAccounts;
        }

        return $booCanChangeEmail;
    }

    /**
     * Load company email for specific member
     * @param $memberId
     * @return string
     */
    public function getMemberCompanyEmail($memberId)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(array())
            ->join(array('c' => 'company'), 'c.company_id = m.company_id', array('companyEmail'), Select::JOIN_LEFT)
            ->where(['m.member_id' => (int)$memberId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load list of members, filter them, check access rights
     *
     * @param int $companyId
     * @param $divisionGroupId
     * @param array $arrFilter
     * @param string $sort
     * @param string $dir
     * @param int $start
     * @param int $limit
     * @param string $memberType
     * @param bool $booWhereAnd
     * @return array
     */
    public function getMembersList($companyId, $divisionGroupId, $arrFilter, $sort, $dir, $start, $limit, $memberType = 'admin_and_user', $booWhereAnd = true)
    {
        $arrMembers = array();
        $totalCount = 0;

        try {
            // Prepare query
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->join(array('c' => 'company'), 'm.company_id = c.company_id', 'companyName', Select::JOIN_LEFT)
                ->group('m.member_id');

            $arrUserTypes = self::getMemberType($memberType);
            if (count($arrUserTypes) > 0) {
                $select->where(['m.userType' => $arrUserTypes]);
            }

            $booHideInactiveUsers = $arrFilter['filter_hide_inactive_users'] ?? false;
            if ($booHideInactiveUsers) {
                $select->where(['m.status' => 1]);
            }

            $oWhere = null;

            $email = isset($arrFilter['filter_email']) ? trim($arrFilter['filter_email']) : '';
            if (!empty($email)) {
                $col = 'm.emailAddress';

                if ($booWhereAnd) {
                    $select->where(
                        [
                            (new Where())->like($col, "%$email%")
                        ]
                    );
                } elseif (empty($oWhere)) {
                    $oWhere = (new Where())->nest();
                    $oWhere->like($col, "%$email%");
                } else {
                    $oWhere->or->like($col, "%$email%");
                }
            }

            $firstName = isset($arrFilter['filter_first_name']) ? trim($arrFilter['filter_first_name']) : '';
            if (strlen($firstName)) {
                $col = 'm.fName';
                if ($booWhereAnd) {
                    $select->where(
                        [
                            (new Where())->like($col, "%$firstName%")
                        ]
                    );
                } elseif (empty($oWhere)) {
                    $oWhere = (new Where())->nest();
                    $oWhere->like($col, "%$firstName%");
                } else {
                    $oWhere->or->like($col, "%$firstName%");
                }
            }

            $lastName = isset($arrFilter['filter_last_name']) ? trim($arrFilter['filter_last_name']) : '';
            if (strlen($lastName)) {
                $col = 'm.lName';
                if ($booWhereAnd) {
                    $select->where(
                        [
                            (new Where())->like($col, "%$lastName%")
                        ]
                    );
                } elseif (empty($oWhere)) {
                    $oWhere = (new Where())->nest();
                    $oWhere->like($col, "%$lastName%");
                } else {
                    $oWhere->or->like($col, "%$lastName%");
                }
            }

            $username = isset($arrFilter['filter_username']) ? trim($arrFilter['filter_username']) : '';
            if (!empty($username)) {
                $col = 'm.username';
                if ($booWhereAnd) {
                    $select->where([(new Where())->like($col, "%$username%")]);
                } elseif (empty($oWhere)) {
                    $oWhere = (new Where())->nest();
                    $oWhere->like($col, "%$username%");
                } else {
                    $oWhere->or->like($col, "%$username%");
                }
            }

            if (!$booWhereAnd) {
                $oWhere->unnest();
                $select->where([$oWhere]);
            }

            $division = isset($arrFilter['filter_division']) ? trim($arrFilter['filter_division']) : '';
            if (strlen($division)) {
                // Load list of Offices current user has access to
                $arrCompanyDivisions = $this->_company->getDivisions($companyId, $divisionGroupId, true);
                $arrCompanyDivisions = empty($arrCompanyDivisions) ? array(0) : $arrCompanyDivisions;

                $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
                $select->where(['md.division_id' => $arrCompanyDivisions]);

                $select->join(array('d' => 'divisions'), 'md.division_id = d.division_id', [], Select::JOIN_LEFT);
                $select->where(
                    [
                        (new Where())->like('d.name', "%$division%")
                    ]
                );

                $select->where->equalTo('md.type', 'access_to');
            }

            $role = isset($arrFilter['filter_role']) ? trim($arrFilter['filter_role']) : '';
            if (!empty($role)) {
                $arrRoles = explode(',', $role);
                if (!empty($arrRoles)) {
                    $select->join(array('mr' => 'members_roles'), 'mr.member_id = m.member_id', [], Select::JOIN_LEFT);
                    $select->where(['mr.role_id' => $arrRoles]);
                }
            }

            // If this is not superadmin - show users related to his company only
            if (!$this->_auth->isCurrentUserSuperadmin()) {
                $arrAllowedMembers = $this->getMembersWhichICanAccess($arrUserTypes);
                $arrAllowedMembers = is_array($arrAllowedMembers) && count($arrAllowedMembers) ? $arrAllowedMembers : array(0);
                if (count($arrAllowedMembers)) {
                    $select->where(['m.member_id' => $arrAllowedMembers]);
                }
            }

            if (!empty($companyId)) {
                $select->where(['m.company_id' => $companyId]);
            }

            $dir = strtoupper($dir) != 'ASC' ? 'DESC' : 'ASC';
            switch ($sort) {
                case 'member_email':
                    $sort = 'm.emailAddress';
                    break;

                case 'member_first_name':
                    $sort = 'm.fName';
                    break;

                case 'member_last_name':
                    $sort = 'm.lName';
                    break;

                case 'member_username':
                    $sort = 'm.username';
                    break;

                case 'member_created_on':
                    $sort = 'm.regTime';
                    break;

                case 'member_status':
                    $sort = 'm.status';
                    break;

                case 'member_id':
                default:
                    $sort = 'm.member_id';
                    break;
            }

            $select
                ->limit($limit)
                ->offset($start)
                ->order($sort . ' ' . $dir);

            $arrMembers = $this->_db2->fetchAll($select);
            $totalCount = $this->_db2->fetchResultsCount($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrMembers, $totalCount);
    }

    /**
     * Update last access time for specific member
     *
     * @param int $memberId
     * @return bool true on success
     */
    public function updateLastAccessTime($memberId)
    {
        $booSuccess = false;

        try {
            if (!empty($memberId) && is_numeric($memberId)) {
                $this->_db2->update(
                    'members',
                    ['last_access' => time()],
                    ['member_id' => (int)$memberId]
                );

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * A cron which checks for members who are marked as logged in, but session was expired -> mark them as logged out
     *
     * @return bool
     */
    public function updateLastAccessTimeByCron()
    {
        $booSuccess = false;

        try {
            $timeout = (int) $this->_config['security']['session_timeout'];
            if ($timeout > 0) {
                $select = (new Select())
                    ->from('members')
                    ->columns(array('member_id', 'company_id', 'logged_in', 'userType'))
                    ->where(
                        [
                            (new Where())
                                ->equalTo('logged_in', 'Y')
                                ->isNotNull('last_access')
                                ->lessThan('last_access', time() - $timeout)
                        ]
                    );

                $arrMembers = $this->_db2->fetchAll($select);

                if (!empty($arrMembers)) {
                    $arrIds = array();

                    /** @var AccessLogs $oLog */
                    $oLog = $this->_serviceContainer->get(AccessLogs::class);
                    foreach ($arrMembers as $member) {
                        $arrLog = array(
                            'log_section'     => $this->isMemberSuperAdmin($member['userType']) ? 'superadmin_login' : 'login',
                            'log_action'      => 'logged_out',
                            'log_description' => '{1} logged out successfully (session expired automatically)',
                            'log_company_id'  => (int)$member['company_id'],
                            'log_created_by'  => (int)$member['member_id']
                        );

                        $oLog->saveLog($arrLog, true);
                        $arrIds[] = $member['member_id'];
                    }

                    if (!empty($arrIds)) {
                        $this->_db2->update(
                            'members',
                            ['logged_in' => 'N'],
                            [
                                'logged_in' => 'Y',
                                'member_id' => $arrIds
                            ]
                        );
                    }
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Update "logged in" status for specific member
     *
     * @param int $memberId
     * @param string $value , must be Y or N
     * @return bool true on success
     */
    public function updateLoggedInOption($memberId, $value)
    {
        $booSuccess = false;

        try {
            $this->_db2->update(
                'members',
                ['logged_in' => $value],
                ['member_id' => (int)$memberId]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if member currently is logged in - log him out and save record to the log
     *
     * @param int $memberId
     * @param string $details
     * @return bool true on success, otherwise false
     */
    public function checkMemberAndLogout($memberId, $details = '')
    {
        $booSuccess = false;

        try {
            if (is_numeric($memberId) && !empty($memberId)) {
                $arrMemberInfo = $this->getMemberInfo($memberId);
                if (isset($arrMemberInfo['logged_in']) && $arrMemberInfo['logged_in'] == 'Y') {
                    $booSuperAdmin = $this->isMemberSuperAdmin($arrMemberInfo['userType']);

                    $details = empty($details) ? '' : " ($details)";

                    $arrLog = array(
                        'log_section'     => $booSuperAdmin ? 'superadmin_login' : 'login',
                        'log_action'      => 'logged_out',
                        'log_description' => '{1} logged out successfully' . $details,
                        'log_company_id'  => $arrMemberInfo['company_id'],
                        'log_created_by'  => $memberId,
                    );

                    /** @var AccessLogs $accessLogs */
                    $accessLogs = $this->_serviceContainer->get(AccessLogs::class);
                    $accessLogs->saveLog($arrLog);

                    $this->updateLoggedInOption($memberId, 'N');
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Search members by email
     *
     * @param string $email
     * @return array
     */
    public function getMembersByEmail($email)
    {
        $arrMembers = [];
        if (!empty($email)) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->where(
                    [
                        (new Where())
                            ->equalTo('m.emailAddress', $email)
                            ->notIn('m.userType', self::getMemberType('superadmin'))
                    ]
                );

            $arrMembers = $this->_db2->fetchAll($select);
        }

        return $arrMembers;
    }


    /**
     * Load and cache member types list
     *
     * @param bool $booVisibleOnly
     * @param bool $booIdsOnly
     * @return array
     */
    public function getMemberTypes($booVisibleOnly = false, $booIdsOnly = false)
    {
        $cacheId = 'members_types';
        if (!($data = $this->_cache->getItem($cacheId))) {
            $select = (new Select())
                ->from('members_types');

            $data = $this->_db2->fetchAll($select);

            $this->_cache->setItem($cacheId, $data);
        }

        if ($booVisibleOnly) {
            $arrFilteredList = array();
            foreach ($data as $arrTypeInfo) {
                if ($arrTypeInfo['member_type_visible'] == 'Y') {
                    $arrFilteredList[] = $arrTypeInfo;
                }
            }

            $data = $arrFilteredList;
        }

        if ($booIdsOnly) {
            $arrFilteredList = array();
            foreach ($data as $arrTypeInfo) {
                $arrFilteredList[] = $arrTypeInfo['member_type_id'];
            }

            $data = $arrFilteredList;
        }

        return $data;
    }

    /**
     * Load member type id by text name
     * @param $memberTypeName
     * @return bool|int
     */
    public function getMemberTypeIdByName($memberTypeName)
    {
        $memberTypeId   = false;
        $memberTypeName = strtolower($memberTypeName ?? '');

        $arrTypes = $this->getMemberTypes();
        foreach ($arrTypes as $arrTypeInfo) {
            $name = $arrTypeInfo['member_type_name'];
            if ($memberTypeName == $name || $memberTypeName == $name . 's') {
                $memberTypeId = (int)$arrTypeInfo['member_type_id'];
                break;
            }
        }

        return $memberTypeId;
    }

    /**
     * Load member type id by text name
     * @param $memberTypeId
     * @return bool|string
     */
    public function getMemberTypeNameById($memberTypeId)
    {
        $memberTypeName = false;

        $arrTypes = $this->getMemberTypes();
        foreach ($arrTypes as $arrTypeInfo) {
            if ($memberTypeId == $arrTypeInfo['member_type_id']) {
                $memberTypeName = $arrTypeInfo['member_type_name'];
                break;
            }
        }

        return $memberTypeName;
    }

    /**
     * Provides template replacements for members
     * @param array|int $memberInfo
     * @throws Exception
     */
    public function getTemplateReplacements($memberInfo)
    {
        $memberInfo = is_int($memberInfo) ? $this->getMemberInfo($memberInfo) : $memberInfo;

        $password = '';
        if (isset($memberInfo['decoded_password'])) {
            $password = $memberInfo['decoded_password'];
        } elseif (isset($memberInfo['password'])) {
            $password = $this->_encryption->decodeHashedPassword($memberInfo['password']);
        }

        return [
            '{user: first name}'    => $memberInfo['fName'] ?? '',
            '{user: last name}'     => $memberInfo['lName'] ?? '',
            '{user: username}'      => $memberInfo['username'] ?? '',
            '{user: password}'      => $password,
            '{user: email}'         => $memberInfo['emailAddress'] ?? '',
            '{user: password hash}' => $memberInfo['hash'] ?? '',
        ];
    }

    /**
     * Toggle on/off Daily Notifications for a user
     *
     * @param int $memberId
     * @param bool $booEnable
     * @return bool
     */
    public function toggleDailyNotifications($memberId, $booEnable)
    {
        $arrUpdateMemberInfo = array(
            'enable_daily_notifications' => $booEnable ? 'Y' : 'N'
        );

        return $this->updateMemberData($memberId, $arrUpdateMemberInfo);
    }

    /**
     * Check if Daily Notifications are enabled for the user
     *
     * @param int $memberId
     * @return bool
     */
    public function areDailyNotificationsEnabledToMember($memberId = 0)
    {
        $arrMemberInfo = $this->getMemberInfo($memberId);

        return isset($arrMemberInfo['enable_daily_notifications']) && $arrMemberInfo['enable_daily_notifications'] === 'Y';
    }

    /**
     * Update date/time of the last viewed banner
     *
     * @param int $memberId
     * @return bool
     */
    public function updateBannerLastViewedTime($memberId)
    {
        $arrUpdateMemberInfo = array(
            'special_announcements_viewed_on' => date('Y-m-d H:i:s')
        );

        return $this->updateMemberData($memberId, $arrUpdateMemberInfo);
    }

    /**
     * Load the list of users to which we want to send announcements
     *
     * @return array
     */
    public function getUsersForMailingList()
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(array('member_id', 'emailAddress', 'fName', 'lName', 'company_id'))
            ->join(array('c' => 'company'), 'm.company_id = c.company_id', [])
            ->where([
                (new Where())
                    ->notIn('m.company_id', [0])
                    ->equalTo('m.enable_daily_notifications', 'Y')
                    ->notEqualTo('m.emailAddress', '')
                    ->isNotNull('m.emailAddress')
                    ->equalTo('c.Status', 1)
                    ->equalTo('m.status', 1)
                    ->in('m.userType', self::getMemberType('admin_and_staff'))
            ]);

        return $this->_db2->fetchAll($select);
    }
}
