<?php

use Officio\Migration\AbstractMigration;

class NewFieldTypes extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `field_types` (
                `field_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `field_type_text_id` VARCHAR(255),
                `field_type_text_aliases` VARCHAR(255) DEFAULT NULL,
                `field_type_label` VARCHAR(255),
                `field_type_can_be_used_in_search` ENUM('Y','N') NOT NULL DEFAULT 'N',
                `field_type_can_be_encrypted` ENUM('Y','N') NOT NULL DEFAULT 'N',
                `field_type_with_max_length` ENUM('Y','N') NOT NULL DEFAULT 'N',
                `field_type_with_options` ENUM('Y','N') NOT NULL DEFAULT 'N',
                `field_type_with_default_value` ENUM('Y','N') NOT NULL DEFAULT 'N',
                `field_type_use_for` ENUM('case','all', 'others') NOT NULL DEFAULT 'all',
                PRIMARY KEY (`field_type_id`)
            ) COLLATE='utf8_general_ci' ENGINE=InnoDB;"
        );

        $this->execute("INSERT INTO `field_types` VALUES (1, 'text', NULL, 'Text', 'Y', 'Y', 'Y', 'N', 'Y', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (2, 'password', NULL, 'Password', 'N', 'N', 'Y', 'N', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (3, 'combo', 'select, combobox', 'Select box', 'Y', 'N', 'N', 'Y', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (4, 'country', NULL, 'Country', 'Y', 'N', 'N', 'N', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (5, 'number', NULL, 'Number', 'Y', 'Y', 'Y', 'N', 'Y', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (6, 'radio', NULL, 'Radio buttons', 'N', 'N', 'N', 'Y', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (7, 'checkbox', NULL, 'Checkbox', 'N', 'N', 'N', 'N', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (8, 'date', NULL, 'Date picker', 'Y', 'Y', 'N', 'N', 'Y', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (9, 'email', NULL, 'Email', 'Y', 'Y', 'N', 'N', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (10, 'phone', 'fax', 'Phone/Fax', 'Y', 'Y', 'N', 'N', 'Y', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (11, 'memo', NULL, 'Memo', 'Y', 'Y', 'N', 'N', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (12, 'agents', 'agent_id, agent', 'Agents', 'Y', 'N', 'N', 'N', 'Y', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (13, 'office', 'division', '%office_label%', 'Y', 'N', 'N', 'Y', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (14, 'assigned_to', 'assigned', 'Assigned to', 'Y', 'N', 'N', 'N', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (15, 'date_repeatable', 'rdate', 'Repeatable Date picker', 'Y', 'Y', 'N', 'N', 'Y', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (16, 'photo', 'image', 'Photo', 'N', 'N', 'N', 'Y', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (17, 'file', NULL, 'File', 'N', 'N', 'N', 'Y', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (18, 'employee', NULL, 'Employee', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (19, 'employer_contacts', NULL, 'Employer Contacts', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (20, 'employer_engagements', NULL, 'Employer Engagements', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (21, 'employer_legal_entities', NULL, 'Employer Legal Entities', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (22, 'employer_locations', NULL, 'Employer Locations', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (23, 'employer_third_party_representatives', NULL, 'Employer Third Party Representatives', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (24, 'office_multi', NULL, '%office_label% (multiple)', 'N', 'N', 'N', 'Y', 'N', 'others');");
        $this->execute("INSERT INTO `field_types` VALUES (25, 'contact_sales_agent', NULL, 'Sales Agent', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (26, 'immigration_office', 'visa_office', 'Immigration Offices', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (27, 'staff_responsible_rma', NULL, 'Authorized Representative', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (28, 'active_users', NULL, 'Active Users', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (29, 'employer_case_link', NULL, 'Employer Sponsorship Case', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (30, 'categories', NULL, 'Subclasses', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (31, 'list_of_occupations', NULL, 'List of Occupations', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (32, 'related_case_selection', NULL, 'Related Case Selection', 'N', 'N', 'N', 'N', 'N', 'case');");
        $this->execute("INSERT INTO `field_types` VALUES (33, 'office_change_date_time', NULL, '%office_label% change date/time', 'N', 'Y', 'N', 'N', 'N', 'others');");
        $this->execute("INSERT INTO `field_types` VALUES (34, 'multiple_text_fields', NULL, 'Multiple Text Fields', 'N', 'N', 'N', 'N', 'N', 'all');");
        $this->execute("INSERT INTO `field_types` VALUES (35, 'auto_calculated', NULL, 'Auto calculated field', 'N', 'N', 'N', 'Y', 'N', 'all');");

        // FOR CA
        $this->execute("UPDATE `field_types` SET `field_type_label`='Visa Offices' WHERE  `field_type_id`=26;");
        $this->execute("UPDATE `field_types` SET `field_type_label`='Categories' WHERE  `field_type_id`=30;");

        $this->execute("ALTER TABLE `client_form_fields` CHANGE COLUMN `type` `type` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `company_field_id`;");
        $this->execute("ALTER TABLE `client_form_fields` ADD CONSTRAINT `FK_client_form_fields_field_types` FOREIGN KEY (`type`) REFERENCES `field_types` (`field_type_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields` DROP FOREIGN KEY `FK_client_form_fields_field_types`;");
        $this->execute("DROP TABLE `field_types`");
    }
}
