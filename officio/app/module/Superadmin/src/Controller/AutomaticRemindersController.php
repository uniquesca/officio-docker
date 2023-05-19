<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Officio\Service\AutomaticReminders;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Automatic Reminders Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AutomaticRemindersController extends BaseController
{
    /** @var  AutomaticReminders */
    private $_automaticReminders;

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_company            = $services[Company::class];
        $this->_clients            = $services[Clients::class];
        $this->_automaticReminders = $services[AutomaticReminders::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $booSuperAdmin         = false;
        $booAutomaticTurnedOn  = false;
        $arrTriggerSettings    = array();
        $arrConditionSettings  = array();
        $arrApplicantsSettings = array();
        $officeLabel           = '';
        try {
            $currentMemberCompanyId = $this->_auth->getCurrentUserCompanyId();
            $booSuperAdmin          = $this->_auth->isCurrentUserSuperadmin();
            $arrTriggerSettings     = $this->_automaticReminders->getTriggers()->getTriggerSettings();
            $arrConditionSettings   = $this->_automaticReminders->getConditions()->getConditionSettings();
            $arrApplicantsSettings  = $this->_clients->getSettings(
                $this->_auth->getCurrentUserId(),
                $currentMemberCompanyId,
                $this->_auth->getCurrentUserDivisionGroupId()
            );

            $booAutomaticTurnedOn = $this->_clients->getCaseNumber()->isAutomaticTurnedOn($currentMemberCompanyId);

            if ($booSuperAdmin) {
                $officeLabel = "Agent's Office";
            } else {
                $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $title = $booSuperAdmin ? "Default Automatic Tasks" : "Automatic Tasks";
        $this->layout()->setVariable('title', $title);
        $view->setVariable('is_superadmin', $booSuperAdmin);
        $view->setVariable('arrTriggerSettings', $arrTriggerSettings);
        $view->setVariable('arrConditionSettings', $arrConditionSettings);
        $view->setVariable('officeLabel', $officeLabel);
        $view->setVariable('booAutomaticTurnedOn', $booAutomaticTurnedOn);

        $view->setVariable('arrApplicantsSettings', $arrApplicantsSettings);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    public function getGridAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $arrReminders = array();
        $totalCount   = 0;
        $booSuccess   = false;

        try {
            $start = (int)$this->params()->fromPost('start');
            $limit = (int)$this->params()->fromPost('limit');

            list($arrReminders, $totalCount) = $this->_automaticReminders->getGrid($start, $limit);
            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrReminders,
            'totalCount' => $totalCount
        );
        return new JsonModel($arrResult);
    }

    public function getReminderInfoAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError        = '';
        $arrReminderInfo = array();

        try {
            $reminderId = (int)$this->params()->fromPost('reminder_id');
            if (!empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrReminderInfo = $this->_automaticReminders->getDetailedReminderInfo(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array_merge(
            $arrReminderInfo,
            array('success' => empty($strError), 'msg' => $strError)
        );
        return new JsonModel($arrResult);
    }

    public function processReminderAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $reminderId           = (int)Json::decode($this->params()->fromPost('reminder_id'), Json::TYPE_ARRAY);
            $reminder             = trim($filter->filter(Json::decode($this->params()->fromPost('reminder', ''), Json::TYPE_ARRAY)));
            $booActiveClientsOnly = (bool)Json::decode($this->params()->fromPost('active_clients_only'), Json::TYPE_ARRAY);
            $arrTriggerTypes      = Json::decode($this->params()->fromPost('trigger_types'), Json::TYPE_ARRAY);
            $arrActionIds         = Json::decode($this->params()->fromPost('action_ids'), Json::TYPE_ARRAY);
            $arrConditionIds      = Json::decode($this->params()->fromPost('condition_ids'), Json::TYPE_ARRAY);

            if (!empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && empty($reminder)) {
                $strError = $this->_tr->translate('Task name is a required field.');
            }

            if (empty($strError) && empty($arrTriggerTypes)) {
                $strError = $this->_tr->translate('Please check at least one Trigger.');
            }

            if (empty($strError)) {
                foreach ($arrTriggerTypes as $triggerTypeId) {
                    if (!$this->_automaticReminders->getTriggers()->isCorrectTriggerType($triggerTypeId)) {
                        $strError = $this->_tr->translate('Incorrectly selected trigger type.');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $booCreateReminder = empty($reminderId);
                $companyId         = $this->_auth->getCurrentUserCompanyId();
                list($strError, $reminderId) = $this->_automaticReminders->createUpdateReminder($companyId, $reminderId, $reminder, $booActiveClientsOnly);

                if (empty($strError) && empty($reminderId)) {
                    $strError = $this->_tr->translate('Internal error.');
                }

                $reminderId = (int)$reminderId;

                if (empty($strError) && !empty($arrTriggerTypes) && !$this->_automaticReminders->getTriggers()->assignToReminder($companyId, $reminderId, $arrTriggerTypes)) {
                    $strError = $this->_tr->translate('Triggers were not assigned to auto task.');
                }

                if (empty($strError) && $booCreateReminder && !empty($arrActionIds) && !$this->_automaticReminders->getActions()->assignToReminder($companyId, $reminderId, $arrActionIds)) {
                    $strError = $this->_tr->translate('Actions were not assigned to auto task.');
                }

                if (empty($strError) && $booCreateReminder && !empty($arrConditionIds) && !$this->_automaticReminders->getConditions()->assignToReminder($companyId, $reminderId, $arrConditionIds)) {
                    $strError = $this->_tr->translate('Conditions were not assigned to auto task.');
                }

                if (!empty($strError) && $booCreateReminder && !empty($reminderId)) {
                    $this->_automaticReminders->deleteReminder($companyId, $reminderId);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'msg' => $strError));
    }

    public function deleteAction()
    {
        $strError = '';

        try {
            $reminderId = (int)$this->params()->fromPost('reminder_id');
            if (!$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_automaticReminders->deleteReminder($this->_auth->getCurrentUserCompanyId(), $reminderId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'message' => $strError));
    }
}