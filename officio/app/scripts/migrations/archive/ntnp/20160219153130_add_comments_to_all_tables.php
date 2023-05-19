<?php

use Phinx\Migration\AbstractMigration;

class AddCommentsToAllTables extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            ALTER TABLE `access_logs`
                COMMENT='Used to save specific log actions, e.g. login success/fail, user profile update, etc.';

            ALTER TABLE `acl_modules`
                COMMENT='List of all modules, used to identify access rights of the logged in/not logged in users';

            ALTER TABLE `acl_roles`
                COMMENT='List of all roles that are used during users/clients login. If role\'s company is 0 - this means that this role will be created for all new companies (except of superadmin roles).';

            ALTER TABLE `acl_role_access`
                COMMENT='Each role\'s access rights are described here.';

            ALTER TABLE `acl_rules`
                COMMENT='Access rights to all parts of the web site are described here. Each access right can be dynamic, so real access will be checked by rule_check_id option (if enabled for specific role).';

            ALTER TABLE `acl_rule_details`
                COMMENT='Each access rule can enable access to specific modules/sections of the web site. This is the place.';

            ALTER TABLE `applicant_form_blocks`
                COMMENT='Client\'s profile is created from blocks. Each block is related to the current client or to related clients (internal contacts).',
                CHANGE COLUMN `contact_block` `contact_block` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Identifies if this block is related to the current client or to related internal contact' AFTER `applicant_type_id`,
                CHANGE COLUMN `repeatable` `repeatable` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Identifies if several internal contacts can be related to the current user (so several contact blocks can be created).' AFTER `contact_block`;

            ALTER TABLE `applicant_form_data`
                COMMENT='Client\'s saved data is saved here. Each field\'s value is saved in the value column. Format is different, based on the field\'s type.',
                CHANGE COLUMN `row` `row` TINYINT(2) UNSIGNED NOT NULL COMMENT 'If client has several internal contacts - each row is related to specific internal contact.' AFTER `value`,
                CHANGE COLUMN `row_id` `row_id` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Is used to identify repeatable internal contacts.' AFTER `row`;

            ALTER TABLE `applicant_form_default`
                COMMENT='Each company can have own list of fields. Radios,checkboxes, other field types have own list of options that are saved here.';

            ALTER TABLE `applicant_form_fields`
                COMMENT='Contains fields that are used/showed in the client\'s profile. Each field\'s unique id (saved in applicant_field_unique_id) must be unique for specific client type (described in applicant_form_order table).';

            ALTER TABLE `applicant_form_fields_access`
                COMMENT='Identifies if specific role has access to specific client\'s field. If there is no record here - role has not access to the field. Because the same field can be placed in different groups - group id is used here too.';

            ALTER TABLE `applicant_form_groups`
                COMMENT='List of fields groups related to specific blocks are described here.';

            ALTER TABLE `applicant_form_order`
                COMMENT='Each field can be placed in specific group and can be showed in the Client\'s profile. If field is placed in the group that is \"unassigned\" - field is not showed in the Client\'s profile.',
                CHANGE COLUMN `use_full_row` `use_full_row` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Used to identify if field must use all available space (columns)  in the table.' AFTER `applicant_field_id`;

            ALTER TABLE `applicant_types`
                COMMENT='Client can have different types. The list of them is described here. Mostly used by Contacts. Type marked as system - cannot be deleted/changed.';

            ALTER TABLE `automated_billing_blacklist`
                COMMENT='List of errors that will be used during company\'s payment process. Failed invoices with these error codes will be ignored and not tried to process again.';

            ALTER TABLE `automated_billing_log`
                COMMENT='During payment process - we save all tries that were sent to the payment system.';

            ALTER TABLE `automated_billing_log_sessions`
                COMMENT='All payment requests are grouped by sessions. Each session is saved in this table.';

            ALTER TABLE `automatic_reminders`
                COMMENT='Automatic reminders (tasks) are described here. Each task has different properties and can be based on different triggers.';

            ALTER TABLE `automatic_reminders_processed`
                COMMENT='List of processed reminders (tasks) that are based on the date fields - is saved here to prevent their duplication.';

            ALTER TABLE `automatic_reminder_actions`
                COMMENT='List of actions that must be done when automatic task will be marked as due.';

            ALTER TABLE `automatic_reminder_action_types`
                COMMENT='List of supported action types that wil be done when automatic task will be marked as due.';

            ALTER TABLE `clients`
                COMMENT='Main table of the cases, contains main fields.';

            ALTER TABLE `clients_import`
                COMMENT='Contains list of files that can be used to import clients.';

            ALTER TABLE `client_form_data`
                COMMENT='All saved information related to the case is saved here. Each field\'s value is saved in the value column. Format is different, based on the field\'s type.';

            ALTER TABLE `client_form_default`
                COMMENT='Each company can have own list of cases\' fields. Radios,checkboxes, other field types have own list of options that are saved here.';

            ALTER TABLE `client_form_dependents`
                COMMENT='Each case can have assigned list of dependents. The list of fields for each dependent is limited/hardcoded.';

            ALTER TABLE `client_form_fields`
                COMMENT='Contains fields that are used/showed in the case\'s profile. Each field\'s unique id (saved in company_field_id) must be unique for specific case type.';

            ALTER TABLE `client_form_field_access`
                COMMENT='Identifies if specific role has access to specific case\'s field. If there is no record here - role has not access to the field. Because the same field can be used in different case types - case type id is used here too.';

            ALTER TABLE `client_form_groups`
                COMMENT='List of fields groups related to specific case types are described here.';

            ALTER TABLE `client_form_group_access`
                COMMENT='Identifies if specific role has access to specific case\'s group. If there is no record here - role has not access to the group.';

            ALTER TABLE `client_form_order`
                COMMENT='Each field can be placed in specific group and can be showed in the Case\'s profile. If field is placed in the group that is \"unassigned\" - field is not showed in the Case\'s profile.';

            ALTER TABLE `client_types`
                COMMENT='List of case types is saved here. Each company can have own list of case types. Some of them can be used for IA and/or Employer client records.',
                CHANGE COLUMN `form_version_id` `form_version_id` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'If is set - pdf form will be automatically assigned to the case during his creation.' AFTER `company_id`,
                CHANGE COLUMN `email_template_id` `email_template_id` INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'If is set - template will be processed and emailed to the case during his creation.' AFTER `form_version_id`;

            ALTER TABLE `client_types_kinds`
                COMMENT='Each Employer/Individual client type can have own lost of case types.';

            ALTER TABLE `company`
                COMMENT='List of all companies is saved here. Company with id 0 is used as default, all related settings will be copied from this company during new companies creation.';

            ALTER TABLE `company_cmi`
                COMMENT='List of CMI codes that can be used/assigned to comanies. CMI id is assigned to the company during company registration (CA version).';

            ALTER TABLE `company_default_options`
                COMMENT='Each company can have own list options (for now Categories combo uses this table).';

            ALTER TABLE `company_details`
                COMMENT='Company related info is saved here (mostly billing related, specific access settings).',
                CHANGE COLUMN `enable_case_management` `enable_case_management` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle to allow create more than one case for each client' AFTER `advanced_search_rows_max_count`;

            ALTER TABLE `company_invoice`
                COMMENT='After the purchasing process generated companies\' invoices are saved here.';

            ALTER TABLE `company_packages`
                COMMENT='Company packages are saved here (relates to subscription method).';

            ALTER TABLE `company_prospects`
                COMMENT='Company prospect records are saved here. Later these prospects will be converted to client + case.';

            ALTER TABLE `company_prospects_categories`
                COMMENT='List of company prospect categories is saved here.';

            ALTER TABLE `company_prospects_data_categories`
                COMMENT='Each prospect can be assigned to specific categories, this list is saved here.';

            ALTER TABLE `company_prospects_divisions`
                COMMENT='Each company prospect is related to specific office(s) (division(s)).';

            ALTER TABLE `company_prospects_job`
                COMMENT='Assigned list of jobs for each company prospect is saved here.';

            ALTER TABLE `company_prospects_noc`
                COMMENT='List of NOC codes that are used to determine company prospect\'s qualification + used during points calculation.';

            ALTER TABLE `company_prospects_noc_job_titles`
                COMMENT='Each job title has own mapping to the NOC code.';

            ALTER TABLE `company_prospects_notes`
                COMMENT='Company prospect\'s notes are saved here (showed in the Notes tab).';

            ALTER TABLE `company_prospects_selected_categories`
                COMMENT='Each company can have own list of prospect categories that can be used to assign prospects during their creation.';

            ALTER TABLE `company_prospects_templates`
                COMMENT='List of templates that can be used during new prospects creation/assigning.';

            ALTER TABLE `company_questionnaires`
                COMMENT='List of questionnaires that can be used to create company prospect records. Each company can have own list of questionnaires. Default questionnaire (id 1) is used as template and is copied to all new companies, list of fields that is showed in company prospect\'s profile (for all companies) is loaded from it too.';

            ALTER TABLE `company_questionnaires_category_template`
                COMMENT='A mapping between company prospect category and company prospect template - that will be used when prospect is assigned to that category.';

            ALTER TABLE `company_questionnaires_fields`
                COMMENT='List of fields that are used in questionnaire and (for default questionnaire with id 1) in prospect\'s profile (for all companies).';

            ALTER TABLE `company_questionnaires_fields_custom_options`
                COMMENT='List of options for referred by comboox related to specific questionnaire.';

            ALTER TABLE `company_questionnaires_fields_options`
                COMMENT='List of options for comboox/radio fields related to specific questionnaire.';

            ALTER TABLE `company_questionnaires_fields_options_templates`
                COMMENT='Each option in combobox/radio can have own label in each QNR.';

            ALTER TABLE `company_questionnaires_fields_templates`
                COMMENT='Each field can have own label in each QNR. ',
                CHANGE COLUMN `q_field_prospect_profile_label` `q_field_prospect_profile_label` CHAR(255) NOT NULL DEFAULT '' COMMENT 'Used in prospect\'s profile tab from default QNR only' AFTER `q_field_label`;

            ALTER TABLE `company_questionnaires_sections`
                COMMENT='List of sections that are used in QNR and in Prospect\'s profile.';

            ALTER TABLE `company_questionnaires_sections_templates`
                COMMENT='Each section can have own label (separate for QNR, separate for prsopect\'s profile) and can show/use help text.';

            ALTER TABLE `company_ta`
                COMMENT='List of trust accounts (client accounts) related to each company.';

            ALTER TABLE `company_ta_divisions`
                COMMENT='Each trust account can be accessible to specific offices only.';

            ALTER TABLE `company_trial`
                COMMENT='During new companies registration \"free trial\" key can be used. Allow to use it only once, save used keys here.';

            ALTER TABLE `company_types`
                COMMENT='List of company types is saved here. Not used for now.';

            ALTER TABLE `company_websites`
                COMMENT='Each company can use a special page (company web site). Main info about the company web site is saved here.';

            ALTER TABLE `company_websites_templates`
                COMMENT='List of supported web site templates (relates to real templates saved in public/templates directory.';

            ALTER TABLE `country_master`
                COMMENT='List of countries with their ISO codes.';

            ALTER TABLE `default_searches`
                COMMENT='Each user can use different default quick searches. There are 2 places where they are used - Clients and Contacts tabs.';

            ALTER TABLE `divisions`
                COMMENT='List of offices/divisions.';

            ALTER TABLE `eml_accounts`
                COMMENT='List of email accounts related to specific user are saved here.';

            ALTER TABLE `eml_attachments`
                COMMENT='Email attachment records which are related to email messages are saved here.';

            ALTER TABLE `eml_cron`
                COMMENT='Email cron that is started contains count of all accounts that must be processed and start date is saved here.';

            ALTER TABLE `eml_cron_accounts`
                COMMENT='List of email accounts that must be processed by cron is saved here.';

            ALTER TABLE `eml_deleted_messages`
                COMMENT='List of deleted email messages is saved here. This is required to prevent to download already deleted emails.';

            ALTER TABLE `eml_folders`
                COMMENT='List of folders related to specific email account is saved here.';

            ALTER TABLE `eml_messages`
                COMMENT='List of emails that relate to specific email account and specific folder is saved here.';

            ALTER TABLE `eml_sample_server_settings`
                COMMENT='List of settings of default email servers (e.g. google, yahoo, etc.).';

            ALTER TABLE `faq`
                COMMENT='List of FAQ messages that are showed in the FAQ section.';

            ALTER TABLE `faq_sections`
                COMMENT='List of FAQ sections that are showed on the FAQ page.';

            ALTER TABLE `folder_access`
                COMMENT='Access rights to specific client folders for specific roles are saved here.';

            ALTER TABLE `FormAssigned`
                COMMENT='List of assigned pdf forms to the case.';

            ALTER TABLE `FormFolder`
                COMMENT='List of folders that are used to group pdf forms is saved here.';

            ALTER TABLE `FormLanding`
                COMMENT='List of folders, that are used for landing pages.';

            ALTER TABLE `FormMap`
                COMMENT='Mapping between pdf fields and client fields.';

            ALTER TABLE `FormProcessed`
                COMMENT='List of landing pages that are used as templates.';

            ALTER TABLE `FormRevision`
                COMMENT='Each assigned form can use different form revisions.';

            ALTER TABLE `FormSynField`
                COMMENT='List of sync fields that can be used in pdf forms is saved here.';

            ALTER TABLE `FormTemplates`
                COMMENT='List of templates that can be showed in specific folder.';

            ALTER TABLE `FormUpload`
                COMMENT='Mapping between pdf forms and their folders, where they will be showed.';

            ALTER TABLE `FormVersion`
                COMMENT='List of pdf forms versions. Each form can have different version. All of them are saved here.';

            ALTER TABLE `form_default`
                COMMENT='Each form version can be marked as default. This is a place where these defaults are defined.';

            ALTER TABLE `hst_companies`
                COMMENT='List of GST/HST taxes that are used during taxes calculations by companies (e.g. in T/A).';

            ALTER TABLE `hst_officio`
                COMMENT='List of GST/HST taxes that are used during taxes calculations (when try to charge companies);';

            ALTER TABLE `letterheads_files`
                COMMENT='List of lettehead files that can be used as templates.';

            ALTER TABLE `letterheads`
                COMMENT='List of letteheads that can be used as templates.';

            ALTER TABLE `members`
                COMMENT='Main table that contains all kind of users and clients.';

            ALTER TABLE `members_divisions`
                COMMENT='List of assigned offices/divisions to users/clients.';

            ALTER TABLE `members_last_access`
                COMMENT='List of last accessed cases by users.';

            ALTER TABLE `members_last_passwords`
                COMMENT='List of last passwords used by users/clients. We save them here and check if new passwords are not the same as X last passwords.';

            ALTER TABLE `members_password_retrievals`
                COMMENT='Used during passwords restoring mechanism.';

            ALTER TABLE `members_queues`
                COMMENT='Settings for users related to Queue tab (Offices selected in combo, default columns).';

            ALTER TABLE `members_relations`
                COMMENT='Relations between clients and their cases (e.g. IA -> case, Individual Contact -> IA).';

            ALTER TABLE `members_roles`
                COMMENT='List of roles assigned to user/client.';

            ALTER TABLE `members_ta`
                COMMENT='Trust accounts are assigned to cases here.';

            ALTER TABLE `members_types`
                COMMENT='List of all supported users/clients.';

            ALTER TABLE `news`
                COMMENT='List of news that are showed in the News section on the home page.';

            ALTER TABLE `packages`
                COMMENT='List of all supported packages that can be assigned to companies.';

            ALTER TABLE `packages_details`
                COMMENT='Mapping between acl rules (specific part/functionality of the web site) and packages where they are allowed.';

            ALTER TABLE `prospects`
                COMMENT='List of prospects is saved here. Prospect is a record that is converted to company after the more detailed registration.';

            ALTER TABLE `reconciliation_log`
                COMMENT='Trust account can be reconciliated, date is saved here. We allow to do this only once per month.';

            ALTER TABLE `rss_black_list`
                COMMENT='List of urls that will be ignored/not showed during rss links parsing.';

            ALTER TABLE `searches`
                COMMENT='List of advanced searches. Each user can have own customized searches.';

            ALTER TABLE `states`
                COMMENT='List of states. Only CA and AU are supported for now. taxes are calculated in relation to the company\'s state.';

            ALTER TABLE `superadmin_searches`
                COMMENT='Searches that are used by superadmin users (to filter companies).';

            ALTER TABLE `superadmin_smtp`
                COMMENT='SMTP settings are used as default during emails sending.';

            ALTER TABLE `system_templates`
                COMMENT='Email and Invoice templates are used during superadmin actions (e.g. during company charging).';

            ALTER TABLE `templates`
                COMMENT='List of templates that can be used in different situations by company users.';

            ALTER TABLE `tickets`
                COMMENT='List of tickets that are used as notes for companies (for superadmin section only).';

            ALTER TABLE `time_tracker`
                COMMENT='List of records that are used in time tracker module.';

            ALTER TABLE `users`
                COMMENT='Users related info is saved here.';

            ALTER TABLE `usertypes`
                COMMENT='List of supported user types. Used to identify which type of role it is.';

            ALTER TABLE `u_assigned_deposits`
                COMMENT='Contains mapping between deposit records from trust account and specific case.';

            ALTER TABLE `u_assigned_withdrawals`
                COMMENT='Contains mapping between withdrawal records from trust account and specific case.';

            ALTER TABLE `u_deposit_types`
                COMMENT='List of supported deposit types for deposit records (unique for each company).';

            ALTER TABLE `u_destination_types`
                COMMENT='List of supported destination types for withdrawal records (unique for each company).';

            ALTER TABLE `u_folders`
                COMMENT='List of folders that are showed in My Docs and Client\'s Docs tabs.';

            ALTER TABLE `u_import_transactions`
                COMMENT='This is a log of records that were imported to trust account.';

            ALTER TABLE `u_invoice`
                COMMENT='Contains mapping between invoice records from trust account and specific case.';

            ALTER TABLE `u_links`
                COMMENT='List of links that are saved for each user, are showed in the Links section on the home page.';

            ALTER TABLE `u_log`
                COMMENT='This is log of actions that were applied to specific trust account records. E.g. reconcile, assign, etc.';

            ALTER TABLE `u_log`
                CHANGE COLUMN `action_id` `action_id` INT(10) NOT NULL COMMENT '1 - assign, 2 - import_transaction, 3 - send_receipt_of_payment, 4 - update_transaction, 5 - unassign, 6 - reconcile' AFTER `trust_account_id`;

            ALTER TABLE `u_notes`
                COMMENT='Contains notes records that are showed in Client\'s Notes tab.';

            ALTER TABLE `u_payment`
                COMMENT='Payment records are created when payment schedule records are due.';

            ALTER TABLE `u_payment_schedule`
                COMMENT='Records that are showed in payment schedule in Accounting tab for each case.';

            ALTER TABLE `u_payment_templates`
                COMMENT='List of templates that can be used to quickly generate records in payment schedule grid.';

            ALTER TABLE `u_sms`
                COMMENT='Contains list of sms records that must be processed (sent to phone number).';

            ALTER TABLE `u_tasks`
                COMMENT='List of tasks that are showed in My Tasks and Client\'s Tasks tabs.';

            ALTER TABLE `u_tasks_assigned_to`
                COMMENT='Relation between task and this task\'s owner.';

            ALTER TABLE `u_tasks_messages`
                COMMENT='Messages that are related to tasks.';

            ALTER TABLE `u_tasks_priority`
                COMMENT='List of priorities in relation to the task and its owner/relating person.';

            ALTER TABLE `u_tasks_read`
                COMMENT='Indicates which tasks were read and by whom.';

            ALTER TABLE `u_trust_account`
                COMMENT='Records that are showed in trust account section.';

            ALTER TABLE `u_variable`
                COMMENT='Site related specific settings. Some of them can be changed by superadmin. E.g. packages prices.';

            ALTER TABLE `u_withdrawal_types`
                COMMENT='List of supported withdrawal types for withdrawal records (unique for each company).';

            ALTER TABLE `zoho_keys`
                COMMENT='List of Zoho API keys that are used during communicating with Zoho service. Allows enable/disable specific keys that can be used in GUI.';
        "
        );
    }

    public function down()
    {
    }
}
