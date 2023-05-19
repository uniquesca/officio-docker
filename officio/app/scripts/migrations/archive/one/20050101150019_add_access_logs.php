<?php

use Phinx\Migration\AbstractMigration;

class AddAccessLogs extends AbstractMigration
{
    public function up()
    {
        // Took 19s on local server
        $this->execute("UPDATE `acl_roles` SET `role_name`='Individual Client', `role_type`='individual_client' WHERE  `role_type`='client';");
        $this->execute("INSERT INTO `acl_roles` (`role_id`, `company_id`, `role_name`, `role_type`, `role_parent_id`, `role_child_id`, `role_visible`, `role_regTime`) 
            SELECT NULL, c.company_id, 'Employer Client', 'employer_client', CONCAT('employer_client_company_', c.company_id), 'guest', 1, UNIX_TIMESTAMP()
            FROM `company` as c;");

        /* Access Logs */
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`, `rule_order`) VALUES
              (1410, 4, 'superadmin', 'Access Logs', 'access-logs-view', 0, 1, 0),
              (1411, 1410, 'superadmin', 'Delete Access Logs', 'access-logs-delete', 0, 1, 0);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES
              (1410, 'superadmin', 'access-logs', 'index', 1),
              (1410, 'superadmin', 'access-logs', 'list', 1),
              (1411, 'superadmin', 'access-logs', 'delete', 1);");

        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1410 FROM `acl_roles` as r WHERE r.role_type IN ('admin');");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1411 FROM `acl_roles` as r WHERE r.role_type IN ('admin');");


        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1410, 'Access Logs', 1), (1, 1411, 'Delete Access Logs', 1);");
        $this->execute("INSERT INTO `system_templates` VALUES (0, 'system', 'Password Changed', 'Officio - Password Changed', 'support@uniques.ca', '{user: email}', '', '', '<font face=\"arial\" size=\"2\">Dear {user: first name},</font>\n<div>Your password was changed.</div>', NOW());");
        $this->execute("INSERT INTO `system_templates` VALUES (0, 'system', 'User account locked', 'Officio - User account locked', 'support@uniques.ca', 'support@uniques.ca', '', '', '<div>User account locked: {user: username}.</div>', NOW());");

        $this->execute('ALTER TABLE `members` ADD COLUMN `login_temporary_disabled_on` DATETIME NULL DEFAULT NULL AFTER `login_enabled`;');
        $this->execute("CREATE TABLE `members_last_passwords` (
                `member_id` BIGINT(20) NOT NULL,
                `password` VARCHAR(200) NOT NULL,
                `timestamp` INT(11) UNSIGNED NOT NULL,
                INDEX `FK_members_last_passwords_members` (`member_id`),
                CONSTRAINT `FK_members_last_passwords_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB;");

        $this->execute('ALTER TABLE `members` ADD COLUMN `password_change_date` INT(11) NULL AFTER `disabled_timestamp`;');

        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `encrypted` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `maxlength`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (10, 'applicants', 'profile', 'change-my-password', 1);");
        $this->execute('ALTER TABLE `members_roles` DROP COLUMN `members_roles_id`;');
        $this->execute('ALTER TABLE `acl_roles` DROP COLUMN `role_moderatorEmail`;');
        $this->execute('ALTER TABLE `folder_access` DROP COLUMN `folder_access_id`;');

        $this->execute('ALTER TABLE `clients`
                ADD COLUMN `case_number_of_parent_client` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `fileNumber`,
                ADD COLUMN `case_number_in_company` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `case_number_of_parent_client`;');
    }

    public function down()
    {
    }
}