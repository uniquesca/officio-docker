<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddTasksViewUsersRule extends AbstractMigration
{

    protected $clearAclCache = true;

    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description' => 'Access To View Tasks Assigned To A User',
                'rule_check_id' => 'tasks-view-users',
            ),
        );

        return $arrRules;
    }

    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

        try {

            $statement = $builder
                ->select('rule_id')
                ->from(array('r' => 'acl_rules'))
                ->where(
                    [
                        'r.rule_check_id' => 'tasks-view'
                    ]
                )
                ->execute();

            $myTasksRuleId = false;
            $row = $statement->fetch();
            if (count($row)) {
                $myTasksRuleId =  $row[array_key_first($row)];
            }

            if (empty($myTasksRuleId)) {
                throw new Exception('My Tasks rule not found.');
            }

            $arrRules = $this->getNewRules();

            $this->getAdapter()->beginTransaction();

            $order = 2;
            foreach ($arrRules as $arrRuleInfo) {
                $statement = $builder
                    ->insert(
                        array(
                            'rule_parent_id',
                            'module_id',
                            'rule_description',
                            'rule_check_id',
                            'superadmin_only',
                            'crm_only',
                            'rule_visible',
                            'rule_order'
                        )
                    )
                    ->into('acl_rules')
                    ->values(
                    array(
                        'rule_parent_id'   => $myTasksRuleId,
                        'module_id'        => 'tasks',
                        'rule_description' => $arrRuleInfo['rule_description'],
                        'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => $order++,
                    )
                    )
                    ->execute();

                $ruleId = $statement->lastInsertId('acl_rules');

                $this->insert(
                    'acl_rule_details',
                    array(
                        'rule_id'            => $ruleId,
                        'module_id'          => 'tasks',
                        'resource_id'        => 'index',
                        'resource_privilege' => 'index',
                        'rule_allow'         => 1,
                    )
                );

                $this->insert(
                    'packages_details',
                    array(
                        'package_id'                 => 1,
                        'rule_id'                    => $ruleId,
                        'package_detail_description' => 'My Tasks - ' . $arrRuleInfo['rule_description'],
                        'visible'                    => 1,
                    )
                );

                $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                            SELECT a.role_id, $ruleId
                            FROM acl_role_access AS a
                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                            WHERE a.rule_id = $myTasksRuleId"
                );
            }


            $this->getAdapter()->commitTransaction();
        } catch (\Exception $e) {
            $this->getAdapter()->rollbackTransaction();
            /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

        $arrRules = $this->getNewRules();

        $arrRuleIds = array();
        foreach ($arrRules as $arrRuleInfo) {
            $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
        }

        $builder
            ->delete('acl_rules')
            ->whereInList('rule_check_id', $arrRuleIds)
            ->execute();
    }
}
