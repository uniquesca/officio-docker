<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\Service\AutomaticReminders;
use Officio\BaseController;

/**
 * Automatic Reminder Actions Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AutomaticReminderActionsController extends BaseController
{
    /** @var  AutomaticReminders */
    protected $_automaticReminders;

    public function initAdditionalServices(array $services)
    {
        $this->_automaticReminders = $services[AutomaticReminders::class];
    }

    public function getGridAction()
    {
        $strError   = '';
        $arrActions = array();

        try {
            $reminderId   = (int)$this->params()->fromPost('reminder_id');
            $arrActionIds = Json::decode($this->params()->fromPost('action_ids'), Json::TYPE_ARRAY);
            if (!is_array($arrActionIds)) {
                $strError = $this->_tr->translate('Incorrect actions.');
            }

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && count($arrActionIds)) {
                foreach ($arrActionIds as $actionId) {
                    if (!$this->_automaticReminders->getActions()->hasAccessToAction($actionId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to action.');
                        break;
                    }
                }
            }

            if (empty($strError) && (!empty($reminderId) || count($arrActionIds))) {
                $arrActions = $this->_automaticReminders->getActions()->getReminderActions(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $arrActionIds,
                    true
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'rows'       => $arrActions,
            'totalCount' => count($arrActions)
        );
        return new JsonModel($arrResult);
    }

    public function getAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError      = '';
        $arrActionInfo = array();

        try {
            $filter     = new StripTags();
            $reminderId = (int)$this->params()->fromPost('reminder_id');
            $actionId   = (int)$this->params()->fromPost('action_id');
            $act        = $filter->filter(Json::decode($this->params()->fromPost('act'), Json::TYPE_ARRAY));

            if (empty($strError) && (empty($act))) {
                $strError = $this->_tr->translate('Incorrectly selected operation with action.');
            }

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && $act == 'edit' && (!is_numeric($actionId) || empty($actionId))) {
                $strError = $this->_tr->translate('Incorrectly selected action.');
            }

            if (empty($strError) && !empty($actionId) && !$this->_automaticReminders->getActions()->hasAccessToAction($actionId)) {
                $strError = $this->_tr->translate('Insufficient access rights to action.');
            }

            if (empty($strError)) {
                $arrActionInfo = $this->_automaticReminders->getActions()->getDetailedActionInfo(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $actionId
                );

                if (empty($arrActionInfo)) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array_merge(
            $arrActionInfo,
            array('success' => empty($strError), 'msg' => $strError)
        );
        return new JsonModel($arrResult);
    }

    public function saveAction()
    {
        $strError = $actionId = '';

        try {
            $filter       = new StripTags();
            $reminderId   = (int)$this->params()->fromPost('reminder_id');
            $actionId     = (int)$this->params()->fromPost('action_id');
            $actionTypeId = (int)$this->params()->fromPost('action_type_id');
            $arrSettings  = Json::decode($this->params()->fromPost('settings'), Json::TYPE_ARRAY);

            foreach ($arrSettings as $key => $val) {
                $arrSettings[$key] = $filter->filter($val);
                if ($key == 'date' && !strtotime($val)) {
                    $strError = $this->_tr->translate('Incorrectly selected date.');
                }
            }

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !empty($actionId) && !$this->_automaticReminders->getActions()->hasAccessToAction($actionId)) {
                $strError = $this->_tr->translate('Insufficient access rights to action.');
            }

            if (empty($strError)) {
                $actionId = $this->_automaticReminders->getActions()->save(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $actionId,
                    $actionTypeId,
                    $arrSettings
                );

                if (!$actionId) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'success'   => empty($strError),
            'msg'       => $strError,
            'action_id' => $actionId
        ];

        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError = '';
        try {
            $reminderId = (int)$this->params()->fromPost('reminder_id');
            $actionId   = (int)Json::decode($this->params()->fromPost('action_id'), Json::TYPE_ARRAY);

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !$this->_automaticReminders->getActions()->hasAccessToAction($actionId)) {
                $strError = $this->_tr->translate('Insufficient access rights to action.');
            }

            if (empty($strError) && !$this->_automaticReminders->getActions()->delete($this->_auth->getCurrentUserCompanyId(), $actionId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }
}