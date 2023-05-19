<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddBcpnpImport extends AbstractMigration
{
    public function up()
    {
        try {

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $db->query("ALTER TABLE `company_details`
                ADD COLUMN `allow_import_bcpnp` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `allow_import`;");

            $db->query("ALTER TABLE `clients_import`
                ADD COLUMN `is_bcpnp_import` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `step`;");

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id' => 4,
                    'module_id' => 'superadmin',
                    'rule_description' => 'BC PNP Import',
                    'rule_check_id' => 'import-bcpnp',
                    'superadmin_only' => 0,
                    'crm_only' => 'N',
                    'rule_visible' => 1,
                    'rule_order' => 0,
                )
            );

            $ruleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $ruleId,
                    'module_id'          => 'superadmin',
                    'resource_id'        => 'import-bcpnp',
                    'resource_privilege' => '',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $ruleId,
                    'package_detail_description' => 'BC PNP Import',
                    'visible'                    => 1,
                )
            );

            $db->commit();


            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            Acl::clearCache($cache);
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->query("ALTER TABLE `company_details`
	            DROP COLUMN `allow_import_bcpnp`;");

            $db->query("ALTER TABLE `clients_import`
	            DROP COLUMN `is_bcpnp_import`;");

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id = ?', 'import-bcpnp')
            );

        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }
}