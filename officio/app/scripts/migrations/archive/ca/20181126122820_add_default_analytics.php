<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddDefaultAnalytics extends AbstractMigration
{
    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select(array('rule_id'))
                ->from(array('acl_rules'))
                ->where(
                    [
                        'rule_check_id' => 'admin-view'
                    ]
                )
                ->execute();

            $ruleParentId = false;
            $row          = $statement->fetch();
            if (!empty($row)) {
                $ruleParentId = $row[array_key_first($row)];
            }

            $statement = $this->getQueryBuilder()
                ->select(array('rule_id'))
                ->from(array('acl_rules'))
                ->where(
                    [
                        'rule_check_id' => 'default-searches-view'
                    ]
                )
                ->execute();

            $searchRuleId = false;
            $row          = $statement->fetch();
            if (!empty($row)) {
                $searchRuleId = $row[array_key_first($row)];
            }

            if (empty($ruleParentId) || empty($searchRuleId)) {
                throw new Exception('Main parent rule not found.');
            }

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
                        'rule_order',
                    ]
                )
                ->into('acl_rules')
                ->values(
                    [
                        'rule_parent_id'   => $ruleParentId,
                        'module_id'        => 'superadmin',
                        'rule_description' => 'Default Analytics',
                        'rule_check_id'    => 'manage-default-analytics',
                        'superadmin_only'  => 1,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 0,
                    ]
                )
                ->execute();

            $analyticsRuleId = $statement->lastInsertId('acl_rules');

            $this->table('acl_rule_details')
                ->insert([
                    [
                        'rule_id'            => $analyticsRuleId,
                        'module_id'          => 'superadmin',
                        'resource_id'        => 'manage-default-analytics',
                        'resource_privilege' => '',
                        'rule_allow'         => 1,
                    ]
                ])
                ->saveData();

            $this->table('packages_details')
                ->insert([
                    [
                        'package_id'                 => 1,
                        'rule_id'                    => $analyticsRuleId,
                        'package_detail_description' => 'Default Analytics',
                        'visible'                    => 1,
                    ]
                ])
                ->saveData();

            $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                        SELECT a.role_id, $analyticsRuleId
                        FROM acl_role_access AS a
                        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                        WHERE a.rule_id = $searchRuleId"
            );
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
            $arrRuleIds = array('manage-default-analytics');

            $this->getQueryBuilder()
                ->delete('acl_rules')
                ->where(function ($exp) use ($arrRuleIds) {
                    return $exp
                        ->in('rule_check_id', $arrRuleIds);
                })
                ->execute();

            $this->query('DROP TABLE `analytics`');
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}