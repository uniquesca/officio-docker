<?php

namespace Clients\Service;

use Clients\Model;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Service\Roles;
use Officio\Service\Users;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class BusinessHours extends BaseService
{
    /** @var Model\BusinessHoursWorkdays */
    private $_businessTime;

    /** @var Model\BusinessHoursHoliday */
    private $_holidays;

    /** @var Company */
    protected $_company;

    /** @var Roles */
    protected $_roles;

    /** @var Users */
    protected $_users;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_roles = $services[Roles::class];
        $this->_users = $services[Users::class];
    }

    public function init()
    {
        $this->_businessTime = new Model\BusinessHoursWorkdays($this->_db2, $this->_log);
        $this->_holidays     = new Model\BusinessHoursHoliday($this->_db2, $this->_log);
    }

    /**
     * Check if a user can login to Officio (if now are "business hours").
     * Don't check user/company settings if:
     * 1. This is a superadmin (or superadmin logged in as company admin)
     * 2. This is a client
     * 3. This is a superadmin logged in as company admin
     * 4. This is the main admin of the company
     * 5. User's role has access to "business hours" management rules (at least one)
     *
     * @param int $memberId
     * @return bool true if user can login
     */
    public function areUserBusinessHoursNow($memberId)
    {
        $arrUserInfo = $this->_users->getUserInfo($memberId);

        // ************************************
        // Check if this is a superadmin/client
        // ************************************
        if ($this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin() || $this->_auth->isCurrentUserClient() || $this->_users->isMemberSuperAdmin(
                $arrUserInfo['userType']
            ) || $this->_users->isMemberClient($arrUserInfo['userType'])) {
            return true;
        }

        // Something wrong?
        if (!isset($arrUserInfo['company_id'])) {
            return false;
        }

        // *********************************************
        // Check if this is a main admin of the company
        // *********************************************
        $arrCompanyInfo = $this->_company->getCompanyInfo($arrUserInfo['company_id']);
        if ($arrCompanyInfo['admin_id'] == $memberId) {
            return true;
        }

        // **********************************************
        // Check if has access to "business hours" rules
        // **********************************************
        $arrMemberRoles = $this->_users->getMemberRoles($memberId, false);
        if (!empty($arrMemberRoles)) {
            $arrMemberRolesIds = array();
            foreach ($arrMemberRoles as $arrMemberRoleInfo) {
                $arrMemberRolesIds[] = $arrMemberRoleInfo['role_parent_id'];
            }
            $arrAssignedRulesIds = $this->_roles->getAssignedRulesIds($arrMemberRolesIds);

            $arrAllowedRules = array(
                'manage-members-business-hours-workdays-update',
                'manage-members-business-hours-holidays-add',
                'manage-members-business-hours-holidays-edit',
                'manage-members-business-hours-holidays-delete',

                'manage-company-business-hours-workdays-update',
                'manage-company-business-hours-holidays-add',
                'manage-company-business-hours-holidays-edit',
                'manage-company-business-hours-holidays-delete',
            );

            $arrRequiredRuleIds = $this->_roles->getRuleIdsByCheckIds($arrAllowedRules);

            if (!empty($arrAssignedRulesIds) && !empty($arrRequiredRuleIds) && array_intersect($arrAssignedRulesIds, $arrRequiredRuleIds)) {
                return true;
            }
        }

        // In case there is no record in the "users" table
        $arrUserInfo['member_id'] = $memberId;

        $tz = $this->_users->getMemberTimezone($arrUserInfo);
        $tz = $tz instanceof DateTimeZone ? $tz : null;

        $dateNow = new DateTime('now', $tz);

        // ***************
        // Check workdays
        // ***************
        $arrWorkdays = $this->getUserWorkdays($memberId);
        if (empty($arrWorkdays)) {
            $arrWorkdays = $this->getCompanyWorkdays($arrUserInfo['company_id']);
        }

        $booBusinessTime = false;
        if (!empty($arrWorkdays)) {
            $prefixDay = strtolower($dateNow->format('l'));
            if (isset($arrWorkdays[$prefixDay . '_time_enabled']) && $arrWorkdays[$prefixDay . '_time_enabled'] == 'Y' && !empty($arrWorkdays[$prefixDay . '_time_from']) && !empty($arrWorkdays[$prefixDay . '_time_to'])) {
                $dateFrom = new DateTime($arrWorkdays[$prefixDay . '_time_from'], $tz);
                $dateTo = new DateTime($arrWorkdays[$prefixDay . '_time_to'], $tz);

                if ($dateFrom <= $dateNow && $dateNow <= $dateTo) {
                    $booBusinessTime = true;
                }
            } else {
                // Checkbox wasn't checked for the current day
                // Or no time was selected
                $booBusinessTime = false;
            }
        } else {
            // Business time wasn't set for the user neither for the company
            $booBusinessTime = true;
        }

        // ***************
        // Check holidays
        // ***************
        if ($booBusinessTime) {
            $arrHolidays = $this->getHolidayRecords($memberId, $arrUserInfo['company_id']);
            foreach ($arrHolidays as $arrHolidayInfo) {
                if (empty($arrHolidayInfo['holiday_date_from'])) {
                    continue;
                }

                if (!empty($arrHolidayInfo['holiday_date_to'])) {
                    // Date range
                    $dateFrom = new DateTime($arrHolidayInfo['holiday_date_from'], $tz);
                    $dateTo = new DateTime($arrHolidayInfo['holiday_date_to'], $tz);

                    if ($dateFrom <= $dateNow && $dateNow <= $dateTo) {
                        $booBusinessTime = false;
                        break;
                    }
                } else {
                    // A simple date
                    $dateSpecific = new DateTime($arrHolidayInfo['holiday_date_from']);

                    if ($dateNow->format('Y-m-d') == $dateSpecific->format('Y-m-d')) {
                        $booBusinessTime = false;
                        break;
                    }
                }
            }
        }

        return $booBusinessTime;
    }

    /**
     * Get list of options for the "time" combobox
     *
     * @return array
     */
    public static function getAllowedTimeRange()
    {
        $startTime = new DateTime('2010-01-01 00:00');
        $endTime = new DateTime('2010-01-01 23:55');
        $timeStep = 15;

        $arrAllowedTimes = array();
        while ($startTime <= $endTime) {
            $arrAllowedTimes[] = $startTime->format('H:i');
            $startTime->add(new DateInterval('PT' . $timeStep . 'M'));
        }

        return $arrAllowedTimes;
    }

    /**
     * Check if current user has access to the specific holiday record
     *
     * @param int $holidayRecordId
     * @param int $memberId
     * @param int $companyId
     * @return bool true if has access, otherwise false
     */
    public function hasAccessToHolidayRecord($holidayRecordId, $memberId, $companyId)
    {
        $booHasAccess = false;

        try {
            $arrHolidayInfo = $this->getHolidayRecord($holidayRecordId);

            if (empty($companyId)) {
                if (isset($arrHolidayInfo['member_id']) && !empty($arrHolidayInfo['member_id']) && $arrHolidayInfo['member_id'] == $memberId) {
                    $booHasAccess = $this->_users->hasCurrentMemberAccessToMember($arrHolidayInfo['member_id']);
                }
            } elseif (isset($arrHolidayInfo['company_id']) && !empty($arrHolidayInfo['company_id']) && $arrHolidayInfo['company_id'] == $companyId) {
                $booHasAccess = $this->_users->hasCurrentMemberAccessToCompany($arrHolidayInfo['company_id']);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Delete specific holiday records
     *
     * @param array $arrHolidayRecordsIds
     * @return bool true on success, otherwise false
     */
    public function deleteHolidayRecords($arrHolidayRecordsIds)
    {
        return $this->_holidays->deleteHolidayRecords($arrHolidayRecordsIds);
    }

    /**
     * Load saved holiday record
     *
     * @param int $holidayRecordId
     * @return array
     */
    public function getHolidayRecord($holidayRecordId)
    {
        return $this->_holidays->getHolidayRecord($holidayRecordId);
    }

    /**
     * Load list  of holiday records created for user/company
     *
     * @param ?int $memberId
     * @param ?int $companyId
     * @param string $sort
     * @param string $dir
     * @return array
     */
    public function getHolidayRecords($memberId, $companyId, $sort = '', $dir = '')
    {
        return $this->_holidays->getHolidayRecords($memberId, $companyId, $sort, $dir);
    }

    /**
     * Create/update holiday record
     *
     * @param array $arrHolidayInfo
     * @return bool
     */
    public function saveHolidayRecord($arrHolidayInfo)
    {
        return $this->_holidays->saveHolidayRecord($arrHolidayInfo);
    }

    /**
     * Load "workdays" for specific user
     *
     * @param int $memberId
     * @return array
     */
    public function getUserWorkdays($memberId)
    {
        return $this->_businessTime->getWorkdays($memberId);
    }

    /**
     * Load "workdays" for specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyWorkdays($companyId)
    {
        return $this->_businessTime->getWorkdays(null, $companyId);
    }

    /**
     * Load "workdays" for specific user AND/OR company
     *
     * @param int $memberId
     * @param int $companyId
     * @return array
     */
    public function getWorkdays($memberId, $companyId)
    {
        return $this->_businessTime->getWorkdays($memberId, $companyId);
    }

    /**
     * Create/update "workdays" for specific user
     *
     * @param int $memberId
     * @param array $arrDataToSave
     * @return bool
     */
    public function updateUserWorkdays($memberId, $arrDataToSave)
    {
        try {
            $this->_businessTime->updateUserWorkdays($memberId, $arrDataToSave);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create/update "workdays" for specific company
     *
     * @param int $companyId
     * @param array $arrDataToSave
     * @return bool
     */
    public function updateCompanyWorkdays($companyId, $arrDataToSave)
    {
        try {
            $this->_businessTime->updateCompanyWorkdays($companyId, $arrDataToSave);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}