DROP TABLE IF EXISTS `company_prospects_notes`;
CREATE TABLE `company_prospects_notes` (
    `note_id` bigint(20) unsigned NOT NULL auto_increment,
    `prospect_id` bigint(20) default NULL,
    `author_id` int(11) unsigned default NULL,
    `note` text,
    `create_date` datetime default NULL,
    PRIMARY KEY  (`note_id`),
    CONSTRAINT `FK_company_prospects_notes_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `company_prospects_templates`;
CREATE TABLE `company_prospects_templates` (
  `prospect_template_id` INT(11) UNSIGNED NOT NULL auto_increment,
  `company_id` bigint(20) NOT NULL,
  `author_id` bigint(20) NOT NULL,
  `name` char(255) DEFAULT NULL,
  `subject` char(255) DEFAULT NULL,
  `from` char(255) DEFAULT NULL,
  `to` char(255) DEFAULT NULL,
  `cc` char(255) DEFAULT NULL,
  `bcc` char(255) DEFAULT NULL,
  `message` longtext,
  `template_default` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `create_date` date DEFAULT NULL,
  PRIMARY KEY  (`prospect_template_id`),
  CONSTRAINT `FK_company_prospects_templates_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_prospects_templates_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_prospects_templates` (`prospect_template_id`, `company_id`, `author_id`, `name`, `subject`, `from`, `to`, `cc`, `bcc`, `message`, `create_date`) VALUES
(1, 0, 1, 'Thank you', '', '', '', '', '', '<font size="6">?<br>Thank you!<br></font><br><br><font size="3">Your questionnaire has been received. You will hear from us shortly.</font>', '2011-11-16'),
(2, 0, 1, 'Skilled Worker', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Skilled Worker class.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br><br><br>', '2011-12-20'),
(3, 0, 1, 'Entrepreneur', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Entrepreneur category.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>', '2011-12-20'),
(4, 0, 1, 'Investor', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Investor class.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>?', '2011-12-20'),
(5, 0, 1, 'Spousal Sponsorship', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Spousal Sponsorship.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>', '2011-12-20'),
(6, 0, 1, 'Parental Sponsorship', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Parental Sponsorship.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>', '2011-12-20'),
(7, 0, 1, 'CEC', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Canadian Experience class.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>', '2011-12-20'),
(8, 0, 1, 'Foreign Worker', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Foreign Worker class.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>', '2011-12-20'),
(9, 0, 1, 'Child Sponsorship', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and are pleased to advise you that you are qualified to apply for immigration to Canada under Child Sponsorship category.<br><br>In this assessment we assumed that you have no criminal records, and no medical conditions that make you inadmissible to Canada.<br><br>If you would like to start the process of immigrating to Canada and wish to use our services, our bank account details are as follows:<br><br>......<br><br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>', '2011-12-20'),
(10, 0, 1, 'Negative Response', 'Immigration to Canada', '', '', '', '', 'Dear {salutation} {fName} {lName},<br><br>Thank you for submitting your preliminary questionnaire.<br><br>We reviewed your questionnaire, and unfortunately you are not qualified to apply for immigration to Canada at this time. <br><br>The assessment criteria to determine eligibility to immigrate to Canada are complex and change from time to time. Your inadmissibility now does not mean that you will be inadmissible in the future. We encourage you to contact us again in the future for a reassessment of your qualification.<br><br>If you have any questions, or need to further assistance, please do not hesitate to contact us.<br><br>Regards,<br><br>{company}<br><br>', '2011-12-20')
;

DROP TABLE IF EXISTS `company_prospects_categories`;
CREATE TABLE `company_prospects_categories` (
    `prospect_category_id` INT(11) UNSIGNED NOT NULL auto_increment,
    `prospect_category_unique_id` VARCHAR(50) NOT NULL DEFAULT '',
    `prospect_category_name` CHAR(255) NOT NULL,
    `prospect_category_short_name` CHAR(255) NOT NULL,
    `prospect_category_show_in_settings` ENUM( 'Y', 'N') NOT NULL DEFAULT 'Y',
    `prospect_category_order` TINYINT(3) UNSIGNED DEFAULT 0,
    PRIMARY KEY  (`prospect_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_prospects_categories` (`prospect_category_id`, `prospect_category_unique_id`, `prospect_category_name`, `prospect_category_short_name`, `prospect_category_show_in_settings`, `prospect_category_order`) VALUES
    (1, 'skilled_worker', 'Skilled Worker', 'SW', 'Y', 0),
    (2, 'entrepreneur', 'Entrepreneur', 'ENT', 'Y', 1),
    (3, 'investor', 'Investor', 'INV', 'Y', 2),
    (4, 'sponsorship_parental', 'Sponsorship (parental/grand parental)', 'SPN', 'Y', 4),
    (5, 'sponsorship_spousal', 'Sponsorship (spousal)', 'SPN', 'Y', 5),
    (6, 'sponsorship_child', 'Sponsorship (child under 22)', 'SPN', 'Y', 6),
    (7, 'cec', 'Canadian Experience Class (CEC)', 'CEC', 'Y', 7),
    (8, 'foreign_worker', 'Foreign Worker', 'FW', 'Y', 8),
    (9, 'student', 'Student', 'STD', 'N', 9),
    (10, 'quebec_skilled_worker', 'Quebec Skilled Worker', 'QSW', 'N', 3)
;

DROP TABLE IF EXISTS `company_prospects_selected_categories`;
CREATE TABLE `company_prospects_selected_categories` (
  `company_id` bigint(20) NOT NULL,
  `prospect_category_id` INT(11) UNSIGNED NOT NULL,
  `order` TINYINT(3) UNSIGNED DEFAULT 0,

  PRIMARY KEY  (`company_id`, `prospect_category_id`),
  CONSTRAINT `FK_company_prospects_1` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_prospects_2` FOREIGN KEY (`prospect_category_id`) REFERENCES `company_prospects_categories` (`prospect_category_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_prospects_selected_categories` (`company_id`, `prospect_category_id`, `order`) VALUES
    (0, 1, 0),
    (0, 2, 1),
    (0, 3, 2),
    (0, 4, 3),
    (0, 5, 4),
    (0, 6, 5),
    (0, 7, 6)
;

/* **** Questionnaires **** */

/*
    Clear DB:

    DROP TABLE IF EXISTS `company_questionnaires_fields_options_templates`;
    DROP TABLE IF EXISTS `company_questionnaires_fields_options`;
    DROP TABLE IF EXISTS `company_questionnaires_fields_templates`;
    DROP TABLE IF EXISTS `company_questionnaires_fields`;

    DROP TABLE IF EXISTS `company_questionnaires_sections_templates`;
    DROP TABLE IF EXISTS `company_questionnaires_sections`;
    
    DROP TABLE IF EXISTS `company_questionnaires_category_template`;

    DROP TABLE IF EXISTS `company_questionnaires`;

*/

/*
    1. Superadmin creates templates
    2. Each company will have own list of qnr
*/
DROP TABLE IF EXISTS `company_questionnaires`;
CREATE TABLE `company_questionnaires` (
  `q_id` INT(11) UNSIGNED NOT NULL auto_increment,
  `company_id` bigint(20) NOT NULL,
  `q_noc` ENUM( 'en', 'fr') DEFAULT 'en',
  `q_name` char(255) NOT NULL,
  `q_section_bg_color` char(6) NOT NULL DEFAULT '4C83C5',
  `q_section_text_color` char(6) NOT NULL DEFAULT 'FFFFFF',
  
  `q_preferred_language` CHAR(255) NULL,
  `q_office_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `q_agent_id` INT(11) UNSIGNED NULL DEFAULT NULL,

  `q_applicant_name` char(255) NOT NULL,
  `q_please_select` char(255) NOT NULL,
  `q_please_answer_all` char(255) NULL,
  `q_please_press_next` char(255) NULL,
  `q_next_page_button` char(255) NOT NULL,
  `q_prev_page_button` char(255) NOT NULL,
  
  `q_step1` CHAR(255) NOT NULL,
  `q_step2` CHAR(255) NOT NULL,
  `q_step3` CHAR(255) NOT NULL,
  `q_step4` CHAR(255) NOT NULL,
  
  `q_rtl` ENUM( 'Y', 'N') DEFAULT 'N',
  
  `q_template_negative` INT(11) UNSIGNED NULL,
  `q_template_thank_you` INT(11) UNSIGNED NULL,

  `q_created_by` bigint(20) NOT NULL,
  `q_updated_by` bigint(20) DEFAULT NULL,
  `q_created_on` datetime NOT NULL,
  `q_updated_on` datetime DEFAULT NULL,

  PRIMARY KEY  (`q_id`, `company_id`),
  CONSTRAINT `FK_company_questionnaires_1` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_2` FOREIGN KEY (`q_created_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_3` FOREIGN KEY (`q_updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_divisions` FOREIGN KEY (`q_office_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires` (`q_id`, `company_id`, `q_noc`, `q_name`, `q_applicant_name`, `q_please_select`, `q_please_answer_all`, `q_please_press_next`, `q_next_page_button`, `q_prev_page_button`, `q_created_by`, `q_created_on`) VALUES
    (1,  0, 'en', 'English Questionnaire', 'Main Applicant', '-- Please select --', 'Please answer ALL questions.', 'Please press Next Page to continue.', 'Next', 'Back', 1, '2010-06-21 00:00:00'),
    (2,  0, 'fr', 'French Questionnaire', 'Main Applicant', '-- Please select --', 'Please answer ALL questions.', 'Please press Next Page to continue.', 'Next', 'Back', 1, '2010-06-21 00:00:00')
;


DROP TABLE IF EXISTS `company_questionnaires_category_template`;
CREATE TABLE `company_questionnaires_category_template` (
  `q_id` INT(11) UNSIGNED,
  `prospect_category_id` INT(11) UNSIGNED NOT NULL,
  `prospect_template_id` INT(11) UNSIGNED NOT NULL,

  PRIMARY KEY  (`q_id`, `prospect_category_id`, `prospect_template_id`),
  CONSTRAINT `FK_company_questionnaires_category_template_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_category_template_2` FOREIGN KEY (`prospect_category_id`) REFERENCES `company_prospects_categories` (`prospect_category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_category_template_3` FOREIGN KEY (`prospect_template_id`) REFERENCES `company_prospects_templates` (`prospect_template_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



DROP TABLE IF EXISTS `company_questionnaires_sections`;
CREATE TABLE `company_questionnaires_sections` (
  `q_section_id` INT(11) UNSIGNED NOT NULL auto_increment,
  `q_section_step` TINYINT(3) UNSIGNED DEFAULT 0,
  `q_section_order` TINYINT(3) UNSIGNED DEFAULT 0,

  PRIMARY KEY  (`q_section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires_sections` (`q_section_id`, `q_section_step`, `q_section_order`) VALUES
    (1,  1, 0),
    (2,  2, 0),
    (3,  2, 1),
    (4,  2, 2),
    (5,  2, 3),
    (6,  2, 4),
    (7,  2, 5),
    (8,  2, 6),
    (9,  3, 0),
    (10, 4, 0),
    (11, 3, 1),
    (12, 3, 2)
;

DROP TABLE IF EXISTS `company_questionnaires_sections_templates`;
CREATE TABLE `company_questionnaires_sections_templates` (
  `q_id` INT(11) UNSIGNED NOT NULL,
  `q_section_id` INT(11) UNSIGNED NOT NULL,
  `q_section_template_name` char(255) NOT NULL,
  `q_section_prospect_profile` CHAR(255) NOT NULL DEFAULT '',
  `q_section_help` TEXT NULL,
  `q_section_help_show` ENUM( 'Y', 'N') DEFAULT 'N',

  PRIMARY KEY  (`q_section_id`, `q_id`),
  CONSTRAINT `FK_company_questionnaires_sections_templates_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_sections_templates_2` FOREIGN KEY (`q_section_id`) REFERENCES `company_questionnaires_sections` (`q_section_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires_sections_templates` (`q_id`, `q_section_id`, `q_section_template_name`, `q_section_prospect_profile`) VALUES
    (1, 1,  'PERSONAL INFORMATION', 'PERSONAL INFORMATION'),
    (1, 2,  'MARITAL STATUS', 'MARITAL STATUS'),
    (1, 3,  'CHILDREN', 'CHILDREN'),
    (1, 4,  'EDUCATION', 'EDUCATION'),
    (1, 5,  'LANGUAGE', 'LANGUAGE'),
    (1, 6,  'WORK IN CANADA', 'Work in Canada (Main Applicant or Spouse/Common-law)'),
    (1, 7,  'STUDY IN CANADA', 'Study in Canada (Main Applicant or Spouse/Common-law)'),
    (1, 8,  'FAMILY RELATIONS IN CANADA', 'Family Relations in Canada/Sponsorship (Main Applicant or Spouse/Common-law)'),
    (1, 9,  'WHAT IS YOUR OCCUPATION?', 'WHAT IS YOUR OCCUPATION?'),
    (1, 10, 'BUSINESS/FINANCES', 'BUSINESS/FINANCES'),
    (1, 11, '', ''),
    (1, 12, 'WHAT IS YOUR SPOUSE\'S OR COMMON-LAW PARTNER\'S OCCUPATION?', 'WHAT IS YOUR SPOUSE\'S OR COMMON-LAW PARTNER\'S OCCUPATION?'),

    (2, 1,  'PERSONAL INFORMATION FR', ''),
    (2, 2,  'MARITAL STATUS FR', ''),
    (2, 3,  'CHILDREN FR', ''),
    (2, 4,  'EDUCATION FR', ''),
    (2, 5,  'LANGUAGE FR', ''),
    (2, 6,  'WORK IN CANADA FR', ''),
    (2, 7,  'STUDY IN CANADA FR', ''),
    (2, 8,  'FAMILY RELATIONS IN CANADA FR', ''),
    (2, 9,  'WHAT IS YOUR OCCUPATION? FR', ''),
    (2, 10, 'BUSINESS/FINANCES FR', '')
;


DROP TABLE IF EXISTS `company_questionnaires_fields`;
CREATE TABLE `company_questionnaires_fields` (
    `q_field_id` INT(11) UNSIGNED NOT NULL auto_increment,
    `q_field_unique_id` char(255) NOT NULL,
    `q_section_id` INT(11) UNSIGNED NOT NULL,
    `q_field_type` ENUM('textfield','textarea','combo','combo_custom','checkbox','radio','date','country','email','label','job','money','number','percentage','age','file') DEFAULT 'textfield',
    `q_field_required` ENUM('Y','N') NULL DEFAULT 'Y',
    `q_field_show_in_prospect_profile` ENUM('Y','N') NULL DEFAULT 'Y',
    `q_field_show_please_select` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `q_field_use_in_search` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `q_field_order` TINYINT(3) UNSIGNED DEFAULT 0,

    PRIMARY KEY (`q_field_id`, `q_section_id`),
    CONSTRAINT `FK_company_questionnaires_fields_1` FOREIGN KEY `FK_company_questionnaires_fields_1` (`q_section_id`) REFERENCES `company_questionnaires_sections` (`q_section_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_order`) VALUES
    /* PERSONAL INFORMATION */
    (1, 'qf_salutation',              1, 'combo', 'Y', 'Y', 'N', 0),
    (2, 'qf_first_name',              1, 'textfield', 'Y', 'Y', 'N', 1),
    (3, 'qf_last_name',               1, 'textfield', 'Y', 'Y', 'N', 2),
    (4, 'qf_age',                     1, 'date', 'Y', 'Y', 'N', 4),
    (5, 'qf_country_of_citizenship',  1, 'country', 'Y', 'Y', 'Y', 6),
    (6, 'qf_country_of_residence',    1, 'country', 'Y', 'Y', 'Y', 7),
    (7, 'qf_email',                   1, 'email', 'Y', 'Y', 'N', 8),
    (8, 'qf_email_confirmation',      1, 'email', 'Y', 'N', 'N', 9),
    (9, 'qf_marital_status',          1, 'combo', 'Y', 'Y', 'N', 3),
    
    /* EDUCATION */
    (16, 'qf_education_level',         4, 'radio', 'Y', 'Y', 'Y', 2),

    /* WORK IN CANADA */
    (29, 'qf_work_temporary_worker',   6, 'radio', 'Y', 'N', 'N', 1),
    (30, 'qf_work_years_worked',       6, 'radio', 'Y', 'Y', 'N', 2),
    (31, 'qf_work_currently_employed', 6, 'radio', 'Y', 'Y', 'N', 3),
    (32, 'qf_work_leave_employment',   6, 'radio', 'Y', 'Y', 'N', 4),
    (33, 'qf_study_previously_studied', 4, 'radio', 'Y', 'Y', 'N', 13),

    /* FAMILY RELATIONS IN CANADA */
    (34, 'qf_family_have_blood_relative',             8, 'radio', 'Y', 'N', 'N', 0),
    (35, 'qf_family_relationship',                    8, 'radio', 'Y', 'Y', 'N', 1),
    (36, 'qf_family_relative_wish_to_sponsor',        8, 'radio', 'Y', 'Y', 'N', 2),
    (37, 'qf_family_sponsor_age',                     8, 'radio', 'Y', 'Y', 'N', 3),
    (38, 'qf_family_employment_status',               8, 'combo', 'Y', 'Y', 'N', 4),
    (39, 'qf_family_sponsor_financially_responsible', 8, 'combo', 'Y', 'Y', 'N', 5),
    (40, 'qf_family_sponsor_income',                  8, 'money', 'Y', 'Y', 'N', 6),
    (41, 'qf_family_currently_fulltime_student',      8, 'radio', 'Y', 'Y', 'N', 7),
    (42, 'qf_family_been_fulltime_student',           8, 'radio', 'Y', 'Y', 'N', 8),
    
    /* WHAT IS YOUR OCCUPATION? */
    (43, 'qf_job_occupation_label',  9, 'label', 'Y', 'Y', 'N', 0),
    (44, 'qf_job_title',             9, 'job', 'Y', 'Y', 'N', 1),
    (45, 'qf_job_duration',          9, 'combo', 'Y', 'Y', 'N', 3),
    (46, 'qf_job_location',          9, 'combo', 'Y', 'Y', 'N', 4),
    (47, 'qf_job_presently_working', 9, 'radio', 'Y', 'Y', 'N', 6),
    
    /* BUSINESS/FINANCE */
    (48, 'qf_cat_net_worth',               10, 'combo', 'Y', 'Y', 'N', 0),
    (49, 'qf_cat_have_experience',         10, 'radio', 'Y', 'N', 'N', 1),
    (50, 'qf_cat_managerial_experience',   10, 'combo', 'Y', 'Y', 'N', 2),
    (51, 'qf_cat_staff_number',            10, 'number', 'Y', 'Y', 'N', 3),
    (52, 'qf_cat_own_this_business',       10, 'radio', 'Y', 'Y', 'N', 4),
    (53, 'qf_cat_percentage_of_ownership', 10, 'percentage', 'Y', 'Y', 'N', 5),
    (54, 'qf_cat_annual_sales',            10, 'money', 'Y', 'Y', 'N', 6),
    (55, 'qf_cat_annual_net_income',       10, 'money', 'Y', 'Y', 'N', 7),
    (56, 'qf_cat_net_assets',              10, 'money', 'Y', 'Y', 'N', 8),
    


    /* PERSONAL INFORMATION - additional fields */
    (57, 'qf_phone',              1, 'textfield', 'N', 'Y', 'N', 10),
    (58, 'qf_fax',                1, 'textfield', 'N', 'Y', 'N', 11),
    (59, 'qf_referred_by',        1, 'combo_custom', 'Y', 'Y', 'N', 12),
    
    /* WORK IN CANADA - additional fields */
    (60, 'qf_work_offer_of_employment', 6, 'radio', 'Y', 'Y', 'N', 6),
    
    /* EDUCATION - additional fields */
    (61,  'qf_education_your_label',   4, 'label', 'Y', 'Y', 'N', 0),
    (62,  'qf_education_spouse_label', 4, 'label', 'Y', 'Y', 'N', 1),
    (63,  'qf_education_spouse_level', 4, 'combo', 'Y', 'Y', 'Y', 3),
    (114, 'qf_education_diploma_name', 4, 'textfield', 'N', 'Y', 'N', 6),
    (115, 'qf_education_spouse_diploma_name', 4, 'textfield', 'N', 'Y', 'N', 7),
    (116, 'qf_education_area_of_studies', 4, 'textfield', 'N', 'Y', 'N', 8),
    (117, 'qf_education_spouse_area_of_studies', 4, 'textfield', 'N', 'Y', 'N', 9),
    (118, 'qf_education_country_of_studies', 4, 'country', 'N', 'Y', 'N', 10),
    (119, 'qf_education_spouse_country_of_studies', 4, 'country', 'N', 'Y', 'N', 10),
    (120, 'qf_education_institute_type', 4, 'combo', 'N', 'Y', 'Y', 11),
    (121, 'qf_education_spouse_institute_type', 4, 'combo', 'N', 'Y', 'Y', 12),


    /* LANGUAGE */
    (65, 'qf_language_proficiency_label',      5, 'label', 'Y', 'N', 'N', 0),
    (66, 'qf_language_your_label',             5, 'label', 'Y', 'Y', 'N', 1),
    (67, 'qf_language_spouse_label',           5, 'label', 'Y', 'Y', 'N', 2),
    
    (68, 'qf_language_eng_label',              5, 'label', 'Y', 'Y', 'N', 3),
    (69, 'qf_language_fr_label',               5, 'label', 'Y', 'Y', 'N', 4),
    (70, 'qf_language_spouse_eng_label',       5, 'label', 'Y', 'Y', 'N', 5),
    (71, 'qf_language_spouse_fr_label',        5, 'label', 'Y', 'Y', 'N', 6),
    
    (72, 'qf_language_speak_label',                   5, 'label', 'Y', 'Y', 'N', 7),
    (73, 'qf_language_eng_proficiency_speak',         5, 'combo', 'Y', 'Y', 'Y', 8),
    (74, 'qf_language_fr_proficiency_speak',          5, 'combo', 'Y', 'Y', 'Y', 9),
    (75, 'qf_language_spouse_speak_label',            5, 'label', 'Y', 'Y', 'N', 10),
    (76, 'qf_language_spouse_eng_proficiency_speak',  5, 'combo', 'Y', 'Y', 'Y', 11),
    (77, 'qf_language_spouse_fr_proficiency_speak',   5, 'combo', 'Y', 'Y', 'Y', 12),
    
    (78, 'qf_language_read_label',                    5, 'label', 'Y', 'Y', 'N', 13),
    (79, 'qf_language_eng_proficiency_read',          5, 'combo', 'Y', 'Y', 'Y', 14),
    (80, 'qf_language_fr_proficiency_read',           5, 'combo', 'Y', 'Y', 'Y', 15),
    (81, 'qf_language_spouse_read_label',             5, 'label', 'Y', 'Y', 'N', 16),
    (82, 'qf_language_spouse_eng_proficiency_read',   5, 'combo', 'Y', 'Y', 'Y', 17),
    (83, 'qf_language_spouse_fr_proficiency_read',    5, 'combo', 'Y', 'Y', 'Y', 18),
    
    (84, 'qf_language_write_label',                    5, 'label', 'Y', 'Y', 'N', 19),
    (85, 'qf_language_eng_proficiency_write',          5, 'combo', 'Y', 'Y', 'Y', 20),
    (86, 'qf_language_fr_proficiency_write',           5, 'combo', 'Y', 'Y', 'Y', 21),
    (87, 'qf_language_spouse_write_label',             5, 'label', 'Y', 'Y', 'N', 22),
    (88, 'qf_language_spouse_eng_proficiency_write',   5, 'combo', 'Y', 'Y', 'Y', 23),
    (89, 'qf_language_spouse_fr_proficiency_write',    5, 'combo', 'Y', 'Y', 'Y', 24),
    
    (90, 'qf_language_listen_label',                    5, 'label', 'Y', 'Y', 'N', 25),
    (91, 'qf_language_eng_proficiency_listen',          5, 'combo', 'Y', 'Y', 'Y', 26),
    (92, 'qf_language_fr_proficiency_listen',           5, 'combo', 'Y', 'Y', 'Y', 27),
    (93, 'qf_language_spouse_listen_label',             5, 'label', 'Y', 'Y', 'Y', 28),
    (94, 'qf_language_spouse_eng_proficiency_listen',   5, 'combo', 'Y', 'Y', 'Y', 29),
    (95, 'qf_language_spouse_fr_proficiency_listen',    5, 'combo', 'Y', 'Y', 'Y', 30),
    
    /* PERSONAL INFORMATION - additional fields */
    (96, 'qf_spouse_age', 1, 'date', 'Y', 'Y', 'N', 5),


    /* CHILDREN */
    (97,  'qf_children_count', 3, 'combo', 'Y', 'Y', 'N', 0),
    (98,  'qf_children_age_1', 3, 'age', 'Y', 'Y', 'N', 1),
    (99,  'qf_children_age_2', 3, 'age', 'Y', 'Y', 'N', 2),
    (100, 'qf_children_age_3', 3, 'age', 'Y', 'Y', 'N', 3),
    (101, 'qf_children_age_4', 3, 'age', 'Y', 'Y', 'N', 4),
    (102, 'qf_children_age_5', 3, 'age', 'Y', 'Y', 'N', 5),
    (103, 'qf_children_age_6', 3, 'age', 'Y', 'Y', 'N', 6),

    /* WHAT IS YOUR OCCUPATION?  - Additional fields*/
    (104, 'qf_job_province', 9, 'combo', 'Y', 'Y', 'N', 5),
    (105, 'qf_job_noc', 9, 'textfield', 'N', 'Y', 'N', 2),
    (122, 'qf_job_qualified_for_social_security', 9, 'combo', 'N', 'Y', 'N', 7),

    /* WHAT IS YOUR OCCUPATION?  - Spouse fields*/
    (106, 'qf_job_spouse_occupation_label',  12, 'label', 'Y', 'Y', 'N', 0),
    (107, 'qf_job_spouse_title',             12, 'job', 'Y', 'Y', 'N', 1),
    (108, 'qf_job_spouse_duration',          12, 'combo', 'Y', 'Y', 'N', 3),
    (109, 'qf_job_spouse_location',          12, 'combo', 'Y', 'Y', 'N', 4),
    (110, 'qf_job_spouse_presently_working', 12, 'radio', 'Y', 'Y', 'N', 6),
    (111, 'qf_job_spouse_province',          12, 'combo', 'Y', 'Y', 'N', 5),
    (112, 'qf_job_spouse_noc',               12, 'textfield', 'N', 'Y', 'N', 2),
    (123, 'qf_job_spouse_qualified_for_social_security', 12, 'combo', 'N', 'Y', 'N', 7),

    (113, 'qf_job_spouse_has_experience',    11, 'radio', 'Y', 'Y', 'N', 0),
    (126, 'qf_education_spouse_previously_studied', 4, 'radio', 'Y', 'Y', 'N', 14)
;

DROP TABLE IF EXISTS `company_questionnaires_fields_templates`;
CREATE TABLE `company_questionnaires_fields_templates` (
  `q_id` INT(11) UNSIGNED NOT NULL,
  `q_field_id` INT(11) UNSIGNED NOT NULL,
  `q_field_label` char(255) NOT NULL,
  `q_field_prospect_profile_label` CHAR(255) NOT NULL DEFAULT '',
  `q_field_help` TEXT NULL,
  `q_field_help_show` ENUM( 'Y', 'N') DEFAULT 'N',

  PRIMARY KEY  (`q_field_id`, `q_id`),
  CONSTRAINT `FK_company_questionnaires_fields_templates_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_fields_templates_2` FOREIGN KEY (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) VALUES
    /* PERSONAL INFORMATION */
    (1, 1,  'Salutation:', ''),
    (1, 2,  'First name:', ''),
    (1, 3,  'Last name:', ''),
    (1, 4,  'Date of birth:', ''),
    (1, 5,  'Country of citizenship:', ''),
    (1, 6,  'Current country of residence:', ''),
    (1, 7,  'Email:', ''),
    (1, 8,  'Email (please confirm again):', ''),

    /* MARITAL STATUS */
    (1, 9,  'Marital Status:', ''),

    /* EDUCATION */
    (1, 16, 'What is your highest level of education?', 'Highest level of education:'),

    /* WORK IN CANADA */
    (1, 29, 'Have you been in Canada as a <b>temporary foreign worker</b>?', ''),
    (1, 30, 'How many years have you worked full-time in this position in Canada?', 'Number of years of full-time employment in Canada'),
    (1, 31, 'Are you currently employed in Canada?', 'Currently employed in Canada?'),
    (1, 32, 'When did you leave your employment in Canada?', 'Left employment in Canada:'),
    (1, 33, 'Have you completed a minimum of 2 years of full-time studies in Canada?', 'Studied full-time for at least 2 years in Canada?'),


    /* FAMILY RELATIONS IN CANADA */
    (1, 34, 'Do you or, if applicable your accompanying spouse, or common-law partner have a <b style="color: red;">blood relative</b> <b>living in Canada</b> who is a <b>citizen</b> or a <b>permanent resident of Canada</b>?', ''),
    (1, 35, 'Their relationship with you', 'Relatives in Canada'),
    (1, 36, 'Does this relative wish to sponsor you? If unsure, please choose No.', 'Does this relative wish to sponsor you?'),
    (1, 37, 'How old is your relative?', "Sponsor's age:"),
    (1, 38, 'What is the employment status of your relative?', "Sponsor's employment status"),
    (1, 39, 'How many people is this relative financially responsible for in his/her household in Canada?', "Sponsor's family size"),
    (1, 40, 'How much is the annual household income of your relative? This includes the combined annual income of your relative and his/her spouse.', "Sponsor's annual income"),
    (1, 41, 'Are you currently a full-time student?', 'Currently a full-time student?'),
    (1, 42, 'Have you been a full-time student and substantially dependent on your parents for financial support since before the age of 22?', 'Have been a dependent child since before 22?'),

    /* WHAT IS YOUR OCCUPATION? */
    (1, 43, 'Please indicate what best describes your occupational job title.', ''),
    (1, 44, 'Job Title:', ''),
    (1, 45, 'Duration:', ''),
    (1, 46, 'Location:', ''),
    (1, 47, 'Are you PRESENTLY WORKING in this job?', ''),

    /* BUSINESS/FINANCE */
    (1, 48, 'How much is your net worth?', 'Networth'),
    (1, 49, 'Do you have experience managing a business?', ''),
    (1, 50, 'In the past 5 years, how many years of managerial experience do you have?', 'Years of mangerial experience:'),
    (1, 51, 'What is the number of full-time staff under your management?', 'Number of staff managed'),
    (1, 52, 'Do you own this business?', 'Own business'),
    (1, 53, 'What is your percentage of ownership in this business?', 'Percentage of ownership'),
    (1, 54, 'What is the annual sales of this business?', 'Annual sales (CDN$)'),
    (1, 55, 'What is the annual net income of this business?', 'Annual income (CDN$)'),
    (1, 56, 'What is the net assets of this business?', 'Net business assets (CDN$)'),
    
    /* PERSONAL INFORMATION - additional fields */
    (1, 57, 'Phone (Optional):', ''),
    (1, 58, 'Fax (Optional):', ''),
    (1, 59, 'How did you hear from us?', 'Referred by:'),
    
    /* WORK IN CANADA - additional fields */
    (1, 60, 'Do you have an Official offer of Employment from a Canadian Employer?', 'Has an Offer of Employment'),
    
    /* EDUCATION - additional fields */
    (1, 61,  'Your Education:', 'Main Applicant'),
    (1, 62,  "Your Spouse's Education:", 'Spouse'),
    (1, 63,  "What is your spouse's highest level of education?", 'Highest level of education:'),
    (1, 114, 'Name of Diploma:', ''),
    (1, 115, 'Name of Diploma:', ''),
    (1, 116, 'Area of Studies:', ''),
    (1, 117, 'Area of Studies:', ''),
    (1, 118, 'Country of Studies:', ''),
    (1, 119, 'Country of Studies:', ''),
    (1, 120, 'Type of Educational Institute:', ''),
    (1, 121, 'Type of Educational Institute:', ''),

    /* Language */
    (1, 65, 'Please specify your proficiency in English and French:', ''),
    (1, 66, 'You', 'Main Applicant'),
    (1, 67, "Your Spouse", 'Spouse'),
    (1, 68, 'English', ''),
    (1, 69, 'French', ''),
    (1, 70, 'English', ''),
    (1, 71, 'French', ''),
    (1, 72, 'Speak', ''),
    (1, 73, '', ''),
    (1, 74, '', ''),
    (1, 75, 'Speak', ''),
    (1, 76, '', ''),
    (1, 77, '', ''),
    (1, 78, 'Read', ''),
    (1, 79, '', ''),
    (1, 80, '', ''),
    (1, 81, 'Read', ''),
    (1, 82, '', ''),
    (1, 83, '', ''),
    (1, 84, 'Write', ''),
    (1, 85, '', ''),
    (1, 86, '', ''),
    (1, 87, 'Write', ''),
    (1, 88, '', ''),
    (1, 89, '', ''),
    (1, 90, 'Listen', ''),
    (1, 91, '', ''),
    (1, 92, '', ''),
    (1, 93, 'Listen', ''),
    (1, 94, '', ''),
    (1, 95, '', ''),
    
    /* PERSONAL INFORMATION - additional fields */
    (1, 96,  "Spouse's date of birth:", ''),
    
    /* CHILDREN */
    (1, 97,   'Number of children:', ''),
    (1, 98,   'Age of child 1:', ''),
    (1, 99,   'Age of child 2:', ''),
    (1, 100,  'Age of child 3:', ''),
    (1, 101,  'Age of child 4:', ''),
    (1, 102,  'Age of child 5:', ''),
    (1, 103,  'Age of child 6:', ''),


    (2, 1,  'Salutation:', ''),
    (2, 2,  'First name:', ''),
    (2, 3,  'Last name:', ''),
    (2, 4,  'Age:', ''),
    (2, 5,  'Country of citizenship:', ''),
    (2, 6,  'Current country of residence:', ''),
    (2, 7,  'Email:', ''),
    (2, 8,  'Email (please confirm again):', ''),

    /* WHAT IS YOUR OCCUPATION? */
    (1, 104, 'Province:', ''),
    (1, 105, 'NOC:', ''),
    (1, 122, 'Is your job qualified for social security?', ''),


    (1, 106, 'Please indicate what best describes your occupational job title.', ''),
    (1, 107, 'Job Title:', ''),
    (1, 108, 'Duration:', ''),
    (1, 109, 'Location:', ''),
    (1, 110, 'Are you PRESENTLY WORKING in this job?', ''),
    (1, 111, 'Province:', ''),
    (1, 112, 'NOC:', ''),
    (1, 123, 'Is your job qualified for social security?', ''),

    (1, 113, 'Does your spouse/common-law partner have any occupational experience?', ''),
    (1, 126, 'Has your spouse or common-law spouse completed a minimum of 2 years of full-time studies in Canada?', 'Spouse/Common-law Spouse has studied full time for at least 2 years in Canada?')
;

DROP TABLE IF EXISTS `company_questionnaires_fields_custom_options`;
CREATE TABLE `company_questionnaires_fields_custom_options` (
  `q_field_custom_option_id` INT(11) UNSIGNED NOT NULL auto_increment,
  `q_id` INT(11) UNSIGNED NOT NULL,
  `q_field_id` INT(11) UNSIGNED NOT NULL,

  `q_field_custom_option_label` char(255) NOT NULL,
  `q_field_custom_option_visible` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `q_field_custom_option_selected` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `q_field_custom_option_order` TINYINT(3) UNSIGNED DEFAULT 0,

  PRIMARY KEY (`q_field_custom_option_id`, `q_id`, `q_field_id`),
  INDEX `FK_company_questionnaires_fields_custom_options_1` (`q_id`),
  INDEX `FK_company_questionnaires_fields_custom_options_2` (`q_field_id`),
  CONSTRAINT `FK_company_questionnaires_fields_custom_options_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_fields_custom_options_2` FOREIGN KEY (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) VALUES
  (1, 59, 'Google', 0),
  (1, 59, 'Yahoo', 1),
  (1, 59, 'Friends', 2),
  (1, 59, 'Your clients', 3),
  (1, 59, 'Other', 4)
;


DROP TABLE IF EXISTS `company_questionnaires_fields_options`;
CREATE TABLE `company_questionnaires_fields_options` (
    `q_field_option_id` INT(11) UNSIGNED NOT NULL auto_increment,
    `q_field_id` INT(11) UNSIGNED NOT NULL,
    `q_field_option_unique_id` char(255) NOT NULL,
    `q_field_option_selected` ENUM('Y', 'N') DEFAULT 'N',
    `q_field_option_order` TINYINT(3) UNSIGNED DEFAULT 0,

    PRIMARY KEY (`q_field_option_id`, `q_field_id`),
    CONSTRAINT `FK_company_questionnaires_fields_options_1` FOREIGN KEY `FK_company_questionnaires_fields_options_1` (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires_fields_options` (`q_field_option_id`, `q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
    /* PERSONAL INFORMATION */
    (1, 1, 'mr', 'N', 0),
    (2, 1, 'ms', 'N', 1),
    (3, 1, 'mrs', 'N', 2),
    (4, 1, 'miss', 'N', 3),
    (5, 1, 'dr', 'N', 4),

    /* MARITAL STATUS */
    (6,  9, 'never_married', 'N', 0),
    (7,  9, 'married', 'N', 1),
    (8,  9, 'widowed', 'N', 2),
    (9,  9, 'legally_separated', 'N', 3),
    (10, 9, 'annulled_marriage', 'N', 4),
    (11, 9, 'divorced', 'N', 5),
    (12, 9, 'common_law', 'N', 6),
    (364, 17, 'widowed', 'N', 7),
    (365, 17, 'separated', 'N', 8),

    /* EDUCATION */
    (49, 16, 'ph_d', 'N', 0),
    (50, 16, 'master', 'N', 1),
    (51, 16, '2_or_more', 'N', 2),
    (52, 16, 'bachelor_4', 'N', 3),
    (53, 16, 'bachelor_3', 'N', 4),
    (54, 16, 'bachelor_2', 'N', 5),
    (55, 16, 'bachelor_1', 'N', 6),
    (56, 16, 'diploma_3', 'N', 7),
    (57, 16, 'diploma_2', 'N', 8),
    (58, 16, 'diploma_1', 'N', 9),
    (59, 16, 'diploma_high', 'N', 10),
    (60, 16, 'diploma_below', 'N', 11),

    /* WORK IN CANADA */
    (126, 29, 'yes', 'N', 0),
    (127, 29, 'no', 'N', 1),

    (128, 30, '1_year', 'N', 0),
    (129, 30, '2_or_more', 'N', 1),

    (130, 31, 'yes', 'N', 0),
    (131, 31, 'no', 'N', 1),

    (132, 32, 'less_than_year', 'N', 0),
    (133, 32, 'more_than_year', 'N', 1),

    /* STUDY IN CANADA */
    (134, 33, 'yes', 'N', 0),
    (135, 33, 'no', 'N', 1),

    /* FAMILY RELATIONS IN CANADA */
    (136, 34, 'yes', 'N', 0),
    (137, 34, 'no', 'N', 1),

    (138, 35, 'mother_or_father', 'N', 1),
    (139, 35, 'daughter_or_son ', 'N', 2),
    (140, 35, 'sister_or_brother ', 'N', 3),
    (141, 35, 'niece_or_nephew ', 'N', 4),
    (142, 35, 'grandmother', 'N', 5),
    (143, 35, 'granddaughter', 'N', 6),
    (144, 35, 'aunt', 'N', 7),
    (145, 35, 'spouse', 'N', 8),

    (146, 36, 'yes', 'N', 0),
    (147, 36, 'no', 'N', 1),

    (148, 37, 'younger_than_18', 'N', 0),
    (149, 37, 'over_than_18', 'N', 1),

    (150, 38, 'employed', 'N', 0),
    (151, 38, 'self_employed', 'N', 1),
    (152, 38, 'unemployed', 'N', 2),

    (153, 39, '0', 'N', 0),
    (154, 39, '1', 'N', 1),
    (155, 39, '2', 'N', 2),
    (156, 39, '3', 'N', 3),
    (157, 39, '4', 'N', 4),
    (158, 39, '5', 'N', 5),
    (159, 39, '6', 'N', 6),
    (160, 39, '7', 'N', 7),
    (161, 39, '8', 'N', 8),
    (162, 39, '9', 'N', 9),

    (163, 41, 'yes', 'N', 0),
    (164, 41, 'no', 'N', 1),

    (165, 42, 'yes', 'N', 0),
    (166, 42, 'no', 'N', 1),
    
    /* WHAT IS YOUR OCCUPATION? */
    (167, 45, 'less_3', 'N', 9),
    (168, 45, 'more_than_3_and_less_6', 'N', 8),
    (169, 45, 'more_than_6_and_less_9', 'N', 7),
    (170, 45, 'more_than_9_and_less_12', 'N', 6),
    (171, 45, 'more_than_1_year_and_less_2', 'N', 5),
    (172, 45, 'more_than_2_year_and_less_3', 'N', 4),
    (173, 45, 'more_than_3_year_and_less_4', 'N', 3),
    (174, 45, 'more_than_4_year_and_less_5', 'N', 2),

    (175, 46, 'canada', 'N', 1),
    (176, 46, 'usa', 'N', 2),
    (177, 46, 'other', 'Y', 0),
    
    (178, 47, 'yes', 'N', 0),
    (179, 47, 'no', 'N', 1),
    
    /* BUSINESS/FINANCE */
    (180, 48, '0_to_9999', 'N', 0),
    (181, 48, '10000_to_24999', 'N', 1),
    (182, 48, '25000_to_49999', 'N', 2),
    (183, 48, '50000_to_99999', 'N', 3),
    (184, 48, '100000_to_299999', 'N', 4),
    (185, 48, '300000_to_499999', 'N', 5),
    (186, 48, '500000_to_799999', 'N', 6),
    (187, 48, '800000_to_999999', 'N', 7),
    
    (188, 49, 'yes', 'N', 0),
    (189, 49, 'no', 'N', 1),
    
    (190, 50, '1_year', 'N', 0),
    (191, 50, '2_years', 'N', 1),
    (192, 50, '3_years', 'N', 2),
    (193, 50, '4_years', 'N', 3),
    (194, 50, '5_years_or_more', 'N', 4),
    
    (195, 52, 'yes', 'N', 0),
    (196, 52, 'no', 'N', 1),
    
    
    (197, 35, 'none', 'N', 0),
    
    /* WORK IN CANADA - additional fields */
    (203, 60, 'yes', 'N', 0),
    (204, 60, 'no', 'N', 1),
    
    /* EDUCATION - additional fields */
    (205, 63, 'ph_d', 'N', 0),
    (206, 63, 'master', 'N', 1),
    (207, 63, '2_or_more', 'N', 2),
    (208, 63, 'bachelor_4', 'N', 3),
    (209, 63, 'bachelor_3', 'N', 4),
    (210, 63, 'bachelor_2', 'N', 5),
    (211, 63, 'bachelor_1', 'N', 6),
    (212, 63, 'diploma_3', 'N', 7),
    (213, 63, 'diploma_2', 'N', 8),
    (214, 63, 'diploma_1', 'N', 9),
    (215, 63, 'diploma_high', 'N', 10),
    (216, 63, 'diploma_below', 'N', 11),

    (363, 120, 'governmental', 'N', 0),
    (364, 120, 'private', 'N', 1),

    (365, 121, 'governmental', 'N', 0),
    (366, 121, 'private', 'N', 1),

    
    /* WORK IN CANADA - additional option*/
    (312, 30, '0', 'Y', 0),

    /* BUSINESS/FINANCE */
    (313, 48, '1000000_to_1599999', 'N', 8),
    (314, 48, '1600000_and_more', 'N', 9),

    /* CHILDREN */
    (315, 97, '0', 'N', 0),
    (316, 97, '1', 'N', 1),
    (317, 97, '2', 'N', 2),
    (318, 97, '3', 'N', 3),
    (319, 97, '4', 'N', 4),
    (320, 97, '5', 'N', 5),
    (321, 97, '6', 'N', 6),

    (322, 104, 'alberta', 'N', 0),
    (323, 104, 'british_columbia', 'N', 1),
    (324, 104, 'manitoba', 'N', 2),
    (325, 104, 'new_brunswick', 'N', 3),
    (326, 104, 'newfoundland_and_labrador', 'N', 4),
    (327, 104, 'northwest_territories', 'N', 5),
    (328, 104, 'nova_scotia', 'N', 6),
    (329, 104, 'nunavut', 'N', 7),
    (330, 104, 'ontario', 'N', 8),
    (331, 104, 'prince_edward_island', 'N', 9),
    (332, 104, 'quebec', 'N', 10),
    (333, 104, 'saskatchewan', 'N', 11),
    (334, 104, 'yukon', 'N', 12),
    (367, 122, 'yes', 'N', 0),
    (368, 122, 'no', 'Y', 1),


    /* spouse job */
    (335, 108, 'less_3', 'N', 9),
    (336, 108, 'more_than_3_and_less_6', 'N', 8),
    (337, 108, 'more_than_6_and_less_9', 'N', 7),
    (338, 108, 'more_than_9_and_less_12', 'N', 6),
    (339, 108, 'more_than_1_year_and_less_2', 'N', 5),
    (340, 108, 'more_than_2_year_and_less_3', 'N', 4),
    (341, 108, 'more_than_3_year_and_less_4', 'N', 3),
    (342, 108, 'more_than_4_year_and_less_5', 'N', 2),

    (343, 109, 'canada', 'N', 1),
    (344, 109, 'usa', 'N', 2),
    (345, 109, 'other', 'Y', 0),

    (346, 110, 'yes', 'N', 0),
    (347, 110, 'no', 'N', 1),

    (348, 111, 'alberta', 'N', 0),
    (349, 111, 'british_columbia', 'N', 1),
    (350, 111, 'manitoba', 'N', 2),
    (351, 111, 'new_brunswick', 'N', 3),
    (352, 111, 'newfoundland_and_labrador', 'N', 4),
    (353, 111, 'northwest_territories', 'N', 5),
    (354, 111, 'nova_scotia', 'N', 6),
    (355, 111, 'nunavut', 'N', 7),
    (356, 111, 'ontario', 'N', 8),
    (357, 111, 'prince_edward_island', 'N', 9),
    (358, 111, 'quebec', 'N', 10),
    (359, 111, 'saskatchewan', 'N', 11),
    (360, 111, 'yukon', 'N', 12),

    (369, 123, 'yes', 'N', 0),
    (370, 123, 'no', 'Y', 1),


    (361, 113, 'yes', 'N', 0),
    (362, 113, 'no', 'Y', 1),

    /* SPOUSE STUDY IN CANADA */
    (371, 126, 'yes', 'N', 0),
    (372, 126, 'no', 'N', 1),


   /* Language */
  /* Speak */
  (373, 73, 'level_12', 'N', 0),
  (374, 73, 'level_11', 'N', 1),
  (375, 73, 'level_10', 'N', 2),
  (376, 73, 'level_9',  'N', 3),
  (377, 73, 'level_8',  'N', 4),
  (378, 73, 'level_7',  'N', 5),
  (379, 73, 'level_6',  'N', 6),
  (380, 73, 'level_5',  'N', 7),
  (381, 73, 'level_4',  'N', 8),
  (382, 73, 'level_3',  'N', 9),
  (383, 73, 'level_2',  'N', 10),
  (384, 73, 'level_1',  'N', 11),

  (385, 74, 'level_12', 'N', 0),
  (386, 74, 'level_11', 'N', 1),
  (387, 74, 'level_10', 'N', 2),
  (388, 74, 'level_9',  'N', 3),
  (389, 74, 'level_8',  'N', 4),
  (390, 74, 'level_7',  'N', 5),
  (391, 74, 'level_6',  'N', 6),
  (392, 74, 'level_5',  'N', 7),
  (393, 74, 'level_4',  'N', 8),
  (394, 74, 'level_3',  'N', 9),
  (395, 74, 'level_2',  'N', 10),
  (396, 74, 'level_1',  'N', 11),

    /* Speak spouse */
  (397, 76, 'level_12', 'N', 0),
  (398, 76, 'level_11', 'N', 1),
  (399, 76, 'level_10', 'N', 2),
  (400, 76, 'level_9',  'N', 3),
  (401, 76, 'level_8',  'N', 4),
  (402, 76, 'level_7',  'N', 5),
  (403, 76, 'level_6',  'N', 6),
  (404, 76, 'level_5',  'N', 7),
  (405, 76, 'level_4',  'N', 8),
  (406, 76, 'level_3',  'N', 9),
  (407, 76, 'level_2',  'N', 10),
  (408, 76, 'level_1',  'N', 11),

  (409, 77, 'level_12', 'N', 0),
  (410, 77, 'level_11', 'N', 1),
  (411, 77, 'level_10', 'N', 2),
  (412, 77, 'level_9',  'N', 3),
  (413, 77, 'level_8',  'N', 4),
  (414, 77, 'level_7',  'N', 5),
  (415, 77, 'level_6',  'N', 6),
  (416, 77, 'level_5',  'N', 7),
  (417, 77, 'level_4',  'N', 8),
  (418, 77, 'level_3',  'N', 9),
  (419, 77, 'level_2',  'N', 10),
  (420, 77, 'level_1',  'N', 11),

  /* Read */
  (421, 79, 'level_12', 'N', 0),
  (422, 79, 'level_11', 'N', 1),
  (423, 79, 'level_10', 'N', 2),
  (424, 79, 'level_9',  'N', 3),
  (425, 79, 'level_8',  'N', 4),
  (426, 79, 'level_7',  'N', 5),
  (427, 79, 'level_6',  'N', 6),
  (428, 79, 'level_5',  'N', 7),
  (429, 79, 'level_4',  'N', 8),
  (430, 79, 'level_3',  'N', 9),
  (431, 79, 'level_2',  'N', 10),
  (432, 79, 'level_1',  'N', 11),

  (433, 80, 'level_12', 'N', 0),
  (434, 80, 'level_11', 'N', 1),
  (435, 80, 'level_10', 'N', 2),
  (436, 80, 'level_9',  'N', 3),
  (437, 80, 'level_8',  'N', 4),
  (438, 80, 'level_7',  'N', 5),
  (439, 80, 'level_6',  'N', 6),
  (440, 80, 'level_5',  'N', 7),
  (441, 80, 'level_4',  'N', 8),
  (442, 80, 'level_3',  'N', 9),
  (443, 80, 'level_2',  'N', 10),
  (444, 80, 'level_1',  'N', 11),

  /* Read Spouse */
  (445, 82, 'level_12', 'N', 0),
  (446, 82, 'level_11', 'N', 1),
  (447, 82, 'level_10', 'N', 2),
  (448, 82, 'level_9',  'N', 3),
  (449, 82, 'level_8',  'N', 4),
  (450, 82, 'level_7',  'N', 5),
  (451, 82, 'level_6',  'N', 6),
  (452, 82, 'level_5',  'N', 7),
  (453, 82, 'level_4',  'N', 8),
  (454, 82, 'level_3',  'N', 9),
  (455, 82, 'level_2',  'N', 10),
  (456, 82, 'level_1',  'N', 11),

  (457, 83, 'level_12', 'N', 0),
  (458, 83, 'level_11', 'N', 1),
  (459, 83, 'level_10', 'N', 2),
  (460, 83, 'level_9',  'N', 3),
  (461, 83, 'level_8',  'N', 4),
  (462, 83, 'level_7',  'N', 5),
  (463, 83, 'level_6',  'N', 6),
  (464, 83, 'level_5',  'N', 7),
  (465, 83, 'level_4',  'N', 8),
  (466, 83, 'level_3',  'N', 9),
  (467, 83, 'level_2',  'N', 10),
  (468, 83, 'level_1',  'N', 11),

  /* Write */
  (469, 85, 'level_12', 'N', 0),
  (470, 85, 'level_11', 'N', 1),
  (471, 85, 'level_10', 'N', 2),
  (472, 85, 'level_9',  'N', 3),
  (473, 85, 'level_8',  'N', 4),
  (474, 85, 'level_7',  'N', 5),
  (475, 85, 'level_6',  'N', 6),
  (476, 85, 'level_5',  'N', 7),
  (477, 85, 'level_4',  'N', 8),
  (478, 85, 'level_3',  'N', 9),
  (479, 85, 'level_2',  'N', 10),
  (480, 85, 'level_1',  'N', 11),

  (481, 86, 'level_12', 'N', 0),
  (482, 86, 'level_11', 'N', 1),
  (483, 86, 'level_10', 'N', 2),
  (484, 86, 'level_9',  'N', 3),
  (485, 86, 'level_8',  'N', 4),
  (486, 86, 'level_7',  'N', 5),
  (487, 86, 'level_6',  'N', 6),
  (488, 86, 'level_5',  'N', 7),
  (489, 86, 'level_4',  'N', 8),
  (490, 86, 'level_3',  'N', 9),
  (491, 86, 'level_2',  'N', 10),
  (492, 86, 'level_1',  'N', 11),

  /* Write spouse*/
  (493, 88, 'level_12', 'N', 0),
  (494, 88, 'level_11', 'N', 1),
  (495, 88, 'level_10', 'N', 2),
  (496, 88, 'level_9',  'N', 3),
  (497, 88, 'level_8',  'N', 4),
  (498, 88, 'level_7',  'N', 5),
  (499, 88, 'level_6',  'N', 6),
  (500, 88, 'level_5',  'N', 7),
  (501, 88, 'level_4',  'N', 8),
  (502, 88, 'level_3',  'N', 9),
  (503, 88, 'level_2',  'N', 10),
  (504, 88, 'level_1',  'N', 11),

  (505, 89, 'level_12', 'N', 0),
  (506, 89, 'level_11', 'N', 1),
  (507, 89, 'level_10', 'N', 2),
  (508, 89, 'level_9',  'N', 3),
  (509, 89, 'level_8',  'N', 4),
  (510, 89, 'level_7',  'N', 5),
  (511, 89, 'level_6',  'N', 6),
  (512, 89, 'level_5',  'N', 7),
  (513, 89, 'level_4',  'N', 8),
  (514, 89, 'level_3',  'N', 9),
  (515, 89, 'level_2',  'N', 10),
  (516, 89, 'level_1',  'N', 11),

  /* Listen */
  (517, 91, 'level_12', 'N', 0),
  (518, 91, 'level_11', 'N', 1),
  (519, 91, 'level_10', 'N', 2),
  (520, 91, 'level_9',  'N', 3),
  (521, 91, 'level_8',  'N', 4),
  (522, 91, 'level_7',  'N', 5),
  (523, 91, 'level_6',  'N', 6),
  (524, 91, 'level_5',  'N', 7),
  (525, 91, 'level_4',  'N', 8),
  (526, 91, 'level_3',  'N', 9),
  (527, 91, 'level_2',  'N', 10),
  (528, 91, 'level_1',  'N', 11),

  (529, 92, 'level_12', 'N', 0),
  (530, 92, 'level_11', 'N', 1),
  (531, 92, 'level_10', 'N', 2),
  (532, 92, 'level_9',  'N', 3),
  (533, 92, 'level_8',  'N', 4),
  (534, 92, 'level_7',  'N', 5),
  (535, 92, 'level_6',  'N', 6),
  (536, 92, 'level_5',  'N', 7),
  (537, 92, 'level_4',  'N', 8),
  (538, 92, 'level_3',  'N', 9),
  (539, 92, 'level_2',  'N', 10),
  (540, 92, 'level_1',  'N', 11),

  /* Listen spouse*/
  (541, 94, 'level_12', 'N', 0),
  (542, 94, 'level_11', 'N', 1),
  (543, 94, 'level_10', 'N', 2),
  (544, 94, 'level_9',  'N', 3),
  (545, 94, 'level_8',  'N', 4),
  (546, 94, 'level_7',  'N', 5),
  (547, 94, 'level_6',  'N', 6),
  (548, 94, 'level_5',  'N', 7),
  (549, 94, 'level_4',  'N', 8),
  (550, 94, 'level_3',  'N', 9),
  (551, 94, 'level_2',  'N', 10),
  (552, 94, 'level_1',  'N', 11),

  (553, 95, 'level_12', 'N', 0),
  (554, 95, 'level_11', 'N', 1),
  (555, 95, 'level_10', 'N', 2),
  (556, 95, 'level_9',  'N', 3),
  (557, 95, 'level_8',  'N', 4),
  (558, 95, 'level_7',  'N', 5),
  (559, 95, 'level_6',  'N', 6),
  (560, 95, 'level_5',  'N', 7),
  (561, 95, 'level_4',  'N', 8),
  (562, 95, 'level_3',  'N', 9),
  (563, 95, 'level_2',  'N', 10),
  (564, 95, 'level_1',  'N', 11),

  /* Additional options for experience field */
  (565, 45,  'more_than_5_year_and_less_6', 'N', 1),
  (566, 45,  'more_than_6_years', 'Y', 0),

  (567, 108, 'more_than_5_year_and_less_6', 'N', 1),
  (568, 108, 'more_than_6_years', 'Y', 0)
;

DROP TABLE IF EXISTS `company_questionnaires_fields_options_templates`;
CREATE TABLE `company_questionnaires_fields_options_templates` (
  `q_id` INT(11) UNSIGNED NOT NULL,
  `q_field_option_id` INT(11) UNSIGNED NOT NULL,
  `q_field_option_label` char(255) NOT NULL,
  `q_field_option_visible` ENUM('Y', 'N') DEFAULT 'Y',

  PRIMARY KEY  (`q_field_option_id`, `q_id`),
  CONSTRAINT `FK_company_questionnaires_fields_options_templates_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_questionnaires_fields_options_templates_2` FOREIGN KEY `FK_company_questionnaires_fields_options_templates_2` (`q_field_option_id`) REFERENCES `company_questionnaires_fields_options` (`q_field_option_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) VALUES
    /* PERSONAL INFORMATION */
    (1, 1,  'Mr.', 'Y'),
    (1, 2,  'Ms.', 'Y'),
    (1, 3,  'Mrs.', 'Y'),
    (1, 4,  'Miss.', 'Y'),
    (1, 5,  'Dr.', 'Y'),

    /* MARITAL STATUS */
    (1, 6,  'Never married', 'Y'),
    (1, 7,  'Married', 'Y'),
    (1, 8,  'Widowed', 'Y'),
    (1, 9,  'Legally separated', 'Y'),
    (1, 10, 'Annulled marriage', 'Y'),
    (1, 11, 'Divorced', 'Y'),
    (1, 12, 'Common-law', 'Y'),
    (1, 364, 'Widowed', 'Y'),
    (1, 365, 'Separated', 'Y'),

    /* EDUCATION */
    (1, 49, 'Ph. D.', 'Y'),
    (1, 50, "Master's degree", 'Y'),
    (1, 51, "2 or more Bachelor's degrees", 'Y'),
    (1, 52, "Bachelor's degree (4 years)", 'Y'),
    (1, 53, "Bachelor's degree (3 years)", 'Y'),
    (1, 54, "Bachelor's degree (2 years)", 'Y'),
    (1, 55, "Bachelor's degree (1 year)", 'Y'),
    (1, 56, 'Diploma, Trade certificate, or Apprenticeship (3 years)', 'Y'),
    (1, 57, 'Diploma, Trade certificate, or Apprenticeship (2 years)', 'Y'),
    (1, 58, 'Diploma, Trade certificate, or Apprenticeship (1 year)', 'Y'),
    (1, 59, 'High school diploma', 'Y'),
    (1, 60, 'Below high school diploma', 'Y'),

    /* WORK IN CANADA */
    (1, 126, 'Yes', 'Y'),
    (1, 127, 'No', 'Y'),

    (1, 128, '1 year', 'Y'),
    (1, 129, '2 years or more', 'Y'),

    (1, 130, 'Yes', 'Y'),
    (1, 131, 'No', 'Y'),

    (1, 132, 'Less than a year ago', 'Y'),
    (1, 133, 'More than a year ago', 'Y'),

    /* STUDY IN CANADA */
    (1, 134, 'Yes', 'Y'),
    (1, 135, 'No', 'Y'),

    /* FAMILY RELATIONS IN CANADA */
    (1, 136, 'Yes', 'Y'),
    (1, 137, 'No', 'Y'),

    (1, 138, 'Mother or father', 'Y'),
    (1, 139, 'Daughter or son', 'Y'),
    (1, 140, 'Sister or brother', 'Y'),
    (1, 141, 'Niece or nephew', 'Y'),
    (1, 142, 'Grandmother or grandfather', 'Y'),
    (1, 143, 'Granddaughter or grandson', 'Y'),
    (1, 144, 'Aunt or uncle', 'Y'),
    (1, 145, 'Spouse or common-law partner', 'Y'),

    (1, 146, 'Yes', 'Y'),
    (1, 147, 'No', 'Y'),

    (1, 148, 'Younger than 18 years', 'Y'),
    (1, 149, '18 years or over', 'Y'),

    (1, 150, 'Employed', 'Y'),
    (1, 151, 'Self-Employed', 'Y'),
    (1, 152, 'Unemployed', 'Y'),

    (1, 153, '0', 'Y'),
    (1, 154, '1', 'Y'),
    (1, 155, '2', 'Y'),
    (1, 156, '3', 'Y'),
    (1, 157, '4', 'Y'),
    (1, 158, '5', 'Y'),
    (1, 159, '6', 'Y'),
    (1, 160, '7', 'Y'),
    (1, 161, '8', 'Y'),
    (1, 162, '9', 'Y'),

    (1, 163, 'Yes', 'Y'),
    (1, 164, 'No', 'Y'),

    (1, 165, 'Yes', 'Y'),
    (1, 166, 'No', 'Y'),
    
    /* WHAT IS YOUR OCCUPATION? */
    (1, 167, 'Less than 3 months', 'Y'),
    (1, 168, '3 months or more, but less than 6 months', 'Y'),
    (1, 169, '6 months or more, but less than 9 months', 'Y'),
    (1, 170, '9 months or more, but less than 1 year', 'Y'),
    (1, 171, '1 year or more, but less than 2 years', 'Y'),
    (1, 172, '2 year or more, but less than 3 years', 'Y'),
    (1, 173, '3 year or more, but less than 4 years', 'Y'),
    (1, 174, '4 years or more, but less than 5 years', 'Y'),
    
    (1, 175, 'In Canada', 'Y'),
    (1, 176, 'In USA', 'Y'),
    (1, 177, 'Outside Canada', 'Y'),
    
    (1, 178, 'Yes', 'Y'),
    (1, 179, 'No', 'Y'),
    
    /* BUSINESS/FINANCE */
    (1, 180, '0 to 9,999', 'Y'),
    (1, 181, '10,000 to 24,999', 'Y'),
    (1, 182, '25,000 to 49,999', 'Y'),
    (1, 183, '50,000 to 99,999', 'Y'),
    (1, 184, '100,000 to 299,999', 'Y'),
    (1, 185, '300,000 to 499,999', 'Y'),
    (1, 186, '500,000 to 799,999', 'Y'),
    (1, 187, '800,000 +', 'Y'),
    
    (1, 188, 'Yes', 'Y'),
    (1, 189, 'No', 'Y'),
    
    (1, 190, '1 Year', 'Y'),
    (1, 191, '2 Years', 'Y'),
    (1, 192, '3 Years', 'Y'),
    (1, 193, '4 Years', 'Y'),
    (1, 194, '5 Years +', 'Y'),

    (1, 195, 'Yes', 'Y'),
    (1, 196, 'No', 'Y'),
    
    (1, 197, 'None', 'Y'),
    
    /* WORK IN CANADA - additional fields */
    (1, 203, 'Yes', 'Y'),
    (1, 204, 'No', 'Y'),
    
    /* EDUCATION - additional fields */
    (1, 205, 'Ph. D.', 'Y'),
    (1, 206, "Master's degree", 'Y'),
    (1, 207, "2 or more Bachelor's degrees", 'Y'),
    (1, 208, "Bachelor's degree (4 years)", 'Y'),
    (1, 209, "Bachelor's degree (3 years)", 'Y'),
    (1, 210, "Bachelor's degree (2 years)", 'Y'),
    (1, 211, "Bachelor's degree (1 year)", 'Y'),
    (1, 212, 'Diploma, Trade certificate, or Apprenticeship (3 years)', 'Y'),
    (1, 213, 'Diploma, Trade certificate, or Apprenticeship (2 years)', 'Y'),
    (1, 214, 'Diploma, Trade certificate, or Apprenticeship (1 year)', 'Y'),
    (1, 215, 'High school diploma', 'Y'),
    (1, 216, 'Below high school diploma', 'Y'),

    (1, 363, 'Governmental', 'Y'),
    (1, 364, 'Private', 'Y'),
    (1, 365, 'Governmental', 'Y'),
    (1, 366, 'Private', 'Y'),

    /* WORK IN CANADA - additional option*/
    (1, 312, 'None', 'Y'),


    (1, 313, '1,000,000 to 1,599,999', 'Y'),
    (1, 314, '1,600,000+', 'Y'),

    /* CHILDREN */
    (1, 315, '0', 'Y'),
    (1, 316, '1', 'Y'),
    (1, 317, '2', 'Y'),
    (1, 318, '3', 'Y'),
    (1, 319, '4', 'Y'),
    (1, 320, '5', 'Y'),
    (1, 321, '6+', 'Y'),


    (1, 322, 'Alberta', 'Y'),
    (1, 323, 'British Columbia', 'Y'),
    (1, 324, 'Manitoba', 'Y'),
    (1, 325, 'New Brunswick', 'Y'),
    (1, 326, 'Newfoundland and Labrador', 'Y'),
    (1, 327, 'Northwest Territories', 'Y'),
    (1, 328, 'Nova Scotia', 'Y'),
    (1, 329, 'Nunavut', 'Y'),
    (1, 330, 'Ontario', 'Y'),
    (1, 331, 'Prince Edward Island', 'Y'),
    (1, 332, 'Quebec', 'Y'),
    (1, 333, 'Saskatchewan', 'Y'),
    (1, 334, 'Yukon', 'Y'),

    (1, 367, 'Yes', 'Y'),
    (1, 368, 'No', 'Y'),


    /* Spouse Job */
    (1, 335, 'Less than 3 months', 'Y'),
    (1, 336, '3 months or more, but less than 6 months', 'Y'),
    (1, 337, '6 months or more, but less than 9 months', 'Y'),
    (1, 338, '9 months or more, but less than 1 year', 'Y'),
    (1, 339, '1 year or more, but less than 2 years', 'Y'),
    (1, 340, '2 year or more, but less than 3 years', 'Y'),
    (1, 341, '3 year or more, but less than 4 years', 'Y'),
    (1, 342, '4 years or more, but less than 5 years', 'Y'),

    (1, 343, 'In Canada', 'Y'),
    (1, 344, 'In USA', 'Y'),
    (1, 345, 'Outside Canada', 'Y'),

    (1, 346, 'Yes', 'Y'),
    (1, 347, 'No', 'Y'),

    (1, 348, 'Alberta', 'Y'),
    (1, 349, 'British Columbia', 'Y'),
    (1, 350, 'Manitoba', 'Y'),
    (1, 351, 'New Brunswick', 'Y'),
    (1, 352, 'Newfoundland and Labrador', 'Y'),
    (1, 353, 'Northwest Territories', 'Y'),
    (1, 354, 'Nova Scotia', 'Y'),
    (1, 355, 'Nunavut', 'Y'),
    (1, 356, 'Ontario', 'Y'),
    (1, 357, 'Prince Edward Island', 'Y'),
    (1, 358, 'Quebec', 'Y'),
    (1, 359, 'Saskatchewan', 'Y'),
    (1, 360, 'Yukon', 'Y'),

    (1, 369, 'Yes', 'Y'),
    (1, 370, 'No', 'Y'),

    (1, 361, 'Yes', 'Y'),
    (1, 362, 'No', 'Y'),

    (1, 371, 'Yes', 'Y'),
    (1, 372, 'No', 'Y'),

    /* Languages */
  (1, 373, 'Level 12', 'Y'),
  (1, 374, 'Level 11', 'Y'),
  (1, 375, 'Level 10', 'Y'),
  (1, 376, 'Level 9', 'Y'),
  (1, 377, 'Level 8', 'Y'),
  (1, 378, 'Level 7', 'Y'),
  (1, 379, 'Level 6', 'Y'),
  (1, 380, 'Level 5', 'Y'),
  (1, 381, 'Level 4', 'Y'),
  (1, 382, 'Level 3', 'Y'),
  (1, 383, 'Level 2', 'Y'),
  (1, 384, 'Level 1', 'Y'),

  (1, 385, 'Level 12', 'Y'),
  (1, 386, 'Level 11', 'Y'),
  (1, 387, 'Level 10', 'Y'),
  (1, 388, 'Level 9', 'Y'),
  (1, 389, 'Level 8', 'Y'),
  (1, 390, 'Level 7', 'Y'),
  (1, 391, 'Level 6', 'Y'),
  (1, 392, 'Level 5', 'Y'),
  (1, 393, 'Level 4', 'Y'),
  (1, 394, 'Level 3', 'Y'),
  (1, 395, 'Level 2', 'Y'),
  (1, 396, 'Level 1', 'Y'),

  (1, 397, 'Level 12', 'Y'),
  (1, 398, 'Level 11', 'Y'),
  (1, 399, 'Level 10', 'Y'),
  (1, 400, 'Level 9', 'Y'),
  (1, 401, 'Level 8', 'Y'),
  (1, 402, 'Level 7', 'Y'),
  (1, 403, 'Level 6', 'Y'),
  (1, 404, 'Level 5', 'Y'),
  (1, 405, 'Level 4', 'Y'),
  (1, 406, 'Level 3', 'Y'),
  (1, 407, 'Level 2', 'Y'),
  (1, 408, 'Level 1', 'Y'),

  (1, 409, 'Level 12', 'Y'),
  (1, 410, 'Level 11', 'Y'),
  (1, 411, 'Level 10', 'Y'),
  (1, 412, 'Level 9', 'Y'),
  (1, 413, 'Level 8', 'Y'),
  (1, 414, 'Level 7', 'Y'),
  (1, 415, 'Level 6', 'Y'),
  (1, 416, 'Level 5', 'Y'),
  (1, 417, 'Level 4', 'Y'),
  (1, 418, 'Level 3', 'Y'),
  (1, 419, 'Level 2', 'Y'),
  (1, 420, 'Level 1', 'Y'),

  (1, 421, 'Level 12', 'Y'),
  (1, 422, 'Level 11', 'Y'),
  (1, 423, 'Level 10', 'Y'),
  (1, 424, 'Level 9', 'Y'),
  (1, 425, 'Level 8', 'Y'),
  (1, 426, 'Level 7', 'Y'),
  (1, 427, 'Level 6', 'Y'),
  (1, 428, 'Level 5', 'Y'),
  (1, 429, 'Level 4', 'Y'),
  (1, 430, 'Level 3', 'Y'),
  (1, 431, 'Level 2', 'Y'),
  (1, 432, 'Level 1', 'Y'),

  (1, 433, 'Level 12', 'Y'),
  (1, 434, 'Level 11', 'Y'),
  (1, 435, 'Level 10', 'Y'),
  (1, 436, 'Level 9', 'Y'),
  (1, 437, 'Level 8', 'Y'),
  (1, 438, 'Level 7', 'Y'),
  (1, 439, 'Level 6', 'Y'),
  (1, 440, 'Level 5', 'Y'),
  (1, 441, 'Level 4', 'Y'),
  (1, 442, 'Level 3', 'Y'),
  (1, 443, 'Level 2', 'Y'),
  (1, 444, 'Level 1', 'Y'),

  (1, 445, 'Level 12', 'Y'),
  (1, 446, 'Level 11', 'Y'),
  (1, 447, 'Level 10', 'Y'),
  (1, 448, 'Level 9', 'Y'),
  (1, 449, 'Level 8', 'Y'),
  (1, 450, 'Level 7', 'Y'),
  (1, 451, 'Level 6', 'Y'),
  (1, 452, 'Level 5', 'Y'),
  (1, 453, 'Level 4', 'Y'),
  (1, 454, 'Level 3', 'Y'),
  (1, 455, 'Level 2', 'Y'),
  (1, 456, 'Level 1', 'Y'),

  (1, 457, 'Level 12', 'Y'),
  (1, 458, 'Level 11', 'Y'),
  (1, 459, 'Level 10', 'Y'),
  (1, 460, 'Level 9', 'Y'),
  (1, 461, 'Level 8', 'Y'),
  (1, 462, 'Level 7', 'Y'),
  (1, 463, 'Level 6', 'Y'),
  (1, 464, 'Level 5', 'Y'),
  (1, 465, 'Level 4', 'Y'),
  (1, 466, 'Level 3', 'Y'),
  (1, 467, 'Level 2', 'Y'),
  (1, 468, 'Level 1', 'Y'),

  (1, 469, 'Level 12', 'Y'),
  (1, 470, 'Level 11', 'Y'),
  (1, 471, 'Level 10', 'Y'),
  (1, 472, 'Level 9', 'Y'),
  (1, 473, 'Level 8', 'Y'),
  (1, 474, 'Level 7', 'Y'),
  (1, 475, 'Level 6', 'Y'),
  (1, 476, 'Level 5', 'Y'),
  (1, 477, 'Level 4', 'Y'),
  (1, 478, 'Level 3', 'Y'),
  (1, 479, 'Level 2', 'Y'),
  (1, 480, 'Level 1', 'Y'),

  (1, 481, 'Level 12', 'Y'),
  (1, 482, 'Level 11', 'Y'),
  (1, 483, 'Level 10', 'Y'),
  (1, 484, 'Level 9', 'Y'),
  (1, 485, 'Level 8', 'Y'),
  (1, 486, 'Level 7', 'Y'),
  (1, 487, 'Level 6', 'Y'),
  (1, 488, 'Level 5', 'Y'),
  (1, 489, 'Level 4', 'Y'),
  (1, 490, 'Level 3', 'Y'),
  (1, 491, 'Level 2', 'Y'),
  (1, 492, 'Level 1', 'Y'),

  (1, 493, 'Level 12', 'Y'),
  (1, 494, 'Level 11', 'Y'),
  (1, 495, 'Level 10', 'Y'),
  (1, 496, 'Level 9', 'Y'),
  (1, 497, 'Level 8', 'Y'),
  (1, 498, 'Level 7', 'Y'),
  (1, 499, 'Level 6', 'Y'),
  (1, 500, 'Level 5', 'Y'),
  (1, 501, 'Level 4', 'Y'),
  (1, 502, 'Level 3', 'Y'),
  (1, 503, 'Level 2', 'Y'),
  (1, 504, 'Level 1', 'Y'),

  (1, 505, 'Level 12', 'Y'),
  (1, 506, 'Level 11', 'Y'),
  (1, 507, 'Level 10', 'Y'),
  (1, 508, 'Level 9', 'Y'),
  (1, 509, 'Level 8', 'Y'),
  (1, 510, 'Level 7', 'Y'),
  (1, 511, 'Level 6', 'Y'),
  (1, 512, 'Level 5', 'Y'),
  (1, 513, 'Level 4', 'Y'),
  (1, 514, 'Level 3', 'Y'),
  (1, 515, 'Level 2', 'Y'),
  (1, 516, 'Level 1', 'Y'),

  (1, 517, 'Level 12', 'Y'),
  (1, 518, 'Level 11', 'Y'),
  (1, 519, 'Level 10', 'Y'),
  (1, 520, 'Level 9', 'Y'),
  (1, 521, 'Level 8', 'Y'),
  (1, 522, 'Level 7', 'Y'),
  (1, 523, 'Level 6', 'Y'),
  (1, 524, 'Level 5', 'Y'),
  (1, 525, 'Level 4', 'Y'),
  (1, 526, 'Level 3', 'Y'),
  (1, 527, 'Level 2', 'Y'),
  (1, 528, 'Level 1', 'Y'),

  (1, 529, 'Level 12', 'Y'),
  (1, 530, 'Level 11', 'Y'),
  (1, 531, 'Level 10', 'Y'),
  (1, 532, 'Level 9', 'Y'),
  (1, 533, 'Level 8', 'Y'),
  (1, 534, 'Level 7', 'Y'),
  (1, 535, 'Level 6', 'Y'),
  (1, 536, 'Level 5', 'Y'),
  (1, 537, 'Level 4', 'Y'),
  (1, 538, 'Level 3', 'Y'),
  (1, 539, 'Level 2', 'Y'),
  (1, 540, 'Level 1', 'Y'),
  
  (1, 541, 'Level 12', 'Y'),
  (1, 542, 'Level 11', 'Y'),
  (1, 543, 'Level 10', 'Y'),
  (1, 544, 'Level 9', 'Y'),
  (1, 545, 'Level 8', 'Y'),
  (1, 546, 'Level 7', 'Y'),
  (1, 547, 'Level 6', 'Y'),
  (1, 548, 'Level 5', 'Y'),
  (1, 549, 'Level 4', 'Y'),
  (1, 550, 'Level 3', 'Y'),
  (1, 551, 'Level 2', 'Y'),
  (1, 552, 'Level 1', 'Y'),    

  (1, 553, 'Level 12', 'Y' ),
  (1, 554, 'Level 11', 'Y' ),
  (1, 555, 'Level 10', 'Y' ),
  (1, 556, 'Level 9', 'Y' ),
  (1, 557, 'Level 8', 'Y' ),
  (1, 558, 'Level 7', 'Y' ),
  (1, 559, 'Level 6', 'Y' ),
  (1, 560, 'Level 5', 'Y' ),
  (1, 561, 'Level 4', 'Y' ),
  (1, 562, 'Level 3', 'Y' ),
  (1, 563, 'Level 2', 'Y' ),
  (1, 564, 'Level 1', 'Y' ),

  (1, 565, '5 years or more, but less than 6 years', 'Y'),
  (1, 566, '6 years or more', 'Y'),
  (1, 567, '5 years or more, but less than 6 years', 'Y'),
  (1, 568, '6 years or more', 'Y')
;

DROP TABLE IF EXISTS `company_prospects`;
CREATE TABLE `company_prospects` (
  `prospect_id` bigint(20) NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `email` varchar(255) default NULL,
  `fName` varchar(255) default NULL,
  `lName` varchar(255) default NULL,
  `category_pnp` VARCHAR(255) NULL DEFAULT NULL,
  `category_other` varchar(255) NULL DEFAULT NULL,
  `qualified` ENUM('Y','N') NULL DEFAULT NULL,
  `viewed` ENUM('Y','N') NULL DEFAULT 'N',
  `seriousness` ENUM('A','B','C','D') NULL DEFAULT NULL,
  `referred_by` VARCHAR(255) NULL DEFAULT NULL,
  `preferred_language` VARCHAR(255) NULL DEFAULT NULL,
  `agent_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `date_of_birth` DATE NULL DEFAULT NULL,
  `spouse_date_of_birth` DATE NULL DEFAULT NULL,
  `assessment` TEXT NULL DEFAULT NULL,
  `create_date` datetime default NULL,
  `update_date` datetime default NULL,
  `email_sent` ENUM('Y','N') NULL DEFAULT 'N',
  `visa` INT(11) UNSIGNED NULL DEFAULT NULL,
  `notes` TEXT NULL DEFAULT NULL,
  PRIMARY KEY  (`prospect_id`),
  CONSTRAINT `FK_company_prospects_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_prospects_company_default_options` FOREIGN KEY (`visa`) REFERENCES `company_default_options` (`default_option_id`) ON UPDATE SET NULL ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `company_prospects_divisions`;
CREATE TABLE `company_prospects_divisions` (
	`prospect_id` BIGINT(20) NOT NULL,
	`office_id` INT(11) UNSIGNED NOT NULL,
	INDEX `FK_company_prospects_divisions_1` (`prospect_id`),
	INDEX `FK_company_prospects_divisions_2` (`office_id`),
	CONSTRAINT `FK_company_prospects_divisions_divisions` FOREIGN KEY (`office_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_company_prospects_divisions_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `company_prospects_data_categories`;
CREATE TABLE `company_prospects_data_categories` (
  `prospect_id` bigint(20) NOT NULL,
  `prospect_category_id` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY  (`prospect_id`, `prospect_category_id`),
  CONSTRAINT `FK_company_prospects_categories_1` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_prospects_categories2` FOREIGN KEY (`prospect_category_id`) REFERENCES `company_prospects_categories` (`prospect_category_id`) ON UPDATE NO ACTION ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `company_prospects_job`;
CREATE TABLE `company_prospects_job` (
  `qf_job_id` bigint(20) NOT NULL auto_increment,
  `prospect_id` bigint(20) NOT NULL,
  `prospect_type` ENUM('main','spouse') NOT NULL DEFAULT 'main',
  `qf_job_order` INT(11) UNSIGNED NOT NULL DEFAULT '0',
  `qf_job_title` varchar(255) NOT NULL,
  `qf_job_noc` varchar(255) NULL,
  `qf_job_duration` INT(11) NOT NULL,
  `qf_job_location` INT(11) NOT NULL,
  `qf_job_province` INT(11) UNSIGNED NULL,
  `qf_job_presently_working` INT(11) NOT NULL,
  `qf_job_qualified_for_social_security` INT(11) UNSIGNED NULL,
  `qf_job_employment_type` VARCHAR(255) NULL DEFAULT NULL,
  `qf_job_employer` VARCHAR(255) NULL DEFAULT NULL,
  `qf_job_text_title` VARCHAR(255) NULL DEFAULT NULL,
  `qf_job_country_of_employment` VARCHAR(255) NULL DEFAULT NULL,
  `qf_job_start_date` DATE NULL DEFAULT NULL,
  `qf_job_end_date` DATE NULL DEFAULT NULL,
  `qf_job_resume` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`qf_job_id`),
  CONSTRAINT `FK_company_prospects_job_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `company_prospects_data`;
CREATE TABLE `company_prospects_data` (
  `prospect_id` bigint(20) NOT NULL,
  `q_field_id` INT(11) UNSIGNED NOT NULL,
  `q_value` varchar(255) NOT NULL,
  PRIMARY KEY  (`prospect_id`, `q_field_id`),
  CONSTRAINT `FK_company_prospects_data_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_company_prospects_data_q_fields` FOREIGN KEY (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;