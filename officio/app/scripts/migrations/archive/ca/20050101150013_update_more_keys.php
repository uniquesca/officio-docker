<?php

use Officio\Migration\AbstractMigration;

class UpdateMoreKeys extends AbstractMigration
{
    public function up()
    {
        // Took 115s on local server
        $this->execute('DELETE FROM folder_access WHERE folder_id NOT IN (SELECT folder_id FROM u_folders) OR role_id NOT IN (SELECT r.role_id FROM acl_roles AS r);');
        $this->execute('ALTER TABLE `folder_access` ADD CONSTRAINT `FK_folder_access_u_folders` FOREIGN KEY (`folder_id`) REFERENCES `u_folders` (`folder_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute('ALTER TABLE `folder_access`
            CHANGE COLUMN `role_id` `role_id` INT(11) NULL DEFAULT NULL AFTER `folder_id`,
            ADD CONSTRAINT `FK_folder_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM FormAssigned WHERE ClientMemberId NOT IN (SELECT m.member_id FROM members AS m);');
        $this->execute('ALTER TABLE `FormAssigned` ADD CONSTRAINT `FK_FormAssigned_members` FOREIGN KEY (`ClientMemberId`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM members_roles WHERE member_id NOT IN (SELECT m.member_id FROM members AS m);');
        $this->execute('DELETE FROM members_roles WHERE role_id NOT IN (SELECT r.role_id FROM acl_roles as r);');
        $this->execute('ALTER TABLE `members_roles`
            ADD CONSTRAINT `FK_members_roles_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            ADD CONSTRAINT `FK_members_roles_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE u_assigned_deposits SET author_id = NULL WHERE author_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_assigned_deposits` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `deposit_id`;');
        $this->execute('ALTER TABLE `u_assigned_deposits` ADD CONSTRAINT `FK_u_assigned_deposits_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('UPDATE u_assigned_withdrawals SET author_id = NULL WHERE author_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_assigned_withdrawals`
            CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `withdrawal_id`,
            ADD CONSTRAINT `FK_u_assigned_withdrawals_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('DELETE FROM u_deposit_types WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `u_deposit_types` ALTER `company_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `u_deposit_types`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AFTER `dtl_id`,
            ADD CONSTRAINT `FK_u_deposit_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE `u_deposit_types` SET `author_id` = NULL WHERE author_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_deposit_types`
            CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_id`,
            ADD CONSTRAINT `FK_u_deposit_types_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('DELETE FROM u_destination_types WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('UPDATE `u_destination_types` SET `author_id` = NULL WHERE author_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_destination_types` ALTER `company_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `u_destination_types`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AFTER `destination_account_id`,
            ADD CONSTRAINT `FK_u_destination_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute('ALTER TABLE `u_destination_types`
            CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_id`,
            ADD CONSTRAINT `FK_u_destination_types_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('DELETE FROM u_invoice WHERE member_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_invoice` ADD CONSTRAINT `FK_u_invoice_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM u_links WHERE member_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_links` ADD CONSTRAINT `FK_u_links_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM u_payment_templates WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `u_payment_templates` ALTER `company_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `u_payment_templates` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AFTER `saved_payment_template_id`;');
        $this->execute('ALTER TABLE `u_payment_templates` ADD CONSTRAINT `FK_u_payment_templates_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM FormUpload WHERE FolderId NOT IN (SELECT FolderId FROM FormFolder);');
        $this->execute('ALTER TABLE `FormUpload` ALTER `FolderId` DROP DEFAULT;');
        $this->execute('ALTER TABLE `FormUpload` CHANGE COLUMN `FolderId` `FolderId` INT(10) UNSIGNED NOT NULL AFTER `FormId`;');
        $this->execute('ALTER TABLE `FormUpload` ADD CONSTRAINT `FK_formupload_formfolder` FOREIGN KEY (`FolderId`) REFERENCES `FormFolder` (`FolderId`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM u_folders WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `u_folders`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `parent_id`,
            ADD CONSTRAINT `FK_u_folders_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE `u_folders` SET `author_id` = NULL WHERE author_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_folders`
            CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_id`,
            ADD CONSTRAINT `FK_u_folders_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('DELETE FROM acl_roles WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `acl_roles`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL DEFAULT 0 AFTER `role_id`,
            ADD CONSTRAINT `FK_acl_roles_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE company_questionnaires SET q_office_id = NULL WHERE q_office_id NOT IN (SELECT d.division_id FROM divisions as d);');
        $this->execute('ALTER TABLE `company_questionnaires` ADD CONSTRAINT `FK_company_questionnaires_divisions` FOREIGN KEY (`q_office_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('ALTER TABLE `company_prospects_templates` ALTER `author_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `company_prospects_templates` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL AFTER `company_id`;');
        $this->execute('UPDATE `company_prospects_templates` SET `author_id` = NULL WHERE author_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE company_prospects_templates ADD CONSTRAINT `FK_company_prospects_templates_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('DELETE FROM default_searches WHERE member_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `default_searches` ALTER `member_id` DROP DEFAULT;');

        $this->execute('ALTER TABLE `default_searches`
            CHANGE COLUMN `member_id` `member_id` BIGINT(20) NOT NULL FIRST,
            ADD CONSTRAINT `FK_default_searches_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM divisions WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `divisions`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `division_id`,
            ADD CONSTRAINT `FK_divisions_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `FormAssigned` ADD CONSTRAINT `FK_formassigned_formversion` FOREIGN KEY (`FormVersionId`) REFERENCES `FormVersion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `FormProcessed` ADD CONSTRAINT `FK_formprocessed_formtemplates` FOREIGN KEY (`template_id`) REFERENCES `FormTemplates` (`template_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM prospects WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `prospects`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `prospect_id`,
            ADD CONSTRAINT `FK_prospects_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('DELETE FROM searches WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('UPDATE `searches` SET `author_id` = NULL WHERE author_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `searches`
            CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `search_type`,
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `author_id`,
            ADD CONSTRAINT `FK_searches_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            ADD CONSTRAINT `FK_searches_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('UPDATE `u_payment` SET `payment_schedule_id` = NULL WHERE payment_schedule_id NOT IN (SELECT p.payment_schedule_id FROM u_payment_schedule as p);');
        $this->execute('ALTER TABLE `u_payment` ADD CONSTRAINT `FK_u_payment_u_payment_schedule` FOREIGN KEY (`payment_schedule_id`) REFERENCES `u_payment_schedule` (`payment_schedule_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM u_withdrawal_types WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `u_withdrawal_types` ALTER `company_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `u_withdrawal_types`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AFTER `wtl_id`,
            ADD CONSTRAINT `FK_u_withdrawal_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM automatic_reminders WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `automatic_reminders`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `automatic_reminder_id`,
            ADD CONSTRAINT `FK_automatic_reminders_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
        $this->execute("UPDATE `automatic_reminders` SET `reminder`='Client\'s payment has become due' WHERE `reminder`= \"Client\\'s payment has become due\"");

        $this->execute('DELETE FROM agents WHERE company_id NOT IN (SELECT c.company_id FROM company as c);');
        $this->execute('ALTER TABLE `agents`
            CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `agent_id`,
            ADD CONSTRAINT `FK_agents_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=10 AND `module_id`='clients' AND `resource_id`='profile' AND `resource_privilege`='get-change-password-data';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=10 AND `module_id`='clients' AND `resource_id`='profile' AND `resource_privilege`='set-password';");

        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='visa_office' WHERE `company_field_id` = 'posts';");

        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `relationship` ENUM('spouse','child','mother','father','sister','brother') NOT NULL DEFAULT 'spouse' AFTER `member_id`;");
        $this->execute('ALTER TABLE `client_form_dependents` DROP COLUMN `depend_id`;');
        $this->execute("UPDATE client_form_dependents as d SET d.relationship = 'child' WHERE line > 0;");
    }

    public function down()
    {
    }
}