<?php

use Phinx\Migration\AbstractMigration;

class UpdateMuchMoreKeys extends AbstractMigration
{
    public function up()
    {
        // Took 650s on local server
        $this->execute('DELETE FROM `packages_details` WHERE `rule_id`=1443;');
        $this->execute('DELETE FROM `acl_rule_details` WHERE `rule_id`=1443;');
        $this->execute('DELETE FROM `acl_rules` WHERE `rule_id`=1443;');

        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Manage Company Tickets Status' WHERE `rule_id`=1442;");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Manage Company Tickets Status', `rule_check_id`='manage-company-tickets-status' WHERE `rule_id`=1442;");
        $this->execute("UPDATE `acl_rule_details` SET `rule_id`=1440 WHERE  `rule_id`=1442 AND `module_id`='superadmin' AND `resource_id`='tickets' AND `resource_privilege`='get-ticket';");
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='change-status' WHERE  `rule_id`=1442 AND `module_id`='superadmin' AND `resource_id`='tickets' AND `resource_privilege`='edit';");

        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1360 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES (1311, 4, 'superadmin', 'Import Client Notes', 'import-client-notes-view', 0, 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1311, 'superadmin', 'import-client-notes', '', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1311, 'Import Client Notes', 1);");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1311 FROM `acl_roles` as r WHERE r.role_type IN ('admin');");

        $this->execute("CREATE TABLE `letterheads` (
                    `letterhead_id` INT(11) NOT NULL AUTO_INCREMENT,
                    `company_id` BIGINT(20) NOT NULL,
                    `name` VARCHAR(50) NULL DEFAULT NULL,
                    `create_date` DATE NULL DEFAULT NULL,
                    `type` ENUM('a4','letter') NULL DEFAULT NULL,
                    `created_by` BIGINT(20) NOT NULL,
                    `same_subsequent` INT(1) NULL DEFAULT '1',
                    PRIMARY KEY (`letterhead_id`),
                    INDEX `FK_company_id` (`company_id`),
                    INDEX `FK_created_by` (`created_by`),
                    CONSTRAINT `FK_created_by` FOREIGN KEY (`created_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT `FK_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;");


        $this->execute("CREATE TABLE `letterheads_files` (
                    `letterhead_file_id` INT(11) NOT NULL AUTO_INCREMENT,
                    `letterhead_id` INT(11) NOT NULL,
                    `file_name` VARCHAR(50) NOT NULL,
                    `size` VARCHAR(45) NULL DEFAULT NULL,
                    `margin_left` INT(11) NULL DEFAULT NULL,
                    `margin_top` INT(11) NULL DEFAULT NULL,
                    `margin_right` INT(11) NULL DEFAULT NULL,
                    `margin_bottom` INT(11) NULL DEFAULT NULL,
                    `number` INT(11) NULL DEFAULT NULL,
                    PRIMARY KEY (`letterhead_file_id`),
                    INDEX `FK_letterhead_id` (`letterhead_id`),
                    CONSTRAINT `FK_letterhead_id` FOREIGN KEY (`letterhead_id`) REFERENCES `letterheads` (`letterhead_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
                (2210, 4, 'superadmin', 'Letterheads', 'manage-letterheads', 0, 1, 0),
                (106, 100, 'documents', 'New Letter on Letterhead', 'new-letter-on-letterhead', 0, 1, 0);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
                (2210, 'superadmin', 'letterheads', '', 1),
                (106, 'documents', 'index', 'get-letterheads-list', 1);");

        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 2210 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 106 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin');");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
                (1, 2210, 'Letterheads', 1),
                (1, 106, 'New Letter on Letterhead', 1);");

        $this->execute("ALTER TABLE `templates` ADD COLUMN `templates_type` ENUM('Email','Letter') NULL DEFAULT 'Email' AFTER `templates_for`;");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES
                (1, 'templates', 'index', 'get-file', '1'),
                (1, 'templates', 'index', 'save-letter-template-file', '1');");


        $this->execute('ALTER TABLE `automatic_reminders` ADD COLUMN `template_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `company_id`, ADD CONSTRAINT `FK_automatic_reminders_template` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`) ON UPDATE SET NULL ON DELETE SET NULL;');
        $this->execute('ALTER TABLE `u_tasks` ADD COLUMN `from` CHAR(255) NULL DEFAULT NULL AFTER `auto_task_type`;');
        $this->execute("ALTER TABLE `u_tasks_messages` ADD COLUMN `from_template` TINYINT(1) NOT NULL DEFAULT '0' AFTER `officio_said`;");
        $this->execute('UPDATE `acl_rules` SET `superadmin_only`=0 WHERE `rule_id`=1044;');

        $this->execute("UPDATE `acl_rules` SET `rule_description`='Login as User/Admin' WHERE  `rule_id`=1044;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1, 'api', 'gv', '', 1);");

        $this->execute('ALTER TABLE `client_types` ADD COLUMN `form_version_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `company_id`;');
        $this->execute('ALTER TABLE `client_types` ADD CONSTRAINT `FK_client_types_FormVersion` FOREIGN KEY (`form_version_id`) REFERENCES `FormVersion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute("CREATE TABLE `eml_sample_server_settings` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(255) NULL DEFAULT NULL,
                    `type` ENUM('pop3','imap','smtp') NOT NULL DEFAULT 'pop3',
                    `host` VARCHAR(255) NULL DEFAULT NULL,
                    `port` VARCHAR(6) NULL DEFAULT NULL,
                    `ssl` ENUM('','ssl','tls') NULL DEFAULT '',
                    PRIMARY KEY (`id`)
                ) ENGINE=INNODB DEFAULT CHARSET=utf8;");

        $this->execute("INSERT INTO `eml_sample_server_settings` (`name`, `type`, `host`, `port`, `ssl`) VALUES
                    ('Google', 'pop3', 'pop.gmail.com', '995', 'ssl'),
                    ('Google', 'imap', 'imap.gmail.com', '993', 'ssl'),
                    ('Google SSL required', 'smtp', 'smtp.gmail.com', '465', 'ssl'),
                    ('Google TLS required', 'smtp', 'smtp.gmail.com', '587', 'tls'),
                
                    ('Yahoo', 'pop3', 'pop.mail.yahoo.com', '995', 'ssl'),
                    ('Yahoo', 'imap', 'imap.mail.yahoo.com', '993', 'ssl'),
                  ('Yahoo SSL required', 'smtp', 'smtp.mail.yahoo.com', '465', 'ssl'),
                  ('Yahoo TLS required', 'smtp', 'smtp.mail.yahoo.com', '587', 'tls'),
                
                    ('Yahoo Mail Plus', 'pop3', 'plus.pop.mail.yahoo.com', '995', 'ssl'),
                    ('Yahoo Mail Plus', 'imap', 'plus.imap.mail.yahoo.com', '993', 'ssl'),
                  ('Yahoo Mail Plus', 'smtp', 'plus.smtp.mail.yahoo.com', '465', 'ssl'),
                
                    ('Yahoo Mail UK', 'pop3', 'pop.mail.yahoo.co.uk', '995', 'ssl'),
                    ('Yahoo Mail UK', 'imap', 'imap.mail.yahoo.co.uk', '993', 'ssl'),
                  ('Yahoo Mail UK', 'smtp', 'smtp.mail.yahoo.co.uk', '465', 'ssl'),
                
                    ('Yahoo Mail AU/NZ', 'pop3', 'pop.mail.yahoo.com.au', '995', 'ssl'),
                    ('Yahoo Mail AU/NZ', 'imap', 'imap.mail.yahoo.au', '993', 'ssl'),
                  ('Yahoo Mail AU/NZ', 'smtp', 'smtp.mail.yahoo.au', '465', 'ssl'),
                
                    ('AT&T', 'pop3', 'pop.att.yahoo.com', '995', 'ssl'),
                    ('AT&T', 'imap', 'imap.att.yahoo.com', '993', 'ssl'),
                  ('AT&T', 'smtp', 'smtp.att.yahoo.com', '465', 'ssl'),
                
                    ('NTL @ntlworld.com', 'pop3', 'pop.ntlworld.com', '995', 'ssl'),
                    ('NTL @ntlworld.com', 'imap', 'imap.ntlworld.com', '993', 'ssl'),
                  ('NTL @ntlworld.com', 'smtp', 'smtp.ntlworld.com', '465', 'ssl'),
                
                    ('BT Connect', 'pop3', 'pop3.btconnect.com', '110', ''),
                    ('BT Connect', 'imap', 'imap4.btconnect.com', '143', ''),
                  ('BT Connect', 'smtp', 'smtp.btconnect.com', '25', ''),
                
                    ('O2 Deutschland', 'imap', 'imap.o2online.de', '143', ''),
                  ('O2 Deutschland', 'smtp', 'mail.o2online.de', '25', ''),
                
                    ('1&1 (1and1)', 'imap', 'imap.1and1.com', '993', 'ssl'),
                  ('1&1 (1and1)', 'smtp', 'smtp.1and1.com', '587', 'tls'),
                
                    ('Verizon', 'imap', 'incoming.verizon.net', '143', ''),
                  ('Verizon', 'smtp', 'outgoing.verizon.net', '587', ''),
                
                    ('Zoho Mail', 'imap', 'imap.zoho.com', '993', 'ssl'),
                  ('Zoho Mail', 'smtp', 'smtp.zoho.com', '465', 'ssl'),
                
                    ('Mail.com', 'imap', 'imap.mail.com', '993', 'ssl'),
                  ('Mail.com', 'smtp', 'smtp.mail.com', '465', 'ssl'),
                
                  ('GMX.com', 'imap', 'imap.gmx.com', '993', 'ssl'),
                  ('GMX.com', 'smtp', 'smtp.gmx.com', '465', 'ssl'),
                
                  ('Outlook.com', 'pop3', 'pop-mail.outlook.com', '995', 'ssl'),
                  ('Outlook.com', 'imap', 'imap-mail.outlook.com', '993', 'ssl'),
                  ('Outlook.com', 'smtp', 'smtp-mail.outlook.com', '587', 'tls'),
                
                  ('AOL', 'pop3', 'pop.aol.com', '110', ''),
                  ('AOL', 'imap', 'imap.aol.com', '143', ''),
                  ('AOL', 'smtp', 'smtp.aol.com', '587', ''),
                
                  ('iCloud', 'imap', 'imap.mail.me.com', '993', 'ssl'),
                  ('iCloud', 'smtp', 'smtp.mail.me.com', '587', 'tls'),
                
                  ('Office365.com', 'pop3', 'outlook.office365.com', '995', 'ssl'),
                  ('Office365.com', 'imap', 'outlook.office365.com', '993', 'ssl'),
                  ('Office365.com', 'smtp', 'smtp.office365.com', '587', 'tls'),
                
                  ('Hotmail', 'pop3', 'pop3.live.com', '995', 'ssl'),
                  ('Hotmail', 'imap', 'imap-mail.outlook.com', '993', 'ssl'),
                  ('Hotmail', 'smtp', 'smtp.live.com', '587', 'tls');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
                (90, 10, 'clients', 'Advanced search', 'clients-advanced-search-run', 0, 1, 21),
                (91, 90, 'clients', 'Export', 'clients-advanced-search-export', 0, 1, 0),
                (92, 90, 'clients', 'Print', 'clients-advanced-search-print', 0, 1, 0);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
                (90, 'applicants', 'search', 'run-search', 1),
                (91, 'applicants', 'search', 'export-to-excel', 1),
                (92, 'applicants', 'search', 'print', 1);");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
                (1, 90, 'Advanced search - run', 1),
                (1, 91, 'Advanced search - export', 1),
                (1, 92, 'Advanced search - print', 1);");

        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 90 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin', 'user');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 91 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin', 'user');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 92 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin', 'user');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES (181, 180, 'templates', 'Manage templates', 'templates-manage', 0, 1, 0);");

        $this->execute('DELETE FROM `acl_rule_details` WHERE  `rule_id`=180;');
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
                  (180,'templates','index','get-templates-list',1),
                  (180,'templates','index','get-message',1),
                  (180,'templates','index','get-email-template',1),
                  (180,'templates','index','view-pdf',1),
                  (180,'templates','index','show-pdf',1),
                  (181,'templates','index','',1);");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 181, 'Manage templates', 1);");
        $this->execute('INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_id, 181 FROM `acl_role_access` as r WHERE r.rule_id = 180;');

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (11, 'applicants', 'profile', 'delete-file'), (11, 'applicants', 'profile', 'download-file');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
                (95, 10, 'applicants', 'Office/Queue', 'clients-queue-run', 0, 1, 22),
                (96, 95, 'applicants', 'Export', 'clients-queue-export', 0, 1, 0),
                (97, 95, 'applicants', 'Print', 'clients-queue-print', 0, 1, 0),
                (98, 95, 'applicants', 'Push to Office/Queue', 'clients-queue-push-to-queue', 0, 1, 0),
                (99, 95, 'applicants', 'Change File Status', 'clients-queue-change-file-status', 0, 1, 0);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
                (95, 'applicants', 'queue', 'run', 1),
                (95, 'applicants', 'queue', 'load-settings', 1),
                (95, 'applicants', 'queue', 'save-settings', 1),
                (96, 'applicants', 'queue', 'export-to-excel', 1),
                (97, 'applicants', 'queue', 'print', 1),
                (98, 'applicants', 'queue', 'push-to-queue', 1),
                (99, 'applicants', 'queue', 'change-file-status', 1);");

        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 95 FROM `acl_roles` as r WHERE r.role_type IN ('admin', 'user');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 96 FROM `acl_roles` as r WHERE r.role_type IN ('admin', 'user');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 97 FROM `acl_roles` as r WHERE r.role_type IN ('admin', 'user');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 98 FROM `acl_roles` as r WHERE r.role_type IN ('admin', 'user');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 99 FROM `acl_roles` as r WHERE r.role_type IN ('admin', 'user');");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
                (1, 95, 'Queue - run', 1),
                (1, 96, 'Queue - export', 1),
                (1, 97, 'Queue - print', 1),
                (1, 98, 'Queue - push to office/queue', 1),
                (1, 99, 'Queue - change file status', 1);");

        $this->execute('CREATE TABLE `members_queues` (
                    `member_id` BIGINT(20) NOT NULL,
                    `queue_member_allowed_queues` TEXT NULL,
                    `queue_member_selected_queues` TEXT NULL,
                    `queue_columns` TEXT NULL,
                    INDEX `FK_members_queues_members` (`member_id`),
                    CONSTRAINT `FK_members_queues_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');

        /** TODO: CHECK !!! */
        $this->execute('DELETE FROM members_divisions WHERE division_id IS NULL OR division_id = 0;');
        $this->execute('ALTER TABLE `members_divisions` CHANGE COLUMN `division_id` `division_id` INT(11) UNSIGNED NOT NULL AFTER `member_id`;');
        $this->execute('ALTER TABLE `members_divisions` ADD CONSTRAINT `FK_members_divisions_divisions` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute('ALTER TABLE `members_divisions` DROP COLUMN `members_divisions_id`;');

        $this->execute('ALTER TABLE `client_types` ADD COLUMN `email_template_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `form_version_id`;');
        $this->execute('ALTER TABLE `client_types` ADD CONSTRAINT `FK_client_types_templates` FOREIGN KEY (`email_template_id`) REFERENCES `templates` (`template_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=11 AND `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='delete-image';");
        $this->execute('DROP TABLE `provincial_hst_rate`;');
        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='office' WHERE company_field_id = 'division';");
        $this->execute("UPDATE templates SET message = REPLACE(message, '&lt;%division%&gt;', '&lt;%office%&gt;');");
        $this->execute("UPDATE searches SET `columns` = REPLACE(`columns`, 'division', 'office');");
        $this->execute("UPDATE searches SET `query` = REPLACE(`query`, 'division', 'office');");

        $this->execute("INSERT INTO `acl_modules` (`module_id`, `module_name`) VALUES ('profile', 'User Profile');");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES
                (500, 5, 'profile', 'User Profile', 'user-profile-view', 0, 1, 34);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (500, 'profile', 'index', '', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 500, 'User Profile', 1);");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 500 FROM `acl_roles` as r WHERE r.role_type IN ('admin', 'user');");
        $this->execute('ALTER TABLE `client_form_data` DROP COLUMN `form_data_id`;');
        $this->execute('ALTER TABLE `members_last_access` DROP FOREIGN KEY `FK_members_last_access_1`;');
        $this->execute('ALTER TABLE `members_last_access` ADD CONSTRAINT `FK_members_last_access_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM eml_deleted_messages WHERE id_account NOT IN (SELECT id FROM eml_accounts);');
        $this->execute('ALTER TABLE `eml_deleted_messages` ADD CONSTRAINT `FK_eml_deleted_messages_eml_accounts` FOREIGN KEY (`id_account`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute('DELETE FROM eml_folders WHERE id_account NOT IN (SELECT id FROM eml_accounts);');
        $this->execute('ALTER TABLE `eml_folders` ADD CONSTRAINT `FK_eml_folders_eml_accounts` FOREIGN KEY (`id_account`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;');

//        $this->execute('DELETE FROM eml_messages WHERE id_account NOT IN (SELECT id FROM eml_accounts);');
//        $this->execute('ALTER TABLE `eml_messages` ADD CONSTRAINT `FK_eml_messages_eml_accounts` FOREIGN KEY (`id_account`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES (1450, 4, 'superadmin', 'Manage Zoho settings', 'zoho-settings', 1, 1, 0);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1450, 'superadmin', 'zoho', '', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (4, 1450, 'Manage Zoho settings', 0);");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1450 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 20 FROM `acl_roles` as r WHERE r.role_type IN ('user','admin', 'superadmin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 21 FROM `acl_roles` as r WHERE r.role_type IN ('user','admin', 'superadmin');");

        $this->execute("INSERT INTO `company_websites` (`company_id`, `template_id`, `company_name`, `entrance_name`, `title`, `company_email`, `company_phone`, `company_skype`, `company_fax`, `company_linkedin`, `company_facebook`, `company_twitter`, `homepage_name`, `about_name`, `canada_name`, `immigration_name`, `assessment_name`, `assessment_url`, `assessment_background`, `contact_name`, `contact_map_coords`, `footer_text`, `external_links_title`, `external_links`, `options`, `updated_date`, `created_date`) VALUES (0, 1, 'Default Company', 'default-company', 'Default Company', '', '', '', '', '', '', '', 'Home', 'About us', 'About Australia', 'Immigration', 'Free assessment', '', '#FFFFFF', 'Contact us', ',,,', 'All Rights reserved @ 2015', '', '[]', '{\"templateId\":\"1\",\"selected-bg\":\"\",\"footer-bg\":\"#61584F\",\"address\":\"\"}', '2015-07-27 16:51:40', '2015-07-27 13:05:50');");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`,`rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `rule_visible`,`rule_order`) VALUES (1054, 4,  'superadmin','Manage Internals fields/groups/layouts', 'manage-internals-fields', 0, 1, 0);");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 1054 FROM `acl_roles` as r WHERE r.role_type = 'admin';");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, 1054, 'Manage Internal Contacts fields/groups/layouts', 1);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
                (1054,'superadmin','manage-applicant-fields-groups','index',1),
                (1054,'superadmin','manage-applicant-fields-groups','internals',1),
                (1054,'superadmin','manage-applicant-fields-groups','add-block',1),
                (1054,'superadmin','manage-applicant-fields-groups','edit-block',1),
                (1054,'superadmin','manage-applicant-fields-groups','remove-block',1),
                (1054,'superadmin','manage-applicant-fields-groups','add-group',1),
                (1054,'superadmin','manage-applicant-fields-groups','edit-group',1),
                (1054,'superadmin','manage-applicant-fields-groups','delete-group',1),
                (1054,'superadmin','manage-applicant-fields-groups','get-contact-fields',1),
                (1054,'superadmin','manage-applicant-fields-groups','toggle-contact-fields',1),
                (1054,'superadmin','manage-applicant-fields-groups','get-field-info',1),
                (1054,'superadmin','manage-applicant-fields-groups','delete-field',1),
                (1054,'superadmin','manage-applicant-fields-groups','save-order',1),
                (1054,'superadmin','manage-applicant-fields-groups','edit-field',1);");

        $this->execute("ALTER TABLE `members_divisions` ADD COLUMN `type` ENUM('access_to','responsible_for','pull_from') NULL DEFAULT 'access_to' AFTER `division_id`;");
        $this->execute("ALTER TABLE `country_master` ADD COLUMN `type` ENUM('general','vevo') NULL DEFAULT 'general' AFTER `countries_iso_code_3`;");
    }

    public function down()
    {
    }
}