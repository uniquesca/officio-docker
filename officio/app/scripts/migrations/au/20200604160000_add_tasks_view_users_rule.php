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
        try {
            $builder      = $this->getQueryBuilder();
            $statement    = $builder
                ->select(['rule_id'])
                ->from('acl_rules')
                ->where(['rule_check_id' => 'tasks-view'])
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
                $builder = $this->getQueryBuilder();
                $statement = $builder->insert(
                    [
                        'rule_parent_id',
                        'module_id',
                        'rule_description',
                        'rule_check_id',
                        'superadmin_only',
                        'crm_only',
                        'rule_visible',
                        'rule_order',
                    ]
                )
                    ->into('acl_rules')
                    ->values(
                        [
                            'rule_parent_id'   => $myTasksRuleId,
                            'module_id'        => 'tasks',
                            'rule_description' => $arrRuleInfo['rule_description'],
                            'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                            'superadmin_only'  => 0,
                            'crm_only'         => 'N',
                            'rule_visible'     => 1,
                            'rule_order'       => $order++,
                        ]
                    )
                    ->execute();
                $ruleId = $statement->lastInsertId('acl_rules');

                $builder = $this->getQueryBuilder();
                $builder->insert(
                    [
                        'rule_id',
                        'module_id',
                        'resource_id',
                        'resource_privilege',
                        'rule_allow',
                    ]
                )
                    ->into('acl_rule_details')
                    ->values(
                        [
                            'rule_id'            => $ruleId,
                            'module_id'          => 'tasks',
                            'resource_id'        => 'index',
                            'resource_privilege' => 'index',
                            'rule_allow'         => 1,
                        ]
                    )
                    ->execute();

                $builder = $this->getQueryBuilder();
                $builder->insert(
                    [
                        'package_id',
                        'rule_id',
                        'package_detail_description',
                        'visible',
                    ]
                )
                    ->into('packages_details')
                    ->values(
                        [
                            'package_id'                 => 1,
                            'rule_id'                    => $ruleId,
                            'package_detail_description' => 'My Tasks - ' . $arrRuleInfo['rule_description'],
                            'visible'                    => 1,
                        ]
                    )
                    ->execute();

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
            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
            /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        $arrRules = $this->getNewRules();

        $arrRuleIds = array();
        foreach ($arrRules as $arrRuleInfo) {
            $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
        }

        $builder = $this->getQueryBuilder();
        $builder
            ->delete('acl_rules')
            ->where(['rule_check_id' => $arrRuleIds])
            ->execute();
    }
}
