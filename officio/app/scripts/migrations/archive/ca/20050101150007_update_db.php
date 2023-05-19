<?php

use Officio\Migration\AbstractMigration;

class UpdateDb extends AbstractMigration
{
    public function up()
    {
        // Took 46.4887s on local server...
        $this->execute("ALTER TABLE `client_form_field_access` CHANGE COLUMN `role_id` `role_id` INT(11) NULL DEFAULT NULL AFTER `access_id`;");
        $this->execute("ALTER TABLE `client_form_field_access` ADD CONSTRAINT `FK_client_form_field_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `city_of_residence` VARCHAR(255) NULL DEFAULT NULL AFTER `country_of_citizenship`;");

        $this->execute("INSERT INTO `acl_rule_details` VALUES (10, 'clients', 'index', 'duplicate', 1);");

        $this->execute("INSERT INTO `acl_rules` VALUES (2204, 4, 'superadmin', 'Contacts', 'manage-company-contacts-types', 0, 'N', 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` VALUES (2204, 'superadmin', 'manage-company-contacts-types', '', 1);");

        $this->execute("INSERT INTO `acl_role_access`
        (`role_id`, `rule_id`)
        SELECT r.role_parent_id, 2204
        FROM `acl_roles` as r
        WHERE r.role_type = 'admin';");


        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
        (3,'calendar','','',1),
        (3,'clients','','',1),
        (3,'crm','','',1),
        (3,'documents','','',1),
        (3,'forms','','',1),
        (3,'help','','',1),
        (3,'links','','',1),
        (3,'mail','','',1),
        (3,'news','','',1),
        (3,'notes','','',1),
        (3,'prospects','','',1),
        (3,'qnr','','',1),
        (3,'signup','','',1),
        (3,'system','','',1),
        (3,'tasks','','',1),
        (3,'templates','','',1),
        (3,'trust-account','','',1),
        (3,'websites','','',1);");

        $this->execute("UPDATE `acl_rules` SET `rule_description`='Manage Help' WHERE  `rule_id`=1140;");

        $this->execute("CREATE TABLE `members_types` (
            `member_type_id` INT(2) UNSIGNED NOT NULL AUTO_INCREMENT,
            `member_type_name` VARCHAR(30) NOT NULL DEFAULT '',
            `member_type_case_template_name` VARCHAR(30) NOT NULL DEFAULT '',
            `member_type_visible` ENUM('Y','N') NOT NULL DEFAULT 'Y',
            PRIMARY KEY (`member_type_id`),
            INDEX `members_types` (`member_type_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("INSERT INTO `members_types` VALUES (1, 'superadmin', '', 'N');");
        $this->execute("INSERT INTO `members_types` VALUES (2, 'admin', '', 'N');");
        $this->execute("INSERT INTO `members_types` VALUES (3, 'case', '', 'N');");
        $this->execute("INSERT INTO `members_types` VALUES (4, 'user', '', 'N');");
        $this->execute("INSERT INTO `members_types` VALUES (5, 'agent', '', 'N');");
        $this->execute("INSERT INTO `members_types` VALUES (6, 'crm_user', '', 'N');");
        $this->execute("INSERT INTO `members_types` VALUES (7, 'employer', 'Employer Clients', 'Y');");
        $this->execute("INSERT INTO `members_types` VALUES (8, 'individual', 'Individual Clients', 'Y');");
        $this->execute("INSERT INTO `members_types` VALUES (9, 'contact', 'Contact', 'N');");


        $this->execute("ALTER TABLE `members` CHANGE COLUMN `userType` `userType` INT(2) UNSIGNED NOT NULL AFTER `company_id`;");
        $this->execute("ALTER TABLE `members` ADD CONSTRAINT `FK_members_members_types` FOREIGN KEY (`userType`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
        (1051, 4,  'superadmin','Manage Individuals fields/groups/layouts', 'manage-individuals-fields', 0,1,0),
        (1052, 4,  'superadmin','Manage Employers fields/groups/layouts', 'manage-employers-fields', 0,1,0);
        UPDATE `acl_rules` SET `rule_description`='Manage Cases fields/groups/layouts' WHERE  `rule_id`=1050;");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
        (1051,'superadmin','manage-applicant-fields-groups','individuals',1),
        (1051,'superadmin','manage-applicant-fields-groups','ajax',1),
        (1051,'superadmin','manage-applicant-fields-groups','delete-field',1),
        (1051,'superadmin','manage-applicant-fields-groups','delete-group',1),
        (1051,'superadmin','manage-applicant-fields-groups','edit-field',1),
        (1051,'superadmin','manage-applicant-fields-groups','get-field-info',1),
        
        (1052,'superadmin','manage-applicant-fields-groups','employers',1),
        (1052,'superadmin','manage-applicant-fields-groups','ajax',1),
        (1052,'superadmin','manage-applicant-fields-groups','delete-field',1),
        (1052,'superadmin','manage-applicant-fields-groups','delete-group',1),
        (1052,'superadmin','manage-applicant-fields-groups','edit-field',1),
        (1052,'superadmin','manage-applicant-fields-groups','get-field-info',1);");

        $this->execute("INSERT INTO `acl_role_access`
        (`role_id`, `rule_id`)
        SELECT r.role_parent_id, 1051
        FROM `acl_roles` as r
        WHERE r.role_type = 'admin';");

        $this->execute("INSERT INTO `acl_role_access`
        (`role_id`, `rule_id`)
        SELECT r.role_parent_id, 1052
        FROM `acl_roles` as r
        WHERE r.role_type = 'admin';");



        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
        (1, 1051, 'Manage Individuals fields/groups/layouts', 1),
        (1, 1052, 'Manage Employers fields/groups/layouts', 1);");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Manage Cases fields/groups/layouts' WHERE  `package_detail_id`=13;");


        $this->execute("DELETE FROM acl_rule_details WHERE rule_id IN (1051, 1052);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
        (1051,'superadmin','manage-applicant-fields-groups','index',1),
        (1051,'superadmin','manage-applicant-fields-groups','individuals',1),
        (1051,'superadmin','manage-applicant-fields-groups','add-block',1),
        (1051,'superadmin','manage-applicant-fields-groups','edit-block',1),
        (1051,'superadmin','manage-applicant-fields-groups','remove-block',1),
        (1051,'superadmin','manage-applicant-fields-groups','add-group',1),
        (1051,'superadmin','manage-applicant-fields-groups','edit-group',1),
        (1051,'superadmin','manage-applicant-fields-groups','delete-group',1),
        (1051,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
        (1051,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
        (1051,'superadmin','manage-applicant-fields-groups','get-field-info',1),
        (1051,'superadmin','manage-applicant-fields-groups','delete-field',1),
        (1051,'superadmin','manage-applicant-fields-groups','save-order',1),
        (1051,'superadmin','manage-applicant-fields-groups','edit-field',1),
        
        (1052,'superadmin','manage-applicant-fields-groups','index',1),
        (1052,'superadmin','manage-applicant-fields-groups','employers',1),
        (1052,'superadmin','manage-applicant-fields-groups','add-block',1),
        (1052,'superadmin','manage-applicant-fields-groups','edit-block',1),
        (1052,'superadmin','manage-applicant-fields-groups','remove-block',1),
        (1052,'superadmin','manage-applicant-fields-groups','add-group',1),
        (1052,'superadmin','manage-applicant-fields-groups','edit-group',1),
        (1052,'superadmin','manage-applicant-fields-groups','delete-group',1),
        (1052,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
        (1052,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
        (1052,'superadmin','manage-applicant-fields-groups','get-field-info',1),
        (1052,'superadmin','manage-applicant-fields-groups','delete-field',1),
        (1052,'superadmin','manage-applicant-fields-groups','save-order',1),
        (1052,'superadmin','manage-applicant-fields-groups','edit-field',1);");

        $this->execute("CREATE TABLE `client_types_kinds` (
            `client_type_id` INT(11) UNSIGNED NOT NULL,
            `member_type_id` INT(2) UNSIGNED NOT NULL,
            INDEX `FK_client_types_kinds_client_types` (`client_type_id`),
            CONSTRAINT `FK_client_types_kinds_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_client_types_kinds_client_types_2` FOREIGN KEY (`member_type_id`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        $this->execute("INSERT INTO `client_types_kinds` (client_type_id, member_type_id) SELECT client_type_id, 8 FROM client_types;");

        $this->execute("CREATE TABLE `applicant_types` (
            `applicant_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `member_type_id` INT(2) UNSIGNED NOT NULL,
            `company_id` BIGINT(20) NULL DEFAULT NULL,
            `applicant_type_name` VARCHAR(100) NULL DEFAULT NULL,
            `is_system` ENUM('Y','N') NOT NULL DEFAULT 'N',
            PRIMARY KEY (`applicant_type_id`),
            INDEX `FK_client_types_company` (`company_id`),
            CONSTRAINT `FK_applicant_types_1` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_types_2` FOREIGN KEY (`member_type_id`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `applicant_form_blocks` (
            `applicant_block_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `member_type_id` INT(2) UNSIGNED NOT NULL,
            `company_id` BIGINT(20) NOT NULL,
            `applicant_type_id` INT(11) UNSIGNED NULL,
            `contact_block` ENUM('Y','N') NOT NULL DEFAULT 'N',
            `repeatable` ENUM('Y','N') NOT NULL DEFAULT 'N',
            `order` INT(11) UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY (`applicant_block_id`),
          CONSTRAINT `FK_applicant_form_blocks_1` FOREIGN KEY (`member_type_id`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_blocks_2` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_blocks_3` FOREIGN KEY (`applicant_type_id`) REFERENCES `applicant_types` (`applicant_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `applicant_form_groups` (
            `applicant_group_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `applicant_block_id` INT(11) UNSIGNED NOT NULL,
            `company_id` BIGINT(20) NOT NULL,
            `title` VARCHAR(255) NULL DEFAULT NULL,
            `cols_count` INT(1) UNSIGNED NOT NULL DEFAULT 3,
            `collapsed` ENUM('Y','N') NOT NULL DEFAULT 'N',
            `order` INT(11) UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY (`applicant_group_id`),
          CONSTRAINT `FK_applicant_form_groups_1` FOREIGN KEY (`applicant_block_id`) REFERENCES `applicant_form_blocks` (`applicant_block_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_groups_2` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `applicant_form_fields` (
            `applicant_field_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `member_type_id` INT(2) UNSIGNED NOT NULL,
            `company_id` BIGINT(20) NOT NULL,
            `applicant_field_unique_id` VARCHAR(100) NULL DEFAULT NULL,
            `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields') NOT NULL DEFAULT 'text',
            `label` CHAR(255) NULL DEFAULT NULL,
            `maxlength` INT(6) UNSIGNED NULL DEFAULT NULL,
            `encrypted` ENUM('Y','N') NOT NULL DEFAULT 'N',
            `required` ENUM('Y','N') NOT NULL DEFAULT 'N',
            `disabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
            `blocked` ENUM('Y','N') NOT NULL DEFAULT 'N',
            PRIMARY KEY (`applicant_field_id`),
          CONSTRAINT `FK_applicant_form_fields_1` FOREIGN KEY (`member_type_id`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_fields_2` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `applicant_form_default` (
            `applicant_form_default_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `applicant_field_id` INT(11) UNSIGNED NULL DEFAULT NULL,
            `value` TEXT NULL,
            `order` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
            PRIMARY KEY (`applicant_form_default_id`),
            INDEX `FK_applicant_form_default_1` (`applicant_field_id`),
            CONSTRAINT `FK_applicant_form_default_1` FOREIGN KEY (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `applicant_form_order` (
          `applicant_group_id` INT(11) UNSIGNED NOT NULL,
          `applicant_field_id` INT(11) UNSIGNED NOT NULL,
          `use_full_row` ENUM('Y','N') NOT NULL DEFAULT 'N',
          `field_order` TINYINT(3) UNSIGNED DEFAULT 1,
          PRIMARY KEY  (`applicant_group_id`, `applicant_field_id`),
          CONSTRAINT `FK_applicant_form_order_1` FOREIGN KEY `FK_applicant_form_order_1` (`applicant_group_id`) REFERENCES `applicant_form_groups` (`applicant_group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_order_2` FOREIGN KEY `FK_applicant_form_order_2` (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `applicant_form_fields_access` (
          `role_id` INT(11) NOT NULL,
          `applicant_group_id` INT(11) UNSIGNED NOT NULL,
          `applicant_field_id` INT(11) UNSIGNED NOT NULL,
          `status` ENUM('R','F') NOT NULL DEFAULT 'R' COMMENT 'R=read only, F=full access',
          PRIMARY KEY  (`role_id`, `applicant_group_id`, `applicant_field_id`),
          CONSTRAINT `FK_applicant_form_fields_access_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_fields_access_2` FOREIGN KEY (`applicant_group_id`) REFERENCES `applicant_form_groups` (`applicant_group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_fields_access_3` FOREIGN KEY (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `applicant_form_data` (
          `applicant_id` BIGINT(20) NOT NULL,
          `applicant_field_id` INT(11) UNSIGNED NOT NULL,
          `value` text,
          `row` TINYINT(2) UNSIGNED NOT NULL,
          `row_id` VARCHAR(32) NULL,
          CONSTRAINT `FK_applicant_form_data_1` FOREIGN KEY (`applicant_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_applicant_form_data_2` FOREIGN KEY (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `members_relations` (
          `parent_member_id` BIGINT(20) NOT NULL,
          `child_member_id` BIGINT(20) NOT NULL,
          `applicant_group_id` INT(11) UNSIGNED NULL,
          `row` TINYINT(2) UNSIGNED NULL,
          INDEX `FK_members_relations` (`parent_member_id`, `child_member_id`),
          CONSTRAINT `FK_members_relations_1` FOREIGN KEY (`parent_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_members_relations_2` FOREIGN KEY (`child_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
          CONSTRAINT `FK_members_relations_3` FOREIGN KEY (`applicant_group_id`) REFERENCES `applicant_form_groups` (`applicant_group_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");



        $this->execute("INSERT INTO `acl_modules` (`module_id`, `module_name`) VALUES ('applicants', 'Applicants');");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
        (10,'applicants','index','',1),
        (10,'applicants','profile','',1),
        (10,'applicants','search','',1);");

        $this->execute("ALTER TABLE `client_types` ADD COLUMN `client_type_needs_ia` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `client_type_name`;");
        $this->execute("UPDATE `client_types` SET `client_type_needs_ia`='Y';");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
        (1053, 4,  'superadmin','Manage Contacts fields/groups/layouts', 'manage-contacts-fields', 0,1,0);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
        (1053,'superadmin','manage-applicant-fields-groups','index',1),
        (1053,'superadmin','manage-applicant-fields-groups','contacts',1),
        (1053,'superadmin','manage-applicant-fields-groups','applicant-types',1),
        (1053,'superadmin','manage-applicant-fields-groups','get-applicant-types',1),
        (1053,'superadmin','manage-applicant-fields-groups','add-applicant-type',1),
        (1053,'superadmin','manage-applicant-fields-groups','update-applicant-type',1),
        (1053,'superadmin','manage-applicant-fields-groups','delete-applicant-type',1),
        (1053,'superadmin','manage-applicant-fields-groups','add-block',1),
        (1053,'superadmin','manage-applicant-fields-groups','edit-block',1),
        (1053,'superadmin','manage-applicant-fields-groups','remove-block',1),
        (1053,'superadmin','manage-applicant-fields-groups','add-group',1),
        (1053,'superadmin','manage-applicant-fields-groups','edit-group',1),
        (1053,'superadmin','manage-applicant-fields-groups','delete-group',1),
        (1053,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
        (1053,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
        (1053,'superadmin','manage-applicant-fields-groups','get-field-info',1),
        (1053,'superadmin','manage-applicant-fields-groups','delete-field',1),
        (1053,'superadmin','manage-applicant-fields-groups','save-order',1),
        (1053,'superadmin','manage-applicant-fields-groups','edit-field',1);");

        $this->execute("INSERT INTO `acl_role_access`
        (`role_id`, `rule_id`)
        SELECT r.role_parent_id, 1053
        FROM `acl_roles` as r
        WHERE r.role_type = 'admin';");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1053, 'Manage Contacts fields/groups/layouts', 1);");

        $this->execute("UPDATE `members_types` SET `member_type_name` = 'internal_contact', `member_type_case_template_name`='Internal Contact' WHERE  `member_type_id`=9;");
        $this->execute("INSERT INTO `members_types` VALUES (10, 'contact', 'Contact', 'Y');");
    }

    public function down()
    {
    }
}