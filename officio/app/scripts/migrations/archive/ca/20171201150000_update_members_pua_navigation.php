<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class UpdateMembersPuaNavigation extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES 
                    (1005, 4, 'superadmin', 'PUA Planning', 'pua-planning', 0, 'N', 1, 0)"
        );
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1005, 'PUA Planning', 1)");

        $booIsPUAEnabled = (bool)Zend_Registry::get('serviceManager')->get('config')['site_version']['pua_enabled'];
        if ($booIsPUAEnabled) {
            $this->execute('INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 1005 FROM `acl_role_access` as a WHERE a.rule_id = 4;');
        }

        $this->execute("UPDATE `acl_rule_details` SET rule_id = 1005 WHERE `resource_id` = 'manage-members-pua'");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1005, 'superadmin', 'manage-members-pua', 'index');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1005 AND `module_id`='superadmin' AND `resource_id`='manage-members-pua' AND `resource_privilege` = 'index';");
        $this->execute("UPDATE `acl_rule_details` SET rule_id = 1030 WHERE `resource_id` = 'manage-members-pua'");
        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id`=1005");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}