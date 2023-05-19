<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddNewRulesForTasksDelete extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (2215, 25, 'tasks', 'Delete Tasks', 'clients-tasks-delete', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2215, 'tasks', 'index', 'delete');");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (2216, 210, 'tasks', 'Delete Tasks', 'tasks-delete', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2216, 'tasks', 'index', 'delete');");

        $this->execute(
            "INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (201, 200, 'tasks', 'Tasks', 'prospects-tasks-view', 0, 'N', 1, 21);"
        );
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (2217, 201, 'tasks', 'Delete Tasks', 'prospects-tasks-delete', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (201, 'tasks', 'index', '');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2217, 'tasks', 'index', 'delete');");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 2215, 'Delete Clients Tasks', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 2216, 'Delete Tasks', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 201, 'Prospects Tasks', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 2217, 'Delete Prospects Tasks', 1);");


        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 2215
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 25 AND r.`role_type` = 'admin';"
        );

        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 2216
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 210 AND r.`role_type` = 'admin';"
        );

        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 201
        FROM acl_role_access AS a
        WHERE a.rule_id = 200;"
        );

        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 2217
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 201 AND r.`role_type` = 'admin';"
        );

        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, 2216
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 210 AND r.`role_type` = 'superadmin'"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id` = 2215;");
        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id` = 2216;");
        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id` = 2217;");
        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id` = 201;");
    }
}