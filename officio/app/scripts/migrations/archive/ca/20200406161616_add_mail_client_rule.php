<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddMailClientRule extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'Webmail Module',
                'rule_check_id'      => 'mail-module',
                'module_id'          => 'mail',
                'resource_id'        => 'index',
                'resource_privilege' => array('')
            )
        );

        return $arrRules;
    }

    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select(array('rule_id'))
                ->from(array('acl_rules'))
                ->where(
                    [
                        'rule_check_id' => 'mail-view'
                    ]
                )
                ->execute();

            $ruleParentId = false;
            $row          = $statement->fetch();
            if (!empty($row)) {
                $ruleParentId = $row[array_key_first($row)];
            }

            if (empty($ruleParentId)) {
                throw new Exception('Mail rule not found.');
            }

            $arrRules = $this->getNewRules();

            $order = 0;
            foreach ($arrRules as $arrRuleInfo) {
                $statement = $this->getQueryBuilder()
                    ->insert(
                        [
                            'rule_parent_id',
                            'module_id',
                            'rule_description',
                            'rule_check_id',
                            'superadmin_only',
                            'crm_only',
                            'rule_visible',
                            'rule_order'
                        ]
                    )
                    ->into('acl_rules')
                    ->values(
                        array(
                            'rule_parent_id'   => $ruleParentId,
                            'module_id'        => $arrRuleInfo['module_id'],
                            'rule_description' => $arrRuleInfo['rule_description'],
                            'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                            'superadmin_only'  => 0,
                            'crm_only'         => 'N',
                            'rule_visible'     => 1,
                            'rule_order'       => $order++,
                        )
                    )
                    ->execute();
                $ruleId    = $statement->lastInsertId('acl_rules');

                foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                    $this->table('acl_rule_details')
                        ->insert([
                            [
                                'rule_id'            => $ruleId,
                                'module_id'          => $arrRuleInfo['module_id'],
                                'resource_id'        => $arrRuleInfo['resource_id'],
                                'resource_privilege' => $resourcePrivilege,
                                'rule_allow'         => 1,
                            ]
                        ])
                        ->saveData();
                }

                $this->table('packages_details')
                    ->insert([
                        [
                            'package_id'                 => 1,
                            'rule_id'                    => $ruleId,
                            'package_detail_description' => $arrRuleInfo['rule_description'],
                            'visible'                    => 1,
                        ]
                    ])->saveData();

                $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                        SELECT a.role_id, $ruleId
                                        FROM acl_role_access AS a
                                        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                        WHERE a.rule_id = $ruleParentId");
            }

            $this->getQueryBuilder()
                ->update('acl_rules')
                ->set(array('rule_description' => 'Send Email Option'))
                ->where(
                    [
                        'rule_id' => (int)$ruleParentId
                    ]
                )
                ->execute();
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $arrRules   = $this->getNewRules();
            $arrRuleIds = array();
            foreach ($arrRules as $arrRuleInfo) {
                $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
            }

            $this->getQueryBuilder()
                ->delete('acl_rules')
                ->whereInList('rule_check_id', $arrRuleIds)
                ->execute();

            $statement = $this->getQueryBuilder()
                ->select(array('rule_id'))
                ->from(array('acl_rules'))
                ->where(
                    [
                        'rule_check_id' => 'mail-view'
                    ]
                )
                ->execute();

            $row = $statement->fetch();

            $ruleParentId = false;
            if (!empty($row)) {
                $ruleParentId = $row[array_key_first($row)];
            }

            $this->getQueryBuilder()
                ->update('acl_rules')
                ->set(array('rule_description' => 'Mail'))
                ->where(
                    [
                        'rule_id' => (int)$ruleParentId
                    ]
                )
                ->execute();
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}