<?php

namespace Tasks\Controller;

use Clients\Service\Clients;
use DateTime;
use DateTimeZone;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;

/**
 * Main tasks controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var Tasks */
    private $_tasks;

    /** @var StripTags */
    private $_filter;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var CompanyProspects */
    protected $_companyProspects;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_clients          = $services[Clients::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_tasks            = $services[Tasks::class];
        $this->_filter           = new StripTags();
    }

    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }

    public function getListAction()
    {
        $strError = '';
        $arrTasks = array();

        try {
            $sort        = $this->_filter->filter($this->params()->fromPost('sort'));
            $dir         = $this->_filter->filter($this->params()->fromPost('dir'));
            $booProspect = $this->params()->fromPost('booProspect') == 'true';

            // Filter params
            $arrFilterParams = array(
                'clientId' => (int)$this->params()->fromPost('clientId', 0),
                'status'   => $this->_filter->filter($this->params()->fromPost('task-filter-status')),
                'assigned' => $this->_filter->filter($this->params()->fromPost('task-filter-assigned')),
                'owner'    => $this->params()->fromPost('task-filter-owner')
            );

            if (!in_array($arrFilterParams['status'], array('', 'active', 'completed', 'due_today', 'due_tomorrow', 'due_next_7_days'))) {
                $strError = 'Incorrect status';
            } elseif (empty($arrFilterParams['status'])) {
                unset($arrFilterParams['status']);
            }

            // For the prospect - we don't show all menu filters (only the Status)
            // as a result - we need to load tasks with a specific status, no matter who assigned to and who is the assigner
            if ($booProspect) {
                unset($arrFilterParams['assigned']);
                unset($arrFilterParams['owner']);

                if (!$this->_companyProspects->allowAccessToProspect($arrFilterParams['clientId'])) {
                    $strError = $this->_tr->translate('Insufficient access rights to prospect.');
                }
            } else {
                // This is My Tasks or Client's Tasks
                if (empty($strError)) {
                    if (empty($arrFilterParams['clientId'])) {
                        // My Tasks (Admin/User/Superadmin)
                        $booHasAccess = $this->_members->hasCurrentMemberAccessToMember($arrFilterParams['owner']);
                    } else {
                        // Client's Tasks
                        $booHasAccess = $this->_members->hasCurrentMemberAccessToMember($arrFilterParams['clientId']);
                    }

                    if (!$booHasAccess) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }

                if (empty($strError)) {
                    $arrAllowedAssignedOptions = array('me', 'following', 'me_and_following', 'others');
                    if (!empty($arrFilterParams['clientId'])) {
                        // For Clients also we have 'Anyone' option, not available for My Tasks
                        $arrAllowedAssignedOptions[] = 'anyone';
                    }

                    if (!in_array($arrFilterParams['assigned'], $arrAllowedAssignedOptions)) {
                        $strError = $this->_tr->translate('Incorrect Assigned');
                    }

                    if (empty($strError) && $arrFilterParams['assigned'] == 'anyone') {
                        unset($arrFilterParams['assigned']);
                    }
                }

                if (empty($strError)) {
                    if (!$this->_acl->isAllowed('tasks-view-users')) {
                        $arrFilterParams['owner'] = $this->_auth->getCurrentUserId();
                    } elseif (empty($arrFilterParams['clientId']) && !empty($arrFilterParams['owner']) && !is_numeric($arrFilterParams['owner'])) {
                        $strError = $this->_tr->translate('Incorrect owner');
                    }
                }
            }


            if (empty($strError)) {
                if ($this->_auth->isCurrentUserSuperadmin()) {
                    $type = 'superadmin_owner';
                } elseif ($booProspect) {
                    $type = 'prospect';
                } elseif (!empty($arrFilterParams['clientId'])) {
                    $type = 'client';
                } else {
                    $type = 'my_tasks';
                }
                $arrTasks = $this->_tasks->getMemberTasks($type, $arrFilterParams, $sort, $dir);
            }

            // Time zone will be used when show the date
            $tz = $this->_auth->getCurrentMemberTimezone();

            foreach ($arrTasks as $key => $task) {
                // For displaying "Created on" date
                if (!Settings::isDateEmpty($task['task_create_date'])) {
                    $date = new DateTime($task['task_create_date']);
                    $dt   = new DateTime('@' . $date->getTimestamp());
                    if ($tz instanceof DateTimeZone) {
                        $dt->setTimezone($tz);
                    }

                    $arrTasks[$key]['task_create_date'] = $dt->format('Y-m-d H:i:s');
                }

                $arrTasks[$key]['task_read_permission'] = $this->_tasks->hasAccessToTask($arrTasks[$key]['task_id'], false);
                $arrTasks[$key]['task_full_permission'] = $this->_tasks->hasAccessToTask($arrTasks[$key]['task_id']);
            }
        } catch (Exception $e) {
            $arrTasks = array();
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'tasks' => $arrTasks,
            'count' => count($arrTasks),
            'error' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function getThreadsListAction()
    {
        $strError   = '';
        $arrThreads = array();

        try {
            $taskId               = (int)$this->params()->fromPost('task_id');
            $sort                 = $this->_filter->filter($this->params()->fromPost('sort'));
            $dir                  = $this->_filter->filter($this->params()->fromPost('dir'));
            $booLoadSystemRecords = (bool)$this->params()->fromPost('show_system_records');

            if (!$this->_tasks->hasAccessToTask($taskId, false)) {
                $strError = $this->_tr->translate('Incorrectly selected task');
            }

            if (empty($strError)) {
                $arrThreads = $this->_tasks->getTaskMessages($taskId, $sort, $dir, $booLoadSystemRecords);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'threads' => $arrThreads,
            'count'   => count($arrThreads),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function updateTaskFlagAction()
    {
        $strError = '';

        try {
            $taskId = (int)Json::decode($this->params()->fromPost('task_id'), Json::TYPE_ARRAY);

            if (!$this->_tasks->hasAccessToTask($taskId)) {
                $strError = $this->_tr->translate('Incorrectly selected task');
            }

            if (empty($strError)) {
                $task_info = $this->_tasks->getTaskInfo($taskId);

                if ($task_info['completed'] == 'Y') {
                    $strError = $this->_tr->translate('This option is forbidden, because task is marked as completed');
                }
            }

            $taskFlag = $this->_filter->filter(Json::decode($this->params()->fromPost('task_flag'), Json::TYPE_ARRAY));
            $arrFlags = $this->_tasks->getTaskFlags();

            if (empty($strError) && !in_array($taskFlag, array_values($arrFlags))) {
                $strError = $this->_tr->translate('Incorrectly selected flag');
            }

            if (empty($strError)) {
                $this->_tasks->updateTaskFlag($taskId, $this->_tasks->getTaskFlagId($taskFlag));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function markCompleteAction()
    {
        $strError = '';

        try {
            $task_ids   = Json::decode($this->params()->fromPost('task_ids'), Json::TYPE_ARRAY);
            $uncomplete = $this->params()->fromQuery('uncomplete', 0) == 1;

            foreach ($task_ids as $task_id) {
                if (empty($strError) && !$this->_tasks->hasAccessToTask($task_id)) {
                    $strError = $this->_tr->translate('Incorrectly selected task(s)');
                    break;
                }
            }

            if (empty($strError)) {
                $this->_tasks->markComplete($task_ids, !$uncomplete);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function markAsReadAction()
    {
        $strError = '';

        try {
            $taskId    = (int)$this->params()->fromPost('task_id');
            $booAsRead = Json::decode($this->params()->fromPost('as_read'), Json::TYPE_ARRAY);

            if (!$this->_tasks->hasAccessToTask($taskId)) {
                $strError = $this->_tr->translate('Incorrectly selected task');
            }

            if (empty($strError)) {
                $this->_tasks->markAsRead($taskId, $booAsRead);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError = '';

        try {
            $taskIds   = Json::decode($this->params()->fromPost('task_ids'), Json::TYPE_ARRAY);
            $tasksType = Json::decode($this->params()->fromPost('tasks_type'), Json::TYPE_ARRAY);

            switch ($tasksType) {
                case 'tasks':
                    $booAllowed = $this->_acl->isAllowed('tasks-delete');
                    break;

                case 'clients':
                    $booAllowed = $this->_acl->isAllowed('clients-tasks-delete');
                    break;

                case 'prospects':
                    $booAllowed = $this->_acl->isAllowed('prospects-tasks-delete');
                    break;

                default:
                    $booAllowed = false;
                    break;
            }

            if (!$booAllowed) {
                $strError = $this->_tr->translate("You can't delete tasks.");
            }

            if (empty($strError)) {
                foreach ($taskIds as $taskId) {
                    if (!$this->_tasks->hasAccessToTask($taskId)) {
                        $strError = $this->_tr->translate('Incorrectly selected task');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $this->_tasks->deleteTasks($taskIds);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function changePriorityAction()
    {
        $strError = '';

        try {
            $task_ids = Json::decode($this->params()->fromPost('task_ids'), Json::TYPE_ARRAY);
            $priority = $this->_filter->filter($this->params()->fromPost('priority'));

            foreach ($task_ids as $task_id) {
                if (!$this->_tasks->hasAccessToTask($task_id)) {
                    $strError = $this->_tr->translate('Incorrectly selected task(s)');
                    break;
                }

                $task_info = $this->_tasks->getTaskInfo($task_id);

                if ($task_info['completed'] == 'Y') {
                    $strError = $this->_tr->translate('This option is forbidden, because task(s) marked as completed');
                    break;
                }
            }

            if (empty($strError) && !in_array($priority, array('low', 'regular', 'medium', 'high', 'critical'))) {
                $strError = $this->_tr->translate('Incorrectly selected priority');
            }

            if (empty($strError)) {
                $this->_tasks->changePriority($task_ids, $priority);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function addMessageAction()
    {
        $strError = '';

        try {
            $taskId      = (int)$this->params()->fromPost('task_id');
            $message     = $this->_filter->filter(Json::decode($this->params()->fromPost('message'), Json::TYPE_ARRAY));
            $arrTaskInfo = $this->_tasks->getTaskInfo($taskId);

            if (!isset($arrTaskInfo['task_id'])) {
                $strError = $this->_tr->translate('Incorrectly selected task');
            }

            if (empty($strError) && !$this->_tasks->hasAccessToTask($taskId, false)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError) && $arrTaskInfo['completed'] == 'Y') {
                $strError = $this->_tr->translate('This option is forbidden, because task is marked as completed');
            }

            if (empty($strError)) {
                $this->_tasks->addMessage($taskId, $message);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function changeParticipantsAction()
    {
        $strError = '';

        try {
            $memberId = $this->_auth->getCurrentUserId();
            $taskId   = (int)Json::decode($this->params()->fromPost('task_id'), Json::TYPE_ARRAY);

            $booHasAccessToManage = $this->_tasks->hasAccessToManageTask($memberId, $taskId);

            $subject      = $this->_filter->filter(Json::decode($this->params()->fromPost('task_subject'), Json::TYPE_ARRAY));
            $to           = $this->_filter->filter(Json::decode($this->params()->fromPost('task_to', ''), Json::TYPE_ARRAY));
            $cc           = $this->_filter->filter(Json::decode($this->params()->fromPost('task_cc', ''), Json::TYPE_ARRAY));
            $notifyClient = $this->params()->fromPost('task_notify_client');
            $priorityFlag = $this->params()->fromPost('flag');
            $postDeadline = $this->_filter->filter($this->params()->fromPost('task_deadline'));
            $deadline     = empty($postDeadline) ? '' : date('Y-m-d', strtotime($postDeadline));

            $to = array_filter(explode(';', $to)); // we use array_filter to remove 0 value (used for 'Select all' in js)
            $cc = array_filter(explode(';', $cc)); // we use array_filter to remove 0 value (used for 'Select all' in js)

            if (!$this->_tasks->hasAccessToTask($taskId)) {
                $strError = $this->_tr->translate('Incorrectly selected task');
            }

            $arrTaskInfo = $this->_tasks->getTaskInfo($taskId);

            if (empty($strError) && $arrTaskInfo['completed'] == 'Y') {
                $strError = $this->_tr->translate('This option is forbidden, because task is marked as completed');
            }

            if (empty($strError) && $subject === '') {
                $strError = $this->_tr->translate('Please provide subject');
            }

            if (empty($strError) && empty($to)) {
                $strError = $this->_tr->translate('Task must be Assigned to at least 1 user');
            }

            if (!$booHasAccessToManage) {
                $old_to = array_filter(explode(';', $arrTaskInfo['to_ids'] ?? ''));
                $old_cc = array_filter(explode(';', $arrTaskInfo['cc_ids'] ?? ''));

                if (count(array_diff($old_to, $to)) || count(array_diff($old_cc, $cc))) {
                    $strError = $this->_tr->translate('You don\'t have access to remove users from Assigned to or CC\'d to');
                }
            }

            if (empty($strError) && !in_array($notifyClient, array('Y', 'N'))) {
                $strError = $this->_tr->translate('Incorrectly selected Notify Case');
            }

            if (empty($strError)) {
                $this->_tasks->changeParticipants($taskId, $to, $cc);
                $this->_tasks->changeNotifyClient($taskId, $notifyClient, false);

                $arrAdditionalInfo = array(
                    'type'   => $this->_filter->filter($this->params()->fromPost('type_checked')),
                    'due_on' => $this->_filter->filter(Json::decode($this->params()->fromPost('date'), Json::TYPE_ARRAY)),
                    'number' => (int)$this->params()->fromPost('number'),
                    'days'   => $this->params()->fromPost('days'),
                    'ba'     => $this->params()->fromPost('ba'),
                    'prof'   => $this->params()->fromPost('prof'),
                );

                $this->_tasks->changeSendOnSection($taskId, $arrAdditionalInfo);

                $booChangedSubject = false;
                if ($booHasAccessToManage) {
                    if ($arrTaskInfo['author_id'] == $memberId || $this->_members->isMemberAdmin($memberId) || $this->_members->isMemberSuperAdmin($memberId) || $this->_company->isLooseTaskRulesEnabledToCompany(
                            $this->_auth->getCurrentUserCompanyId()
                        )) {
                        $this->_tasks->changeSubject($taskId, $subject, false);
                        $booChangedSubject = true;
                    }
                    $this->_tasks->changeDeadline($taskId, $deadline ?: null);
                }


                sort($to);
                sort($cc);

                $arrMessages = array();
                if ($arrTaskInfo['task'] != $subject && $booChangedSubject) {
                    $arrMessages[] = $this->_tr->translate('changed the Subject to') . ' "' . $subject . '"';
                }

                if ($arrTaskInfo['deadline'] != $deadline) {
                    if (Settings::isDateEmpty($postDeadline)) {
                        $arrMessages[] = $this->_tr->translate('cleared the deadline');
                    } else {
                        $arrMessages[] = $this->_tr->translate('changed the deadline to ') . date('M j, Y', strtotime($postDeadline));
                    }
                }

                if (implode(';', $to) . ';' != $arrTaskInfo['to_ids']) {
                    $arrMessages[] = $this->_tr->translate('changed the Assigned to value to ' . $this->_members->getCommaSeparatedMemberNames($to));
                }

                if (implode(';', $cc) . ';' != ($arrTaskInfo['cc_ids'] ? $arrTaskInfo['cc_ids'] : $arrTaskInfo['cc_ids'] . ';')) {
                    if (empty($cc)) {
                        $arrMessages[] = $this->_tr->translate('removed all users from CC\'d to value');
                    } else {
                        $arrMessages[] = $this->_tr->translate('changed the CC\'d to value to ' . $this->_members->getCommaSeparatedMemberNames($cc));
                    }
                }

                if ($arrTaskInfo['notify_client'] != $notifyClient) {
                    $arrMessages[] = sprintf('%s Notify Case checkbox', $notifyClient == 'Y' ? 'checked' : 'unchecked');
                }

                if (count($arrMessages)) {
                    $this->_tasks->addMessage($taskId, implode(",\n", $arrMessages), true);
                }
            }

            if (empty($strError)) {
                $taskInfo = $this->_tasks->getTaskInfo($taskId);

                if (isset($taskInfo['completed']) && $taskInfo['completed'] == 'Y' && !empty($priorityFlag)) {
                    $strError = $this->_tr->translate('Assigning flag is forbidden, because task is marked as completed');
                }
            }

            if (empty($strError) && !empty($priorityFlag)) {
                $arrFlags = $this->_tasks->getTaskFlags();

                if (!array_key_exists($priorityFlag, $arrFlags)) {
                    $strError = $this->_tr->translate('Incorrectly selected flag');
                }
            }

            if (empty($strError)) {
                $this->_tasks->updateTaskFlag($taskId, $priorityFlag);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function addAction()
    {
        $strError = '';

        try {
            $subject     = $this->_filter->filter(Json::decode($this->params()->fromPost('task_subject'), Json::TYPE_ARRAY));
            $message     = $this->_filter->filter(trim(Json::decode($this->params()->fromPost('task_message', ''), Json::TYPE_ARRAY)));
            $to          = $this->_filter->filter(Json::decode($this->params()->fromPost('task_to', ''), Json::TYPE_ARRAY));
            $cc          = $this->_filter->filter(Json::decode($this->params()->fromPost('task_cc', ''), Json::TYPE_ARRAY));
            $client_type = $this->_filter->filter($this->params()->fromPost('client_type', 'client'));

            $post_deadline = $this->_filter->filter($this->params()->fromPost('task_deadline'));
            $deadline      = '';
            if (!empty($post_deadline)) {
                $deadline = date('Y-m-d', strtotime($post_deadline));
            }

            $to = array_filter(explode(';', $to)); // we use array_filter to remove 0 value (used for 'Select all' in js)
            $cc = array_filter(explode(';', $cc)); // we use array_filter to remove 0 value (used for 'Select all' in js)

            $priorityFlag = $this->params()->fromPost('flag');

            if ($subject === '') {
                $strError = $this->_tr->translate('Please provide subject.');
            }

            if (empty($strError) && empty($to)) {
                $strError = $this->_tr->translate('Task must be assigned to at least 1 user.');
            }


            if (empty($strError) && !in_array($client_type, array('prospect', 'client'))) {
                $strError = $this->_tr->translate('Incorrectly assigned task.');
            }

            $booSuperAdmin = $this->_auth->isCurrentUserSuperadmin();

            // Check incoming member id
            $member_id = null; // Means that it is assigned to or created by superadmin
            if (empty($strError) && !$booSuperAdmin) {
                $booHasAccess = false;

                $member_id = (int)$this->params()->fromPost('member_id');
                if ($client_type == 'client' && empty($member_id)) {
                    // General task
                    $booHasAccess = true;
                } elseif ($client_type == 'client' && !empty($member_id)) {
                    $booHasAccess = $this->_members->hasCurrentMemberAccessToMember($member_id);
                } elseif ($client_type == 'prospect') {
                    $booHasAccess = $this->_companyProspects->allowAccessToProspect($member_id);
                }

                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Access denied.');
                }
            }

            $taskId = 0;
            if (empty($strError)) {
                $task_data = array(
                    'subject'     => $subject,
                    'client_type' => $client_type,
                    'deadline'    => $deadline,
                    'to'          => $to,
                    'cc'          => $cc,
                    'message'     => $message,

                    'type'          => $this->_filter->filter($this->params()->fromPost('type_checked')),
                    'due_on'        => $this->_filter->filter(Json::decode($this->params()->fromPost('date'), Json::TYPE_ARRAY)),
                    'number'        => (int)$this->params()->fromPost('number'),
                    'days'          => $this->params()->fromPost('days'),
                    'ba'            => $this->params()->fromPost('ba'),
                    'prof'          => $this->params()->fromPost('prof'),
                    'notify_client' => $this->params()->fromPost('task_notify_client'),
                    'member_id'     => $member_id,
                    'company_id'    => $booSuperAdmin ? $this->params()->fromPost('company_id') : null,
                );

                $taskId   = $this->_tasks->addTask($task_data);
                $taskInfo = $this->_tasks->getTaskInfo($taskId);
            }

            if (empty($strError) && isset($taskInfo['completed']) && $taskInfo['completed'] == 'Y' && !empty($priorityFlag)) {
                $strError = $this->_tr->translate('Assigning flag is forbidden, because task is marked as completed');
            }

            if (empty($strError) && !empty($priorityFlag)) {
                $arrFlags = $this->_tasks->getTaskFlags();

                if (!array_key_exists($priorityFlag, $arrFlags)) {
                    $strError = $this->_tr->translate('Incorrectly selected flag');
                }
            }

            if (empty($strError)) {
                $this->_tasks->updateTaskFlag($taskId, $priorityFlag);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function tooltipTaskInfoAction()
    {
        $view = new ViewModel(
            [
                'content' => ''
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $id      = $this->findParam('id', '');
        $task_id = substr($id, strlen('task-tip-'));

        //check access
        if ($this->_tasks->hasAccessToTask($task_id)) {
            $tip = $this->_tasks->getTip($task_id);
            $view->setVariable('content', $tip);
        }

        return $view;
    }

    public function subjectSuggestionAction()
    {
        $arrResult    = array();
        $arrSearch    = array();
        $totalRecords = 0;

        try {
            $start = (int)$this->params()->fromPost('start', 0);
            $limit = (int)$this->params()->fromPost('limit', 10);

            $filter = new StripTags();

            if (!is_numeric($start) || $start <= 0) {
                $start = 0;
            }

            if (!is_numeric($limit) || $limit <= 0 || $limit > 50) {
                $limit = 10;
            }

            // Search only if text was received
            $searchName = trim($filter->filter($this->params()->fromPost('query', '')));
            if (!empty($searchName)) {
                list($arrResult, $totalRecords, $arrSearch) = $this->_tasks->getSubjectSuggestions($searchName, $start, $limit);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'totalCount' => $totalRecords,
            'rows'       => $arrResult,
            'search'     => $arrSearch
        );

        return new JsonModel($arrResult);
    }

    public function getDateFieldsAction()
    {
        $arrDateFields = array();

        try {
            $member_id = (int)$this->params()->fromPost('member_id');
            if ($this->_members->hasCurrentMemberAccessToMember($member_id)) {
                $oCaseInfo = $this->_clients->getClientInfo($member_id);
                if ($oCaseInfo && array_key_exists('client_type_id', $oCaseInfo)) {
                    $arrDateFields = $this->_clients->getFields()->getDateFieldsByCaseTypes(array($oCaseInfo['client_type_id']));
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'totalCount' => count($arrDateFields),
            'rows'       => $arrDateFields,
        );

        return new JsonModel($arrResult);
    }

    public function getTaskSettingsAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError = '';

        try {
            $booLoadClients = Json::decode($this->params()->fromPost('booLoadClients'), Json::TYPE_ARRAY);
            $booLoadTo      = Json::decode($this->params()->fromPost('booLoadTo'), Json::TYPE_ARRAY);

            if (!$booLoadClients && !$booLoadTo) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            $arrClients = [];
            if (empty($strError) && $booLoadClients) {
                $arrAssignedCases = $this->_clients->getClientsList();
                $arrClients       = array_merge(array(array('clientId' => 0, 'clientName' => 'General Task', 'clientFullName' => 'General Task')), $this->_clients->getCasesListWithParents($arrAssignedCases));
            }

            $arrTo = [];
            if (empty($strError) && $booLoadTo) {
                $caseId = (int)Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);

                if (!$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError)) {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                    $fieldId   = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId('processing', $companyId);
                    if (!empty($fieldId)) {
                        $fieldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);
                        if (!empty($fieldValue)) {
                            $arrTo = $this->_tasks->getReminderAssignedToMembers(
                                $companyId,
                                $caseId,
                                $fieldValue,
                                1
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $arrTo      = [];
            $arrClients = [];
            $strError   = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'success'    => empty($strError),
            'message'    => $strError,
            'arrTo'      => $arrTo,
            'arrClients' => $arrClients,
        ];

        return new JsonModel($arrResult);
    }
}