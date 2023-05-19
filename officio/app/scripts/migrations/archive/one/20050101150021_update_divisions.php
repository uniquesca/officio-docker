<?php

use Phinx\Migration\AbstractMigration;

class UpdateDivisions extends AbstractMigration
{
    public function up()
    {
        // Took 177.4671s on local server
        $this->execute('UPDATE `clients` SET `case_number_of_parent_client`=1;');

        $this->execute("ALTER TABLE `FormMap`
                CHANGE COLUMN `FromFamilyMemberId` `FromFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','other','parent1','parent2','parent3','parent4','parent5','parent6','parent7','parent8','parent9','parent10','sister1','sister2','sister3','sister4','sister5','sister6','sister7','sister8','sister9','sister10','brother1','brother2','brother3','brother4','brother5','brother6','brother7','brother8','brother9','brother10','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10') NULL DEFAULT 'main_applicant' AFTER `FormMapId`,
                CHANGE COLUMN `ToFamilyMemberId` `ToFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','other','parent1','parent2','parent3','parent4','parent5','parent6','parent7','parent8','parent9','parent10','sister1','sister2','sister3','sister4','sister5','sister6','sister7','sister8','sister9','sister10','brother1','brother2','brother3','brother4','brother5','brother6','brother7','brother8','brother9','brother10','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10') NULL DEFAULT 'main_applicant' AFTER `FromSynFieldId`,
                CHANGE COLUMN `ToProfileFamilyMemberId` `ToProfileFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','other','parent1','parent2','parent3','parent4','parent5','parent6','parent7','parent8','parent9','parent10','sister1','sister2','sister3','sister4','sister5','sister6','sister7','sister8','sister9','sister10','brother1','brother2','brother3','brother4','brother5','brother6','brother7','brother8','brother9','brother10','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10') NULL DEFAULT 'main_applicant' AFTER `ToSynFieldId`;");

        $this->execute("ALTER TABLE `automatic_reminders` CHANGE COLUMN `type` `type` ENUM('TRIGGER','CLIENT_PROFILE','PROFILE','FILESTATUS') NULL DEFAULT 'TRIGGER' AFTER `assign_to_member_id`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1, 'api', 'index', 'get-prices', 1);");
        $this->execute('INSERT INTO company_packages VALUES (0, 1), (0, 2), (0, 3), (0, 4);');

        $this->execute("UPDATE `acl_roles` SET `role_child_id`='guest' WHERE `role_child_id` IS NOT NULL;");

        $this->execute('DELETE FROM `automatic_reminders_processed` WHERE member_id NOT IN (SELECT member_id FROM members);');
        $this->execute('DELETE FROM automatic_reminders_processed WHERE automatic_reminder_id NOT IN (SELECT automatic_reminder_id FROM automatic_reminders);');
        $this->execute('ALTER TABLE `automatic_reminders_processed` ADD CONSTRAINT `FK_automatic_reminders_processed_automatic_reminders` FOREIGN KEY (`automatic_reminder_id`) REFERENCES `automatic_reminders` (`automatic_reminder_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute('ALTER TABLE `automatic_reminders_processed` CHANGE COLUMN `member_id` `member_id` BIGINT(20) NULL DEFAULT NULL AFTER `automatic_reminder_id`;');
        $this->execute('ALTER TABLE `automatic_reminders_processed` ADD CONSTRAINT `FK_automatic_reminders_processed_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (3, 'applicants', 'search', '', 1);");

        $this->execute('ALTER TABLE `prospects` ADD COLUMN `company_abn` VARCHAR(255) NULL DEFAULT NULL AFTER `company`;');
        $this->execute('ALTER TABLE `prospects` ADD COLUMN `extra_users` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `free_clients`;');

        $this->execute('ALTER TABLE `company_details` ADD COLUMN `extra_users` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `free_clients`;');

        $this->execute('ALTER TABLE `FormMap`
                ADD CONSTRAINT `FK_formmap_formsynfield` FOREIGN KEY (`FromSynFieldId`) REFERENCES `FormSynField` (`SynFieldId`) ON UPDATE CASCADE ON DELETE CASCADE,
                ADD CONSTRAINT `FK_formmap_formsynfield_2` FOREIGN KEY (`ToSynFieldId`) REFERENCES `FormSynField` (`SynFieldId`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute("ALTER TABLE `client_form_dependents` CHANGE COLUMN `relationship` `relationship` ENUM('parent','spouse','sister','brother','child','other','sibling') NOT NULL DEFAULT 'spouse' AFTER `member_id`;");
        $this->execute("UPDATE `client_form_dependents` SET `relationship`='sibling' WHERE `relationship`='sister' OR `relationship`='brother';");
        $this->execute("ALTER TABLE `client_form_dependents` CHANGE COLUMN `relationship` `relationship` ENUM('parent','spouse','sibling','child','other') NOT NULL DEFAULT 'spouse' AFTER `member_id`;");

        $this->execute("ALTER TABLE `FormMap`
                CHANGE COLUMN `FromFamilyMemberId` `FromFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') DEFAULT 'main_applicant' AFTER `FormMapId`,
                CHANGE COLUMN `ToFamilyMemberId` `ToFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') DEFAULT 'main_applicant' AFTER `FromSynFieldId`,
                CHANGE COLUMN `ToProfileFamilyMemberId` `ToProfileFamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') DEFAULT 'main_applicant' AFTER `ToSynFieldId`;");

        $this->execute('ALTER TABLE `users` ADD COLUMN `user_migration_number` VARCHAR(15) DEFAULT NULL AFTER `user_is_rma`;');
        $this->execute("ALTER TABLE `FormAssigned` CHANGE COLUMN `FamilyMemberId` `FamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other','other1','other2') NULL DEFAULT 'main_applicant' AFTER `ClientMemberId`;");
        $this->execute("UPDATE `FormAssigned` SET `FamilyMemberId`='other1' WHERE `FamilyMemberId`='other';");
        $this->execute("ALTER TABLE `FormAssigned` CHANGE COLUMN `FamilyMemberId` `FamilyMemberId` ENUM('main_applicant','sponsor','employer','spouse','parent1','parent2','parent3','parent4','sibling1','sibling2','sibling3','sibling4','sibling5','child1','child2','child3','child4','child5','child6','child7','child8','child9','child10','other1','other2') NULL DEFAULT 'main_applicant' AFTER `ClientMemberId`;");

        $this->execute("UPDATE client_form_fields SET company_field_id = 'passport_number' WHERE company_field_id = 'pasport_number';");
        $this->execute("UPDATE `FormMap` SET `ToProfileFieldId`='passport_number' WHERE `ToProfileFieldId`='pasport_number';");
        $this->execute("UPDATE templates SET message = REPLACE(message, 'pasport_number', 'passport_number');");
        $this->execute("UPDATE searches SET `columns` = REPLACE(`columns`, 'pasport_number', 'passport_number');");
        $this->execute('ALTER TABLE `company_prospects`
                ADD COLUMN `visa`INT(11) UNSIGNED NULL DEFAULT NULL AFTER `mp_prospect_expiration_date`,
                ADD CONSTRAINT `FK_company_prospects_company_default_options` FOREIGN KEY (`visa`) REFERENCES `company_default_options` (`default_option_id`) ON UPDATE SET NULL ON DELETE SET NULL;');

        $this->execute('ALTER TABLE `company_prospects_job` ADD COLUMN `qf_job_employer` VARCHAR(255) NULL DEFAULT NULL AFTER `qf_job_employment_type`;');
        $this->execute('ALTER TABLE `company_prospects_job` ADD COLUMN `qf_job_text_title` VARCHAR(255) NULL DEFAULT NULL AFTER `qf_job_employer`;');
        $this->execute('ALTER TABLE `company_prospects_job` ADD COLUMN `qf_job_country_of_employment` VARCHAR(255) NULL DEFAULT NULL AFTER `qf_job_text_title`;');
        $this->execute('ALTER TABLE `company_prospects_job` ADD COLUMN `qf_job_start_date` DATE NULL DEFAULT NULL AFTER `qf_job_country_of_employment`;');
        $this->execute('ALTER TABLE `company_prospects_job` ADD COLUMN `qf_job_end_date` DATE NULL DEFAULT NULL AFTER `qf_job_start_date`;');

        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  q_field_unique_id LIKE 'qf_job_%';");
        $this->execute("ALTER TABLE `company_questionnaires_fields` CHANGE COLUMN `q_field_type` `q_field_type` ENUM('textfield','textarea','combo','combo_custom','checkbox','radio','date','country','email','label','job','job_and_noc','money','number','percentage','age','file') NULL DEFAULT 'textfield' AFTER `q_section_id`;");
        $this->execute('ALTER TABLE `company_prospects` ADD COLUMN `notes` TEXT NULL DEFAULT NULL AFTER `visa`;');
        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES (220, 10, 'applicants', 'ABN/ACN Check', 'abn-check', 0, 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (220, 'applicants', 'index', 'open-link', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (2, 220, 'ABN/ACN Check', 1);");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1061, 1060, 'superadmin', 'Manage Landing Pages', 'manage-forms-view-landing-pages', 1, 'N', 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1061, 'superadmin', 'landing-pages', 'manage-landing-pages', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1061, 'Manage Landing Pages', 1);");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1061 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        $this->execute("INSERT INTO `acl_roles` (`role_id`, `company_id`, `role_name`, `role_type`, `role_parent_id`, `role_child_id`, `role_visible`, `role_status`, `role_regTime`) VALUES (NULL, 0, 'Support-CA', 'superadmin', 'supportadmin_ca', 'guest', 0, 1, 1394194654);");

        $this->execute("UPDATE `acl_rules` SET `rule_description`='View Roles Details', `rule_check_id`='admin-roles-view-details' WHERE  `rule_id`=1012;");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='View Users Details', `rule_check_id`='manage-members-view-details' WHERE  `rule_id`=1032;");
        $this->execute('UPDATE `acl_rules` SET `rule_order`=1 WHERE  `rule_id`=1042;');
        $this->execute('UPDATE `acl_rules` SET `rule_order`=2 WHERE  `rule_id`=1043;');
        $this->execute('UPDATE `acl_rules` SET `rule_order`=3 WHERE  `rule_id`=1044;');
        $this->execute('UPDATE `acl_rules` SET `rule_order`=4 WHERE  `rule_id`=1046;');
        $this->execute('UPDATE `acl_rules` SET `rule_order`=5 WHERE  `rule_id`=1047;');

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES
                (1034, 1032, 'superadmin', 'Edit Users', 'manage-members-edit', 0, 'N', 1, 0),
                (1035, 1032, 'superadmin', 'Change Users Password', 'manage-members-change-password', 0, 'N', 1, 0),
                (1231, 1230, 'superadmin', 'Generate Invoice Template', 'manage-invoices-generate-template', 1, 'N', 0, 0),
                (1232, 1230, 'superadmin', 'Run Charge', 'manage-invoices-run-charge', 1, 'N', 0, 0);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES
                (1031, 'superadmin', 'manage-members', 'check-is-user-exists', 1),
                (1034, 'superadmin', 'manage-members', 'edit-extra-details', 1),
                (1035, 'superadmin', 'manage-members', 'change-password', 1),
                (1040, 'superadmin', 'manage-company', 'get-company-details', 1),
                (1231, 'superadmin', 'manage-company', 'generate-invoice-template', 1),
                (1232, 'superadmin', 'manage-company', 'run-charge', 1);");

        $this->execute("UPDATE `packages_details` SET `package_detail_description`='View Roles Details' WHERE  `rule_id`=1012;");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='View Users Details' WHERE  `rule_id`=1032;");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
                (3, 1041, 'Add Company', 0),
                (1, 192, 'F.A.Q.', 1),
                (3, 300, 'Company Websites', 1),
                (3, 1020, 'Manage Super Admin Users', 0),
                (3, 1021, 'Add New Super Admin User', 0),
                (3, 1022, 'Edit Super Admin User', 0),
                (3, 1023, 'Delete Super Admin User', 0),
                (3, 1043, 'Delete Company', 0),
                (3, 1044, 'Manage Company As Admin', 0),
                (1, 1070, 'Announcements', 0),
                (3, 1100, 'Manage Templates', 0),
                (3, 1130, 'System', 1),
                (3, 1140, 'Manage Help', 0),
                (3, 1150, 'Manage Prospects', 0),
                (3, 1160, 'Signup', 1),
                (3, 1170, 'Mail Server Settings', 0),
                (3, 1180, 'Last Logged In Info', 0),
                (3, 1200, 'Manage GST/HST', 0),
                (3, 1210, 'Manage CMI', 0),
                (3, 1220, 'Trial users pricing', 0),
                (3, 1230, 'Manage PT Invoices', 0),
                (3, 1240, 'Manage pricing', 0),
                (3, 1250, 'Bad debts log', 0),
                (3, 1260, 'Automated billing log', 0),
                (3, 1270, 'Manage PT Error codes', 0),
                (3, 1280, 'Accounts', 0),
                (3, 1300, 'Statistics', 0),
                (3, 1320, 'Prospects Matching', 0),
                (3, 1330, 'Manage RSS feed', 0),
                (3, 1350, 'Advanced Search', 0),
                (3, 1380, 'Manage system variables', 0),
                (3, 1400, 'Company Website', 1),
                (3, 2000, 'CRM Manage', 0),
                (3, 2001, 'Manage Users', 0),
                (3, 2002, 'Define CRM users', 0),
                (3, 2003, 'Define CRM roles', 0),
                (3, 2010, 'Settings', 0),
                (3, 2011, 'Change own password', 1),
                (3, 2100, 'Companies', 1),
                (3, 2101, 'New company', 0),
                (3, 2102, 'Edit company', 0),
                (3, 2103, 'Delete company', 0),
                (3, 2200, 'Prospects', 0),
                (3, 2201, 'New prospect', 0),
                (3, 2202, 'Edit prospect', 0),
                (3, 2203, 'Delete prospect', 0),
                (3, 2204, 'Contacts', 1),
                (1, 1034, 'Edit Users', 1),
                (3, 1231, 'Generate Invoice Template', 0),
                (3, 1232, 'Run Charge', 0),
                (1, 1035, 'Change Users Password', 1);");

        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 33 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1034 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1044 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin');");
        $this->execute('INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 1034 FROM `acl_role_access` as a WHERE a.rule_id = 1032;');
        $this->execute('INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 1035 FROM `acl_role_access` as a WHERE a.rule_id = 1032;');

        $this->execute('DELETE FROM `packages_details` WHERE  `rule_id`=1231;');
        $this->execute('DELETE FROM `packages_details` WHERE  `rule_id`=1232;');
        $this->execute('DELETE FROM `acl_rules` WHERE `rule_id`=1231;');
        $this->execute('DELETE FROM `acl_rules` WHERE `rule_id`=1232;');

        /* TODO: REVIEW prices for CA */
        $this->execute("UPDATE `u_variable` SET `name`='price_lite_license_user_monthly' WHERE `name`='price_license_user_monthly';");
        $this->execute("UPDATE `u_variable` SET `name`='price_lite_license_user_annual' WHERE `name`='price_license_user_annual';");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_license_user_monthly', '69.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_license_user_annual', '699.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_license_user_monthly', '99.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_license_user_annual', '999.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_training', '125.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('free_storage_lite', '2');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('free_storage_pro', '10');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('free_storage_ultimate', '50');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('user_included_lite', '1');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('user_included_pro', '1');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('user_included_ultimate', '1');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_package_monthly', '69.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_package_yearly', '699.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_package_2_years', '1275.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_package_monthly', '99.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_package_yearly', '999.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_package_2_years', '1799.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_package_monthly', '129.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_package_yearly', '1299.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_package_2_years', '2380.00');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('users_add_over_limit_lite', '0');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('users_add_over_limit_pro', '1');");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('users_add_over_limit_ultimate', '1');");

        $this->execute("CREATE TABLE `tickets` (
            `ticket_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `company_id` BIGINT(20) NULL DEFAULT NULL,
            `company_member_id` BIGINT(20) NULL DEFAULT NULL,
            `author_id` BIGINT(20) NULL DEFAULT NULL,
            `ticket` TEXT NULL,
            `contacted_by` ENUM('phone','email','chat','system') NOT NULL DEFAULT 'phone',
            `status` ENUM('resolved','not_resolved') NOT NULL DEFAULT 'not_resolved',
            `create_date` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`ticket_id`),
            INDEX `FK_tickets_author` (`author_id`),
            INDEX `FK_tickets_company_member` (`company_member_id`),
            INDEX `FK_tickets_company` (`company_id`),
            CONSTRAINT `FK_tickets_company_member` FOREIGN KEY (`company_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_tickets_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_tickets_author` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES
            (1440, 1040, 'superadmin', 'Manage Company Tickets', 'manage-company-tickets', 1, 'N', 1, 0),
            (1441, 1440, 'superadmin', 'Add Company Tickets', 'manage-company-tickets-add', 1, 'N', 1, 0),
            (1442, 1440, 'superadmin', 'Edit Company Tickets', 'manage-company-tickets-edit', 1, 'N', 1, 0),
            (1443, 1440, 'superadmin', 'Delete Company Tickets', 'manage-company-tickets-delete', 1, 'N', 1, 0);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES
            (1440, 'superadmin', 'tickets', 'get-tickets', 1),
            (1441, 'superadmin', 'tickets', 'add', 1),
            (1442, 'superadmin', 'tickets', 'edit', 1),
            (1442, 'superadmin', 'tickets', 'get-ticket', 1),
            (1443, 'superadmin', 'tickets', 'delete', 1);");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
          (4, 1440, 'Manage Company Tickets', 1),
            (4, 1441, 'Add Company Tickets', 1),
            (4, 1442, 'Edit Company Tickets', 1),
            (4, 1443, 'Delete Company Tickets', 1);");

        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1440 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1441 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1442 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        $this->execute("CREATE TABLE `company_ta_divisions` (
            `company_ta_id` INT(11) NOT NULL,
            `division_id` INT(11) UNSIGNED NOT NULL,
            INDEX `FK_u_trust_account_divisions_divisions` (`division_id`),
            INDEX `FK_company_ta_divisions_company_ta` (`company_ta_id`),
            CONSTRAINT `FK_company_ta_divisions_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_u_trust_account_divisions_divisions` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;");

        $booIsCheckABNEnabled = (bool)Zend_Registry::get('serviceManager')->get('config')['site_version']['check_abn_enabled'];
        if ($booIsCheckABNEnabled) {
            $this->execute("INSERT INTO `acl_role_access`
                (`role_id`, `rule_id`)
                SELECT r.role_parent_id, 220
                FROM `acl_roles` as r
                WHERE r.role_type IN ('user', 'admin', 'employer_client', 'individual_client');
            ");
        }
    }

    public function down()
    {
    }
}