DROP TABLE IF EXISTS `applicant_types`;
CREATE TABLE `applicant_types` (
    `applicant_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_type_id` INT(2) UNSIGNED NOT NULL,
    `company_id` BIGINT(20) NULL DEFAULT NULL,
    `applicant_type_name` VARCHAR(100) NULL DEFAULT NULL,
    `is_system` ENUM('Y','N') NOT NULL DEFAULT 'N',
    PRIMARY KEY (`applicant_type_id`),
    INDEX `FK_client_types_company` (`company_id`),
    CONSTRAINT `FK_applicant_types_1` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_applicant_types_2` FOREIGN KEY (`member_type_id`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `applicant_types` (`applicant_type_id`, `member_type_id`, `company_id`, `applicant_type_name`, `is_system`) VALUES (1, 10, 0, 'Sales Agent', 'Y');
INSERT INTO `applicant_types` (`applicant_type_id`, `member_type_id`, `company_id`, `applicant_type_name`, `is_system`) VALUES (2, 10, 0, 'Translator', 'Y');
INSERT INTO `applicant_types` (`applicant_type_id`, `member_type_id`, `company_id`, `applicant_type_name`, `is_system`) VALUES (3, 10, 0, 'Visa Office', 'Y');

DROP TABLE IF EXISTS `applicant_form_blocks`;
CREATE TABLE `applicant_form_blocks` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* IA blocks */
INSERT INTO `applicant_form_blocks` VALUES (1, 8, 0, NULL, 'Y', 'N', 0);
INSERT INTO `applicant_form_blocks` VALUES (2, 8, 0, NULL, 'N', 'N', 1);
INSERT INTO `applicant_form_blocks` VALUES (3, 8, 0, NULL, 'N', 'N', 2);

/* Employer blocks */
INSERT INTO `applicant_form_blocks` VALUES (4, 7, 0, NULL, 'Y', 'N', 0);
INSERT INTO `applicant_form_blocks` VALUES (5, 7, 0, NULL, 'Y', 'Y', 1);
INSERT INTO `applicant_form_blocks` VALUES (6, 7, 0, NULL, 'N', 'Y', 2);
INSERT INTO `applicant_form_blocks` VALUES (7, 7, 0, NULL, 'N', 'Y', 3);
INSERT INTO `applicant_form_blocks` VALUES (8, 7, 0, NULL, 'N', 'Y', 4);
INSERT INTO `applicant_form_blocks` VALUES (9, 7, 0, NULL, 'N', 'Y', 5);

/* Other Contacts -> Sales Agent */
INSERT INTO `applicant_form_blocks` VALUES (10, 10, 0, 1, 'Y', 'N', 0);

/* Other Contacts -> Visa Office */
INSERT INTO `applicant_form_blocks` VALUES (11, 10, 0, 3, 'Y', 'N', 0);


ALTER TABLE `applicant_form_blocks` AUTO_INCREMENT = 100;

DROP TABLE IF EXISTS `applicant_form_groups`;
CREATE TABLE `applicant_form_groups` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* IA groups */
INSERT INTO `applicant_form_groups` VALUES (1, 1, 0, 'Primary Applicant Personal Info', 3, 'N', 0);
INSERT INTO `applicant_form_groups` VALUES (2, 1, 0, 'Business Contact info', 3, 'Y', 1);
INSERT INTO `applicant_form_groups` VALUES (3, 2, 0, 'For Office Use',    3, 'Y', 0);
INSERT INTO `applicant_form_groups` VALUES (4, 3, 0, 'Client Login Info', 3, 'Y', 1);

/* Employer groups */
INSERT INTO `applicant_form_groups` VALUES (5,  4, 0, 'Company Information',     3, 'N', 0);
INSERT INTO `applicant_form_groups` VALUES (6,  5, 0, 'Authorised Contacts',     3, 'Y', 1);
INSERT INTO `applicant_form_groups` VALUES (7,  6, 0, 'Engagements',             3, 'Y', 2);
INSERT INTO `applicant_form_groups` VALUES (8,  7, 0, 'Other legal entities',    3, 'Y', 3);
INSERT INTO `applicant_form_groups` VALUES (9,  8, 0, 'Other locations',         3, 'Y', 4);
INSERT INTO `applicant_form_groups` VALUES (10, 9, 0, 'Third party contractors', 3, 'Y', 5);

/* Other Contacts -> Sales Agent */
INSERT INTO `applicant_form_groups` VALUES (11,  10, 0, 'Contact Information',       4, 'N', 0);
INSERT INTO `applicant_form_groups` VALUES (12,  10, 0, 'Address & Contact Details', 4, 'Y', 1);
INSERT INTO `applicant_form_groups` VALUES (13,  10, 0, 'Notes & Comments',          4, 'Y', 2);

/* Other Contacts -> Visa Office */
INSERT INTO `applicant_form_groups` VALUES (14,  11, 0, 'Contact Information',       4, 'N', 0);
INSERT INTO `applicant_form_groups` VALUES (15,  11, 0, 'Address & Contact Details', 4, 'Y', 1);
INSERT INTO `applicant_form_groups` VALUES (16,  11, 0, 'Notes & Comments',          4, 'Y', 2);

ALTER TABLE `applicant_form_groups` AUTO_INCREMENT = 100;


DROP TABLE IF EXISTS `applicant_form_fields`;
CREATE TABLE `applicant_form_fields` (
    `applicant_field_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_type_id` INT(2) UNSIGNED NOT NULL,
    `company_id` BIGINT(20) NOT NULL,
    `applicant_field_unique_id` VARCHAR(100) NULL DEFAULT NULL,
    `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','auto_calculated') NOT NULL DEFAULT 'text',
    `label` CHAR(255) NULL DEFAULT NULL,
    `maxlength` INT(6) UNSIGNED NULL DEFAULT NULL,
    `encrypted` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `required` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `disabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `blocked` ENUM('Y','N') NOT NULL DEFAULT 'N',
    PRIMARY KEY (`applicant_field_id`),
  CONSTRAINT `FK_applicant_form_fields_1` FOREIGN KEY (`member_type_id`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_applicant_form_fields_2` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/***** INTERNAL CONTACT *****/
INSERT INTO `applicant_form_fields` VALUES (1,  9, 0, 'first_name', 'text', 'First name', 64, 'N', 'Y', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (2,  9, 0, 'last_name', 'text', 'Last name', 64, 'N', 'Y', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (3,  9, 0, 'phone_h', 'phone', 'Phone (Home)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (4,  9, 0, 'phone_secondary', 'phone', 'Phone (Secondary)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (5,  9, 0, 'phone_main', 'phone', 'Phone (Main)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (6,  9, 0, 'email', 'email', 'Email (Primary)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (7,  9, 0, 'email_1', 'email', 'Email (Other)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (8,  9, 0, 'contact_type', 'combo', 'Contact type', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (9,  9, 0, 'title', 'combo', 'Salutation', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (10, 9, 0, 'DOB', 'date_repeatable', 'Date of Birth', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (11, 9, 0, 'passport_number', 'text', 'Passport #', 16, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (12, 9, 0, 'passport_exp_date', 'date_repeatable', 'Date of Birth', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (13, 9, 0, 'country_of_birth', 'text', 'Country of Birth', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (14, 9, 0, 'country_of_residence', 'text', 'Country of Residence', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (15, 9, 0, 'country_of_citizenship', 'text', 'Country of Citizenship', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (16, 9, 0, 'salutation_in_native_lang', 'text', 'Salutation in Native Language', 20, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (17, 9, 0, 'name_in_native_lang', 'text', 'Name in Native Language', 50, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (18, 9, 0, 'preferred_language', 'text', 'Preferred Language', 20, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (19, 9, 0, 'photo', 'photo', 'Photo', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (20, 9, 0, 'address_1', 'text', 'Address 1', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (21, 9, 0, 'address_2', 'text', 'Address 2', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (22, 9, 0, 'city', 'text', 'City', 64, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (23, 9, 0, 'state', 'text', 'Province/State', 64, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (24, 9, 0, 'country', 'text', 'Country', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (25, 9, 0, 'zip_code', 'text', 'Postal/zip code', 16, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (26, 9, 0, 'fax_w', 'phone', 'Fax', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (27, 9, 0, 'pref_contact_method', 'combo', 'Preferred Contact Method', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (28, 9, 0, 'special_instruction', 'memo', 'Special Instruction', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (29, 9, 0, 'entity_name', 'text', 'Entity Name', NULL, 'N', 'Y', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (30, 9, 0, 'company_legal_name', 'text', 'Company Legal Name', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (31, 9, 0, 'company_activity', 'text', 'Company Activity', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (32, 9, 0, 'website', 'text', 'Website', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (33, 9, 0, 'status', 'combo', 'Status', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (34, 9, 0, 'email_2', 'email', 'Email 2', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (35, 9, 0, 'fax_home', 'phone', 'Fax (Home)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (36, 9, 0, 'fax_other', 'phone', 'Fax (Others)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (37, 9, 0, 'notes', 'memo', 'Notes', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (38, 9, 0, 'entered_queue_on', 'office_change_date_time', 'Entered Queue On', NULL, 'N', 'N', 'N', 'N');

/***** EMPLOYER *****/
/* Company info (main section)  */
INSERT INTO `applicant_form_fields` VALUES (133, 7, 0, 'company_date_business_started',    'date',   'Date business started',             NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (134, 7, 0, 'company_cra_business_number',      'text',   'C.R.A. business number',            NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (135, 7, 0, 'company_number_cdn_employees',     'number', 'Number of Canadian employees',      NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (136, 7, 0, 'company_number_foreign_employees', 'number', 'Number of foreign employees',       NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (137, 7, 0, 'company_number_lay_offs',          'number', 'Number lay offs in last 12 months', NULL, 'N', 'N', 'N', 'N');

/* Engagements  */
INSERT INTO `applicant_form_fields` VALUES (138, 7, 0, 'engagement_number',      'text', 'Engagement #',    NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (139, 7, 0, 'engagement_name',        'text', 'Engagement name', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (140, 7, 0, 'engagement_description', 'text', 'Engagement description', NULL, 'N', 'N', 'N', 'N');

/* Other legal entities  */
INSERT INTO `applicant_form_fields` VALUES (141, 7, 0, 'entity_legal_name',            'text', 'Legal name of entity',           NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (142, 7, 0, 'entity_legal_name_common',     'text', 'Legal entity name (common use)', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (143, 7, 0, 'entity_date_business_started', 'date', 'Date business started',          NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (144, 7, 0, 'entity_cra_business_number',   'text', 'C.R.A. business number',         NULL, 'N', 'N', 'N', 'N');

/* Other locations */
INSERT INTO `applicant_form_fields` VALUES (145, 7, 0, 'other_address_line_1',             'text',   'Address line 1',                    NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (146, 7, 0, 'other_address_line_2',             'text',   'Address line 2',                    NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (147, 7, 0, 'other_province',                   'text',   'City province/state',               NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (148, 7, 0, 'other_country',                    'text',   'Country',                           NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (149, 7, 0, 'other_postal_code',                'text',   'Postal code',                       NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (150, 7, 0, 'other_city',                       'text',   'City',                              NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (151, 7, 0, 'other_number_cdn_employees',       'number', 'Number of Canadian employees',      NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (152, 7, 0, 'other_number_foreign_employees',   'number', 'Number of foreign employees',       NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (153, 7, 0, 'other_number_lay_offs',            'number', 'Number lay offs in last 12 months', NULL, 'N', 'N', 'N', 'N');

/* Third party contractors  */
INSERT INTO `applicant_form_fields` VALUES (154, 7, 0, 'contractor_legal_company_name_common', 'text',         'Company common name (common use)',  NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (155, 7, 0, 'contractor_legal_company_name',        'text',         'Legal name of company',             NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (156, 7, 0, 'contractor_business_type',             'text',         'Type of business',                  NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (157, 7, 0, 'contractor_business_description',      'text',         'Description of business',           NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (158, 7, 0, 'contractor_date_business_started',     'date',         'Date business started',             NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (159, 7, 0, 'contractor_address_line_1',            'text',         'Address line 1',                    NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (160, 7, 0, 'contractor_address_line_2',            'text',         'Address line 2',                    NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (161, 7, 0, 'contractor_province',                  'text',         'City province/state',               NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (162, 7, 0, 'contractor_country',                   'text',         'Country',                           NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (163, 7, 0, 'contractor_postal_code',               'text',         'Postal code',                       NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (164, 7, 0, 'contractor_city',                      'text',         'City',                              NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (165, 7, 0, 'contractor_cra_business_number',       'text',         'C.R.A. business number',            NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (166, 7, 0, 'contractor_number_cdn_employees',      'number',       'Number of Canadian employees',      NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (167, 7, 0, 'contractor_number_foreign_employees',  'number',       'Number of foreign employees',       NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (168, 7, 0, 'contractor_number_lay_offs',           'number',       'Number lay offs in last 12 months', NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (169, 7, 0, 'office',                               'office_multi', 'Office',                            NULL, 'N', 'Y', 'N', 'N');

/***** IA *****/
INSERT INTO `applicant_form_fields` VALUES (170, 8, 0, 'date_client_signed',     'date',           'Date Client Signed',            NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (171, 8, 0, 'file_number',            'text',           'File Number',                     32, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (173, 8, 0, 'agent',                  'agents',         'Sales Agent',                   NULL, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (174, 8, 0, 'office',                 'office_multi',   'Office',                        NULL, 'N', 'Y', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (175, 8, 0, 'username',               'text',           'Username (for client login)',     64, 'N', 'N', 'N', 'N');
INSERT INTO `applicant_form_fields` VALUES (176, 8, 0, 'password',               'password',       'Password',                        64, 'N', 'N', 'N', 'N');

/***** Contact *****/
INSERT INTO `applicant_form_fields` VALUES (177, 10, 0, 'office', 'office_multi', 'Office', NULL, 'N', 'Y', 'N', 'N');

ALTER TABLE `applicant_form_fields` AUTO_INCREMENT = 500;

DROP TABLE IF EXISTS `applicant_form_default`;
CREATE TABLE `applicant_form_default` (
    `applicant_form_default_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `applicant_field_id` INT(11) UNSIGNED NULL DEFAULT NULL,
    `value` TEXT NULL,
    `order` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (`applicant_form_default_id`),
    INDEX `FK_applicant_form_default_1` (`applicant_field_id`),
    CONSTRAINT `FK_applicant_form_default_1` FOREIGN KEY (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'HR representative', 0);
INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Mobility advisor',  1);
INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Team lead',         2);
INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Legal counsel',     3);
INSERT INTO `applicant_form_default` VALUES (NULL, 8, 'Other',             4);

INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Mr.',  0);
INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Ms.',  1);
INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Mrs.', 2);
INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Miss', 3);
INSERT INTO `applicant_form_default` VALUES (NULL, 9, 'Dr.',  4);

INSERT INTO `applicant_form_default` VALUES (NULL, 27, 'Email', 0);
INSERT INTO `applicant_form_default` VALUES (NULL, 27, 'Fax',   1);
INSERT INTO `applicant_form_default` VALUES (NULL, 27, 'Phone', 2);

INSERT INTO `applicant_form_default` VALUES (NULL, 33, 'Active',   0);
INSERT INTO `applicant_form_default` VALUES (NULL, 33, 'Closed',   1);
INSERT INTO `applicant_form_default` VALUES (NULL, 33, 'Archived', 2);


DROP TABLE IF EXISTS `applicant_form_order`;
CREATE TABLE `applicant_form_order` (
  `applicant_group_id` INT(11) UNSIGNED NOT NULL,
  `applicant_field_id` INT(11) UNSIGNED NOT NULL,
  `use_full_row` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `field_order` TINYINT(3) UNSIGNED DEFAULT 1,
  PRIMARY KEY  (`applicant_group_id`, `applicant_field_id`),
  CONSTRAINT `FK_applicant_form_order_1` FOREIGN KEY `FK_applicant_form_order_1` (`applicant_group_id`) REFERENCES `applicant_form_groups` (`applicant_group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_applicant_form_order_2` FOREIGN KEY `FK_applicant_form_order_2` (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/***** IA *****/
INSERT INTO `applicant_form_order` VALUES (1, 9, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (1, 1, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (1, 2, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (1, 10, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (1, 11, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (1, 12, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (1, 13, 'N', 7);
INSERT INTO `applicant_form_order` VALUES (1, 14, 'N', 8);
INSERT INTO `applicant_form_order` VALUES (1, 15, 'N', 9);
INSERT INTO `applicant_form_order` VALUES (1, 16, 'N', 10);
INSERT INTO `applicant_form_order` VALUES (1, 17, 'N', 11);
INSERT INTO `applicant_form_order` VALUES (1, 18, 'N', 12);
INSERT INTO `applicant_form_order` VALUES (1, 19, 'N', 13);

INSERT INTO `applicant_form_order` VALUES (2, 20, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (2, 21, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (2, 22, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (2, 23, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (2, 24, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (2, 25, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (2, 5, 'N', 7);
INSERT INTO `applicant_form_order` VALUES (2, 3, 'N', 8);
INSERT INTO `applicant_form_order` VALUES (2, 4, 'N', 9);
INSERT INTO `applicant_form_order` VALUES (2, 6, 'N', 10);
INSERT INTO `applicant_form_order` VALUES (2, 7, 'N', 11);
INSERT INTO `applicant_form_order` VALUES (2, 26, 'N', 12);
INSERT INTO `applicant_form_order` VALUES (2, 27, 'N', 13);
INSERT INTO `applicant_form_order` VALUES (2, 28, 'N', 14);

INSERT INTO `applicant_form_order` VALUES (3, 170, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (3, 171, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (3, 33, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (3, 173, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (3, 174, 'N', 5);

INSERT INTO `applicant_form_order` VALUES (4, 175, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (4, 176, 'N', 2);


/***** EMPLOYER *****/
INSERT INTO `applicant_form_order` VALUES (5, 29, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (5, 30, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (5, 31, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (5, 169, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (5, 20, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (5, 21, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (5, 22, 'N', 7);
INSERT INTO `applicant_form_order` VALUES (5, 23, 'N', 8);
INSERT INTO `applicant_form_order` VALUES (5, 24, 'N', 9);
INSERT INTO `applicant_form_order` VALUES (5, 25, 'N', 10);
INSERT INTO `applicant_form_order` VALUES (5,  4, 'N', 11);
INSERT INTO `applicant_form_order` VALUES (5,  5, 'N', 12);
INSERT INTO `applicant_form_order` VALUES (5, 26, 'N', 13);
INSERT INTO `applicant_form_order` VALUES (5, 32, 'N', 14);
INSERT INTO `applicant_form_order` VALUES (5, 133, 'N', 15);
INSERT INTO `applicant_form_order` VALUES (5, 134, 'N', 16);
INSERT INTO `applicant_form_order` VALUES (5, 135, 'N', 17);
INSERT INTO `applicant_form_order` VALUES (5, 136, 'N', 18);
INSERT INTO `applicant_form_order` VALUES (5, 137, 'N', 19);

INSERT INTO `applicant_form_order` VALUES (6, 1, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (6, 2, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (6, 5, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (6, 4, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (6, 6, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (6, 7, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (6, 8, 'N', 7);

INSERT INTO `applicant_form_order` VALUES (7, 138, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (7, 139, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (7, 140, 'N', 3);

INSERT INTO `applicant_form_order` VALUES (8, 141, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (8, 142, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (8, 143, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (8, 144, 'N', 4);

INSERT INTO `applicant_form_order` VALUES (9, 145, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (9, 146, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (9, 147, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (9, 148, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (9, 149, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (9, 150, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (9, 151, 'N', 7);
INSERT INTO `applicant_form_order` VALUES (9, 152, 'N', 8);
INSERT INTO `applicant_form_order` VALUES (9, 153, 'N', 9);

INSERT INTO `applicant_form_order` VALUES (10, 154, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (10, 155, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (10, 156, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (10, 157, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (10, 158, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (10, 159, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (10, 160, 'N', 7);
INSERT INTO `applicant_form_order` VALUES (10, 161, 'N', 8);
INSERT INTO `applicant_form_order` VALUES (10, 162, 'N', 9);
INSERT INTO `applicant_form_order` VALUES (10, 163, 'N', 10);
INSERT INTO `applicant_form_order` VALUES (10, 164, 'N', 11);
INSERT INTO `applicant_form_order` VALUES (10, 165, 'N', 12);
INSERT INTO `applicant_form_order` VALUES (10, 166, 'N', 13);
INSERT INTO `applicant_form_order` VALUES (10, 167, 'N', 14);
INSERT INTO `applicant_form_order` VALUES (10, 168, 'N', 15);

/* Other Contacts -> Sales Agent */
INSERT INTO `applicant_form_order` VALUES (11, 9, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (11, 1, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (11, 2, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (11, 33, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (11, 19, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (11, 177, 'N', 6);

INSERT INTO `applicant_form_order` VALUES (12, 20, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (12, 21, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (12, 22, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (12, 24, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (12, 23, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (12, 25, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (12, 5, 'N', 7);
INSERT INTO `applicant_form_order` VALUES (12, 3, 'N', 8);
INSERT INTO `applicant_form_order` VALUES (12, 4, 'N', 9);
INSERT INTO `applicant_form_order` VALUES (12, 6, 'N', 10);
INSERT INTO `applicant_form_order` VALUES (12, 7, 'N', 11);
INSERT INTO `applicant_form_order` VALUES (12, 34, 'N', 12);
INSERT INTO `applicant_form_order` VALUES (12, 35, 'N', 13);
INSERT INTO `applicant_form_order` VALUES (12, 26, 'N', 14);
INSERT INTO `applicant_form_order` VALUES (12, 36, 'N', 15);

INSERT INTO `applicant_form_order` VALUES (13, 37, 'Y', 1);

/* Other Contacts -> Visa Office */
INSERT INTO `applicant_form_order` VALUES (14, 9, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (14, 1, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (14, 2, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (14, 33, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (14, 177, 'N', 5);

INSERT INTO `applicant_form_order` VALUES (15, 20, 'N', 1);
INSERT INTO `applicant_form_order` VALUES (15, 21, 'N', 2);
INSERT INTO `applicant_form_order` VALUES (15, 22, 'N', 3);
INSERT INTO `applicant_form_order` VALUES (15, 24, 'N', 4);
INSERT INTO `applicant_form_order` VALUES (15, 23, 'N', 5);
INSERT INTO `applicant_form_order` VALUES (15, 25, 'N', 6);
INSERT INTO `applicant_form_order` VALUES (15, 5, 'N', 7);
INSERT INTO `applicant_form_order` VALUES (15, 4, 'N', 8);
INSERT INTO `applicant_form_order` VALUES (15, 6, 'N', 9);
INSERT INTO `applicant_form_order` VALUES (15, 7, 'N', 10);
INSERT INTO `applicant_form_order` VALUES (15, 34, 'N', 11);
INSERT INTO `applicant_form_order` VALUES (15, 26, 'N', 12);
INSERT INTO `applicant_form_order` VALUES (15, 36, 'N', 13);

INSERT INTO `applicant_form_order` VALUES (16, 37, 'Y', 1);



DROP TABLE IF EXISTS `applicant_form_fields_access`;
CREATE TABLE `applicant_form_fields_access` (
  `role_id` INT(11) DEFAULT NULL,
  `applicant_group_id` INT(11) UNSIGNED NOT NULL,
  `applicant_field_id` INT(11) UNSIGNED NOT NULL,
  `status` ENUM('R','F') NOT NULL DEFAULT 'R' COMMENT 'R=read only, F=full access',
  PRIMARY KEY  (`role_id`, `applicant_group_id`, `applicant_field_id`),
  CONSTRAINT `FK_applicant_form_fields_access_1` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_applicant_form_fields_access_2` FOREIGN KEY (`applicant_group_id`) REFERENCES `applicant_form_groups` (`applicant_group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_applicant_form_fields_access_3` FOREIGN KEY (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `applicant_form_data`;
CREATE TABLE `applicant_form_data` (
  `applicant_id` BIGINT(20) NOT NULL,
  `applicant_field_id` INT(11) UNSIGNED NOT NULL,
  `value` text,
  `row` TINYINT(2) UNSIGNED NOT NULL,
  `row_id` VARCHAR(32) NULL,
  CONSTRAINT `FK_applicant_form_data_1` FOREIGN KEY (`applicant_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_applicant_form_data_2` FOREIGN KEY (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `members_relations`;
CREATE TABLE `members_relations` (
  `parent_member_id` BIGINT(20) NOT NULL,
  `child_member_id` BIGINT(20) NOT NULL,
  `applicant_group_id` INT(11) UNSIGNED NULL,
  `row` TINYINT(2) UNSIGNED NULL,
  INDEX `FK_members_relations` (`parent_member_id`, `child_member_id`),
  CONSTRAINT `FK_members_relations_1` FOREIGN KEY (`parent_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_members_relations_2` FOREIGN KEY (`child_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_members_relations_3` FOREIGN KEY (`applicant_group_id`) REFERENCES `applicant_form_groups` (`applicant_group_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;