<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class FixSupportadminTemplatesAccess extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('1420', 'superadmin', 'manage-templates', 'create-invoice');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('1420', 'superadmin', 'manage-company', 'generate-invoice-template');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('1420', 'superadmin', 'manage-company', 'run-charge');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1420 AND `module_id`='superadmin' AND `resource_id`='manage-templates' AND `resource_privilege`='create-invoice';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1420 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='generate-invoice-template';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1420 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='run-charge';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}