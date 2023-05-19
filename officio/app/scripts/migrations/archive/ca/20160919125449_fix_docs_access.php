<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class FixDocsAccess extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rules` SET `module_id`='documents' WHERE  `rule_id`=100;");
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='documents' WHERE  `rule_id`=100 AND `module_id`='documents123' AND `resource_id`='index' AND `resource_privilege`='';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='documents123' WHERE  `rule_id`=100 AND `module_id`='documents' AND `resource_id`='index' AND `resource_privilege`='';");
        $this->execute("UPDATE `acl_rules` SET `module_id`='documents123' WHERE  `rule_id`=100;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}