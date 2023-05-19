<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddManageOfficesRules extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1040, 'superadmin', 'manage-offices', '');");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1040 AND `module_id`='superadmin' AND `resource_id`='manage-own-company' AND `resource_privilege`='office';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1040 AND `module_id`='superadmin' AND `resource_id`='manage-offices' AND `resource_privilege`='';");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1040, 'superadmin', 'manage-own-company', 'office');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}