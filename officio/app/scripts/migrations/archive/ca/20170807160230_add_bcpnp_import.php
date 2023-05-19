<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddBcpnpImport extends AbstractMigration
{
    public function up()
    {
        try {
            $this->query("ALTER TABLE `company_details` ADD COLUMN `allow_import_bcpnp` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `allow_import`;");
            $this->query("ALTER TABLE `clients_import` ADD COLUMN `is_bcpnp_import` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `step`;");

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
                        'rule_order',
                    )
                )
                ->into('acl_rules')
                ->values(
                    array(
                        'rule_parent_id'   => 4,
                        'module_id'        => 'superadmin',
                        'rule_description' => 'BC PNP Import',
                        'rule_check_id'    => 'import-bcpnp',
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 0,
                    )
                )
                ->execute();

            $ruleId = $statement->lastInsertId('acl_rules');

            $dataAclRuleDetails = array(
                'rule_id'            => $ruleId,
                'module_id'          => 'superadmin',
                'resource_id'        => 'import-bcpnp',
                'resource_privilege' => '',
                'rule_allow'         => 1,
            );

            $this->table('acl_rule_details')
                ->insert($dataAclRuleDetails)
                ->saveData();

            $dataPackagesDetails = array(
                'package_id'                 => 1,
                'rule_id'                    => $ruleId,
                'package_detail_description' => 'BC PNP Import',
                'visible'                    => 1,
            );

            $this->table('packages_details')
                ->insert($dataPackagesDetails)
                ->saveData();
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
            $this->query("ALTER TABLE `company_details`
                DROP COLUMN `allow_import_bcpnp`;");

            $this->query("ALTER TABLE `clients_import`
                DROP COLUMN `is_bcpnp_import`;");

            $this->getQueryBuilder()
                ->delete('acl_rules')
                ->where(['rule_check_id' => 'import-bcpnp'])
                ->execute();
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}
