<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ManageDefaultMailServers extends AbstractMigration
{
    public function up()
    {
        try {

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id' => 4,
                    'module_id' => 'superadmin',
                    'rule_description' => 'Manage Default Mail Servers',
                    'rule_check_id' => 'manage-default-mail-servers',
                    'superadmin_only' => 1,
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
                    'resource_id'        => 'manage-default-mail-servers',
                    'resource_privilege' => '',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $ruleId,
                    'package_detail_description' => 'Manage Default Mail Servers',
                    'visible'                    => 1,
                )
            );

            $db->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                            SELECT r.role_parent_id, $ruleId
                            FROM acl_roles as r
                            WHERE r.role_type IN ('superadmin');"
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

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id = ?', 'manage-default-mail-servers')
            );

        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }
}