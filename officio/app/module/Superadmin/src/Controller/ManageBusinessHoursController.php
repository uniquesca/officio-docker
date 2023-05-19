<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Clients\Service\BusinessHours;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Settings;

/**
 * Manage Business Hours Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageBusinessHoursController extends BaseController
{
    /** @var BusinessHours */
    private $_businessSchedule;

    /** @var StripTags */
    private $_filter;

    public function initAdditionalServices(array $services)
    {
        $this->_filter           = new StripTags();
        $this->_businessSchedule = $services[BusinessHours::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function holidaysViewAction()
    {
        $arrHolidays = array();
        try {
            $memberId  = Json::decode($this->findParam('member_id'), Json::TYPE_ARRAY);
            $companyId = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);

            $dir = strtoupper($this->_filter->filter($this->findParam('dir', '')));
            if ($dir != 'ASC') {
                $dir = 'DESC';
            }

            $sort = $this->findParam('sort');
            $sort = in_array($sort, array('holiday_name', 'holiday_date_from')) ? $sort : 'holiday_date_from';

            if (empty($companyId)) {
                // Check if current user has access to this member
                if (!is_numeric($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_acl->isAllowed('manage-members-business-hours-holidays-view')) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError)) {
                    $arrMemberInfo = $this->_members->getMemberInfo($memberId);
                    $arrHolidays   = $this->_businessSchedule->getHolidayRecords($memberId, $arrMemberInfo['company_id'], $sort, $dir);
                }
            } else {
                // Check if current user has access to this member
                if (!is_numeric($companyId) || !$this->_members->hasCurrentMemberAccessToCompany($companyId) || !$this->_acl->isAllowed('manage-company-business-hours-holidays-view')) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError)) {
                    $arrHolidays = $this->_businessSchedule->getHolidayRecords(null, $companyId, $sort, $dir);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrHolidays,
            'totalCount' => count($arrHolidays)
        );

        return new JsonModel($arrResult);
    }

    private function manageHolidayRecord()
    {
        $strError = '';
        try {
            $memberId  = $this->findParam('member_id');
            $companyId = $this->findParam('company_id');
            $holidayId = (int)$this->findParam('holiday_id');

            // Check if current user has access to this member/company
            if (empty($companyId)) {
                $accessId = empty($holidayId) ? 'manage-members-business-hours-holidays-add' : 'manage-members-business-hours-holidays-edit';
                if (!is_numeric($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_acl->isAllowed($accessId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            } else {
                $accessId = empty($holidayId) ? 'manage-company-business-hours-holidays-add' : 'manage-company-business-hours-holidays-edit';
                if (!is_numeric($companyId) || !$this->_members->hasCurrentMemberAccessToCompany($companyId) || !$this->_acl->isAllowed($accessId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strError)) {
                $arrHolidayInfo = array(
                    'holiday_id'        => $holidayId,
                    'member_id'         => $memberId,
                    'company_id'        => $companyId,
                    'holiday_name'      => trim($this->_filter->filter($this->findParam('holiday_name', ''))),
                    'holiday_date_from' => $this->_filter->filter($this->findParam('holiday_date_from')),
                    'holiday_date_to'   => $this->_filter->filter($this->findParam('holiday_date_to')),
                );

                if (!empty($arrHolidayInfo['holiday_id']) && !$this->_businessSchedule->hasAccessToHolidayRecord($arrHolidayInfo['holiday_id'], $memberId, $companyId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError) && empty($arrHolidayInfo['holiday_name'])) {
                    $strError = $this->_tr->translate('Name is a required field.');
                }

                if (Settings::isDateEmpty($arrHolidayInfo['holiday_date_to'])) {
                    $arrHolidayInfo['holiday_date_to'] = null;
                }

                if (empty($strError)) {
                    if (empty($arrHolidayInfo['member_id'])) {
                        unset($arrHolidayInfo['member_id']);
                    }

                    if (empty($arrHolidayInfo['company_id'])) {
                        unset($arrHolidayInfo['company_id']);
                    }

                    if (!isset($arrHolidayInfo['member_id']) && !isset($arrHolidayInfo['company_id'])) {
                        $strError = $this->_tr->translate('Incorrect incoming info.');
                    }
                }

                if (empty($strError) && !$this->_businessSchedule->saveHolidayRecord($arrHolidayInfo)) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Information was saved successfully.') : $strError
        );
    }


    public function holidaysAddAction()
    {
        return new JsonModel($this->manageHolidayRecord());
    }

    public function holidaysEditAction()
    {
        return new JsonModel($this->manageHolidayRecord());
    }

    public function holidaysDeleteAction()
    {
        $strError = '';
        $count    = 0;
        try {
            $memberId       = $this->findParam('member_id');
            $companyId      = $this->findParam('company_id');
            /** @var array $arrHolidaysIds */
            $arrHolidaysIds = Json::decode($this->findParam('arrIds'), Json::TYPE_ARRAY);
            if (!is_array($arrHolidaysIds)) {
                $strError = $this->_tr->translate('Incorrect holidays.');
            }

            // Check if current user has access to this member/company
            if (empty($companyId)) {
                if (!is_numeric($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_acl->isAllowed('manage-members-business-hours-holidays-delete')) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            } elseif (!is_numeric($companyId) || !$this->_members->hasCurrentMemberAccessToCompany($companyId) || !$this->_acl->isAllowed('manage-company-business-hours-holidays-delete')) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $count = count($arrHolidaysIds);
            if (empty($strError) && (!is_array($arrHolidaysIds) || empty($arrHolidaysIds))) {
                $strError = $this->_tr->translate('Incorrectly selected records.');
            }

            if (empty($strError)) {
                foreach ($arrHolidaysIds as $holidayId) {
                    if (!$this->_businessSchedule->hasAccessToHolidayRecord($holidayId, $memberId, $companyId)) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                        break;
                    }
                }
            }

            if (empty($strError) && !$this->_businessSchedule->deleteHolidayRecords($arrHolidaysIds)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translatePlural('The record was deleted successfully.', 'Selected records were deleted successfully.', $count) : $strError
        );

        return new JsonModel($arrResult);
    }


    public function loadWorkdaysDataAction()
    {
        $arrWorkdaysData = array();
        $strError        = '';

        try {
            $memberId  = Json::decode($this->findParam('member_id'), Json::TYPE_ARRAY);
            $companyId = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);

            // Check if current user has access to this member/company
            if (empty($companyId)) {
                if (!is_numeric($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_acl->isAllowed('manage-members-business-hours-workdays-view')) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            } elseif (!is_numeric($companyId) || !$this->_members->hasCurrentMemberAccessToCompany($companyId) || !$this->_acl->isAllowed('manage-company-business-hours-workdays-view')) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                if (!empty($companyId)) {
                    $memberId = null;
                } else {
                    $companyId = null;
                }

                $arrWorkdaysData = $this->_businessSchedule->getWorkdays($memberId, $companyId);
                $arrWorkdaysData = empty($arrWorkdaysData) ? array('business_time_enabled' => 'N') : array_merge($arrWorkdaysData, array('business_time_enabled' => 'Y'));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'data'    => $arrWorkdaysData
        );

        return new JsonModel($arrResult);
    }

    public function saveWorkdaysDataAction()
    {
        $strError = '';

        try {
            $memberId  = Json::decode($this->findParam('member_id'), Json::TYPE_ARRAY);
            $companyId = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);

            // Check if current user has access to this member
            if (empty($companyId)) {
                if (!is_numeric($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_acl->isAllowed('manage-members-business-hours-workdays-update')) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            } elseif (!is_numeric($companyId) || !$this->_members->hasCurrentMemberAccessToCompany($companyId) || !$this->_acl->isAllowed('manage-company-business-hours-workdays-update')) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrDataToSave = array();

                if ($this->findParam('business_time_enabled') == 'Y') {
                    $arrAllowedTimes = $this->_businessSchedule::getAllowedTimeRange();

                    for ($i = 0; $i < 7; $i++) {
                        $day = strtolower(jddayofweek($i, 1));

                        $key   = $day . '_time_enabled';
                        $value = $this->_filter->filter($this->findParam($key));

                        $arrDataToSave[$key] = empty($value) ? 'N' : 'Y';

                        $key   = $day . '_time_from';
                        $value = $this->_filter->filter($this->findParam($key));

                        $arrDataToSave[$key] = in_array($value, $arrAllowedTimes) ? $value : null;

                        $key   = $day . '_time_to';
                        $value = $this->_filter->filter($this->findParam($key));

                        $arrDataToSave[$key] = in_array($value, $arrAllowedTimes) ? $value : null;
                    }
                }

                $booSuccess = empty($memberId) ? $this->_businessSchedule->updateCompanyWorkdays($companyId, $arrDataToSave) : $this->_businessSchedule->updateUserWorkdays($memberId, $arrDataToSave);
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
            'message' => empty($strError) ? $this->_tr->translate('Information was saved successfully.') : $strError
        );

        return new JsonModel($arrResult);
    }
}
