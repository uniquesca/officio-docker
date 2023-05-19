<?php

namespace Superadmin\Controller;

use Exception;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\Service\AutomaticReminders;
use Officio\BaseController;

/**
 * Automatic Reminder Triggers Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AutomaticReminderTriggersController extends BaseController
{

    /** @var  AutomaticReminders */
    private $_automaticReminders;

    public function initAdditionalServices(array $services)
    {
        $this->_automaticReminders        = $services[AutomaticReminders::class];
    }

    public function getGridAction()
    {
        $view = new JsonModel();
        $strError                     = '';
        $arrActions                   = array();
        $booShowChangedFieldCondition = $booLastFieldValueChangedTrigger = false;

        try {
            $reminderId   = (int)$this->findParam('reminder_id');
            /** @var array $arrTriggerIds */
            $arrTriggerIds = Json::decode($this->findParam('trigger_ids'), Json::TYPE_ARRAY);
            if (!is_array($arrTriggerIds)) {
                $strError = $this->_tr->translate('Incorrect triggers.');
            }


            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && count($arrTriggerIds)) {
                foreach ($arrTriggerIds as $triggerId) {
                    if (!$this->_automaticReminders->getTriggers()->hasAccessToTrigger($triggerId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to action.');
                        break;
                    }
                }
            }

            if (empty($strError) && (!empty($reminderId) || count($arrTriggerIds))) {
                /** @var array $arrActions */
                list($arrActions, $booShowChangedFieldCondition, $booLastFieldValueChangedTrigger) = $this->_automaticReminders->getTriggers()->getReminderTriggers(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $arrTriggerIds,
                    true
                );
            }

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'                         => empty($strError),
            'rows'                            => $arrActions,
            'totalCount'                      => count($arrActions),
            'booShowChangedFieldCondition'    => $booShowChangedFieldCondition,
            'booLastFieldValueChangedTrigger' => $booLastFieldValueChangedTrigger
        );
        return $view->setVariables($arrResult);
    }

    public function getAction()
    {
        $view = new JsonModel();
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError       = '';
        $arrTriggerInfo = array();

        try {
            $reminderId = (int)$this->findParam('reminder_id');
            $triggerId  = (int)$this->findParam('trigger_id');

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !empty($triggerId) && !is_numeric($triggerId)) {
                $strError = $this->_tr->translate('Incorrectly selected trigger.');
            }

            if (empty($strError) && !empty($triggerId) && !$this->_automaticReminders->getTriggers()->hasAccessToTrigger($triggerId)) {
                $strError = $this->_tr->translate('Insufficient access rights to trigger.');
            }

            if (empty($strError)) {
                $arrTriggerInfo = $this->_automaticReminders->getTriggers()->getTrigger(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $triggerId
                );

                if (empty($arrTriggerInfo)) {
                    $arrTriggerInfo = array();
                    $strError       = $this->_tr->translate('Internal error.');
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array_merge(
            $arrTriggerInfo,
            array('success' => empty($strError), 'msg' => $strError)
        );
        return $view->setVariables($arrResult);
    }

    public function saveAction()
    {
        $view = new JsonModel();
        $strError = $triggerId = '';

        try {
            $reminderId    = (int)$this->findParam('reminder_id');
            $triggerId     = (int)$this->findParam('trigger_id');
            $triggerTypeId = (int)$this->findParam('trigger_type_id');

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !empty($triggerId) && !$this->_automaticReminders->getTriggers()->hasAccessToTrigger($triggerId)) {
                $strError = $this->_tr->translate('Insufficient access rights to trigger.');
            }

            if (empty($strError) && !$this->_automaticReminders->getTriggers()->isCorrectTriggerType($triggerTypeId)) {
                $strError = $this->_tr->translate('Incorrectly selected trigger type.');
            }

            if (empty($strError)) {
                $triggerId = $this->_automaticReminders->getTriggers()->save(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $triggerId,
                    $triggerTypeId
                );

                if (!$triggerId) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => empty($strError), 'msg' => $strError, 'trigger_id' => $triggerId));
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $reminderId = (int)$this->findParam('reminder_id');
            $triggerId  = (int)Json::decode($this->findParam('trigger_id'), Json::TYPE_ARRAY);

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !$this->_automaticReminders->getTriggers()->hasAccessToTrigger($triggerId)) {
                $strError = $this->_tr->translate('Insufficient access rights to trigger.');
            }

            if (empty($strError)) {
                if ($this->_automaticReminders->getTriggers()->delete($this->_auth->getCurrentUserCompanyId(), $triggerId)) {
                    $this->_automaticReminders->getTriggers()->deleteDependentConditions($reminderId);
                } else {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => empty($strError), "error" => $strError));
    }
}