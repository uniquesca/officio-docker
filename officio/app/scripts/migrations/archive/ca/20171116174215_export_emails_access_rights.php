<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ExportEmailsAccessRights extends AbstractMigration
{
    public function up()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1370, 'superadmin', 'manage-company', 'get-export-email-users');");
            $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1370, 'superadmin', 'manage-company', 'get-export-email-accounts');");
            $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1370, 'superadmin', 'manage-company', 'get-export-email-accounts-folders');");
            $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1370, 'superadmin', 'manage-company', 'export-emails');");

            $db->commit();

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
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

            $db->beginTransaction();

            $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1370 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='export-emails';");
            $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1370 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='get-export-email-accounts';");
            $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1370 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='get-export-email-accounts-folders';");
            $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1370 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='get-export-email-users';");

            $db->commit();

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}