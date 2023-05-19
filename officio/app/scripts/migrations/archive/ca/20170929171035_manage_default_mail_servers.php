<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ManageDefaultMailServers extends AbstractMigration
{
    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
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
                        'rule_parent_id'   => 4,
                        'module_id'        => 'superadmin',
                        'rule_description' => 'Manage Default Mail Servers',
                        'rule_check_id'    => 'manage-default-mail-servers',
                        'superadmin_only'  => 1,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 0
                    )
                )
                ->execute();

            $ruleId = $statement->lastInsertId('acl_rules');

            $this->getQueryBuilder()
                ->insert(
                    array(
                        'rule_id',
                        'module_id',
                        'resource_id',
                        'resource_privilege',
                        'rule_allow'
                    )
                )
                ->into('acl_rule_details')
                ->values(
                    array(
                        'rule_id'            => $ruleId,
                        'module_id'          => 'superadmin',
                        'resource_id'        => 'manage-default-mail-servers',
                        'resource_privilege' => '',
                        'rule_allow'         => 1
                    )
                )
                ->execute();

            $this->getQueryBuilder()
                ->insert(
                    array(
                        'package_id',
                        'rule_id',
                        'package_detail_description',
                        'visible'
                    )
                )
                ->into('packages_details')
                ->values(
                    array(
                        'package_id'                 => 1,
                        'rule_id'                    => $ruleId,
                        'package_detail_description' => 'Manage Default Mail Servers',
                        'visible'                    => 1
                    )
                )
                ->execute();

            $this->query(
                "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                            SELECT r.role_parent_id, $ruleId
                            FROM acl_roles as r
                            WHERE r.role_type IN ('superadmin');"
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
            $this->getQueryBuilder()->delete(
                'acl_rules'
            )
                ->where([
                    'rule_check_id' => 'manage-default-mail-servers'
                ])
                ->execute();
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}