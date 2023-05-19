<?php

use Phinx\Migration\AbstractMigration;

class UpdatePackages extends AbstractMigration
{
    public function up()
    {
        // Took 52s on local server

        $this->execute("UPDATE `client_form_fields` SET `type`=30 WHERE `company_field_id` = 'categories';");

        $this->execute("ALTER TABLE `faq` ADD COLUMN `client_view` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `order`;");

        $this->execute("ALTER TABLE `client_types` ADD COLUMN `client_type_employer_sponsorship` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `client_type_needs_ia`;");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1042,'superadmin','manage-company','remove-company-logo',1);");
        $this->execute("UPDATE `acl_modules` SET `module_name`='Client Account' WHERE  `module_id`='trust-account';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account History' WHERE  `rule_id`=101;");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Import' WHERE  `rule_id`=102;");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Assign' WHERE  `rule_id`=103;");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Edit' WHERE  `rule_id`=104;");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account' WHERE  `rule_id`=110;");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Settings' WHERE  `rule_id`=105;");

        $this->execute("UPDATE `packages` SET `package_description`='This is an additional package. Client Login, Client Account and Accounting Sub tab.' WHERE  `package_id`=2;");

        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Client Account' WHERE  `package_detail_id`=28;");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Client Account History' WHERE  `package_detail_id`=29;");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Client Account Import' WHERE  `package_detail_id`=30;");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Client Account Assign' WHERE  `package_detail_id`=31;");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Client Account Edit' WHERE  `package_detail_id`=32;");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Client Account Settings' WHERE  `package_detail_id`=33;");

        $this->execute("ALTER TABLE `company_ta` CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL DEFAULT 'Client Account' AFTER `company_id`;");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
              (400, 5, 'applicants', 'Contacts', 'contacts-view', 0, 1, 30),
              (401, 400, 'applicants', 'Change/Save Profile', 'contacts-profile-edit', 0, 1, 31),
              (402, 400, 'applicants', 'Delete Contact', 'contacts-profile-delete', 0, 1, 32),
              (403, 400, 'applicants', 'New Contact', 'contacts-profile-new', 0, 1, 33);");


        $this->execute('DELETE FROM acl_rule_details WHERE `rule_id` IN (10, 11, 12, 13);');
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES
              (10, 'clients', 'index', 'get-sub-tab', 1),
              (10, 'applicants', 'index', '', 1),
              (10, 'applicants', 'search', '', 1),
              (10, 'applicants', 'profile', 'index', 1),
              (10, 'applicants', 'profile', 'check-employer-case', 1),
              (10, 'applicants', 'profile', 'load-employer-cases-list', 1),
              (10, 'applicants', 'profile', 'view-image', 1),
              (10, 'applicants', 'profile', 'get-login-info', 1),
              (10, 'applicants', 'profile', 'load', 1),
              (10, 'applicants', 'profile', 'load-short-info', 1),
              (11, 'applicants', 'profile', 'save', 1),
              (11, 'applicants', 'profile', 'update-login-info', 1),
              (11, 'applicants', 'profile', 'delete-image', 1),
              (12, 'applicants', 'profile', 'delete', 1),
              (13, 'applicants', 'profile', 'save', 1),
              (13, 'applicants', 'profile', 'update-login-info', 1),
            
              (400, 'applicants', 'index', '', 1),
              (400, 'applicants', 'search', '', 1),
              (400, 'applicants', 'profile', 'index', 1),
              (400, 'applicants', 'profile', 'check-employer-case', 1),
              (400, 'applicants', 'profile', 'load-employer-cases-list', 1),
              (400, 'applicants', 'profile', 'view-image', 1),
              (400, 'applicants', 'profile', 'get-login-info', 1),
              (400, 'applicants', 'profile', 'load', 1),
              (400, 'applicants', 'profile', 'load-short-info', 1),
              (401, 'applicants', 'profile', 'save', 1),
              (401, 'applicants', 'profile', 'update-login-info', 1),
              (401, 'applicants', 'profile', 'delete-image', 1),
              (402, 'applicants', 'profile', 'delete', 1),
              (403, 'applicants', 'profile', 'save', 1),
              (403, 'applicants', 'profile', 'update-login-info', 1);");


        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
            (1, 400, 'Manage Contacts', 1),
            (1, 401, 'Edit Contact', 1),
            (1, 402, 'Delete Contact', 1),
            (1, 403, 'New Contact', 1);");

        # Allow access to Contacts tab if there is access to Agents
        $this->execute('INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 400 FROM acl_role_access as a WHERE a.rule_id = 120;');
        $this->execute('INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 401 FROM acl_role_access as a WHERE a.rule_id = 120;');
        $this->execute('INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 402 FROM acl_role_access as a WHERE a.rule_id = 120;');
        $this->execute('INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 403 FROM acl_role_access as a WHERE a.rule_id = 120;');


        # Delete old Agents module, access, etc.
        $this->execute("DELETE FROM `acl_modules` WHERE  `module_id`='agents';");
        $this->execute('DELETE FROM `acl_role_access` WHERE `rule_id` IN (120);');
        $this->execute('DELETE FROM `acl_rule_details` WHERE `rule_id` IN (120);');
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'agents';");
        $this->execute('DELETE FROM `acl_rules` WHERE `rule_id` IN (120);');
        $this->execute('DELETE FROM `packages_details` WHERE `rule_id` IN (120);');

        $this->execute("UPDATE `acl_rules` SET `rule_description`='My Tasks' WHERE  `rule_id`=210;");
        $this->execute('UPDATE `acl_rules` SET `rule_order`=11 WHERE  `rule_id`=210;');

        $this->execute("UPDATE `packages_details` SET `package_detail_description`='My Tasks' WHERE  `package_detail_id`=59;");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
            (20, 10, 'clients', 'Employer Client Login', 'clients-employer-client-login', 0, 1, 6),
            (21, 10, 'clients', 'Individual Client Login', 'clients-individual-client-login', 0, 1, 6);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
              (20, 'applicants', 'index', 'index', 1),
              (21, 'applicants', 'index', 'index', 1);");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
            (2, 21, 'Individual Client Login', 1),
            (2, 20, 'Employer Client Login', 1);");

        $this->execute("ALTER TABLE `client_form_dependents` CHANGE COLUMN `relationship` `relationship` ENUM('parent','spouse','sister','brother','child','other') NOT NULL DEFAULT 'spouse' AFTER `member_id`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (11, 'applicants', 'profile', 'link-case-to-employer');");

        $this->execute("ALTER TABLE `company_details` CHANGE COLUMN `gst_type` `gst_type` ENUM('auto','exception','included','excluded') NULL DEFAULT 'auto' AFTER `gst`;");
        $this->execute("UPDATE `company_details` SET `gst_type` = 'excluded' WHERE `gst_type` = 'exception';");
        $this->execute("ALTER TABLE `company_details` CHANGE COLUMN `gst_type` `gst_type` ENUM('auto','included','excluded') NULL DEFAULT 'auto' AFTER `gst`;");

        $this->execute("ALTER TABLE `hst_companies` ADD COLUMN `is_system` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `tax_label`;");
        $this->execute("ALTER TABLE `hst_officio` ADD COLUMN `is_system` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `tax_label`;");
        $this->execute("ALTER TABLE `hst_companies` ADD COLUMN `province_order` TINYINT(3) NOT NULL DEFAULT '2' AFTER `is_system`;");
        $this->execute("ALTER TABLE `hst_officio` ADD COLUMN `province_order` TINYINT(3) NOT NULL DEFAULT '2' AFTER `is_system`;");
        $this->execute("ALTER TABLE `hst_companies` ADD COLUMN `tax_type` ENUM('exempt','included','excluded') NOT NULL DEFAULT 'excluded' AFTER `tax_label`;");
        $this->execute("ALTER TABLE `hst_officio` ADD COLUMN `tax_type` ENUM('exempt','included','excluded') NOT NULL DEFAULT 'excluded' AFTER `tax_label`;");

        /* TODO: CHECK!!! */
        $this->execute("SET @rownumber = 0;
            UPDATE hst_companies SET province_order = (@rownumber:=@rownumber+1) ORDER BY province ASC;
            INSERT INTO `hst_companies` (`province`, `rate`, `is_system`, `tax_type`, `province_order`) VALUES ('GST Exempt', 0, 'Y', 'exempt', 0);
            UPDATE `hst_companies` SET `province_id`=0 WHERE  `province`='GST Exempt';");

        $this->execute("SET @rownumber = 0;
            UPDATE hst_officio SET province_order = (@rownumber:=@rownumber+1) ORDER BY province ASC;
            INSERT INTO `hst_officio` (`province`, `rate`, `is_system`, `tax_type`, `province_order`) VALUES ('GST Exempt', 0, 'Y', 'exempt', 0);
            UPDATE `hst_officio` SET `province_id`=0 WHERE  `province`='GST Exempt';");
        /* CHECK!!! */


        $this->execute("ALTER TABLE `company` ADD COLUMN `company_abn` VARCHAR(255) NOT NULL DEFAULT '' AFTER `companyName`;");
        $this->execute("CREATE TABLE `members_password_retrievals` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `member_id` BIGINT(20) NOT NULL,
              `hash` VARCHAR(40) NOT NULL,
              `expiration` INT(11) NOT NULL COMMENT 'datetime when hash will be expired',
              PRIMARY KEY (`id`),
              INDEX `FK_passwd_retr_members` (`member_id`),
              CONSTRAINT `FK_passwd_retr_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB;");


        /* Update all already created IA/Employer/Contacts and assign role to them and remove from case */
        $this->execute('DELETE FROM members_roles WHERE member_id IN (SELECT m.member_id FROM members as m WHERE m.userType IN (3, 7, 8, 9, 10));');
        $this->execute("INSERT INTO members_roles (`member_id`, `role_id`) SELECT m.member_id, r.role_id FROM members as m LEFT JOIN acl_roles as r ON r.company_id = m.company_id AND r.role_name = 'Client' WHERE m.userType IN (7, 8, 10);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
            (1040, 'superadmin', 'manage-company', 'case-number-settings', 1),
            (1040, 'superadmin', 'manage-company', 'case-number-settings-save', 1);");


        $this->execute("ALTER TABLE `company_details` CHANGE COLUMN `client_file_number_settings` `case_number_settings` TEXT NULL COMMENT 'Case number generation settings' AFTER `employers_module_enabled`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (11, 'applicants', 'profile', 'generate-case-number', 1);");

        $this->execute('ALTER TABLE `company_default_options` ADD COLUMN `default_option_abbreviation` CHAR(255) NULL DEFAULT NULL AFTER `default_option_name`;');
        $this->execute('UPDATE `company_default_options` SET `default_option_abbreviation` = SUBSTRING(default_option_name, 1, 3);');
        $this->execute("UPDATE `company_default_options` SET `default_option_abbreviation`='HC' WHERE  `default_option_name`='H & C';");
        $this->execute('ALTER TABLE `company_prospects_noc` CHANGE COLUMN `noc_code` `noc_code` VARCHAR(6) NULL DEFAULT NULL FIRST;');
        $this->execute('ALTER TABLE `company_prospects_noc_job_titles` CHANGE COLUMN `noc_code` `noc_code` VARCHAR(6) NULL DEFAULT NULL FIRST;');
    }

    public function down()
    {
    }
}