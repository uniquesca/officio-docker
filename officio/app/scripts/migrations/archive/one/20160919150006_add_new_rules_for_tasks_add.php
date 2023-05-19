<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddNewRulesForTasksAdd extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (2241, 25, 'tasks', 'Add Tasks', 'clients-tasks-add', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2241, 'tasks', 'index', 'add');");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (2242, 210, 'tasks', 'Add Tasks', 'tasks-add', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2242, 'tasks', 'index', 'add');");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (2243, 201, 'tasks', 'Add Tasks', 'prospects-tasks-add', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2243, 'tasks', 'index', 'add');");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 2241, 'Add Clients Tasks', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 2242, 'Add Tasks', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 2243, 'Add Prospects Tasks', 1);");

        $this->execute("UPDATE `acl_rules` SET `rule_order`='1' WHERE  `rule_id`=2215;");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='1' WHERE  `rule_id`=2216;");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='1' WHERE  `rule_id`=2217;");


        $this->execute("INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 2241
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 25;");

        $this->execute("INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 2242
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 210;");

        $this->execute("INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 2243
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 201;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id` IN (2241, 2242, 2243);");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='0' WHERE  `rule_id`=2215;");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='0' WHERE  `rule_id`=2216;");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='0' WHERE  `rule_id`=2217;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}