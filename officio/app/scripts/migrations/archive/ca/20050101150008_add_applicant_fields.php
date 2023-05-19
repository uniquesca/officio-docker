<?php

use Officio\Migration\AbstractMigration;

class AddApplicantFields extends AbstractMigration
{
    public function up()
    {
        // Took 1.1291s on local server

        $allQueries = <<<EOD
        INSERT INTO `applicant_types` VALUES (1, 10, 0, 'Sales Agent', 'Y');
        INSERT INTO `applicant_types` VALUES (2, 10, 0, 'Lawyer/Legal Aid', 'N');
        INSERT INTO `applicant_types` VALUES (3, 10, 0, 'Immigration Department', 'N');
        INSERT INTO `applicant_types` VALUES (4, 10, 0, 'Provincial Government', 'N');
        INSERT INTO `applicant_types` VALUES (5, 10, 0, 'Association/Regional Body', 'N');
        INSERT INTO `applicant_types` VALUES (6, 10, 0, 'Regulatory Body', 'N');
        INSERT INTO `applicant_types` VALUES (7, 10, 0, 'Translator', 'N');
        INSERT INTO `applicant_types` VALUES (8, 10, 0, 'Education Provider', 'N');
        INSERT INTO `applicant_types` VALUES (9, 10, 0, 'Others', 'N');
        
        ALTER TABLE `applicant_types` AUTO_INCREMENT = 100;


        /* IA blocks */
        INSERT INTO `applicant_form_blocks` VALUES (1, 8, 0, NULL, 'Y', 'N', 0);
        INSERT INTO `applicant_form_blocks` VALUES (2, 8, 0, NULL, 'N', 'N', 1);

        /* Employer blocks */
        INSERT INTO `applicant_form_blocks` VALUES (3, 7, 0, NULL, 'Y', 'N', 0);
        INSERT INTO `applicant_form_blocks` VALUES (4, 7, 0, NULL, 'N', 'N', 1);

        /* Contact blocks */
        INSERT INTO `applicant_form_blocks` VALUES (20, 10, 0, 1, 'Y', 'N', 0);
        INSERT INTO `applicant_form_blocks` VALUES (21, 10, 0, 2, 'Y', 'N', 1);
        INSERT INTO `applicant_form_blocks` VALUES (22, 10, 0, 3, 'Y', 'N', 2);
        INSERT INTO `applicant_form_blocks` VALUES (23, 10, 0, 4, 'Y', 'N', 3);
        INSERT INTO `applicant_form_blocks` VALUES (24, 10, 0, 5, 'Y', 'N', 4);
        INSERT INTO `applicant_form_blocks` VALUES (25, 10, 0, 7, 'Y', 'N', 5);
        INSERT INTO `applicant_form_blocks` VALUES (26, 10, 0, 8, 'Y', 'N', 6);
        INSERT INTO `applicant_form_blocks` VALUES (27, 10, 0, 9, 'Y', 'N', 7);
        INSERT INTO `applicant_form_blocks` VALUES (28, 10, 0, 6, 'Y', 'N', 8);


        ALTER TABLE `applicant_form_blocks` AUTO_INCREMENT = 100;

        /* IA groups */
        INSERT INTO `applicant_form_groups` VALUES (1, 1, 0, 'Personal Information', 3, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (2, 1, 0, 'Contact Information', 3, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (3, 2, 0, 'Client Login Information', 3, 'Y', 2);

        /* Employer groups */
        INSERT INTO `applicant_form_groups` VALUES (4, 3, 0, 'Business Info', 3, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (5, 3, 0, 'Business Address', 3, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (6, 3, 0, 'Mailing Address', 3, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (7, 3, 0, 'Principal Employer contact', 3, 'Y', 3);
        INSERT INTO `applicant_form_groups` VALUES (8, 3, 0, 'Alternate Employer contact', 3, 'Y', 4);
        INSERT INTO `applicant_form_groups` VALUES (9, 4, 0, 'Employer Login', 3, 'Y', 5);

        /* Contact groups */
        INSERT INTO `applicant_form_groups` VALUES (10, 20, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (11, 20, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (12, 20, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (13, 21, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (14, 21, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (15, 21, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (16, 22, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (17, 22, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (18, 22, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (19, 23, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (20, 23, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (21, 23, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (22, 24, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (23, 24, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (24, 24, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (25, 25, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (26, 25, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (27, 25, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (28, 26, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (29, 26, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (30, 26, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (31, 27, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (32, 27, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (33, 27, 0, 'Notes & Comments', 4, 'Y', 2);
        INSERT INTO `applicant_form_groups` VALUES (34, 28, 0, 'Contact Information', 4, 'N', 0);
        INSERT INTO `applicant_form_groups` VALUES (35, 28, 0, 'Address & Contact Details', 4, 'Y', 1);
        INSERT INTO `applicant_form_groups` VALUES (36, 28, 0, 'Notes & Comments', 4, 'Y', 2);

        ALTER TABLE `applicant_form_groups` AUTO_INCREMENT = 100;

        /* Internal contact fields */
        INSERT INTO `applicant_form_fields` VALUES (1, 9, 0, 'last_name', 'text', 'Last Name', 64, 'N', 'Y', 'N', 'Y');
        INSERT INTO `applicant_form_fields` VALUES (2, 9, 0, 'first_name', 'text', 'First Name', 64, 'N', 'Y', 'N', 'Y');
        INSERT INTO `applicant_form_fields` VALUES (3, 9, 0, 'phone_h', 'phone', 'Phone (Home)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (4, 9, 0, 'phone_secondary', 'phone', 'Phone (Secondary)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (5, 9, 0, 'phone_main', 'phone', 'Phone (Main)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (6, 9, 0, 'email', 'email', 'Email (Primary)', NULL, 'N', 'N', 'N', 'Y');
        INSERT INTO `applicant_form_fields` VALUES (7, 9, 0, 'email_1', 'email', 'Email (Other)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (8, 9, 0, 'contact_type', 'combo', 'Contact type', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (9, 9, 0, 'title', 'combo', 'Title', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (10, 9, 0, 'DOB', 'date_repeatable', 'Date of Birth', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (11, 9, 0, 'passport_number', 'text', 'Passport #', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (12, 9, 0, 'passport_exp_date', 'date', 'Passport Expiry Date', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (13, 9, 0, 'country_of_birth', 'text', 'Country of Birth', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (14, 9, 0, 'country_of_residence', 'text', 'Country of Residence', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (15, 9, 0, 'country_of_citizenship', 'text', 'Country of Citizenship', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (16, 9, 0, 'salutation_in_native_lang', 'text', 'Title in Native Language', 40, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (17, 9, 0, 'name_in_native_lang', 'text', 'Name in Native Language', 80, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (18, 9, 0, 'preferred_language', 'text', 'Preferred Language', 60, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (19, 9, 0, 'photo', 'photo', 'Photo', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (20, 9, 0, 'address_1', 'text', 'Address 1', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (21, 9, 0, 'address_2', 'text', 'Address 2', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (22, 9, 0, 'city', 'text', 'City', 120, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (23, 9, 0, 'state', 'text', 'Province/State', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (24, 9, 0, 'country', 'text', 'Country', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (25, 9, 0, 'zip_code', 'text', 'Postal/zip code', 16, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (26, 9, 0, 'fax_w', 'phone', 'Fax (Work)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (27, 9, 0, 'pref_contact_method', 'combo', 'Preferred Contact Method', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (28, 9, 0, 'special_instruction', 'memo', 'Special Instruction', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (29, 9, 0, 'entity_name', 'text', 'Business Legal Name', NULL, 'N', 'Y', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (30, 9, 0, 'company_legal_name', 'text', 'Company Legal Name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (31, 9, 0, 'company_activity', 'text', 'Company Activity', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (32, 9, 0, 'website', 'text', 'Website', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (33, 9, 0, 'status', 'combo', 'Status', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (34, 9, 0, 'email_2', 'email', 'Email (Other 2)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (35, 9, 0, 'fax_h', 'phone', 'Fax (Home)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (36, 9, 0, 'fax_o', 'phone', 'Fax (Other)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (37, 9, 0, 'notes', 'memo', 'Notes', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (38, 9, 0, 'position', 'text', 'Position', 30, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (39, 9, 0, 'other_names', 'text', 'Other Names', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (42, 9, 0, 'nationality', 'text', 'Nationality', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (44, 9, 0, 'passport_issue_date', 'date', 'Passport Issue Date', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (45, 9, 0, 'passport_expiry_date', 'date', 'Passport Expiry Date', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (46, 9, 0, 'current_visa', 'text', 'Current Visa', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (48, 9, 0, 'passport_country_of_issue', 'text', 'Passport Issued Country', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (49, 9, 0, 'emergency_contact', 'text', 'Emergency Contact', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (50, 9, 0, 'emergency_contact_phone_number', 'phone', 'Emergency Contact Phone Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (51, 9, 0, 'department', 'text', 'Department', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (52, 9, 0, 'medical_expiration_date', 'date', 'Medical Expiration Date', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (53, 9, 0, 'migrating', 'combo', 'Migrating', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (54, 9, 0, 'entered_queue_on', 'office_change_date_time', 'Entered Queue On', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (55, 9, 0, 'phone_w', 'phone', 'Phone (Work)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (56, 9, 0, 'phone_m', 'phone', 'Phone (Mobile)', NULL, 'N', 'N', 'N', 'N');

        /* NEW Internal contact fields */
        INSERT INTO `applicant_form_fields` VALUES (57, 9, 0, 'UCI', 'text', 'Unique Client Identifier (UCI)', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (59, 9, 0, 'city_of_birth', 'text', 'City of Birth', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (60, 9, 0, 'address_3', 'text', 'Address 3', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (61, 9, 0, 'email_3', 'email', 'Email (Other 3)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (62, 9, 0, 'linkedin', 'text', 'Linkedin ID', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (63, 9, 0, 'skype', 'text', 'Skype ID', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (64, 9, 0, 'whatsapp', 'text', 'Whatsapp ID', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (65, 9, 0, 'wechat', 'text', 'Wechat ID', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (66, 9, 0, 'line_id', 'text', 'Line ID', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (67, 9, 0, 'date_signed_up', 'date', 'Date Signed Up', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (68, 9, 0, 'status_simple', 'combo', 'Status', NULL, 'N', 'Y', 'N', 'N');

        /* IA fields */
        INSERT INTO `applicant_form_fields` VALUES (100, 8, 0, 'office', 'office_multi', 'Office', NULL, 'N', 'Y', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (101, 8, 0, 'username', 'text', 'Username', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (102, 8, 0, 'password', 'password', 'Password', 64, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (103, 8, 0, 'disable_login', 'combo', 'Client Login Access', NULL, 'N', 'Y', 'N', 'N');

        /* Employer fields */
        INSERT INTO `applicant_form_fields` VALUES (200, 7, 0, 'employer_cra_account_number', 'text', 'CRA Payroll Account Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (201, 7, 0, 'employer_website', 'text', 'Website Address', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (202, 7, 0, 'employer_date_business_started', 'date', 'Date business started', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (203, 7, 0, 'office', 'office_multi', 'Agent\'s Office', NULL, 'N', 'Y', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (204, 7, 0, 'employer_organization_type', 'combo', 'Organization type and structure', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (205, 7, 0, 'username', 'text', 'Username', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (206, 7, 0, 'password', 'password', 'Password', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (207, 7, 0, 'disable_login', 'combo', 'Client Login Access', NULL, 'N', 'Y', 'N', 'N');
                
        INSERT INTO `applicant_form_fields` VALUES (208, 7, 0, 'employer_business_address_line_1', 'text', 'Address Line 1', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (209, 7, 0, 'employer_business_city', 'text', 'City', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (210, 7, 0, 'employer_business_province', 'text', 'Province/Territory/State', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (211, 7, 0, 'employer_business_address_line_2', 'text', 'Address Line 2', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (212, 7, 0, 'employer_business_country', 'text', 'Country', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (213, 7, 0, 'employer_business_postal_code', 'text', 'Postal/Zip Code', NULL, 'N', 'N', 'N', 'N');   
             
        INSERT INTO `applicant_form_fields` VALUES (214, 7, 0, 'employer_mailing_address_line_1', 'text', 'Address Line 1', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (215, 7, 0, 'employer_mailing_city', 'text', 'City', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (216, 7, 0, 'employer_mailing_province', 'text', 'Province/Territory/State', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (217, 7, 0, 'employer_mailing_address_line_2', 'text', 'Address Line 2', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (218, 7, 0, 'employer_mailing_country', 'text', 'Country', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (219, 7, 0, 'employer_mailing_postal_code', 'text', 'Postal/Zip Code', NULL, 'N', 'N', 'N', 'N');

        INSERT INTO `applicant_form_fields` VALUES (220, 7, 0, 'employer_principal_contact_first_name', 'text', 'First Name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (221, 7, 0, 'employer_principal_contact_middle_name', 'text', 'Middle Name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (222, 7, 0, 'employer_principal_contact_last_name', 'text', 'Last Name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (223, 7, 0, 'employer_principal_contact_job_title', 'text', 'Job Title', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (224, 7, 0, 'employer_principal_contact_phone', 'text', 'Telephone Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (225, 7, 0, 'employer_principal_contact_phone_ext', 'text', 'Ext', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (226, 7, 0, 'employer_principal_contact_phone_other', 'text', 'Other Telephone Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (227, 7, 0, 'employer_principal_contact_phone_other_ext', 'text', 'Ext', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (228, 7, 0, 'employer_principal_contact_fax', 'text', 'Fax Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (229, 7, 0, 'employer_principal_contact_email', 'email', 'Email Address', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (230, 7, 0, 'employer_principal_contact_do_not_contact_via_email', 'checkbox', 'Do not contact via email', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (231, 7, 0, 'employer_principal_contact_language_of_correspondence', 'combo', 'Language of Correspondence', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (232, 7, 0, 'employer_principal_contact_address_line_1', 'text', 'Address Line 1', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (233, 7, 0, 'employer_principal_contact_city', 'text', 'City', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (234, 7, 0, 'employer_principal_contact_province', 'text', 'Province/Territory/State', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (235, 7, 0, 'employer_principal_contact_address_line_2', 'text', 'Address Line 2', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (236, 7, 0, 'employer_principal_contact_country', 'text', 'Country', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (237, 7, 0, 'employer_principal_contact_postal_code', 'text', 'Postal/Zip Code', NULL, 'N', 'N', 'N', 'N');   

        INSERT INTO `applicant_form_fields` VALUES (238, 7, 0, 'employer_alternate_contact_first_name', 'text', 'First Name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (239, 7, 0, 'employer_alternate_contact_middle_name', 'text', 'Middle Name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (240, 7, 0, 'employer_alternate_contact_last_name', 'text', 'Last Name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (241, 7, 0, 'employer_alternate_contact_job_title', 'text', 'Job Title', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (242, 7, 0, 'employer_alternate_contact_phone', 'text', 'Telephone Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (243, 7, 0, 'employer_alternate_contact_phone_ext', 'text', 'Ext', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (244, 7, 0, 'employer_alternate_contact_phone_other', 'text', 'Other Telephone Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (245, 7, 0, 'employer_alternate_contact_phone_other_ext', 'text', 'Ext', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (246, 7, 0, 'employer_alternate_contact_fax', 'text', 'Fax Number', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (247, 7, 0, 'employer_alternate_contact_email', 'email', 'Email Address', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (248, 7, 0, 'employer_alternate_contact_do_not_contact_via_email', 'checkbox', 'Do not contact via email', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (249, 7, 0, 'employer_alternate_contact_language_of_correspondence', 'combo', 'Language of Correspondence', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (250, 7, 0, 'employer_alternate_contact_address_line_1', 'text', 'Address Line 1', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (251, 7, 0, 'employer_alternate_contact_city', 'text', 'City', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (252, 7, 0, 'employer_alternate_contact_province', 'text', 'Province/Territory/State', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (253, 7, 0, 'employer_alternate_contact_address_line_2', 'text', 'Address Line 2', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (254, 7, 0, 'employer_alternate_contact_country', 'text', 'Country', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (255, 7, 0, 'employer_alternate_contact_postal_code', 'text', 'Postal/Zip Code', NULL, 'N', 'N', 'N', 'N');   
  
        INSERT INTO `applicant_form_fields` VALUES (256, 7, 0, 'engagement_number', 'text', 'Engagement #', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (257, 7, 0, 'engagement_name', 'text', 'Engagement name', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (258, 7, 0, 'entity_legal_name_common', 'text', 'Legal entity name (common use)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (259, 7, 0, 'other_address_line_1', 'text', 'Address Line 1', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (260, 7, 0, 'contractor_legal_company_name_common', 'text', 'Company common name (common use)', NULL, 'N', 'N', 'N', 'N');
        INSERT INTO `applicant_form_fields` VALUES (261, 7, 0, 'trading_name', 'text', 'Trading Name (Doing Business As)', NULL, 'N', 'N', 'N', 'N');

        /* Contact fields */
        INSERT INTO `applicant_form_fields` VALUES (300, 10, 0, 'office', 'office_multi', 'Office', NULL, 'N', 'Y', 'N', 'N');


        ALTER TABLE `applicant_form_fields` AUTO_INCREMENT = 500;

        INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'HR representative', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Mobility advisor', 1);
        INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Team lead', 2);
        INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Legal counsel', 3);
        INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Other', 4);

        INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Mr.', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Ms.', 1);
        INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Mrs.', 2);
        INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Miss', 3);
        INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Dr.', 4);
        INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Mr. & Mrs.', 5);

        INSERT INTO `applicant_form_default` VALUES (NULL, 27, 'Email', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 27, 'Fax', 1);
        INSERT INTO `applicant_form_default` VALUES (NULL, 27, 'Phone', 2);

        INSERT INTO `applicant_form_default` VALUES (NULL, 33, 'Active', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 33, 'Closed', 1);
        INSERT INTO `applicant_form_default` VALUES (NULL, 33, 'Archived', 2);

        INSERT INTO `applicant_form_default` VALUES (NULL, 53, 'Yes', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 53, 'No', 1);
        
        INSERT INTO `applicant_form_default` VALUES (NULL, 68, 'Active', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 68, 'Inactive', 1);


        INSERT INTO `applicant_form_default` VALUES (NULL, 103, 'Enabled', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 103, 'Disabled', 1);

        INSERT INTO `applicant_form_default` VALUES (NULL, 204, 'Sole proprietor', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 204, 'Partnership', 1);
        INSERT INTO `applicant_form_default` VALUES (NULL, 204, 'Corporation', 2);
        INSERT INTO `applicant_form_default` VALUES (NULL, 204, 'Co-operative', 3);
        INSERT INTO `applicant_form_default` VALUES (NULL, 204, 'Non-profit', 4);
        INSERT INTO `applicant_form_default` VALUES (NULL, 204, 'Registered Charity', 5);
        
        INSERT INTO `applicant_form_default` VALUES (NULL, 207, 'Enabled', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 207, 'Disabled', 1);

        INSERT INTO `applicant_form_default` VALUES (NULL, 231, 'English', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 231, 'French', 1);

        INSERT INTO `applicant_form_default` VALUES (NULL, 249, 'English', 0);
        INSERT INTO `applicant_form_default` VALUES (NULL, 249, 'French', 1);
        
        
        /***** IA *****/
        INSERT INTO `applicant_form_order` VALUES (1, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (1, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (1, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (1, 16, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (1, 17, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (1, 18, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (1, 10, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (1, 59, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (1, 13, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (1, 14, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (1, 15, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (1, 11, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (1, 48, 'N', 15);
        INSERT INTO `applicant_form_order` VALUES (1, 44, 'N', 16);
        INSERT INTO `applicant_form_order` VALUES (1, 12, 'N', 18);
        INSERT INTO `applicant_form_order` VALUES (1, 57, 'N', 19);
        INSERT INTO `applicant_form_order` VALUES (1, 100, 'N', 20);
        INSERT INTO `applicant_form_order` VALUES (1, 19, 'N', 23);
        
        INSERT INTO `applicant_form_order` VALUES (2, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (2, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (2, 60, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (2, 22, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (2, 23, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (2, 24, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (2, 25, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (2, 55, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (2, 3, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (2, 56, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (2, 6, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (2, 7, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (2, 34, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (2, 61, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (2, 26, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (2, 35, 'N', 15);
        INSERT INTO `applicant_form_order` VALUES (2, 36, 'N', 16);
        INSERT INTO `applicant_form_order` VALUES (2, 27, 'N', 17);
        INSERT INTO `applicant_form_order` VALUES (2, 62, 'N', 18);
        INSERT INTO `applicant_form_order` VALUES (2, 63, 'N', 19);
        INSERT INTO `applicant_form_order` VALUES (2, 64, 'N', 20);
        INSERT INTO `applicant_form_order` VALUES (2, 65, 'N', 21);
        INSERT INTO `applicant_form_order` VALUES (2, 28, 'N', 22);
        
        INSERT INTO `applicant_form_order` VALUES (3, 101, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (3, 102, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (3, 103, 'N', 2);

        /***** Employer *****/
        INSERT INTO `applicant_form_order` VALUES (4, 29, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (4, 200, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (4, 203, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (4, 6, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (4, 201, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (4, 202, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (4, 204, 'N', 6);
        
        INSERT INTO `applicant_form_order` VALUES (5, 208, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (5, 211, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (5, 209, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (5, 210, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (5, 212, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (5, 213, 'N', 5);        
        
        INSERT INTO `applicant_form_order` VALUES (6, 214, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (6, 217, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (6, 215, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (6, 216, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (6, 218, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (6, 219, 'N', 5);
        
        INSERT INTO `applicant_form_order` VALUES (7, 220, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (7, 221, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (7, 222, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (7, 223, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (7, 224, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (7, 225, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (7, 226, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (7, 227, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (7, 228, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (7, 229, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (7, 230, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (7, 231, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (7, 232, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (7, 235, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (7, 233, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (7, 234, 'N', 15);
        INSERT INTO `applicant_form_order` VALUES (7, 236, 'N', 16);
        INSERT INTO `applicant_form_order` VALUES (7, 237, 'N', 17);     
           
        INSERT INTO `applicant_form_order` VALUES (8, 238, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (8, 239, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (8, 240, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (8, 241, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (8, 242, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (8, 243, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (8, 244, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (8, 245, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (8, 246, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (8, 247, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (8, 248, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (8, 249, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (8, 250, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (8, 253, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (8, 251, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (8, 252, 'N', 15);
        INSERT INTO `applicant_form_order` VALUES (8, 254, 'N', 16);
        INSERT INTO `applicant_form_order` VALUES (8, 255, 'N', 17);
        
        INSERT INTO `applicant_form_order` VALUES (9, 205, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (9, 206, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (9, 207, 'N', 2);
        
        /***** Contact *****/
        INSERT INTO `applicant_form_order` VALUES (10, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (10, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (10, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (10, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (10, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (10, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (10, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (10, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (10, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (10, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (10, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (10, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (10, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (10, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (10, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (11, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (11, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (11, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (11, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (11, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (11, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (11, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (11, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (11, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (11, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (11, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (11, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (11, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (11, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (11, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (12, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (13, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (13, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (13, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (13, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (13, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (13, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (13, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (13, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (13, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (13, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (13, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (13, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (13, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (13, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (13, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (14, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (14, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (14, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (14, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (14, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (14, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (14, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (14, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (14, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (14, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (14, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (14, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (14, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (14, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (14, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (15, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (16, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (16, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (16, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (16, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (16, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (16, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (16, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (16, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (16, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (16, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (16, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (16, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (16, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (16, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (16, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (17, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (17, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (17, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (17, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (17, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (17, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (17, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (17, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (17, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (17, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (17, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (17, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (17, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (17, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (17, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (18, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (19, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (19, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (19, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (19, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (19, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (19, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (19, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (19, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (19, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (19, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (19, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (19, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (19, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (19, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (19, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (20, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (20, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (20, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (20, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (20, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (20, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (20, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (20, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (20, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (20, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (20, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (20, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (20, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (20, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (20, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (21, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (22, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (22, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (22, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (22, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (22, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (22, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (22, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (22, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (22, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (22, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (22, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (22, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (22, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (22, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (22, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (23, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (23, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (23, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (23, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (23, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (23, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (23, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (23, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (23, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (23, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (23, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (23, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (23, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (23, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (23, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (24, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (25, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (25, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (25, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (25, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (25, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (25, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (25, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (25, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (25, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (25, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (25, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (25, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (25, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (25, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (25, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (26, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (26, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (26, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (26, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (26, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (26, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (26, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (26, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (26, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (26, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (26, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (26, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (26, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (26, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (26, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (27, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (28, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (28, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (28, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (28, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (28, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (28, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (28, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (28, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (28, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (28, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (28, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (28, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (28, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (28, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (28, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (29, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (29, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (29, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (29, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (29, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (29, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (29, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (29, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (29, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (29, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (29, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (29, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (29, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (29, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (29, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (30, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (31, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (31, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (31, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (31, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (31, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (31, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (31, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (31, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (31, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (31, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (31, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (31, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (31, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (31, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (31, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (32, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (32, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (32, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (32, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (32, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (32, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (32, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (32, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (33, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (32, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (32, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (32, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (32, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (32, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (32, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (33, 37, 'Y', 1);
        
        INSERT INTO `applicant_form_order` VALUES (34, 9, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (34, 1, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (34, 2, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (34, 67, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (34, 68, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (34, 19, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (34, 38, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (34, 51, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (34, 300, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (34, 32, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (34, 62, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (34, 63, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (34, 64, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (34, 65, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (34, 66, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (35, 20, 'N', 0);
        INSERT INTO `applicant_form_order` VALUES (35, 21, 'N', 1);
        INSERT INTO `applicant_form_order` VALUES (35, 22, 'N', 2);
        INSERT INTO `applicant_form_order` VALUES (35, 23, 'N', 3);
        INSERT INTO `applicant_form_order` VALUES (35, 24, 'N', 4);
        INSERT INTO `applicant_form_order` VALUES (35, 25, 'N', 5);
        INSERT INTO `applicant_form_order` VALUES (35, 55, 'N', 6);
        INSERT INTO `applicant_form_order` VALUES (35, 3, 'N', 7);
        INSERT INTO `applicant_form_order` VALUES (35, 56, 'N', 8);
        INSERT INTO `applicant_form_order` VALUES (35, 6, 'N', 9);
        INSERT INTO `applicant_form_order` VALUES (35, 7, 'N', 10);
        INSERT INTO `applicant_form_order` VALUES (35, 34, 'N', 11);
        INSERT INTO `applicant_form_order` VALUES (35, 35, 'N', 12);
        INSERT INTO `applicant_form_order` VALUES (35, 26, 'N', 13);
        INSERT INTO `applicant_form_order` VALUES (35, 36, 'N', 14);
        INSERT INTO `applicant_form_order` VALUES (36, 37, 'Y', 1);
EOD;

         $this->execute($allQueries);
    }

    public function down()
    {
    }
}