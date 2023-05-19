<?php

use Phinx\Migration\AbstractMigration;

class UpdateKeys extends AbstractMigration
{
    public function up()
    {
        // Took 680s on local server
        $this->execute('ALTER TABLE `client_form_default` DROP FOREIGN KEY `FK_client_form_default_1`;');
        $this->execute('ALTER TABLE `client_form_default` ADD CONSTRAINT `FK_client_form_default_1` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `client_form_field_access` DROP FOREIGN KEY `FK_client_form_field_access_1`;');
        $this->execute('ALTER TABLE `client_form_field_access` ADD CONSTRAINT `FK_client_form_field_access_1` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM client_form_group_access WHERE role_id NOT IN (SELECT r.role_id FROM acl_roles as r);');
        $this->execute('ALTER TABLE `client_form_group_access`
        	CHANGE COLUMN `role_id` `role_id` INT(11) NULL DEFAULT NULL AFTER `access_id`,
        	ADD CONSTRAINT `FK_client_form_group_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_prospects_selected_categories`
        	DROP FOREIGN KEY `FK_company_prospects_1`,
        	DROP FOREIGN KEY `FK_company_prospects_2`;');
        $this->execute('ALTER TABLE `company_prospects_selected_categories`
        	ADD CONSTRAINT `FK_company_prospects_1` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_prospects_2` FOREIGN KEY (`prospect_category_id`) REFERENCES `company_prospects_categories` (`prospect_category_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_questionnaires`
        	DROP FOREIGN KEY `FK_company_questionnaires_1`,
        	DROP FOREIGN KEY `FK_company_questionnaires_2`,
        	DROP FOREIGN KEY `FK_company_questionnaires_3`;');
        $this->execute('ALTER TABLE `company_questionnaires`
        	ADD CONSTRAINT `FK_company_questionnaires_1` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_questionnaires_2` FOREIGN KEY (`q_created_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_questionnaires_3` FOREIGN KEY (`q_updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM form_default WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `form_default`
        	DROP FOREIGN KEY `FK_form_default_company`,
        	DROP FOREIGN KEY `FK_form_default_FormVersion`,
        	DROP FOREIGN KEY `FK_form_default_members`;');
        $this->execute('ALTER TABLE `form_default`
        	ADD CONSTRAINT `FK_form_default_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_form_default_FormVersion` FOREIGN KEY (`form_version_id`) REFERENCES `FormVersion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_form_default_members` FOREIGN KEY (`updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `members` DROP FOREIGN KEY `FK_members_company`;');
        $this->execute('ALTER TABLE `members` ADD CONSTRAINT `FK_members_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `u_tasks` DROP FOREIGN KEY `FK_u_tasks_company`;');
        $this->execute('ALTER TABLE `u_tasks` ADD CONSTRAINT `FK_u_tasks_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_details WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_details`
        	CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AUTO_INCREMENT FIRST,
        	ADD CONSTRAINT `FK_company_details_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE company_invoice SET company_id = NULL WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_invoice`
        	CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `prospect_id`,
        	ADD CONSTRAINT `FK_company_invoice_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_packages WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_packages`
        	CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL FIRST,
        	ADD CONSTRAINT `FK_company_packages_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_prospects_data_categories WHERE prospect_id NOT IN (SELECT p.prospect_id FROM company_prospects AS p);');
        $this->execute('ALTER TABLE `company_prospects_data_categories` DROP FOREIGN KEY `FK_company_prospects_categories_1`;');
        $this->execute('ALTER TABLE `company_prospects_data_categories` ADD CONSTRAINT `FK_company_prospects_categories_1` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_prospects_data` DROP FOREIGN KEY `FK_company_prospects_data_company_prospects`, DROP FOREIGN KEY `FK_company_prospects_data_q_fields`;');
        $this->execute('ALTER TABLE `company_prospects_data`
        	ADD CONSTRAINT `FK_company_prospects_data_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_prospects_data_q_fields` FOREIGN KEY (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_prospects_job` DROP FOREIGN KEY `FK_company_prospects_job_company_prospects`;');
        $this->execute('ALTER TABLE `company_prospects_job` ADD CONSTRAINT `FK_company_prospects_job_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_prospects_notes WHERE prospect_id NOT IN (SELECT p.prospect_id FROM company_prospects AS p);');
        $this->execute('ALTER TABLE `company_prospects_notes` ADD CONSTRAINT `FK_company_prospects_notes_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_prospects_templates WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_prospects_templates` ADD CONSTRAINT `FK_company_prospects_templates_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_questionnaires_category_template`
          DROP FOREIGN KEY `FK_company_questionnaires_category_template_1`,
          DROP FOREIGN KEY `FK_company_questionnaires_category_template_2`,
          DROP FOREIGN KEY `FK_company_questionnaires_category_template_3`;');

        $this->execute('ALTER TABLE `company_questionnaires_category_template`
        	ADD CONSTRAINT `FK_company_questionnaires_category_template_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_questionnaires_category_template_2` FOREIGN KEY (`prospect_category_id`) REFERENCES `company_prospects_categories` (`prospect_category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_questionnaires_category_template_3` FOREIGN KEY (`prospect_template_id`) REFERENCES `company_prospects_templates` (`prospect_template_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_questionnaires_fields_custom_options`
          DROP FOREIGN KEY `FK_company_questionnaires_fields_custom_options_1`,
          DROP FOREIGN KEY `FK_company_questionnaires_fields_custom_options_2`;');

        $this->execute('ALTER TABLE `company_questionnaires_fields_custom_options`
        	ADD CONSTRAINT `FK_company_questionnaires_fields_custom_options_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_questionnaires_fields_custom_options_2` FOREIGN KEY (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_questionnaires_fields_options_templates` DROP FOREIGN KEY `FK_company_questionnaires_fields_options_templates_1`;');
        $this->execute('ALTER TABLE `company_questionnaires_fields_options_templates` ADD CONSTRAINT `FK_company_questionnaires_fields_options_templates_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `company_questionnaires_sections_templates`
          DROP FOREIGN KEY `FK_company_questionnaires_sections_templates_1`,
          DROP FOREIGN KEY `FK_company_questionnaires_sections_templates_2`;');

        $this->execute('ALTER TABLE `company_questionnaires_sections_templates`
        	ADD CONSTRAINT `FK_company_questionnaires_sections_templates_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_company_questionnaires_sections_templates_2` FOREIGN KEY (`q_section_id`) REFERENCES `company_questionnaires_sections` (`q_section_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM company_ta WHERE company_id NOT IN (SELECT c.company_id FROM company AS c);');
        $this->execute('ALTER TABLE `company_ta`
        	CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_ta_id`,
        	ADD CONSTRAINT `FK_company_ta_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `u_assigned_withdrawals`
          DROP FOREIGN KEY `FK_company_ta_id`,
          DROP FOREIGN KEY `FK_invoice_id`,
          DROP FOREIGN KEY `FK_returned_payment_member_id`,
          DROP FOREIGN KEY `FK_trust_account_id`;');
        $this->execute('ALTER TABLE `u_assigned_withdrawals`
	      ADD CONSTRAINT `FK_company_ta_id` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	      ADD CONSTRAINT `FK_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `u_invoice` (`invoice_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	      ADD CONSTRAINT `FK_returned_payment_member_id` FOREIGN KEY (`returned_payment_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	      ADD CONSTRAINT `FK_trust_account_id` FOREIGN KEY (`trust_account_id`) REFERENCES `u_trust_account` (`trust_account_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `u_payment`
          DROP FOREIGN KEY `FK_company_ta_id_2_u_payment`,
          DROP FOREIGN KEY `FK_trust_account_id_2_u_payment`,
        DROP FOREIGN KEY `FK_u_payment_members`;');

        $this->execute('ALTER TABLE `u_payment`
        	ADD CONSTRAINT `FK_company_ta_id_2_u_payment` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_trust_account_id_2_u_payment` FOREIGN KEY (`trust_account_id`) REFERENCES `u_trust_account` (`trust_account_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK_u_payment_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
    }

    public function down()
    {
    }
}