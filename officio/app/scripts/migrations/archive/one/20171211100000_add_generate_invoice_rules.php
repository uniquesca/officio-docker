<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddGenerateInvoiceRules extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` VALUES (1230, 'superadmin', 'manage-company', 'run-charge', 1);");
        $this->execute("INSERT INTO `acl_rule_details` VALUES (1230, 'superadmin', 'manage-company', 'generate-invoice-template', 1);");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1230 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='generate-invoice-template';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1230 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='run-charge';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}