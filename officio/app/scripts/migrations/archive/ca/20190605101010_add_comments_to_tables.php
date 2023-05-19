<?php

use Officio\Migration\AbstractMigration;

class AddCommentsToTables extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `analytics` COMMENT = 'Contains saved analytics params about client and case fields data.';");
        $this->execute("ALTER TABLE `client_form_dependents_required_files` COMMENT = 'Contains info about required forms/files for case\'s dependents. Is used in the Documents Checklist sub tab under the Clients tab.';");
        $this->execute("ALTER TABLE `client_form_dependents_uploaded_files` COMMENT = 'Contains info about uploaded forms/files for case\'s dependents. Is used in the Documents Checklist sub tab under the Clients tab.';");
        $this->execute("ALTER TABLE `client_form_dependents_uploaded_file_tags` COMMENT = 'Contains tags assigned to uploaded forms/files for case\'s dependents. Is used in the Documents Checklist sub tab under the Clients tab.';");
        $this->execute("ALTER TABLE `client_form_dependents_visa_survey` COMMENT = 'Contains info about case\'s dependents visa survey.';");
        $this->execute("ALTER TABLE `client_types_forms` COMMENT = 'Contains list of assigned forms for specific case types.';");
        $this->execute("ALTER TABLE `company_marketplace_profiles` COMMENT = 'Contains list of MarketPlace profiles assigned to the company.';");
        $this->execute("ALTER TABLE `company_prospects_activities` COMMENT = 'Contains list of company prospects activities.';");
        $this->execute("ALTER TABLE `company_prospects_converted` COMMENT = 'Contains links between company prospects and converted clients + links to invoice.';");
        $this->execute("ALTER TABLE `company_prospects_data` COMMENT = 'Contains company prospects data.';");
        $this->execute("ALTER TABLE `company_prospects_invited` COMMENT = 'Contains info about prospects invited by companies.';");
        $this->execute("ALTER TABLE `company_prospects_settings` COMMENT = 'Contains info about company prospects settings.';");
        $this->execute("ALTER TABLE `field_types` COMMENT = 'Contains client and case field types.';");
        $this->execute("ALTER TABLE `file_number_reservations` COMMENT = 'Contains company reserved file numbers (case reference numbers).';");
        $this->execute("ALTER TABLE `members_vevo_mapping` COMMENT = 'Contains vevo mapping list for the VEVO feature in the Clients.';");
        $this->execute("ALTER TABLE `pricing_categories` COMMENT = 'Contains general and promotional pricing categories.';");
        $this->execute("ALTER TABLE `pricing_category_details` COMMENT = 'Contains general and promotional pricing categories details.';");
        $this->execute("ALTER TABLE `subscriptions` COMMENT = 'Contains list of all supported subscriptions that can be assigned to companies.';");
        $this->execute("ALTER TABLE `subscriptions_packages` COMMENT = 'Contains matching between subscriptions and packages.';");
        $this->execute("ALTER TABLE `template_attachments` COMMENT = 'Contains links between email templates and attached to them letter templates.';");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `analytics` COMMENT = '';");
        $this->execute("ALTER TABLE `client_form_dependents_required_files` COMMENT = '';");
        $this->execute("ALTER TABLE `client_form_dependents_uploaded_files` COMMENT = '';");
        $this->execute("ALTER TABLE `client_form_dependents_uploaded_file_tags` COMMENT = '';");
        $this->execute("ALTER TABLE `client_form_dependents_visa_survey` COMMENT = '';");
        $this->execute("ALTER TABLE `client_types_forms` COMMENT = '';");
        $this->execute("ALTER TABLE `company_marketplace_profiles` COMMENT = '';");
        $this->execute("ALTER TABLE `company_prospects_activities` COMMENT = '';");
        $this->execute("ALTER TABLE `company_prospects_converted` COMMENT = '';");
        $this->execute("ALTER TABLE `company_prospects_data` COMMENT = '';");
        $this->execute("ALTER TABLE `company_prospects_invited` COMMENT = '';");
        $this->execute("ALTER TABLE `company_prospects_settings` COMMENT = '';");
        $this->execute("ALTER TABLE `field_types` COMMENT = '';");
        $this->execute("ALTER TABLE `file_number_reservations` COMMENT = '';");
        $this->execute("ALTER TABLE `members_vevo_mapping` COMMENT = '';");
        $this->execute("ALTER TABLE `pricing_categories` COMMENT = '';");
        $this->execute("ALTER TABLE `pricing_category_details` COMMENT = '';");
        $this->execute("ALTER TABLE `subscriptions` COMMENT = '';");
        $this->execute("ALTER TABLE `subscriptions_packages` COMMENT = '';");
        $this->execute("ALTER TABLE `template_attachments` COMMENT = '';");
    }
}