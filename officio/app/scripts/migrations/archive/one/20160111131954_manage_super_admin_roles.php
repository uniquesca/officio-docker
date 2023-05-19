<?php

use Phinx\Migration\AbstractMigration;

class ManageSuperAdminRoles extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`) VALUES (2211, 4, 'superadmin', 'Manage Super Admin Roles', 'manage-superadmin-roles', 1, 1);");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`) VALUES (2212, 2211, 'superadmin', 'Add Super Admin Role', 'manage-superadmin-roles-add', 1, 1);");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`) VALUES (2213, 2211, 'superadmin', 'Edit Super Admin Role', 'manage-superadmin-roles-edit', 1, 1);");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`) VALUES (2214, 2211, 'superadmin', 'delete Super Admin Role', 'manage-superadmin-roles-delete', 1, 1);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2211, 'superadmin', 'roles', 'index');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2212, 'superadmin', 'roles', 'add');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2213, 'superadmin', 'roles', 'edit');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (2214, 'superadmin', 'roles', 'delete');");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 2211, 'Manage Super Admin Roles', 0);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 2212, 'Add New Super Admin Role', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 2213, 'Edit Super Admin Role', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 2214, 'Delete Super Admin Role', 1);");

        //For first main superadmin entrance (id = 1) when checkbox "Manage Superadmin Roles" haven't been checked yet
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('superadmin', 2211);");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('superadmin', 2212);");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('superadmin', 2213);");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('superadmin', 2214);");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_id` = 2211");
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_parent_id` = 2211");

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id` = 2211");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id` = 2212");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id` = 2213");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id` = 2214");

        $this->execute("DELETE FROM `packages_details` WHERE  `rule_id` = 2211");
        $this->execute("DELETE FROM `packages_details` WHERE  `rule_id` = 2212");
        $this->execute("DELETE FROM `packages_details` WHERE  `rule_id` = 2213");
        $this->execute("DELETE FROM `packages_details` WHERE  `rule_id` = 2214");

        $this->execute("DELETE FROM `acl_role_access` WHERE  `rule_id` = 2211 AND `role_id` = 'superadmin'");
        $this->execute("DELETE FROM `acl_role_access` WHERE  `rule_id` = 2212 AND `role_id` = 'superadmin'");
        $this->execute("DELETE FROM `acl_role_access` WHERE  `rule_id` = 2213 AND `role_id` = 'superadmin'");
        $this->execute("DELETE FROM `acl_role_access` WHERE  `rule_id` = 2214 AND `role_id` = 'superadmin'");
    }

}
