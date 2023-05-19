<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddNewSuperadminRole extends AbstractMigration
{
    public function up()
    {
        $this->execute("DROP TABLE IF EXISTS `admin_navigation`;");
        $this->execute("DROP TABLE IF EXISTS `admin_navigation_sections`;");

        $this->execute("ALTER TABLE `packages_details`
        	ADD CONSTRAINT `FK_packages_details_packages` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_packages_details_acl_rules` FOREIGN KEY (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `acl_rule_details` DROP FOREIGN KEY `FK_acl_rule_details_1`;");
        $this->execute("ALTER TABLE `acl_rule_details` ADD CONSTRAINT `FK_acl_rule_details_1` FOREIGN KEY (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `users` DROP FOREIGN KEY `FK_users_members`");
        $this->execute("ALTER TABLE `users` ADD CONSTRAINT `FK_users_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `acl_role_access` DROP FOREIGN KEY `FK_acl_role_access_1`;");
        $this->execute("ALTER TABLE `acl_role_access` ADD CONSTRAINT `FK_acl_role_access_1` FOREIGN KEY (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("DELETE FROM acl_role_access WHERE role_id NOT IN (SELECT role_parent_id FROM acl_roles);");
        $this->execute("ALTER TABLE `acl_role_access` ADD CONSTRAINT `FK_acl_role_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_parent_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("UPDATE `acl_rules` SET `rule_id`=1380 WHERE `rule_id`=1370;");
        $this->execute("UPDATE `acl_rules` SET `rule_id`=1370, `rule_parent_id`=1040 WHERE `rule_id`=1360;");
        $this->execute("UPDATE `acl_rules` SET `rule_visible`=1, `rule_parent_id`=1040 WHERE `rule_id`=1350;");
        $this->execute("UPDATE `acl_rules` SET `rule_parent_id`=4 WHERE  `rule_id`=1080;");
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='' WHERE `rule_id`=1070 AND `module_id`='superadmin' AND `resource_id`='news' AND `resource_privilege`='index';");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1360, 1040, 'superadmin', 'Quick Search', 'run-companies-search', 1, 'N', 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1360, 'superadmin', 'manage-company', 'company-search', 1)");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 1360, 'Quick Search', 0)");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1360 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1390, 4, 'superadmin', 'View Superadmin Tab', 'admin-tab-view', 1, 'N', 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1390, 'superadmin', 'index', 'index', 1)");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 1390, 'View Superadmin Tab', 0)");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1390 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES (1046, 1040, 'superadmin','Change Company Status','manage-company-change-status',1,1,4);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1046,'superadmin','manage-company','update-status',1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 1046, 'Change Company Status', 0);");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 1046 FROM `acl_role_access` as a WHERE a.rule_id = 1040;");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1047, 1040, 'superadmin','View Companies List','manage-company-view-companies',1,'N',1,5)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1047,'superadmin','manage-company','get-companies',1)");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 1047, 'View Companies List', 0)");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1047 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1048, 1040, 'superadmin', 'Manage Company Email', 'manage-company-email', 1, 'N', 1, 0)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1048, 'superadmin', 'manage-company', 'email', 1)");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1048, 'Company Email', 0)");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1048 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1049, 1042, 'superadmin', 'Edit Company Extra Details', 'edit-company-extra-details', 0, 'N', 1, 0)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1049, 'superadmin', 'manage-company', 'index', 1)");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1049, 'Edit Company Extra Details', 1)");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 1049 FROM `acl_role_access` as a WHERE a.rule_id = 1042;");

        $this->execute("UPDATE `acl_rules` SET `rule_description`='View Roles Details', `rule_check_id`='admin-roles-view-details' WHERE  `rule_id`=1012;");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='View Roles Details' WHERE  `rule_id`=1012;");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1014, 1012, 'superadmin', 'Edit Roles ', 'admin-roles-edit', 0, 'N', 1, 0)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1014, 'superadmin', 'roles', 'edit-extra-details', 1)");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1014, 'Edit Roles', 1)");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1014 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin');");

        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id`=1045;");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1420, 1040, 'superadmin', 'Manage Company Packages', 'manage-company-packages', 1, 'N', 1, 4), (1421, 1420, 'superadmin', 'Manage Company Packages Extra Details', 'manage-company-packages-extra-details', 1, 'N', 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1420, 'superadmin', 'manage-company', 'save-packages', 1), (1420, 'superadmin', 'manage-company-packages', 'index', 1), (1421, 'superadmin', 'manage-company-packages', 'edit-extra-details', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 1420, 'Manage Company Packages', 0), (3, 1421, 'Manage Company Packages Extra Details', 0);");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1420 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1421 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->insert('acl_rules', array(
            'rule_parent_id'   => '1040',
            'module_id'        => 'superadmin',
            'rule_description' => 'Define Client File Number Settings',
            'rule_check_id'    => 'manage-client-file-number-settings',
            'rule_visible'     => '1',
        ));
        $id = $db->lastInsertId('acl_rules');

        $this->execute("UPDATE `acl_rule_details` SET `rule_id`='$id' WHERE `resource_privilege`='client-file-number-settings';");
        $this->execute("UPDATE `acl_rule_details` SET `rule_id`='$id' WHERE `resource_privilege`='client-file-number-settings-save';");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, $id, 'Define Client File Number Settings', 1);");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, $id FROM acl_role_access WHERE `rule_id` = '1040';");


        $this->execute("INSERT INTO `acl_roles` (`role_id`, `role_name`, `role_type`, `role_parent_id`, `role_child_id`, `role_regTime`) VALUES (NULL, 'Support Admin', 'superadmin', 'supportadmin', 'guest', UNIX_TIMESTAMP());");

        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) VALUES 
            ('supportadmin', 4),
            ('supportadmin', 150),
            ('supportadmin', 160),
            ('supportadmin', 210),
            ('supportadmin', 1040),
            ('supportadmin', 1042),
            ('supportadmin', 1046),
            ('supportadmin', 1047),
            ('supportadmin', 1360),
            ('supportadmin', 1420);"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_roles` WHERE `role_parent_id` = 'supportadmin';");

        $this->execute("UPDATE `acl_rule_details` SET `rule_id`='1040' WHERE `resource_privilege`='client-file-number-settings';");
        $this->execute("UPDATE `acl_rule_details` SET `rule_id`='1040' WHERE `resource_privilege`='client-file-number-settings-save';");
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_check_id` = 'manage-client-file-number-settings';");
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='index' WHERE `rule_id`=1070 AND `module_id`='superadmin' AND `resource_id`='news' AND `resource_privilege`='';");

        $this->execute("DELETE FROM `acl_rules` WHERE rule_id IN (1360, 1390, 1046, 1047, 1048, 1049, 1421, 1420)");
        $this->execute("UPDATE `acl_rules` SET `rule_id`=1350 WHERE `rule_id`=1340;");
        $this->execute("UPDATE `acl_rules` SET `rule_id`=1360 WHERE `rule_id`=1370;");
        $this->execute("UPDATE `acl_rules` SET `rule_id`=1370 WHERE `rule_id`=1380;");

        $this->execute("ALTER TABLE `packages_details`
        	DROP FOREIGN KEY `FK_packages_details_packages`,
        	DROP FOREIGN KEY `FK_packages_details_acl_rules`;");

        $this->execute("ALTER TABLE `acl_rule_details` DROP FOREIGN KEY `FK_acl_rule_details_1`;");
        $this->execute("ALTER TABLE `acl_rule_details` ADD CONSTRAINT `FK_acl_rule_details_1` FOREIGN KEY (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;");

        $this->execute("ALTER TABLE `users` DROP FOREIGN KEY `FK_users_members`;");
        $this->execute("ALTER TABLE `users` ADD CONSTRAINT `FK_users_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;");

        $this->execute("ALTER TABLE `acl_role_access` DROP FOREIGN KEY `FK_acl_role_access_1`;");
        $this->execute("ALTER TABLE `acl_role_access` ADD CONSTRAINT `FK_acl_role_access_1` FOREIGN KEY (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;");
        $this->execute("ALTER TABLE `acl_role_access` DROP FOREIGN KEY `FK_acl_role_access_acl_roles`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}