<?php

namespace Tasks\Service;

use Clients\Service\Clients;
use Clients\Service\Members;
use DateTime;
use DateTimeZone;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Mailer\Service\Mailer;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Common\ServiceContainerHolder;
use Prospects\Service\CompanyProspects;
use Templates\Service\Templates;
use Laminas\Validator\EmailAddress;

/**
 * Class Tasks
 * @package Tasks\Service
 */
class Tasks extends BaseService
{

    use ServiceContainerHolder;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Templates */
    protected $_templates;

    /** @var Files */
    protected $_files;

    /** @var Mailer */
    protected $_mailer;


    public function initAdditionalServices(array $services)
    {
        $this->_files   = $services[Files::class];
        $this->_clients = $services[Clients::class];
        $this->_company = $services[Company::class];
        $this->_mailer  = $services[Mailer::class];
    }

    /**
     * Check if current member can access the task
     *
     * @param int $taskId
     * @param bool $booEdit
     * @return bool true if member can access
     */
    public function hasAccessToTask($taskId, $booEdit = true)
    {
        $arrTaskInfo = $this->getTaskInfo($taskId);
        if (!isset($arrTaskInfo['author_id'])) {
            return false;
        }

        // Owner can manage the task
        $currentMemberId = $this->_auth->getCurrentUserId();
        if ($arrTaskInfo['author_id'] == $currentMemberId) {
            return true;
        }

        // Company admin can manage tasks for all clients from the same company
        if ($this->_auth->isCurrentUserAdmin() && $this->_auth->getCurrentUserCompanyId() == $arrTaskInfo['company_id']) {
            return true;
        }

        // User can manage task if he is in to/cc list
        $select = (new Select())
            ->from('u_tasks_assigned_to')
            ->columns(['count' => new Expression('COUNT(task_id)')])
            ->where([
                'task_id'   => (int)$taskId,
                'member_id' => $currentMemberId
            ]);

        $booIsInToCC = $this->_db2->fetchOne($select) > 0;

        if ($booIsInToCC) {
            return true;
        }

        $booHasAccess = false;
        if (!$booEdit) {
            if (empty($arrTaskInfo['member_id'])) {
                $booHasAccess = $this->_auth->isCurrentUserSuperadmin();
            } else {
                switch ($arrTaskInfo['client_type']) {
                    case 'client':
                        $booHasAccess = $this->_clients->hasCurrentMemberAccessToMember($arrTaskInfo['member_id']);
                        break;

                    case 'prospect':
                        $booHasAccess = $this->getServiceContainer()->get(CompanyProspects::class)->allowAccessToProspect($arrTaskInfo['member_id']);
                        break;

                    default:
                        break;
                }
            }
        }

        return $booHasAccess;
    }

    public function hasAccessToManageTask($memberId, $taskId)
    {
        // Owner can manage a task
        $select = (new Select())
            ->from('u_tasks')
            ->columns(['count' => new Expression('COUNT(task_id)')])
            ->where([
                'task_id'   => (int)$taskId,
                'author_id' => (int)$memberId
            ]);

        $booOwner = $this->_db2->fetchOne($select) > 0;
        if ($booOwner) {
            return true;
        }

        // User can manage task if he is in TO list
        $select = (new Select())
            ->from('u_tasks_assigned_to')
            ->columns(['count' => new Expression('COUNT(task_id)')])
            ->where([
                'task_id'   => (int)$taskId,
                'to_cc'     => 'to',
                'member_id' => (int)$memberId
            ]);

        $booInTo = $this->_db2->fetchOne($select) > 0;
        if ($booInTo) {
            return true;
        }

        // Company admin can manage tasks for all clients from the same company
        $booAdminHasAccess = false;
        if ($this->_auth->isCurrentUserAdmin()) {
            $arrTaskInfo       = $this->getTaskInfo($taskId);
            $booAdminHasAccess = $this->_auth->getCurrentUserCompanyId() == $arrTaskInfo['company_id'];
        }

        if ($booAdminHasAccess) {
            return true;
        }

        return false;
    }

    public function getTaskInfo($taskId)
    {
        $select = (new Select())
            ->from(['t' => 'u_tasks'])
            ->join(['m' => 'members'], 't.author_id = m.member_id', ['company_id'], Select::JOIN_LEFT_OUTER)
            ->join(
                ['a' => 'u_tasks_assigned_to'],
                'a.task_id = t.task_id',
                [
                    'to_ids' => new Expression('GROUP_CONCAT(IF(a.to_cc="to", CONCAT(a.member_id, ";"), "") ORDER BY a.member_id SEPARATOR "")'),
                    'cc_ids' => new Expression('GROUP_CONCAT(IF(a.to_cc="cc", CONCAT(a.member_id, ";"), "") ORDER BY a.member_id SEPARATOR "")'),
                ],
                Select::JOIN_LEFT_OUTER)
            ->where(['t.task_id' => (int)$taskId])
            ->group('t.task_id');

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load a list of tasks for different situations:
     * - case's tasks
     * - case's activities (notes + tasks)
     * - prospect's tasks
     * - prospect's activities (notes + tasks)
     * - my tasks
     * - superadmin tasks
     *
     * @param string $strTasksType can be one of:
     * 'client'           - if clientId is passed in the $arrFilterParams - load tasks for this client only, otherwise for all company clients
     * 'prospect'         - if clientId is passed in the $arrFilterParams - load tasks for this prospect only, otherwise for all company prospects
     * 'my_tasks'         - tasks for all company clients, prospects and current user's general tasks
     * 'superadmin_owner' - tasks for superadmins (tasks assigned to the default company 0 and not assigned to clients/prospects)
     * @param array $arrFilterParams
     * @param string $sort
     * @param string $dir
     * @param bool $booLoadAdditionalInfo true to load to, cc, etc additional info
     * @return array
     */
    public function getMemberTasks($strTasksType, $arrFilterParams, $sort = '', $dir = '', $booLoadAdditionalInfo = true)
    {
        try {
            $memberId  = $arrFilterParams['memberId'] ?? $this->_auth->getCurrentUserId();
            $companyId = $arrFilterParams['companyId'] ?? $this->_auth->getCurrentUserCompanyId();

            if ($sort == 'task_deadline_date') {
                $sort = 'task_deadline';
            } elseif ($sort == 'task_due_on_date') {
                $sort = 'task_due_on';
            }

            $arrColumnsAllowedForSorting = array(
                'task_flag',
                'task_subject',
                'task_create_date',
                'task_deadline',
                'task_completed',
                'task_due_on',
                'task_completed'
            );
            if (!in_array($sort, $arrColumnsAllowedForSorting)) {
                $sort = 't.task_id'; // default sorting
            }

            $dir = strtoupper($dir) == 'ASC' ? 'ASC' : 'DESC';

            $select = (new Select())
                ->from(array('t' => 'u_tasks'))
                ->columns(array(
                    'task_id',
                    'task_subject'       => 'task',
                    'client_type'        => 'client_type',
                    'task_type'          => 'type',
                    'task_days_count'    => 'number',
                    'task_days_type'     => 'days',
                    'task_days_when'     => 'ba',
                    'task_profile_field' => 'prof',
                    'task_flag'          => 'flag',
                    'task_completed'     => 'completed',
                    'task_is_due'        => 'is_due',
                    'task_notify_client' => 'notify_client',
                    'task_deadline'      => 'deadline',
                    'task_created_by_id' => 'author_id',
                    'task_create_date'   => 'create_date',
                    'task_due_on'        => 'due_on',
                    'task_unread'        => new Expression('IF (r.id, 0, 1)'),
                    'auto_task_type'     => 'auto_task_type',
                    'member_id'          => 'member_id',
                ))
                ->join(array('to' => 'u_tasks_assigned_to'), 't.task_id = to.task_id', array())
                ->join(array('r' => 'u_tasks_read'), new PredicateExpression('t.task_id = r.task_id AND r.member_id =' . $memberId), array(), Select::JOIN_LEFT_OUTER)
                ->join(
                    array('p' => 'u_tasks_priority'),
                    new PredicateExpression('t.task_id = p.task_id AND p.member_id =' . $memberId),
                    array('task_priority' => 'priority'),
                    Select::JOIN_LEFT_OUTER
                )
                ->join(
                    array('m' => 'members'),
                    't.author_id = m.member_id',
                    array('task_created_by' => new Expression("CONCAT(m.fName, ' ', m.lName)")),
                    Select::JOIN_LEFT_OUTER
                )
                ->group('t.task_id')
                ->order("$sort $dir");

            $booIsClientOrProspectIdPassed = !empty($arrFilterParams['clientId']) && is_numeric($arrFilterParams['clientId']);

            $arrMembersIds = array();
            if ($strTasksType === 'client' && $booIsClientOrProspectIdPassed) {
                // Use the provided client id - will be used to load additional info
                $arrMembersIds[] = $arrFilterParams['clientId'];
            } elseif (($strTasksType === 'client' && !$booIsClientOrProspectIdPassed) || $strTasksType === 'my_tasks') {
                // Load a list of allowed clients only when needed
                if (isset($arrFilterParams['booLoadAccessToMember']) && $arrFilterParams['booLoadAccessToMember']) {
                    $arrMembersIds = $this->_clients->getMembersWhichICanAccess(null, $memberId);
                } else {
                    $arrMembersIds = $this->_clients->getMembersWhichICanAccess();
                }
            }

            // Load a list of allowed prospects only when needed
            $arrProspectsIds = array();
            if ($strTasksType === 'prospect' && $booIsClientOrProspectIdPassed) {
                // Use the provided prospect id - will be used to load additional info
                $arrProspectsIds[] = $arrFilterParams['clientId'];
            } elseif (($strTasksType === 'prospect' && !$booIsClientOrProspectIdPassed) || $strTasksType === 'my_tasks') {
                // Load prospects only for allowed divisions
                $arrMemberOffices = $this->_clients->getDivisions(true);

                $arrWhere = [
                    (new Where())->equalTo('cp.company_id', $companyId)
                ];

                if (empty($arrMemberOffices)) {
                    $arrWhere[] = (new Where())->isNull('cpd.office_id');
                } else {
                    if (!is_array($arrMemberOffices)) {
                        $arrMemberOffices = [$arrMemberOffices];
                    }
                    $arrWhere[] = (new Where())
                        ->nest()
                        ->isNull('cpd.office_id')
                        ->or
                        ->in('cpd.office_id', $arrMemberOffices)
                        ->unnest();
                }

                $select2 = (new Select())
                    ->from(['cp' => 'company_prospects'])
                    ->columns(['prospect_id'])
                    ->join(['cpd' => 'company_prospects_divisions'], 'cpd.prospect_id = cp.prospect_id', [], Select::JOIN_LEFT_OUTER)
                    ->where($arrWhere)
                    ->group('cp.prospect_id');

                $arrProspectsIds = $this->_db2->fetchCol($select2);
            }

            switch ($strTasksType) {
                case 'client':
                    // Load tasks only for the client
                    if ($booIsClientOrProspectIdPassed) {
                        $select->where([
                            (new Where())
                                ->equalTo('t.client_type', 'client')
                                ->equalTo('t.member_id', (int)$arrFilterParams['clientId'])
                        ]);
                    } else {
                        $select->where(
                            [
                                (new Where())
                                    ->equalTo('t.client_type', 'client')
                                    ->in('t.member_id', $arrMembersIds)
                            ]
                        );
                    }
                    break;

                case 'prospect':
                    // Load tasks only for the prospect
                    if ($booIsClientOrProspectIdPassed) {
                        $select->where([
                            (new Where())
                                ->equalTo('t.client_type', 'prospect')
                                ->equalTo('t.member_id', (int)$arrFilterParams['clientId'])
                        ]);
                    } else {
                        $select->where(
                            [
                                (new Where())
                                    ->equalTo('t.client_type', 'prospect')
                                    ->in('t.member_id', $arrProspectsIds)
                            ]
                        );
                    }
                    break;

                case 'my_tasks':
                    // Load tasks for company clients, prospects and assigned to the company (general tasks)
                    $arrMembersCanAccess = $this->_company->getCompanyMembersIds($companyId, 'admin_and_user');

                    $select->where([
                        (new Where())
                            ->nest()
                            ->equalTo('t.client_type', 'client')
                            ->in('t.member_id', $arrMembersIds)
                            ->unnest()
                            ->or
                            ->nest()
                            ->equalTo('t.client_type', 'prospect')
                            ->in('t.member_id', $arrProspectsIds)
                            ->unnest()
                            ->or
                            ->nest()
                            ->isNull('t.member_id')
                            ->in('t.author_id', $arrMembersCanAccess)
                            ->unnest()
                    ]);
                    break;

                case 'superadmin_owner':
                    // Load tasks for the superadmin page
                    $arrMembersCanAccess = $this->_company->getCompanyMembersIds(0, 'superadmin', true);
                    $select->where(['t.author_id' => $arrMembersCanAccess]);

                    $select->where([(new Where())->isNull('t.member_id')]);
                    $select->where(['t.company_id' => 0]);
                    break;
            }

            // active filter
            if (isset($arrFilterParams['status'])) {
                // Calculate dates in relation to the current user's time zone
                $tz = $this->_auth->getCurrentMemberTimezone();
                if ($tz instanceof DateTimeZone) {
                    $datetime     = new DateTime('tomorrow', $tz);
                    $tomorrowDate = $datetime->format('Y-m-d');

                    $datetime   = new DateTime('+7 days', $tz);
                    $dueIn7Days = $datetime->format('Y-m-d');
                } else {
                    $tomorrowDate = date('Y-m-d', strtotime('tomorrow'));
                    $dueIn7Days   = date('Y-m-d', strtotime('+7 days'));
                }

                switch ($arrFilterParams['status']) {
                    case 'due_today':
                        $select->where(['t.completed' => 'N']);
                        $select->where(['t.is_due' => 'Y']);
                        break;

                    case 'due_tomorrow':
                        $select->where(['t.completed' => 'N']);
                        $select->where(['t.due_on' => $tomorrowDate]);
                        break;

                    case 'due_next_7_days':
                        $select->where(['t.completed' => 'N']);
                        $select->where([(new Where())->greaterThanOrEqualTo('t.due_on', $tomorrowDate)]);
                        $select->where([(new Where())->lessThanOrEqualTo('t.due_on', $dueIn7Days)]);
                        break;

                    default:
                        $select->where(['t.completed' => $arrFilterParams['status'] != 'active' ? 'Y' : 'N']);
                        break;
                }
            }

            // Check if task is due
            if (isset($arrFilterParams['task_is_due'])) {
                $select->where(['t.is_due' => $arrFilterParams['task_is_due'] == 'Y' ? 'Y' : 'N']);
            }

            // Based on the Due date
            if (isset($arrFilterParams['due_on'])) {
                $select->where(['t.due_on' => $arrFilterParams['due_on']]);
            }

            // read/unread filter
            if (isset($arrFilterParams['unread']) && $arrFilterParams['unread']) {
                $select->where([(new Where())->isNull('r.id')]);
            }


            // assigned filter
            if (isset($arrFilterParams['assigned'])) {
                // owner filter
                $owner = !empty($arrFilterParams['owner']) && is_numeric($arrFilterParams['owner']) ? $arrFilterParams['owner'] : $memberId;

                switch ($arrFilterParams['assigned']) {
                    case 'following':
                        $select->where(['to.member_id' => (int)$owner]);
                        $select->where(['to.to_cc' => 'cc']);
                        break;

                    case 'me_and_following':
                        $select->where(['to.member_id' => (int)$owner]);
                        break;

                    case 'others':
                        $select->where(['t.author_id' => $owner]);
                        break;

                    case 'me':
                    default:
                        $select->where(['to.member_id' => (int)$owner]);
                        $select->where(['to.to_cc' => 'to']);
                        break;
                }
            }

            $arrTasks = $this->_db2->fetchAssoc($select);

            // Load additional info only when it is needed
            if ($booLoadAdditionalInfo) {
                $arrTasksProspectIds = array();
                $arrTasksClientIds   = array();
                foreach ($arrTasks as $task) {
                    if (!empty($task['member_id'])) {
                        if ($task['client_type'] == 'prospect') {
                            $arrTasksProspectIds[] = $task['member_id'];
                        } elseif ($task['client_type'] == 'client') {
                            $arrTasksClientIds[] = $task['member_id'];
                        }
                    }
                }
                $arrTasksProspectIds = array_intersect(array_unique($arrTasksProspectIds), $arrProspectsIds);
                $arrTasksClientIds   = array_intersect(array_unique($arrTasksClientIds), $arrMembersIds);

                $arrProspectsFullNames = array();
                if (count($arrTasksProspectIds)) {
                    $select = (new Select())
                        ->from('company_prospects')
                        ->columns(['prospect_id', 'member_full_name' => new Expression("CONCAT(fName, ' ', lName)")])
                        ->where(['prospect_id' => $arrTasksProspectIds]);

                    $arrProspectsFullNames = $this->_db2->fetchAssoc($select);
                }

                $arrMembersFullNames = array();
                if (count($arrTasksClientIds)) {
                    $select = (new Select())
                        ->from(['m' => 'members'])
                        ->columns(['member_id' => 'member_id', 'case_first_name' => 'fName', 'case_last_name' => 'lName'])
                        ->join(['c' => 'clients'], 'm.member_id = c.member_id', ['fileNumber'], Select::JOIN_LEFT_OUTER)
                        ->where(['m.member_id' => $arrTasksClientIds]);

                    $arrMembersFullNames = $this->_db2->fetchAssoc($select);
                }

                foreach ($arrTasks as $key => $task) {
                    if (!empty($task['member_id'])) {
                        if ($task['client_type'] == 'prospect') {
                            $task['member_full_name'] = $arrProspectsFullNames[$task['member_id']]['member_full_name'];
                        } elseif ($task['client_type'] == 'client') {
                            $task['case_first_name'] = $arrMembersFullNames[$task['member_id']]['case_first_name'];
                            $task['case_last_name']  = $arrMembersFullNames[$task['member_id']]['case_last_name'];
                            $task['fileNumber']      = $arrMembersFullNames[$task['member_id']]['fileNumber'];
                        }
                    } else {
                        $task['case_first_name']  = '';
                        $task['case_last_name']   = '';
                        $task['fileNumber']       = '';
                        $task['member_full_name'] = '';
                    }

                    $arrTasks[$key] = $task;
                }

                // Load 'To' and 'Cc' fields for tasks
                if (is_array($arrTasks) && count($arrTasks)) {
                    // Collect tasks + members ids
                    $arrTasksIds  = array();
                    $arrMemberIds = array();
                    foreach ($arrTasks as $arrTaskInfo) {
                        $arrTasksIds[] = $arrTaskInfo['task_id'];

                        if (!empty($arrTaskInfo['member_id'])) {
                            $arrMemberIds[] = $arrTaskInfo['member_id'];
                        }
                    }
                    $arrTasksIds  = Settings::arrayUnique($arrTasksIds);
                    $arrMemberIds = Settings::arrayUnique($arrMemberIds);

                    if (($strTasksType == 'client' || $strTasksType === 'my_tasks') && (!is_null($arrMemberIds) && count($arrMemberIds))) {
                        $arrParents = $this->_clients->getParentsForAssignedApplicants($arrMemberIds);
                        foreach ($arrTasks as $key => $arrTaskInfo) {
                            if ($arrTaskInfo['client_type'] == 'client') {
                                // Generate parent name
                                $arrCaseInfo = array(
                                    'fName'      => $arrTaskInfo['case_first_name'],
                                    'lName'      => $arrTaskInfo['case_last_name'],
                                    'fileNumber' => $arrTaskInfo['fileNumber'],
                                );
                                $arrCaseInfo = $this->_clients->generateClientName($arrCaseInfo);
                                $caseName    = trim($arrCaseInfo['full_name_with_file_num'], '');

                                // Generate case name and use parent's name
                                $parentId = 0;
                                if (array_key_exists($arrTaskInfo['member_id'], $arrParents)) {
                                    $arrParentInfo = $arrParents[$arrTaskInfo['member_id']];
                                    $arrParentInfo = $this->_clients->generateClientName($arrParentInfo);

                                    if (strlen($caseName ?? '')) {
                                        $caseName = $arrParentInfo['full_name'] . ' (' . $caseName . ')';
                                    } else {
                                        $caseName = $arrParentInfo['full_name'];
                                    }

                                    $parentId = $arrParentInfo['parent_member_id'];
                                }

                                $arrTaskInfo['member_full_name'] = $caseName;
                                $arrTaskInfo['member_parent_id'] = $parentId;
                                $arrTasks[$key]                  = $arrTaskInfo;
                            }
                        }
                    }

                    // Load/group 'To' and 'Cc' fields for these tasks
                    $select = (new Select())
                        ->from(['asv' => 'u_tasks_assigned_to'])
                        ->columns(['task_id', 'member_id', 'to_cc'])
                        ->join(['m' => 'members'], 'm.member_id = asv.member_id', ['member_name' => new Expression('CONCAT(m.fName, " ", m.lName)')], Select::JOIN_LEFT_OUTER)
                        ->where(['asv.task_id' => $arrTasksIds]);

                    $arrTasksAssignedTo = $this->_db2->fetchAll($select);

                    foreach ($arrTasksAssignedTo as $arrAssigned) {
                        if (isset($arrTasks[$arrAssigned['task_id']])) {
                            $key = $arrAssigned['to_cc'] == 'to' ? 'task_assigned_to' : 'task_assigned_cc';

                            $arrTasks[$arrAssigned['task_id']][$key][] = array(
                                $arrAssigned['member_id'],
                                $arrAssigned['member_name']
                            );
                        }
                    }
                    $arrTasks = array_values($arrTasks);

                    // Check if required keys exist
                    foreach ($arrTasks as &$arrTaskInfo) {
                        if (!array_key_exists('task_assigned_to', $arrTaskInfo)) {
                            $arrTaskInfo['task_assigned_to'] = array();
                        }
                        if (!array_key_exists('task_assigned_cc', $arrTaskInfo)) {
                            $arrTaskInfo['task_assigned_cc'] = array();
                        }
                        if (isset($arrTaskInfo['task_profile_field'])) {
                            $arrTaskInfo['task_profile_field_label'] = $this->_clients->getFields()->getFieldName($arrTaskInfo['task_profile_field']);
                        } else {
                            $arrTaskInfo['task_profile_field_label'] = '';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $arrTasks = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $arrTasks;
    }

    public function getTaskMessages($task_id, $sort = '', $dir = '', $booLoadSystemRecords = true)
    {
        try {
            if (!in_array($sort, array('timestamp', 'thread_id'))) {
                $sort = 'timestamp'; // default sorting
            }

            $dir = strtoupper($dir) == 'ASC' ? 'ASC' : 'DESC';

            $arrWhere = [];

            $arrWhere['m.task_id'] = (int)$task_id;

            if (!$booLoadSystemRecords) {
                $arrWhere['m.officio_said'] = 0;
            }

            $select = (new Select())
                ->from(['m' => 'u_tasks_messages'])
                ->columns([
                    'thread_id'            => 'id',
                    'thread_content'       => new Expression(
                        "IF (officio_said=0 OR from_template = 1, message, IF (m.member_id IS NULL, CONCAT('Officio ', message, '.'), CONCAT(fName, ' ', lName, ' ', message, '.')))"
                    ),
                    'thread_created_on'    => 'timestamp',
                    'thread_officio_said'  => 'officio_said',
                    'thread_from_template' => 'from_template'
                ])
                ->join(array('t' => 'u_tasks'), 't.task_id = m.task_id', array('author_id', 'is_due'), Select::JOIN_LEFT_OUTER)
                ->join(array('mem' => 'members'), 'mem.member_id = m.member_id', array('thread_created_by' => new Expression('CONCAT(mem.fName, " ", mem.lName)')), Select::JOIN_LEFT_OUTER)
                ->where($arrWhere)
                ->order("$sort $dir");

            $arrMessages = $this->_db2->fetchAll($select);

            // Apply time zone when format the date
            $tz = $this->_auth->getCurrentMemberTimezone();
            foreach ($arrMessages as $key => $arrMessage) {
                $dt = new DateTime('@' . $arrMessage['thread_created_on']);
                if ($tz instanceof DateTimeZone) {
                    $dt->setTimezone($tz);
                }

                $arrMessages[$key]['thread_created_on'] = $dt->format('Y-m-d H:i:s');
            }
        } catch (Exception $e) {
            $arrMessages = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMessages;
    }

    /**
     * Load a list of messages for the task's thread
     *
     * @param array $arrMessages
     * @return string
     */
    public function formatThreadContent(array $arrMessages)
    {
        $strMessages = '';
        foreach ($arrMessages as $arrMessageInfo) {
            $color = $arrMessageInfo['is_due'] === 'N' ? 'gray' : 'black';

            $strMessages .= sprintf(
                '<div class="x-grid3-cell-inner" style="color:' . $color . ';">%s</div>',
                $arrMessageInfo['thread_content'] ? nl2br($arrMessageInfo['thread_content']) : '(no subject)'
            );
        }

        return $strMessages;
    }

    /**
     * Load a list of authors for the task's thread
     *
     * @param array $arrMessages
     * @return string
     */
    public function formatThreadAuthor(array $arrMessages)
    {
        $strMessages = '';
        foreach ($arrMessages as $arrMessageInfo) {
            $strMessages .= sprintf(
                '<div class="x-grid3-cell-inner">%s%s</div>',
                $arrMessageInfo['thread_created_by'],
                str_repeat('<br>', count(explode("\n", $arrMessageInfo['thread_content'] ?? '')))
            );
        }
        return $strMessages;
    }

    /**
     * Load a list of dates for the task's thread
     *
     * @param array $arrMessages
     * @return string
     */
    public function formatThreadDate(array $arrMessages)
    {
        $strMessages = '';
        foreach ($arrMessages as $arrMessageInfo) {
            $strMessages .= sprintf(
                '<div class="x-grid3-cell-inner">%s%s</div>',
                empty($arrMessageInfo['thread_created_on']) ? '' : date('M d, Y H:i:s', strtotime($arrMessageInfo['thread_created_on'])),
                str_repeat('<br>', count(explode("\n", $arrMessageInfo['thread_content'] ?? '')))
            );
        }
        return $strMessages;
    }

    /**
     * Mark task(s) as due + send email(s) if needed + save these email(s) if needed
     *
     * @param array|int $arrTaskIds
     * @param bool $booNotifyClient
     * @param array $arrAttachments
     * @return bool true on success, otherwise false
     */
    public function markIsDue($arrTaskIds, $booNotifyClient = true, $arrAttachments = array())
    {
        $booSuccess = false;
        try {
            if (!is_array($arrTaskIds)) {
                $arrTaskIds = array($arrTaskIds);
            }

            if (count($arrTaskIds)) {
                $this->_db2->update('u_tasks', ['is_due' => 'Y'], ['task_id' => $arrTaskIds]);

                foreach ($arrTaskIds as $taskId) {
                    $this->addMessage(
                        $taskId,
                        'marked this task as due',
                        true,
                        true,
                        $booNotifyClient,
                        0,
                        false,
                        $arrAttachments
                    );
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Mark task(s) ad complete/incomplete
     *
     * @param array $arrTaskIds
     * @param bool $booMarkComplete
     * @return bool true on success, otherwise false
     */
    public function markComplete($arrTaskIds, $booMarkComplete = true)
    {
        $booSuccess = false;
        try {
            if (!is_array($arrTaskIds)) {
                $arrTaskIds = array($arrTaskIds);
            }

            if (count($arrTaskIds)) {
                $message = 'marked this task as ' . ($booMarkComplete ? 'complete' : 'incomplete');
                foreach ($arrTaskIds as $taskId) {
                    // Don't try to mark if the task was already marked
                    $arrTaskInfo = $this->getTaskInfo($taskId);
                    if (($booMarkComplete && $arrTaskInfo['completed'] === 'Y') || (!$booMarkComplete && $arrTaskInfo['completed'] === 'N')) {
                        continue;
                    }

                    $count = $this->_db2->update(
                        'u_tasks',
                        ['completed' => $booMarkComplete ? 'Y' : 'N'],
                        ['task_id' => (int)$taskId]
                    );

                    if ($count) {
                        $this->addMessage($taskId, $message, true);
                    }
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Mark task(s) as read/unread
     * @param $arrTaskIds
     * @param bool $booMarkAsRead
     */
    public function markAsRead($arrTaskIds, $booMarkAsRead)
    {
        if (!is_array($arrTaskIds)) {
            $arrTaskIds = array($arrTaskIds);
        }

        $memberId = $this->_auth->getCurrentUserId();

        if ($memberId && count($arrTaskIds)) {
            $this->_db2->delete(
                'u_tasks_read',
                [
                    'task_id'   => $arrTaskIds,
                    'member_id' => $memberId
                ]
            );

            if ($booMarkAsRead) {
                foreach ($arrTaskIds as $taskId) {
                    $this->_db2->insert(
                        'u_tasks_read',
                        [
                            'task_id'   => $taskId,
                            'member_id' => $memberId
                        ]
                    );
                }
            }
        }
    }

    public function changePriority($arrTaskIds, $priority)
    {
        if (!is_array($arrTaskIds)) {
            $arrTaskIds = array($arrTaskIds);
        }

        $this->_db2->delete('u_tasks_priority', ['task_id' => $arrTaskIds]);

        $currentMemberId = $this->_auth->getCurrentUserId();
        foreach ($arrTaskIds as $taskId) {
            $this->_db2->insert(
                'u_tasks_priority',
                [
                    'task_id'   => $taskId,
                    'member_id' => $currentMemberId,
                    'priority'  => $priority,
                ]
            );
        }
    }

    /**
     * Load flags list (as they are saved in DB)
     * @NOTE the same list is hardcoded in js too
     *
     * @return array
     */
    public function getTaskFlags()
    {
        return array(
            0 => 'empty',
            1 => 'red',
            2 => 'blue',
            3 => 'yellow',
            4 => 'green',
            5 => 'orange',
            6 => 'purple',
        );
    }

    public function getTaskFlagId($strFlag)
    {
        $arrFlags = array_flip($this->getTaskFlags());

        return $arrFlags[$strFlag];
    }

    public function getTaskFlagColor($intFlagId)
    {
        $arrFlags = $this->getTaskFlags();

        return $arrFlags[$intFlagId];
    }

    public function updateTaskFlag($taskId, $intFlagId)
    {
        $success = $this->_db2->update('u_tasks', ['flag' => (int)$intFlagId], ['task_id' => (int)$taskId]);

        if ($success) {
            $this->addMessage($taskId, 'changed the flag to ' . ucfirst($this->getTaskFlagColor($intFlagId)), true);
        }

        return $success;
    }

    public function changeDeadline($arrTaskIds, $deadline)
    {
        if (!is_array($arrTaskIds)) {
            $arrTaskIds = array($arrTaskIds);
        }

        $success = false;
        if (!empty($arrTaskIds)) {
            $success = $this->_db2->update('u_tasks', ['deadline' => $deadline], ['task_id' => $arrTaskIds]);
        }

        return $success;
    }

    public function changeSendOnSection($taskId, $arrParams)
    {
        try {
            $arrUpdate = array(
                'type'   => $arrParams['type'],
                'due_on' => $arrParams['due_on'] ?: null,
                'number' => (int)$arrParams['number'],
                'days'   => strtoupper($arrParams['days'] ?? '') == 'CALENDAR' ? 'CALENDAR' : 'BUSINESS',
                'ba'     => strtoupper($arrParams['ba'] ?? '') == 'BEFORE' ? 'BEFORE' : 'AFTER',
                'prof'   => $arrParams['prof'] ?: null,
            );

            $this->_db2->update('u_tasks', $arrUpdate, ['task_id' => $taskId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function changeSubject($task_ids, $subject, $post_message = true)
    {
        if (!is_array($task_ids)) {
            $task_ids = array($task_ids);
        }

        $success = false;
        if (!empty($task_ids)) {
            $success = $this->_db2->update('u_tasks', ['task' => $subject], ['task_id' => $task_ids]);

            if ($success && $post_message) {
                foreach ($task_ids as $taskId) {
                    $this->addMessage($taskId, 'changed the Subject to "' . $subject . '"', true);
                }
            }
        }

        return $success;
    }

    public function changeDueOn($arrTaskIds, $dueOnDate, $booWriteLog = true)
    {
        if (!is_array($arrTaskIds)) {
            $arrTaskIds = array($arrTaskIds);
        }

        $booSuccess = false;
        if (!empty($arrTaskIds)) {
            $booSuccess = $this->_db2->update('u_tasks', ['due_on' => $dueOnDate], ['task_id' => $arrTaskIds]) > 0;

            if ($booSuccess && $booWriteLog) {
                foreach ($arrTaskIds as $taskId) {
                    $this->addMessage($taskId, 'changed the Due On date to "' . $dueOnDate . '"', true);
                }
            }
        }

        return $booSuccess;
    }

    public function addTask($arrTaskData, $booIsOfficioMessage = false, $booFromCron = false, $booRunTrigger = true)
    {
        try {
            $arrInsert = array(
                'task'        => $arrTaskData['subject'],
                'client_type' => (isset($arrTaskData['client_type']) && $arrTaskData['client_type'] == 'prospect') ? 'prospect' : 'client',
                'create_date' => $arrTaskData['create_date'] ?? date('Y-m-d H:i:s'),
                'author_id'   => $arrTaskData['author_id'] ?? $this->_auth->getCurrentUserId(),
                'deadline'    => $arrTaskData['deadline'] ?: null,

                'type'           => $arrTaskData['type'],
                'due_on'         => $arrTaskData['due_on'] ?: null,
                'number'         => (int)$arrTaskData['number'],
                'days'           => strtoupper($arrTaskData['days'] ?? '') == 'CALENDAR' ? 'CALENDAR' : 'BUSINESS',
                'ba'             => strtoupper($arrTaskData['ba'] ?? '') == 'BEFORE' ? 'BEFORE' : 'AFTER',
                'prof'           => $arrTaskData['prof'] ?: null,
                'notify_client'  => $arrTaskData['notify_client'] == 'Y' ? 'Y' : 'N',
                'member_id'      => $arrTaskData['member_id'] ? (int)$arrTaskData['member_id'] : null,
                'company_id'     => isset($arrTaskData['company_id']) ? (int)$arrTaskData['company_id'] : null,
                'auto_task_type' => array_key_exists(
                    'auto_task_type',
                    $arrTaskData
                ) && !empty($arrTaskData['auto_task_type']) ? (int)$arrTaskData['auto_task_type'] : null,
            );

            $booIsTemplateUsed = false;
            $arrAttachments    = array();

            if ($arrInsert['auto_task_type'] && array_key_exists('template_id', $arrTaskData) && !empty($arrTaskData['template_id'])) {
                $arrTemplateInfo        = $this->getServiceContainer()->get(Templates::class)->getMessage($arrTaskData['template_id'], $arrInsert['member_id']);
                $arrTaskData['message'] = $arrTemplateInfo['message'];
                $arrInsert['from']      = $arrTemplateInfo['from'];
                $arrInsert['cc']        = $arrTemplateInfo['cc'];
                $booIsTemplateUsed      = true;

                $arrAttachments = $this->getServiceContainer()->get(Templates::class)->parseTemplateAttachments(
                    (int)$arrTaskData['template_id'],
                    (int)$arrInsert['member_id'],
                    $booFromCron
                );
            }

            if (isset($arrTaskData['completed']) && in_array($arrTaskData['completed'], array('Y', 'N'))) {
                $arrInsert['completed'] = $arrTaskData['completed'];
            }

            $booMarkAsDue = false;
            if (isset($arrTaskData['is_due']) && in_array($arrTaskData['is_due'], array('Y', 'N'))) {
                $booMarkAsDue = $arrTaskData['is_due'] == 'Y';

                $arrInsert['is_due'] = $arrTaskData['is_due'];
            }

            $taskId = $this->_db2->insert('u_tasks', $arrInsert);

            // add to/cc participants
            $this->changeParticipants($taskId, $arrTaskData['to'], $arrTaskData['cc']);

            // Message is NOT a required field
            if ($arrTaskData['message']) {
                $this->addMessage(
                    $taskId,
                    $arrTaskData['message'],
                    $booIsOfficioMessage,
                    $booFromCron,
                    !$booMarkAsDue && $booIsTemplateUsed,
                    $arrTaskData['author_id'] ?? 0,
                    $booIsTemplateUsed,
                    $arrAttachments
                );
            }

            if ($booMarkAsDue) {
                $this->markIsDue($taskId, $booIsTemplateUsed, $arrAttachments);
            } elseif ($booRunTrigger) {
                $this->triggerTaskIsDue($taskId, $booIsTemplateUsed, $arrAttachments);
            }

            // update last_task_written property for company stats
            if (!$booIsOfficioMessage) {
                $this->_company->updateLastField(
                    $arrTaskData['author_id'] ?? false,
                    'last_task_written'
                );
            }
        } catch (Exception $e) {
            $taskId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $taskId;
    }

    /**
     * Action: task becomes due
     * Result: Mark the task as complete
     *
     * @param int $taskId
     * @param bool $booNotifyClient
     * @param array $arrAttachments
     */
    public function triggerTaskIsDue($taskId = 0, $booNotifyClient = true, $arrAttachments = array())
    {
        // Load tasks, mark them as complete, send emails to clients
        $arrWhere             = [];
        $arrWhere[]           = (new Where())->lessThanOrEqualTo('t.due_on', date('Y-m-d H:i:s'));
        $arrWhere['t.is_due'] = 'N';

        if (!empty($taskId)) {
            $arrWhere['t.task_id'] = (int)$taskId;
        }
        $select = (new Select())
            ->from(['t' => 'u_tasks'])
            ->columns([Select::SQL_STAR, 'subject' => 'task'])
            ->join(array('m' => 'u_tasks_messages'), 't.task_id = m.task_id', ['message'], Select::JOIN_LEFT_OUTER)
            ->where($arrWhere)
            ->group('t.task_id')
            ->group('m.message');

        $arrDueTasks = $this->_db2->fetchAll($select);

        // Collect ids to mark all tasks at once
        $arrTaskIds = array();
        foreach ($arrDueTasks as $arrTaskInfo) {
            $arrTaskIds[] = $arrTaskInfo['task_id'];
        }

        if (count($arrTaskIds)) {
            $this->markIsDue($arrTaskIds, $booNotifyClient, $arrAttachments);
        }
    }

    /**
     * Send notification email for a task if that setting is enabled
     *
     * @param int $task_id
     * @param array $arrAttachments
     * @return void
     */
    public function sendEmailToClient($task_id, $arrAttachments = array())
    {
        try {
            $arrTaskInfo = $this->getTaskInfo($task_id);
            $emailFrom   = $arrTaskInfo['from'];
            $emailCC     = $arrTaskInfo['cc'];

            // Check if we need to send email
            if ($arrTaskInfo['notify_client'] == 'Y' && (int)$arrTaskInfo['member_id']) {
                $select = (new Select())
                    ->from('members')
                    ->columns(['emailAddress'])
                    ->where(['member_id' => (int)$arrTaskInfo['member_id']]);

                $client_email = $this->_db2->fetchOne($select);

                $validator = new EmailAddress();
                if ($validator->isValid($client_email)) {
                    $task_messages = $this->getTaskMessages($task_id);

                    $message         = '';
                    $booFromTemplate = false;
                    foreach ($task_messages as $m) {
                        $message .= "<style type='text/css'>* { font-family: Arial, serif; font-size: 12px; }</style>";

                        $message .= "<div style='margin-bottom:15px; border:1px solid #ADD8E6;'>";

                        $message .= "<div style='background-color: #EDF6F9; border-bottom: 1px solid #D9E9F0; padding: 4px;'>";
                        $message .= "<div style='float:left;'>" . (!$m['thread_officio_said'] ? $m['thread_created_by'] : 'Officio') . " said:</div>";
                        $message .= "<div style='float:right;'>" . date(
                                'M d, Y H:i:s',
                                strtotime($m['thread_created_on'])
                            ) . "</div>";
                        $message .= "<div style='clear:left;'></div>";
                        $message .= "</div>";

                        $message .= "<div style='padding: 4px;'>" . nl2br($m['thread_content'] ?? '') . "</div>";
                        $message .= "</div>";

                        if ($m['thread_from_template']) {
                            $message = "<style type='text/css'>* { font-family: Arial, serif; font-size: 12px; }</style>";
                            if (!(strcmp($m['thread_content'] ?? '', strip_tags($m['thread_content'] ?? '')) == 0)) {
                                $message .= "<div>" . $m['thread_content'] . "</div>";
                            } else {
                                $message .= "<div>" . nl2br($m['thread_content'] ?? '') . "</div>";
                            }
                            $booFromTemplate = true;
                            break;
                        }
                    }

                    if (empty($emailFrom)) {
                        $select = (new Select())
                            ->from(['m' => 'members'])
                            ->columns([])
                            ->join(array('c' => 'company'), 'c.company_id=m.company_id', array('companyEmail'), Select::JOIN_LEFT_OUTER)
                            ->where(['m.member_id' => (int)$arrTaskInfo['member_id']]);

                        $emailFrom = $this->_db2->fetchOne($select);

                        $booShowSenderName = true;
                    } else {
                        $booShowSenderName = false;
                    }

                    $params = array(
                        'from_email'    => $emailFrom,
                        'cc'            => $emailCC,
                        'friendly_name' => '',
                        'email'         => $client_email,
                        'subject'       => $arrTaskInfo['task'],
                        'message'       => $message,
                        'attached'      => $arrAttachments
                    );

                    $senderInfo = $this->_clients->getMemberInfo();
                    list(, $email) = $this->_mailer->send($params, false, $senderInfo, false, true, $booShowSenderName);
                    if ($booFromTemplate) {
                        $arrMemberInfo = $this->_clients->getMemberInfo($arrTaskInfo['member_id']);
                        $companyId     = $arrMemberInfo['company_id'];
                        $booLocal      = $this->_company->isCompanyStorageLocationLocal($companyId);
                        $clientFolder  = $this->_files->getClientCorrespondenceFTPFolder($companyId, $arrTaskInfo['member_id'], $booLocal);
                        $this->_mailer->saveRawEmailToClient($email, $arrTaskInfo['task'], 0, $companyId, $arrTaskInfo['member_id'], $this->_clients->getMemberInfo(), 0, $clientFolder, $booLocal);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Delete tasks of a specific member (case/prospect)
     *
     * @param int $memberId
     * @param bool $booProspect
     */
    public function deleteMemberTasks($memberId, $booProspect = false)
    {
        // Load all tasks related to the client/prospect
        $select = (new Select())
            ->from(['t' => 'u_tasks'])
            ->columns(['task_id'])
            ->where([
                'member_id'   => (int)$memberId,
                'client_type' => $booProspect ? 'prospect' : 'client'
            ]);

        $arrTaskIds = $this->_db2->fetchCol($select);

        $this->deleteTasks($arrTaskIds);
    }

    /**
     * Delete tasks by their ids
     *
     * @param array $arrTaskIds
     * @return void
     */
    public function deleteTasks($arrTaskIds)
    {
        if (!is_array($arrTaskIds)) {
            $arrTaskIds = array($arrTaskIds);
        }

        // Clear all tables which contain these tasks info
        if (!empty($arrTaskIds)) {
            $arrTablesClear = array(
                'u_tasks_messages',
                'u_tasks_assigned_to',
                'u_tasks_priority',
                'u_tasks_read',
                'u_tasks'
            );

            foreach ($arrTablesClear as $strTable) {
                $this->_db2->delete($strTable, ['task_id' => $arrTaskIds]);
            }
        }
    }

    public function convertTaskOwner($prospectId, $memberId)
    {
        $this->_db2->update(
            'u_tasks',
            [
                'member_id'   => $memberId,
                'client_type' => 'client'
            ],
            [
                'member_id'   => (int)$prospectId,
                'client_type' => 'prospect'
            ]
        );
    }

    public function addMessage(
        $taskId,
        $message,
        $booIsOfficioMessage = false,
        $booFromCron = false,
        $booSendNotify = true,
        $authorId = 0,
        $fromTemplate = false,
        $arrAttachments = array()
    ) {
        $taskId = (int)$taskId;

        if (empty($authorId)) {
            $authorId = $booFromCron ? null : $this->_auth->getCurrentUserId();
        }

        $this->_db2->insert(
            'u_tasks_messages',
            [
                'task_id'       => $taskId,
                'member_id'     => $authorId,
                'message'       => $message,
                'timestamp'     => time(),
                'officio_said'  => (int)$booIsOfficioMessage,
                'from_template' => (int)$fromTemplate
            ]
        );

        // mark task as unread for all, except message author
        $arrWhere = array(
            'task_id' => $taskId
        );
        if (!$booFromCron) {
            $arrWhere[] = (new Where())->notEqualTo('member_id', $authorId);
        }
        $this->_db2->delete('u_tasks_read', $arrWhere);

        // send notification e-mail to client
        if ($booSendNotify) {
            $this->sendEmailToClient($taskId, $arrAttachments);
        }
    }

    /**
     * Update 'notify client' checkbox's state for a specific task
     *
     * @param int $taskId
     * @param string $notifyClient Y or N
     * @param bool $booPostMessage true to add a task message that checkbox was checked/unchecked
     * @return void
     */
    public function changeNotifyClient($taskId, $notifyClient, $booPostMessage = true)
    {
        if (in_array($notifyClient, array('Y', 'N'))) {
            $updatedCount = $this->_db2->update('u_tasks', ['notify_client' => $notifyClient], ['task_id' => $taskId]);

            if ($updatedCount && $booPostMessage) {
                $this->addMessage(
                    $taskId,
                    sprintf('%s Notify Client checkbox', $notifyClient == 'Y' ? 'checked' : 'unchecked'),
                    true
                );
            }
        }
    }

    /**
     * Update the list of participants of the task (TO and/or CC)
     *
     * @param int $taskId
     * @param array $to members' ids
     * @param array $cc members' ids
     * @return void
     */
    public function changeParticipants($taskId, $to, $cc)
    {
        $this->_db2->delete('u_tasks_assigned_to', ['task_id' => (int)$taskId]);

        if (!empty($to)) {
            foreach ($to as $a) {
                $this->_db2->insert(
                    'u_tasks_assigned_to',
                    [
                        'task_id'   => $taskId,
                        'member_id' => $a,
                        'to_cc'     => 'to',
                    ]
                );
            }
        }

        if (!empty($cc)) {
            foreach ($cc as $a) {
                $this->_db2->insert(
                    'u_tasks_assigned_to',
                    [
                        'task_id'   => $taskId,
                        'member_id' => $a,
                        'to_cc'     => 'cc',
                    ]
                );
            }
        }
    }

    /**
     * Load grouped list of tasks for the home page and daily email notifications
     *
     * @param array $arrExtraFilterParams
     * @param bool $booLoadAdditionalInfo
     * @return array
     */
    public function getTasksForMember($arrExtraFilterParams = array(), $booLoadAdditionalInfo = true)
    {
        $arrFilterParams = array(
            'assigned'    => 'me',
            'status'      => 'active',
            'task_is_due' => 'Y',
            'unread'      => true,
        );

        // Apply extra filter params if needed
        $arrFilterParams = array_merge($arrFilterParams, $arrExtraFilterParams);

        // Get tasks list
        $arrTasks = $this->getMemberTasks(
            'my_tasks',
            $arrFilterParams,
            'task_due_on',
            'ASC',
            $booLoadAdditionalInfo
        );

        // now sort array by task type
        $tasks_other     = $tasks_payment_due = $tasks_uploaded_docs = $tasks_completed_form = array();
        $tasks_other_ids = $tasks_payment_due_ids = $tasks_uploaded_docs_ids = $tasks_completed_form_ids = array(); // unique member_ids
        foreach ($arrTasks as $t) {
            // remove general tasks
            if (!$t['member_id']) {
                continue;
            }

            if ($t['auto_task_type'] == 1 && !in_array($t['member_id'], $tasks_payment_due_ids)) {
                $tasks_payment_due[]     = $t;
                $tasks_payment_due_ids[] = $t['member_id'];
            } elseif ($t['auto_task_type'] == 2 && !in_array($t['member_id'], $tasks_completed_form_ids)) {
                $tasks_completed_form[]     = $t;
                $tasks_completed_form_ids[] = $t['member_id'];
            } elseif ($t['auto_task_type'] == 3 && !in_array($t['member_id'], $tasks_uploaded_docs_ids)) {
                $tasks_uploaded_docs[]     = $t;
                $tasks_uploaded_docs_ids[] = $t['member_id'];
            } elseif (!in_array($t['member_id'], $tasks_other_ids)) {
                $tasks_other[]     = $t;
                $tasks_other_ids[] = $t['member_id'];
            }
        }

        return array(
            'tasks_other'          => $tasks_other,
            'tasks_payment_due'    => $tasks_payment_due,
            'tasks_uploaded_docs'  => $tasks_uploaded_docs,
            'tasks_completed_form' => $tasks_completed_form,
        );
    }

    public function getTip($task_id)
    {
        $task = $this->getTaskInfo($task_id);

        $client = array();
        if ($task['member_id']) {
            $client = $this->_clients->getClientInfo($task['member_id']);
        }

        return 'Task: ' . $task['task'] .
            ($task['member_id'] ? '<br /> Client: ' . $client['full_name'] : '') .
            '<br /> Assigned to: ' . $this->_clients->getCommaSeparatedMemberNames(array_filter(explode(';', $task['to_ids'] ?? ''))) .
            ($task['cc_ids'] ? '<br /> Assigned CC: ' . $this->_clients->getCommaSeparatedMemberNames(array_filter(explode(';', $task['cc_ids'] ?? ''))) : '') .
            ($task['due_on'] ? '<br /> Due on: ' . date('M j, Y', strtotime($task['due_on'])) : '') .
            '<br /> Created date: ' . date('M j, Y', strtotime($task['create_date']));
    }

    /**
     * Load member ids related to specific reminder
     *
     * @param $companyId
     * @param int $caseId
     * @param string $strAssignedTo
     * @param int $level
     * @return array - array with ids of all members this reminder is assigned
     */
    public function getReminderAssignedToMembers($companyId, $caseId, $strAssignedTo, $level = 0)
    {
        $arrMemberIds = array();
        try {
            if (preg_match('/^(.*):(.*)$/', $strAssignedTo, $regs)) {
                switch ($regs[1]) {
                    case 'user':
                        if ($regs[2] == 'all') {
                            $select = (new Select())
                                ->from('members')
                                ->columns(['member_id'])
                                ->where([
                                    'company_id' => (int)$companyId,
                                    'status'     => 1,
                                    'userType'   => Members::getMemberType('admin_and_user')
                                ]);

                            $arrMemberIds = $this->_db2->fetchCol($select);
                        } else {
                            $arrMemberIds = array($regs[2]);
                        }
                        break;

                    case 'assigned':
                        switch ($regs[2]) {
                            case '4':
                                $companyFieldId = 'sales_and_marketing';
                                break;

                            case '5':
                                $companyFieldId = 'processing';
                                break;

                            case '6':
                                $companyFieldId = 'accounting';
                                break;

                            default:
                                $companyFieldId = 'registered_migrant_agent';
                                break;
                        }

                        // get field_id
                        $fieldId = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId($companyFieldId, $companyId);

                        if (!empty($fieldId)) {
                            // get field value
                            $fieldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);
                            if (!empty($fieldValue)) {
                                if ($companyFieldId == 'registered_migrant_agent') {
                                    $arrMemberIds = array($fieldValue);
                                } elseif (empty($level)) {
                                    // Don't dive more than once
                                    $arrMemberIds = $this->getReminderAssignedToMembers(
                                        $companyId,
                                        $caseId,
                                        $fieldValue,
                                        1
                                    );
                                }
                            }
                        }
                        break;

                    case 'role':
                        if (is_numeric($regs[2])) {
                            $select = (new Select())
                                ->from(['mr' => 'members_roles'])
                                ->columns(['member_id'])
                                ->join(array('m' => 'members'), 'm.member_id = mr.member_id', [], Select::JOIN_LEFT_OUTER)
                                ->where([
                                    'mr.role_id'   => (int)$regs[2],
                                    'm.company_id' => (int)$companyId,
                                    'm.status'     => 1,
                                    'm.userType'   => Members::getMemberType('admin_and_user')
                                ]);

                            $arrMemberIds = $this->_db2->fetchCol($select);
                        }
                        break;

                    default:
                        break;
                }

                // Make sure that saved/found members are valid
                if (!empty($arrMemberIds)) {
                    $select = (new Select())
                        ->from('members')
                        ->columns(['member_id'])
                        ->where([
                            'member_id' => $arrMemberIds
                        ]);

                    $arrMemberIds = Settings::arrayUnique($this->_db2->fetchCol($select));
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMemberIds;
    }

    /**
     * Load the list of tasks for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyTasks($companyId)
    {
        $select = (new Select())
            ->from(array('t' => 'u_tasks'))
            ->join(array('m' => 'members'),
                't.author_id = m.member_id',
                array(
                    'author_name'      => new Expression("CONCAT(m.fName, ' ', m.lName)"),
                    'assigned_to_name' => new Expression('""')
                ),
                Select::JOIN_LEFT_OUTER)
            ->join(array('a' => 'u_tasks_assigned_to'),
                'a.task_id = t.task_id',
                array(
                    'to_ids' => new Expression(
                        'GROUP_CONCAT(IF(a.to_cc = "to", CONCAT(a.member_id, ";"), "") ORDER BY a.member_id SEPARATOR "")'
                    ),
                    'cc_ids' => new Expression(
                        'GROUP_CONCAT(IF(a.to_cc = "cc", CONCAT(a.member_id, ";"), "") ORDER BY a.member_id SEPARATOR "")'
                    ),
                ),
                Select::JOIN_LEFT_OUTER)
            ->where(['m.company_id' => $companyId])
            ->group('t.task_id')
            ->order('t.create_date');

        $arrAllTasks = $this->_db2->fetchAll($select);

        $arrCaseIds          = array();
        $arrCompanyProspects = $this->getServiceContainer()->get(CompanyProspects::class)->getCompanyProspectsList($companyId);
        foreach ($arrAllTasks as $key => $arrTaskInfo) {
            if (empty($arrTaskInfo['member_id'])) {
                continue;
            }

            switch ($arrTaskInfo['client_type']) {
                case 'client':
                    // Collect cases ids - we'll load their parent clients and generate names for them later
                    $arrCaseIds[] = $arrTaskInfo['member_id'];
                    break;

                case 'prospect':
                    // Generate Prospect name
                    foreach ($arrCompanyProspects as $arrProspectInfo) {
                        if ($arrProspectInfo['prospect_id'] == $arrTaskInfo['member_id']) {
                            $arrTaskInfo['assigned_to_name'] = $this->getServiceContainer()->get(CompanyProspects::class)->generateProspectName($arrProspectInfo);
                            break;
                        }
                    }

                    $arrAllTasks[$key] = $arrTaskInfo;
                    break;

                default:
                    break;
            }
        }

        // Generate Case names
        $arrCaseIds = array_unique($arrCaseIds);
        if (count($arrCaseIds)) {
            $arrCasesList = $this->_clients->getClientsInfo($arrCaseIds);
            $arrParents   = $this->_clients->getCasesListWithParents($arrCasesList);
            foreach ($arrAllTasks as $key => $arrTaskInfo) {
                if (!empty($arrTaskInfo['member_id']) && $arrTaskInfo['client_type'] == 'client') {
                    foreach ($arrParents as $arrParentInfo) {
                        if ($arrTaskInfo['member_id'] == $arrParentInfo['clientId']) {
                            $arrTaskInfo['assigned_to_name'] = $arrParentInfo['clientFullName'];
                            $arrAllTasks[$key]               = $arrTaskInfo;
                            break;
                        }
                    }
                }
            }
        }

        return $arrAllTasks;
    }

    /**
     * Get subject suggestions (for each word)
     *
     * @param string $searchName
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getSubjectSuggestions($searchName, $start, $limit)
    {
        $arrResult    = array();
        $arrSearch    = array();
        $totalRecords = 0;

        try {
            $select = (new Select())
                ->from(['t' => 'u_tasks'])
                ->columns(['task'])
                ->join(array('m' => 'members'), 't.author_id = m.member_id', [])
                ->where(['m.company_id' => $this->_auth->getCurrentUserCompanyId()])
                ->group('t.task')
                ->order('t.task')
                ->limit($limit)
                ->offset($start);

            $searchName = substr($searchName ?? '', 0, 1000);

            $arrSearch = Settings::generateWordsCombinations($searchName);
            if (count($arrSearch) > 24) {
                $arrSearch = array($searchName);
            }

            $arrSearchFor = [];
            foreach ($arrSearch as $strSearch) {
                $arrSearchFor[] = (new Where())->like('t.task', "%$strSearch%");
            }

            if (count($arrSearchFor)) {
                $select->where([(new Where())->addPredicates($arrSearchFor, Where::OP_OR)]);
            }

            $arrResult    = $this->_db2->fetchAll($select);
            $totalRecords = $this->_db2->fetchResultsCount($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrResult, $totalRecords, $arrSearch);
    }

    /**
     * Load count of tasks:
     *  - due today
     *  - due tomorrow
     *  - due from tomorrow to 7 days
     *
     * @return array
     */
    public function getTasksCountForHomepage()
    {
        $countDueToday    = 0;
        $countDueTomorrow = 0;
        $countDueIn7Days  = 0;

        try {
            // Get tasks list
            $arrTasks = $this->getMemberTasks(
                'my_tasks',
                array(
                    'assigned' => 'me',
                    'status'   => 'active',
                ),
                'task_due_on',
                'ASC'
            );

            // Calculate dates in relation to the current user's time zone
            $tz = $this->_auth->getCurrentMemberTimezone();
            if ($tz instanceof DateTimeZone) {
                $datetime     = new DateTime('tomorrow', $tz);
                $tomorrowDate = $datetime->format('Y-m-d');

                $datetime   = new DateTime('+7 days', $tz);
                $dueIn7Days = $datetime->format('Y-m-d');
            } else {
                $tomorrowDate = date('Y-m-d', strtotime('tomorrow'));
                $dueIn7Days   = date('Y-m-d', strtotime('+7 days'));
            }

            foreach ($arrTasks as $arrTaskInfo) {
                if ($arrTaskInfo['task_is_due'] === 'Y') {
                    $countDueToday++;
                }

                if (!Settings::isDateEmpty($arrTaskInfo['task_due_on'])) {
                    if ($arrTaskInfo['task_due_on'] == $tomorrowDate) {
                        $countDueTomorrow++;
                    }

                    if (Settings::isDateBetweenDates($arrTaskInfo['task_due_on'] . ' 00:00:01', $tomorrowDate, $dueIn7Days)) {
                        $countDueIn7Days++;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($countDueToday, $countDueTomorrow, $countDueIn7Days);
    }
}
