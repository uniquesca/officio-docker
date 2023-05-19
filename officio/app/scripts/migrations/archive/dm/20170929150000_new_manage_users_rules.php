<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class NewManageUsersRules extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('1030', 'superadmin', 'manage-members', 'list');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('1034', 'superadmin', 'manage-members', 'change-status');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1030 AND `module_id`='superadmin' AND `resource_id`='manage-members' AND `resource_privilege`='list';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1034 AND `module_id`='superadmin' AND `resource_id`='manage-members' AND `resource_privilege`='change-status';");
    }
}
