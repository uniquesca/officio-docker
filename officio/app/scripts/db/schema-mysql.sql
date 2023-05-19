DROP TABLE IF EXISTS `u_payment_templates`;
CREATE TABLE IF NOT EXISTS `u_payment_templates` (
  `saved_payment_template_id` int(11) unsigned NOT NULL auto_increment,
  `company_id` BIGINT(20) NOT NULL,
  `name` varchar(255) default NULL,
  `payments` text,
  `created_date` date default NULL,
  PRIMARY KEY  (`saved_payment_template_id`),
  CONSTRAINT `FK_u_payment_templates_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `default_searches`;
CREATE TABLE `default_searches` (
    `member_id` BIGINT(20) NOT NULL,
    `default_search` VARCHAR (50) NOT NULL,
    `default_search_type` ENUM('clients','contacts') NOT NULL DEFAULT 'clients',
  PRIMARY KEY  (`member_id`),
  CONSTRAINT `FK_default_searches_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `faq`;
CREATE TABLE `faq` (
  `faq_id` int(11) unsigned NOT NULL auto_increment,
  `faq_section_id` int(3) unsigned default NULL,
  `question` text,
  `answer` longtext,
  `order` int(3) unsigned default NULL,
  `client_view` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY  (`faq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `faq_sections`;
CREATE TABLE `faq_sections` (
  `faq_section_id` int(11) unsigned NOT NULL auto_increment,
  `parent_section_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `section_name` varchar(255) default NULL,
  `order` int(3) unsigned default NULL,
  `client_view` enum('Y', 'N') default 'N',
  PRIMARY KEY (`faq_section_id`),
  INDEX `FK_faq_sections_faq_sections` (`parent_section_id`),
  CONSTRAINT `FK_faq_sections_faq_sections` FOREIGN KEY (`parent_section_id`) REFERENCES `faq_sections` (`faq_section_id`) ON UPDATE NO ACTION ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `folder_access`;
CREATE TABLE `folder_access` (
  `folder_id` int(11) unsigned default NULL,
  `role_id` INT(11) NULL DEFAULT NULL,
  `access` enum('R','RW') default NULL,
  CONSTRAINT `FK_folder_access_u_folders` FOREIGN KEY (`folder_id`) REFERENCES `u_folders` (`folder_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_folder_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* admin */
INSERT INTO `folder_access` VALUES (1,4,'RW');
INSERT INTO `folder_access` VALUES (2,4,'RW');
INSERT INTO `folder_access` VALUES (3,4,'RW');
INSERT INTO `folder_access` VALUES (4,4,'RW');
INSERT INTO `folder_access` VALUES (5,4,'RW');
INSERT INTO `folder_access` VALUES (6,4,'RW');
INSERT INTO `folder_access` VALUES (7,4,'RW');

/* user */
INSERT INTO `folder_access` VALUES (1,3,'R');
INSERT INTO `folder_access` VALUES (2,3,'R');
INSERT INTO `folder_access` VALUES (3,3,'RW');
INSERT INTO `folder_access` VALUES (4,3,'RW');
INSERT INTO `folder_access` VALUES (5,3,'RW');
INSERT INTO `folder_access` VALUES (6,3,'RW');
INSERT INTO `folder_access` VALUES (7,3,'RW');

/* client */
INSERT INTO `folder_access` VALUES (2,2,'R');
INSERT INTO `folder_access` VALUES (3,2,'RW');

/* For superadmin - see below (records will be inserted when new folders will be created) */
DROP TABLE IF EXISTS `members_types`;
CREATE TABLE `members_types` (
	`member_type_id` INT(2) UNSIGNED NOT NULL AUTO_INCREMENT,
	`member_type_name` VARCHAR(30) NOT NULL DEFAULT '',
	`member_type_case_template_name` VARCHAR(30) NOT NULL DEFAULT '',
	`member_type_visible` ENUM('Y','N') NOT NULL DEFAULT 'Y',
	PRIMARY KEY (`member_type_id`),
	INDEX `members_types` (`member_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `members_types` VALUES (1, 'superadmin', '', 'N');
INSERT INTO `members_types` VALUES (2, 'admin', '', 'N');
INSERT INTO `members_types` VALUES (3, 'case', '', 'N');
INSERT INTO `members_types` VALUES (4, 'user', '', 'N');
INSERT INTO `members_types` VALUES (5, 'agent', '', 'N');
INSERT INTO `members_types` VALUES (6, 'crm_user', '', 'N');
INSERT INTO `members_types` VALUES (7, 'employer', 'Employer Clients', 'Y');
INSERT INTO `members_types` VALUES (8, 'individual', 'Individual Clients', 'Y');
INSERT INTO `members_types` VALUES (9, 'internal_contact', 'Internal Contact', 'N');
INSERT INTO `members_types` VALUES (10, 'contact', 'Contact', 'Y');


DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `member_id` bigint(20) NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `userType` INT(2) UNSIGNED NOT NULL,
  `username` varchar(50) default NULL,
  `password` varchar(50) default NULL,
  `emailAddress` varchar(255) default NULL,
  `fName` varchar(255) default NULL,
  `lName` varchar(255) NOT NULL DEFAULT '',
  `regTime` int(11) default NULL,
  `lastLogin` int(11) default NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `login_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `login_temporary_disabled_on` DATETIME NULL DEFAULT NULL,
  `disabled_timestamp` int(11) NOT NULL DEFAULT '0',
  `password_change_date` INT(11) NULL DEFAULT NULL,
  PRIMARY KEY  (`member_id`),
  CONSTRAINT `FK_members_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_members_members_types` FOREIGN KEY (`userType`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `members` VALUES (1,0,1,'superadmin','KecLX14Sr7xpcPFQoTFoCKXqJ7GDwX987oFMMnjvA+Y=','superadmin@uniques.com','Super','Admin',UNIX_TIMESTAMP(),'',1,'Y','');

CREATE TABLE `members_password_retrievals` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` BIGINT(20) NOT NULL,
  `hash` VARCHAR(40) NOT NULL,
  `expiration` INT(11) NOT NULL COMMENT 'datetime when hash will be expired',
  PRIMARY KEY (`id`),
  INDEX `FK_passwd_retr_members` (`member_id`),
  CONSTRAINT `FK_passwd_retr_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;


DROP TABLE IF EXISTS `members_last_access`;
CREATE TABLE `members_last_access` (
  `member_id` bigint(20) NOT NULL,
  `view_member_id` bigint(20) NOT NULL,
  `access_date` datetime default NULL,
  PRIMARY KEY  (`member_id`, `view_member_id`),
  CONSTRAINT `FK_members_last_access_1` FOREIGN KEY `FK_members_last_access_1` (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `reconciliation_log`;
CREATE TABLE `reconciliation_log` (
  `reconciliation_id` int(11) unsigned NOT NULL auto_increment,
  `reconciliation_type` ENUM('general','iccrc') NOT NULL DEFAULT 'general',
  `ta_id` int(11) unsigned default NULL,
  `author_id` int(11) unsigned default NULL,
  `recon_date` date default NULL,
  `create_date` date default NULL,
  PRIMARY KEY  (`reconciliation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `templates`;
CREATE TABLE `templates` (
  `template_id` int(11) unsigned NOT NULL auto_increment,
  `member_id` BIGINT(20) NOT NULL,
  `folder_id` int(11) unsigned default NULL,
  `order` int(11) unsigned DEFAULT '0',
  `templates_for` ENUM( 'Invoice', 'Payment', 'General', 'Password', 'Request', 'Prospect' ) DEFAULT 'General',
  `templates_type` ENUM('Email','Letter') NULL DEFAULT 'Email',
  `name` char(255) default NULL,
  `subject` char(255) default NULL,
  `from` char(255) default NULL,
  `cc` char(255) default NULL,
  `bcc` char(255) default NULL,
  `message` longtext,
  `create_date` date default NULL,
  `default` enum('Y','N') default 'N',
  PRIMARY KEY  (`template_id`),
  CONSTRAINT `FK_templates_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_templates_u_folders` FOREIGN KEY (`folder_id`) REFERENCES `u_folders` (`folder_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `templates` VALUES (0,1,7,1,'General','Default Template','','','','','Dear &lt;%title%&gt; &lt;%last_name%&gt;,<br>\n<br>\nThank you for your email.<br>\n<br>\n<br>\n<br>\nYours truly,<br>\n&lt;%current_user_fName%&gt; &lt;%current_user_lName%&gt;<br>\n<br>\n&lt;%company_name%&gt;<br>\n&lt;%company_address%&gt;<br>\n&lt;%company_city%&gt;, &lt;%company_state%&gt;<br>\n&lt;%company_zip%&gt;, &lt;%company_country%&gt;<br>\nEmail: &lt;%company_email%&gt;<br>\nPhone: &lt;%company_phone_1%&gt;<br>\nFax: &lt;%company_fax%&gt;<br>\n<br>\n<br>\nNOTICE OF CONFIDENTIALITY: This material is intended for the use of the\nindividual to whom it is addressed and may contain information that is\nprivileged, proprietary, confidential and exempt from disclosure. If\nyou are\nnot the intended recipient or the person responsible for delivering the\nmaterial to the intended recipient, you are notified that\ndissemination,\ndistribution or copying of this communication is strictly prohibited.\nIf you\nhave received this communication in error, please contact the sender\nimmediately via e-mail and destroy this message accordingly.','2009-10-05','Y');

DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
  `news_id` int(11) unsigned NOT NULL auto_increment,
  `title` tinytext,
  `content` longtext,
  `create_date` date default NULL,
  PRIMARY KEY  (`news_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `divisions`;
CREATE TABLE `divisions` (
  `division_id` int(11) unsigned NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `name` char(255) default '',
  `order` tinyint(3) unsigned default 0,
  PRIMARY KEY  (`division_id`),
  CONSTRAINT `FK_divisions_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `members_divisions`;
CREATE TABLE `members_divisions` (
  `member_id` BIGINT(20) NOT NULL,
  `division_id` INT(11) UNSIGNED NOT NULL,
  CONSTRAINT `FK_members_divisions_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_members_divisions_divisions` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `user_smtp`;
CREATE TABLE `user_smtp` (
  `smtp_id` int(11) unsigned NOT NULL auto_increment,
  `member_id` bigint(20) default NULL,
  `email_signature` char(255) default NULL,
  `email_friendly_name` char(255) default NULL,
  `smtp_use_own` enum('Y','N') default 'N',
  `smtp_host` char(255) default NULL,
  `smtp_port` int(4) unsigned default NULL,
  `smtp_username` char(255) default NULL,
  `smtp_password` char(255) default NULL,
  `smtp_use_ssl` ENUM('','ssl','tls') NOT NULL,
  PRIMARY KEY  (`smtp_id`),
  CONSTRAINT `FK_user_smtp_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `agents`;
CREATE TABLE `agents` (
  `agent_id` int(11) unsigned NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `title` enum('Mr','Miss','Ms','Mrs','Dr') default 'Mr',
  `fName` varchar(255) default NULL,
  `lName` varchar(255) default NULL,
  `logoFileName` VARCHAR(255) NOT NULL DEFAULT '',
  `dateSigned` date NOT NULL default '0000-00-00',
  `notes` text,
  `address1` varchar(255) default NULL,
  `address2` varchar(255) default NULL,
  `city` varchar(255) default NULL,
  `country` int(6) unsigned NOT NULL default '0',
  `state` varchar(255) NOT NULL default '',
  `zip` int(6) unsigned NOT NULL default '0',
  `workPhone` varchar(255) default NULL,
  `homePhone` varchar(255) default NULL,
  `mobilePhone` varchar(255) default NULL,
  `email1` varchar(255) default NULL,
  `email2` varchar(255) default NULL,
  `email3` varchar(255) default NULL,
  `faxHome` varchar(255) default NULL,
  `faxWork` varchar(255) default NULL,
  `faxOthers` varchar(255) default NULL,
  `status` enum('A','I') NOT NULL default 'A',
  `regTime` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`agent_id`),
  CONSTRAINT `FK_agents_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `client_form_dependents`;
CREATE TABLE `client_form_dependents` (
  `member_id` bigint(20) default NULL,
  `relationship` ENUM('parent','spouse','sibling','child','other') NOT NULL DEFAULT 'spouse',
  `line` tinyint(3) unsigned default NULL,
  `fName` char(255) default NULL,
  `lName` char(255) default NULL,
  `sex` enum('M','F') default 'M',
  `DOB` date default NULL,
  `passport_num` char(128) default NULL,
  `passport_date` date default NULL,
  `canadian` enum('Y','N') default 'Y',
  `country_of_birth` int(6) unsigned NOT NULL default '0',
  `country_of_citizenship` int(6) unsigned NOT NULL default '0',
  `city_of_residence` VARCHAR(255) NULL DEFAULT NULL,
  `country_of_residence` int(6) unsigned NOT NULL default '0',
  PRIMARY KEY (`member_id`, `relationship`, `line`),
  CONSTRAINT `FK_client_form_dependents_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `searches`;
CREATE TABLE `searches` (
  `search_id` int(11) NOT NULL auto_increment,
  `search_type` ENUM('clients','contacts') NOT NULL DEFAULT 'clients',
  `author_id` BIGINT(20) NULL DEFAULT NULL,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `title` char(255) default NULL,
  `query` text,
  `columns` text,
  PRIMARY KEY  (`search_id`),
  CONSTRAINT `FK_searches_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_searches_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Clients who owe', '{\"cntr\":\"3\",\"srchField-0\":\"ob_total|0\"}', '[\"outstanding_balance_secondary\",\"outstanding_balance_primary\",\"first_name\",\"last_name\",\"file_number\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Clients with money in Trust AC', '{\"cntr\":\"3\",\"srchField-0\":\"ta_total|0\"}', '[\"trust_account_summary_secondary\",\"trust_account_summary_primary\",\"first_name\",\"last_name\",\"file_number\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Passport Expires in 2 months', '{\"cntr\":\"3\",\"srchDateCondition-0\":\"is in the next\",\"txtSrchDate-0\":\"\",\"txtSrchDateTo-0\":\"\",\"txtNextNum-0\":\"2\",\"txtNextPeriod-0\":\"MONTHS\",\"srchField-0\":\"passport_exp_date|8\"}', '[\"first_name\",\"last_name\",\"file_number\",\"passport_number\",\"passport_exp_date\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Medical Expires in 2 months', '{\"cntr\":\"3\",\"srchDateCondition-0\":\"is in the next\",\"txtSrchDate-0\":\"\",\"txtSrchDateTo-0\":\"\",\"txtNextNum-0\":\"2\",\"txtNextPeriod-0\":\"MONTHS\",\"srchField-0\":\"medical_date|8\"}', '[\"first_name\",\"last_name\",\"file_number\",\"medical_date\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Interview Scheduled in 1 week', '{\"cntr\":\"3\",\"srchDateCondition-0\":\"is in the next\",\"txtSrchDate-0\":\"\",\"txtSrchDateTo-0\":\"\",\"txtNextNum-0\":\"1\",\"txtNextPeriod-0\":\"WEEKS\",\"srchField-0\":\"interview_date|8\"}', '[\"first_name\",\"last_name\",\"interview_date\",\"interview_location\",\"interview_time\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Interview Scheduled in 2 weeks', '{\"cntr\":\"3\",\"srchDateCondition-0\":\"is in the next\",\"txtSrchDate-0\":\"\",\"txtSrchDateTo-0\":\"\",\"txtNextNum-0\":\"2\",\"txtNextPeriod-0\":\"WEEKS\",\"srchField-0\":\"interview_date|8\"}', '[\"first_name\",\"last_name\",\"interview_date\",\"interview_location\",\"interview_time\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Interview Scheduled in 4 weeks', '{\"cntr\":\"3\",\"srchDateCondition-0\":\"is in the next\",\"txtSrchDate-0\":\"\",\"txtSrchDateTo-0\":\"\",\"txtNextNum-0\":\"4\",\"txtNextPeriod-0\":\"WEEKS\",\"srchField-0\":\"interview_date|8\"}', '[\"first_name\",\"last_name\",\"file_number\",\"interview_date\",\"interview_location\",\"interview_time\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Skilled Worker Clients', '{\"cntr\":\"3\",\"srcTxtCondition-0\":\"is\",\"txtSrchClient-0\":\"Skilled Worker\",\"srchField-0\":\"categories|3\"}', '[\"first_name\",\"last_name\",\"file_number\",\"division\",\"categories\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Self-Employed Clients', '{\"cntr\":\"3\",\"srcTxtCondition-0\":\"is\",\"txtSrchClient-0\":\"Self-Employed\",\"srchField-0\":\"categories|3\"}', '[\"first_name\",\"last_name\",\"file_number\",\"division\",\"categories\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Entrepreneur', '{\"cntr\":\"3\",\"srcTxtCondition-0\":\"is\",\"txtSrchClient-0\":\"Entrepreneur\",\"srchField-0\":\"categories|3\"}', '[\"first_name\",\"last_name\",\"file_number\",\"division\",\"categories\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Investor Clients', '{\"cntr\":\"3\",\"srcTxtCondition-0\":\"is\",\"txtSrchClient-0\":\"Investor\",\"srchField-0\":\"categories|3\"}', '[\"first_name\",\"last_name\",\"file_number\",\"division\",\"categories\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Sponsorship Clients', '{\"cntr\":\"3\",\"srcTxtCondition-0\":\"is\",\"txtSrchClient-0\":\"Sponsorship\",\"srchField-0\":\"categories|3\"}', '[\"first_name\",\"last_name\",\"file_number\",\"division\",\"categories\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'Foreign Worker Clients', '{\"cntr\":\"3\",\"srcTxtCondition-0\":\"is\",\"txtSrchClient-0\":\"Foreign Worker\",\"srchField-0\":\"categories|3\"}', '[\"first_name\",\"last_name\",\"file_number\",\"division\",\"categories\"]');
INSERT INTO `searches` VALUES (0, 'clients', 2, 0, 'All Active Clients', '{\"cntr\":\"3\",\"srcTxtCondition-0\":\"is\",\"txtSrchClient-0\":\"Active\",\"srchField-0\":\"Client_file_status|3\"}', '[\"first_name\",\"last_name\",\"file_number\",\"Client_file_status\"]');

DROP TABLE IF EXISTS `u_links`;
CREATE TABLE `u_links` (
  `link_id` int(11) NOT NULL auto_increment,
  `title` char(255) default NULL,
  `url` text,
  `member_id` bigint(20) default NULL,
  PRIMARY KEY  (`link_id`),
  CONSTRAINT `FK_u_links_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_notes`;
CREATE TABLE `u_notes` (
  `note_id` int(11) unsigned NOT NULL auto_increment,
  `member_id` bigint(20) default NULL,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `author_id` int(11) unsigned default NULL,
  `note` text,
  `create_date` datetime default NULL,
  `visible_to_clients` enum('Y','N') NOT NULL default 'N',
  `rtl` enum('Y','N') NOT NULL default 'N',
  `note_color` TINYINT( 1 ) NOT NULL DEFAULT '0',
  PRIMARY KEY  (`note_id`),
  CONSTRAINT `FK_u_notes_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_notes_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `members_roles`;
CREATE TABLE `members_roles` (
  `member_id` bigint(20) NOT NULL,
  `role_id` int(11) NOT NULL default '0',
  CONSTRAINT `FK_members_roles_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_members_roles_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `members_roles` VALUES (1, 5);


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL auto_increment,
  `member_id` bigint(20) default NULL,
  `notes` text,
  `activationCode` varchar(50) NOT NULL default '',
  `city` varchar(255) NOT NULL default '',
  `state` varchar(255) NOT NULL default '',
  `country` int(6) NOT NULL default '0',
  `homePhone` varchar(30) NOT NULL default '',
  `workPhone` varchar(30) NOT NULL default '',
  `mobilePhone` varchar(30) NOT NULL default '',
  `fax` varchar(50) NOT NULL default '',
  `zip` varchar(10) NOT NULL default '',
  `address` text NOT NULL,
  `timeZone` INT(2) NOT NULL DEFAULT '0',
  `code` varchar(255) default NULL,
  `user_is_rma` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `user_migration_number` VARCHAR(15) DEFAULT NULL,
  `time_tracker_enable` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `time_tracker_disable_popup` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `time_tracker_rate` FLOAT UNSIGNED NULL,
  `time_tracker_round_up` TINYINT NOT NULL DEFAULT '0',
  PRIMARY KEY  (`user_id`),
  CONSTRAINT `FK_users_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `usertypes`;
CREATE TABLE `usertypes` (
  `usertype_id` int(11) NOT NULL auto_increment,
  `role_type`  varchar(50) NOT NULL,
  `usertype_name` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`usertype_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `usertypes` (`usertype_id`,`role_type`,`usertype_name`) VALUES
 (1,'superadmin','Super Admin'),
 (2,'admin','Company Admin'),
 (3,'client','Client'),
 (4,'user','Company Staff');

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `client_id` bigint(20) NOT NULL auto_increment,
  `member_id` bigint(20) default NULL,
  `client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `applicant_type_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `added_by_member_id` bigint(20) NOT NULL default '0',
  `fileNumber` varchar(32) default NULL,
  `case_number_of_parent_client` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `case_number_in_company` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `agent_id` bigint(20) NOT NULL default '0',
  `forms_locked` int(1) NOT NULL default '0',
  `modified_by` int(11) unsigned default null,
  `modified_on` datetime default null,
  PRIMARY KEY  (`client_id`),
  CONSTRAINT `FK_clients_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_clients_2` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_clients_3` FOREIGN KEY (`applicant_type_id`) REFERENCES `applicant_types` (`applicant_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `time_tracker`;
CREATE TABLE IF NOT EXISTS `time_tracker` (
  `track_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `track_member_id` bigint(20) DEFAULT NULL,
  `track_company_id` bigint(20) DEFAULT NULL,
  `track_posted_on` date NOT NULL,
  `track_posted_by_member_id` bigint(20) NOT NULL,
  `track_time_billed` int(11) NOT NULL COMMENT 'in min',
  `track_time_actual` int(11) NOT NULL COMMENT 'in min',
  `track_round_up` tinyint(4) NOT NULL DEFAULT '0',
  `track_rate` double(12,2) NOT NULL,
  `track_total` double(12,2) NOT NULL,
  `track_comment` text NOT NULL,
  `track_billed` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`track_id`),
  KEY `FK_clients_time_tracker_members` (`track_member_id`),
  KEY `FK_time_tracker_company` (`track_company_id`),
  CONSTRAINT `FK_clients_time_tracker_members` FOREIGN KEY (`track_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_time_tracker_company` FOREIGN KEY (`track_company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `company`;
CREATE TABLE `company` (
  `company_id` BIGINT(20) NOT NULL auto_increment,
  `company_type_id` int(11) NOT NULL default '0',
  `admin_id` int(11) default NULL,
  `company_template_id` int(11) NOT NULL default '0',
  `companyName` varchar(255) NOT NULL default '',
  `company_abn` VARCHAR(255) NOT NULL DEFAULT '',
  `companyLogo` varchar(255) NOT NULL default '',
  `city` varchar(255) NOT NULL default '',
  `state` varchar(255) NOT NULL default '',
  `country` int(6) NOT NULL default '0',
  `companyEmail` varchar(255) NOT NULL default '',
  `phone1` varchar(50) NOT NULL default '',
  `phone2` varchar(50) NOT NULL default '',
  `contact` varchar(50) default NULL,
  `fax` varchar(50) NOT NULL default '',
  `zip` varchar(10) NOT NULL default '',
  `address` text NOT NULL,
  `note` TEXT NULL,
  `companyCode` varchar(50) NOT NULL default '',
  `companyTimeZone` varchar(255) NOT NULL default '',
  `Status` tinyint(1) NOT NULL default '0',
  `regTime` int(11) NOT NULL default '0',
  `last_doc_uploaded` int(11) NOT NULL DEFAULT '0',
  `last_adv_search` int(11) NOT NULL DEFAULT '0',
  `last_calendar_entry` int(11) NOT NULL DEFAULT '0',
  `last_reminder_written` int(11) NOT NULL DEFAULT '0',
  `last_note_written` int(11) NOT NULL DEFAULT '0',
  `last_accounting_subtab_updated` int(11) NOT NULL DEFAULT '0',
  `storage_today` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  `storage_yesterday` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  `storage_location` ENUM('local','s3') NOT NULL DEFAULT 's3' COMMENT 'Location of company data directories/files. local - data will be saved on server, s3 - will be saved in Amazon S3 bucket.',
  PRIMARY KEY  (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `company` (`company_id`, `admin_id`, `companyName`) VALUES (0, 0, 'Default Company');
UPDATE `company` SET company_id = 0;

DROP TABLE IF EXISTS `company_cmi`;
CREATE TABLE `company_cmi` (
 `cmi_id` VARCHAR(255) NOT NULL,
 `regulator_id` VARCHAR(255) NOT NULL,
 `company_id` BIGINT(20) NULL DEFAULT NULL,
 PRIMARY KEY (`cmi_id`, `regulator_id`),
 CONSTRAINT `FK_company_cmi_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `company_trial` (
	`company_id` BIGINT(20) NOT NULL,
	`key` VARCHAR(50) NOT NULL,
	`ip` VARCHAR(50) NOT NULL,
	`date_used` DATETIME NOT NULL,
	INDEX `FK_company_trial_company` (`company_id`),
  CONSTRAINT `FK_company_trial_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `company_details`;
CREATE TABLE `company_details` (
  `company_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `support_and_training` enum('Y','N') default 'Y',
  `payment_term` tinyint(3) unsigned default NULL,
  `paymentech_profile_id` varchar(255) default NULL,
  `paymentech_mode_of_payment` ENUM('Visa','Mastercard') NULL DEFAULT NULL,
  `subscription` varchar(255) default NULL,
  `default_label_office` VARCHAR(255) NULL DEFAULT NULL,
  `default_label_trust_account` VARCHAR(255) NULL DEFAULT NULL,
  `next_billing_date` date default NULL,
  `max_users` int(11) unsigned default NULL,
  `billing_amount` double(11,2) unsigned default NULL,
  `internal_note` text,
  `account_created_on` date default NULL,
  `price` double(11,2) unsigned default NULL,
  `gst` double(11,2) unsigned default NULL,
  `gst_type` ENUM('auto','included','excluded') NULL DEFAULT 'auto',
  `billed_fee` double(11,2) unsigned default NULL,
  `subscription_fee` double(11,2) unsigned default NULL,
  `support_fee` double(11,2) unsigned default NULL,
  `free_users` int(11) unsigned default NULL,
  `extra_users` INT(11) UNSIGNED NULL DEFAULT NULL,
  `free_storage` int(11) unsigned default NULL,
  `use_annotations` enum('Y','N') default 'N',
  `show_expiration_dialog_after` DATE NULL,
  `trial` ENUM('Y','N') NULL DEFAULT 'N',
  `send_mass_email` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `company_website` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `allow_import` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `allow_export` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `time_tracker_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle for the Client\'s Time Tracker',
  `employers_module_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle for the Employers module',
  `log_client_changes_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle to save client profile changes in log',
  `case_number_settings` TEXT NULL COMMENT 'Case number generation settings',
  `advanced_search_rows_max_count` INT(11) NOT NULL DEFAULT '3',
  PRIMARY KEY  (`company_id`),
  CONSTRAINT `FK_company_details_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `company_default_options`;
CREATE TABLE `company_default_options` (
  `default_option_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `default_option_type` ENUM('categories') NULL DEFAULT 'categories',
  `default_option_name` CHAR(255) NULL DEFAULT '',
  `default_option_abbreviation` CHAR(255) NULL DEFAULT NULL,
  `default_option_order` TINYINT(3) UNSIGNED NULL DEFAULT '0',
  PRIMARY KEY (`default_option_id`),
  CONSTRAINT `FK_company_default_options` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
UPDATE `company_default_options` SET `default_option_abbreviation` = SUBSTRING(default_option_name, 1, 3);

DROP TABLE IF EXISTS `company_invoice`;
CREATE TABLE `company_invoice` (
  `company_invoice_id` int(11) unsigned NOT NULL auto_increment,
  `prospect_id` int(11) unsigned default NULL,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `product` ENUM('Officio') NOT NULL DEFAULT 'Officio',
  `subject` char(255) default NULL,
  `invoice_number` char(255) default NULL,
  `subscription_fee` double(11,2) default NULL,
  `support_fee` double(11,2) default NULL,
  `discount` double(11,2) NULL DEFAULT '0',
  `invoice_date` date default NULL,
  `invoice_posted_date` DATE NULL DEFAULT NULL,
  `free_users` int(11) unsigned default NULL,
  `free_storage` double(11,2) unsigned default NULL,
  `additional_users` int(11) unsigned default NULL,
  `additional_users_fee` double(11,2) default NULL,
  `additional_storage` int(11) unsigned default NULL,
  `additional_storage_charges` double(11,2) default NULL,
  `subtotal` double(11,2) default NULL,
  `total` double(11,2) default NULL,
  `message` text default NULL,
  `tax` double(12,2) default NULL,
  `mode_of_payment` ENUM('Visa','Mastercard') NULL DEFAULT NULL,
  `deleted` enum('Y','N') default 'N',
  `sent_request_to_PT` ENUM('Y','N') NULL DEFAULT 'N',
  `last_PT_error_code` VARCHAR(3) NULL DEFAULT NULL,
  `status` ENUM('C','F','Q','U') NOT NULL DEFAULT 'Q' COMMENT 'C - complete, F - failed, Q - queued, U - unpaid',
  PRIMARY KEY  (`company_invoice_id`),
  INDEX `company_id` (`company_id`),
  CONSTRAINT `FK_company_invoice_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `company_types`;
CREATE TABLE `company_types` (
  `company_type_id` int(10) NOT NULL auto_increment,
  `company_type_name` varchar(30) NOT NULL default '',
  `status` int(1) NOT NULL default '1',
  `regTime` int(11) NOT NULL default '0',
  PRIMARY KEY  (`company_type_id`),
  KEY `company_type_name` (`company_type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_types` (`company_type_id`,`company_type_name`,`status`,`regTime`) VALUES
 (1,'Computers',0,1217849718),
 (2,'Architect',0,1217849733),
 (3,'Marketing',0,1217849742),
 (4,'Finance',0,1217849749),
 (5,'Engineering',0,1217934550),
 (6,'Consulting',1,1222628068),
 (7,'Legal',1,1222628087);

DROP TABLE IF EXISTS `company_ta`;
CREATE TABLE `company_ta` (
  `company_ta_id` int(11) NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `name` varchar(255) NOT NULL default 'Client Account',
  `currency` varchar(3) NOT NULL default 'usd',
  `view_transactions_months` int(11) NOT NULL default '2',
  `balance` double(12,2) NOT NULL DEFAULT 0,
  `create_date` date default NULL,
  `last_reconcile` date default NULL,
  `last_reconcile_iccrc` DATE NULL DEFAULT NULL,
  `status` int(1) unsigned NOT NULL default '1',
  `allow_new_bank_id` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `bankid` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY  (`company_ta_id`),
  CONSTRAINT `FK_company_ta_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `company_ta` (`company_ta_id`, `company_id`, `name`, `currency`, `view_transactions_months`, `create_date`, `last_reconcile`) VALUES
 (1, 0, 'Client Account', 'cad', 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

DROP TABLE IF EXISTS `company_ta_divisions`;
CREATE TABLE `company_ta_divisions` (
	`company_ta_id` INT(11) NOT NULL,
	`division_id` INT(11) UNSIGNED NOT NULL,
	INDEX `FK_u_trust_account_divisions_divisions` (`division_id`),
	INDEX `FK_company_ta_divisions_company_ta` (`company_ta_id`),
	CONSTRAINT `FK_company_ta_divisions_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_u_trust_account_divisions_divisions` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

DROP TABLE IF EXISTS `members_ta`;
CREATE TABLE `members_ta` (
  `members_ta_id` int(11) NOT NULL auto_increment,
  `member_id` bigint(20) default NULL,
  `company_ta_id` int(11) default NULL,
  `order` tinyint(3) unsigned default 0,
  `outstanding_balance` double(12,2) NOT NULL DEFAULT 0,
  `sub_total` double(12,2) NOT NULL DEFAULT 0,
  `sub_total_cleared` double(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY  (`members_ta_id`),
  CONSTRAINT `FK_members_ta_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `client_form_groups`;
CREATE TABLE `client_form_groups` (
  `group_id` int(11) unsigned NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `title` varchar(255) default NULL,
  `order` int(11) unsigned default NULL,
  `cols_count` INT(1) UNSIGNED NOT NULL DEFAULT 3,
  `regTime` int(11) unsigned NOT NULL default '0',
  `assigned` enum('A','U') default 'U',
  PRIMARY KEY  (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `client_form_groups` VALUES (1,0,'Main Details',0,3,1238408422,'A');
INSERT INTO `client_form_groups` VALUES (2,0,'Not Assigned',100,3,1238408422,'U');
INSERT INTO `client_form_groups` VALUES (3,0,'Federal',4,3,1238518700,'A');
INSERT INTO `client_form_groups` VALUES (4,0,'Contact information',2,3,1238519930,'A');
INSERT INTO `client_form_groups` VALUES (5,0,'Medical',5,3,1238575398,'A');
INSERT INTO `client_form_groups` VALUES (6,0,'Missing Documents',6,3,1238576294,'A');
INSERT INTO `client_form_groups` VALUES (7,0,'Permanent Residency',7,3,1238578237,'A');
INSERT INTO `client_form_groups` VALUES (8,0,'Staff responsible for this client',3,3,1238578237,'A');
INSERT INTO `client_form_groups` VALUES (9,0,'Dependants',1,3,1238578237,'A');

DROP TABLE IF EXISTS `client_form_group_access`;
CREATE TABLE `client_form_group_access` (
  `access_id` int(11) unsigned NOT NULL auto_increment,
  `role_id` INT(11) NULL DEFAULT NULL,
  `group_id` int(11) unsigned default NULL,
  `status` enum('R','F') default NULL,
  PRIMARY KEY  (`access_id`),
  CONSTRAINT `FK_client_form_group_access_1` FOREIGN KEY (`group_id`) REFERENCES `client_form_groups` (`group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_client_form_group_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `client_form_group_access` VALUES (65,4,1,'F');
INSERT INTO `client_form_group_access` VALUES (66,4,3,'F');
INSERT INTO `client_form_group_access` VALUES (67,4,4,'F');
INSERT INTO `client_form_group_access` VALUES (68,4,5,'F');
INSERT INTO `client_form_group_access` VALUES (69,4,6,'F');
INSERT INTO `client_form_group_access` VALUES (70,4,7,'F');
INSERT INTO `client_form_group_access` VALUES (71,4,8,'F');
INSERT INTO `client_form_group_access` VALUES (72,4,9,'F');
INSERT INTO `client_form_group_access` VALUES (73,2,1,'F');
INSERT INTO `client_form_group_access` VALUES (74,2,3,'F');
INSERT INTO `client_form_group_access` VALUES (75,2,4,'F');
INSERT INTO `client_form_group_access` VALUES (76,2,5,'F');
INSERT INTO `client_form_group_access` VALUES (77,2,6,'F');
INSERT INTO `client_form_group_access` VALUES (78,2,7,'F');
INSERT INTO `client_form_group_access` VALUES (79,2,8,'F');
INSERT INTO `client_form_group_access` VALUES (80,2,9,'F');
INSERT INTO `client_form_group_access` VALUES (89,5,1,'F');
INSERT INTO `client_form_group_access` VALUES (90,5,3,'F');
INSERT INTO `client_form_group_access` VALUES (91,5,4,'F');
INSERT INTO `client_form_group_access` VALUES (92,5,5,'F');
INSERT INTO `client_form_group_access` VALUES (93,5,6,'F');
INSERT INTO `client_form_group_access` VALUES (94,5,7,'F');
INSERT INTO `client_form_group_access` VALUES (95,5,8,'F');
INSERT INTO `client_form_group_access` VALUES (96,5,9,'F');
INSERT INTO `client_form_group_access` VALUES (97,3,1,'F');
INSERT INTO `client_form_group_access` VALUES (98,3,3,'F');
INSERT INTO `client_form_group_access` VALUES (99,3,4,'F');
INSERT INTO `client_form_group_access` VALUES (100,3,5,'F');
INSERT INTO `client_form_group_access` VALUES (101,3,6,'F');
INSERT INTO `client_form_group_access` VALUES (102,3,7,'F');
INSERT INTO `client_form_group_access` VALUES (103,3,8,'F');
INSERT INTO `client_form_group_access` VALUES (104,3,9,'F');

DROP TABLE IF EXISTS `client_form_fields`;
CREATE TABLE `client_form_fields` (
  `field_id` int(11) unsigned NOT NULL auto_increment,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `company_field_id` varchar(100) NOT NULL NULL,
  `type` tinyint(3) unsigned NOT NULL default '1',
  `label` char(255) default NULL,
  `maxlength` int(6) unsigned default NULL,
  `encrypted` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `required` enum('Y','N') NOT NULL default 'N',
  `disabled` enum('Y','N') NOT NULL default 'N',
  `blocked` enum('Y','N') NOT NULL default 'N',
  PRIMARY KEY  (`field_id`),
  CONSTRAINT `FK_client_form_fields_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `client_form_fields` VALUES (1,0,'date_client_signed',8,'Date Client Signed',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (3,0,'title',3,'Title',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (4,0,'DOB',15,'Date of Birth',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (5,0,'dependents',1,'Dependants',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (6,0,'address_1',1,'Address',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (7,0,'address_2',1,'Address',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (8,0,'city',1,'City',64,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (9,0,'country',4,'Country',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (10,0,'state',1,'Province/State',64,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (11,0,'zip_code',1,'Postal/zip code',16,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (12,0,'phone_w',10,'Phone (Work)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (13,0,'phone_h',10,'Phone (Home)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (14,0,'phone_m',10,'Phone (Mobile)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (15,0,'email_1',9,'Email (1)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (16,0,'email_2',9,'Email (2)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (17,0,'email_3',9,'Email (3)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (18,0,'fax_h',10,'Fax (Home)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (19,0,'fax_w',10,'Fax (Work)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (20,0,'fax_o',10,'Fax (Others)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (21,0,'pref_contact_method',3,'Preferred Contact Method',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (22,0,'passport_number',1,'Passport #',16,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (23,0,'passport_exp_date',8,'Passport expiry date',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (25,0,'processing_coordinator',1,'Processing Coordinator',256,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (26,0,'proc_coord_date_assigned',8,'Date assigned',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (27,0,'proc_coord_date_completed',8,'Date Completed',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (28,0,'special_instruction',11,'Special Instruction',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (29,0,'program',3,'Program',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (30,0,'Client_file_status',7,'Status',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (31,0,'quebec',3,'Quebec',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (32,0,'embassy',3,'Embassy',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (33,0,'federal_date',8,'Date',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (34,0,'b-file-number',5,'B-File Number',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (35,0,'date-b-file-number',8,'Date B-File Number Issued',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (36,0,'interview_date',8,'Interview Date',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (37,0,'interview_time',1,'Interview Time',5,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (38,0,'interview_location',1,'Interview Location',256,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (39,0,'cvo_last_status',3,'CVO Last Status',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (40,0,'medical_date_issued',8,'Date Issued',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (41,0,'medical_date_completed',8,'Date Completed',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (42,0,'medical_date_furthered',8,'Date Furthered',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (43,0,'medical_date_furher_completed',8,'Date Further Completed',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (44,0,'miss_docs_date_requested',8,'Date Requested',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (45,0,'miss_docs_deadline',8,'Deadline for Submission',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (46,0,'miss_docs_description',1,'Description',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (47,0,'miss_docs_date_sent',8,'Date Sent',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (48,0,'permanent_residency_issued',8,'Permanent Residency Issued/Visa issued on',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (49,0,'valid_until',8,'Valid until',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (50,0,'landing_arrival',8,'Landing/Arrival',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (51,0,'departure',8,'Departure (for temp Visa only)',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (52,0,'expected_citizenship',8,'Expected Citizenship date',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (53,0,'ce_file_no',1,'CE file No',32,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (54,0,'app_processing_post',11,'App. Processing Post',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (55,0,'app_date',8,'Application Date',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (57,0,'medical_date',8,'Medical Date',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (58,0,'categories',30,'Category',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (59,0,'closed',7,'Closed',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (60,0,'notes',11,'Notes',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (61,0,'office_notes',11,'Office Notes',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (62,0,'visa_office',3,'Visa Offices',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (63,0,'file_status',3,'Case Status',NULL,'N','N','N','Y');
INSERT INTO `client_form_fields` VALUES (64,0,'sales_and_marketing',14,'Sales & Marketing',NULL,'N','Y','N','Y');
INSERT INTO `client_form_fields` VALUES (65,0,'processing',14,'Processing',NULL,'N','Y','N','Y');
INSERT INTO `client_form_fields` VALUES (66,0,'accounting',14,'Accounting',NULL,'N','Y','N','Y');
INSERT INTO `client_form_fields` VALUES (67,0,'photo', 16, 'Person Photo',NULL,'N','N','N','N');
INSERT INTO `client_form_fields` VALUES (154,0,'username',1,'Username (for client login)',64,'N','N','N','Y');
INSERT INTO `client_form_fields` VALUES (155,0,'password',2,'Password',64,'N','N','N','Y');
INSERT INTO `client_form_fields` VALUES (156,0,'email',9,'Primary Email',64,'N','N','N','Y');
INSERT INTO `client_form_fields` VALUES (157,0,'first_name',1,'First Name',64,'N','Y','N','Y');
INSERT INTO `client_form_fields` VALUES (158,0,'last_name',1,'Last Name',64,'N','Y','N','Y');
INSERT INTO `client_form_fields` VALUES (159,0,'file_number',1,'File Number',32,'N','N','N','Y');
INSERT INTO `client_form_fields` VALUES (160,0,'agent',12,'Sales Agent',NULL,'N','N','N','Y');
INSERT INTO `client_form_fields` VALUES (161,0,'division',13,'Office',NULL,'N','Y','N','Y');


DROP TABLE IF EXISTS `client_form_data`;
CREATE TABLE `client_form_data` (
  `member_id` bigint(20) default NULL,
  `field_id` int(11) unsigned  not NULL,
  `value` text,
  CONSTRAINT `FK_client_form_data_1` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_client_form_data_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



DROP TABLE IF EXISTS `client_form_default`;
CREATE TABLE `client_form_default` (
  `form_default_id` int(11) UNSIGNED NOT NULL auto_increment,
  `field_id` int(11) UNSIGNED default NULL,
  `value` text,
  `order` tinyint(3) UNSIGNED default NULL,
  PRIMARY KEY  (`form_default_id`),
  CONSTRAINT `FK_client_form_default_1` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `client_form_default` VALUES (0,3,'Mr.',0);
INSERT INTO `client_form_default` VALUES (0,3,'Miss',1);
INSERT INTO `client_form_default` VALUES (0,3,'Ms.',2);
INSERT INTO `client_form_default` VALUES (0,3,'Mrs.',3);
INSERT INTO `client_form_default` VALUES (0,3,'Dr.',4);
INSERT INTO `client_form_default` VALUES (0,21,'Email',0);
INSERT INTO `client_form_default` VALUES (0,21,'Fax',1);
INSERT INTO `client_form_default` VALUES (0,21,'Phone',2);
INSERT INTO `client_form_default` VALUES (0,29,'Federal',0);
INSERT INTO `client_form_default` VALUES (0,29,'Quebec',1);
INSERT INTO `client_form_default` VALUES (0,29,'PNP-Ontario',2);
INSERT INTO `client_form_default` VALUES (0,29,'PNP-BC all provinces including Yukon not Nunavut',3);
INSERT INTO `client_form_default` VALUES (0,29,'not NWT',4);
INSERT INTO `client_form_default` VALUES (0,31,'SIQ Office',0);
INSERT INTO `client_form_default` VALUES (0,31,'Date Filed',1);
INSERT INTO `client_form_default` VALUES (0,31,'SIQ Ref. Number',2);
INSERT INTO `client_form_default` VALUES (0,31,'Date SIQ Ref. Number Issued',3);
INSERT INTO `client_form_default` VALUES (0,31,'Interview Date',4);
INSERT INTO `client_form_default` VALUES (0,31,'Interview Time',5);
INSERT INTO `client_form_default` VALUES (0,31,'Interview Location',6);
INSERT INTO `client_form_default` VALUES (0,31,'Date Promise Letter Issued',7);
INSERT INTO `client_form_default` VALUES (0,31,'CSQ Number',8);
INSERT INTO `client_form_default` VALUES (0,31,'Date CSQ Issued',9);
INSERT INTO `client_form_default` VALUES (0,31,'CSQ Validity',10);
INSERT INTO `client_form_default` VALUES (0,31,'SIQ Last Status',11);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian Consulate General Hong Kong',0);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian Consulate General New York',1);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian Embassy Paris',2);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian High Commission Islamabad',3);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian High Commission London',4);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian High Commission New Delhi',5);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian High Commission Singapore',6);
INSERT INTO `client_form_default` VALUES (0,32,'Canadian High Commission Sri Lanka',7);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Consulate General Buffalo',8);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Embassy Abu Dhabi',9);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Embassy Ankara',10);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Embassy Buenos Aires',11);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Embassy Caracas',12);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Embassy Damascus',13);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Embassy Manila',14);
INSERT INTO `client_form_default` VALUES (0,32,'The Canadian Embassy Rabat',15);
INSERT INTO `client_form_default` VALUES (0,37,'00:00',0);
INSERT INTO `client_form_default` VALUES (0,39,'AOR &amp; B#',0);
INSERT INTO `client_form_default` VALUES (0,39,'Request for additional docs',1);
INSERT INTO `client_form_default` VALUES (0,39,'Medicals issued',2);
INSERT INTO `client_form_default` VALUES (0,39,'Medicals issued and request for Docs',3);
INSERT INTO `client_form_default` VALUES (0,39,'Further medicals issued',4);
INSERT INTO `client_form_default` VALUES (0,39,'Visas ready',5);
INSERT INTO `client_form_default` VALUES (0,39,'Visas issued',6);
INSERT INTO `client_form_default` VALUES (0,39,'Call for interview',7);
INSERT INTO `client_form_default` VALUES (0,39,'Refusal',8);
INSERT INTO `client_form_default` VALUES (0,58,'Provincial Nominee',0);
INSERT INTO `client_form_default` VALUES (0,58,'Investor',1);
INSERT INTO `client_form_default` VALUES (0,58,'Work Visa',2);
INSERT INTO `client_form_default` VALUES (0,63,'File Preparation',0);
INSERT INTO `client_form_default` VALUES (0,63,'Waiting for Interview',1);
INSERT INTO `client_form_default` VALUES (0,63,'First retainer paid',2);
INSERT INTO `client_form_default` VALUES (0,63,'CE File Number Rcvd',3);
INSERT INTO `client_form_default` VALUES (0,63,'Waiting for Meds',4);
INSERT INTO `client_form_default` VALUES (0,63,'Waiting for Visa',5);
INSERT INTO `client_form_default` VALUES (0,63,'Visa Issued',6);
INSERT INTO `client_form_default` VALUES (0,63,'ROLF requested',7);
INSERT INTO `client_form_default` VALUES (0,67,150,0);
INSERT INTO `client_form_default` VALUES (0,67,60,1);

INSERT INTO `client_form_default` VALUES (0,62,'Abidjian',0);
INSERT INTO `client_form_default` VALUES (0,62,'Accra',1);
INSERT INTO `client_form_default` VALUES (0,62,'Ankara',2);
INSERT INTO `client_form_default` VALUES (0,62,'Bangkok',3);
INSERT INTO `client_form_default` VALUES (0,62,'Beijing',4);
INSERT INTO `client_form_default` VALUES (0,62,'Belgrade',5);
INSERT INTO `client_form_default` VALUES (0,62,'Bogota',6);
INSERT INTO `client_form_default` VALUES (0,62,'Bonn',7);
INSERT INTO `client_form_default` VALUES (0,62,'Bucharest',8);
INSERT INTO `client_form_default` VALUES (0,62,'Budapest',9);
INSERT INTO `client_form_default` VALUES (0,62,'Buenos Airies',10);
INSERT INTO `client_form_default` VALUES (0,62,'Buffalo',11);
INSERT INTO `client_form_default` VALUES (0,62,'Cairo',12);
INSERT INTO `client_form_default` VALUES (0,62,'Colombo',13);
INSERT INTO `client_form_default` VALUES (0,62,'Damascus',14);
INSERT INTO `client_form_default` VALUES (0,62,'Guatemala',15);
INSERT INTO `client_form_default` VALUES (0,62,'Havana',16);
INSERT INTO `client_form_default` VALUES (0,62,'Hong Kong',17);
INSERT INTO `client_form_default` VALUES (0,62,'Islamabad',18);
INSERT INTO `client_form_default` VALUES (0,62,'Kiev',19);
INSERT INTO `client_form_default` VALUES (0,62,'Kingston',20);
INSERT INTO `client_form_default` VALUES (0,62,'Lima',21);
INSERT INTO `client_form_default` VALUES (0,62,'Lisbon',22);
INSERT INTO `client_form_default` VALUES (0,62,'London',23);
INSERT INTO `client_form_default` VALUES (0,62,'Los Angeles',24);
INSERT INTO `client_form_default` VALUES (0,62,'Manila',25);
INSERT INTO `client_form_default` VALUES (0,62,'Mexico',26);
INSERT INTO `client_form_default` VALUES (0,62,'Moscow',27);
INSERT INTO `client_form_default` VALUES (0,62,'Nairobi',28);
INSERT INTO `client_form_default` VALUES (0,62,'New Delhi',29);
INSERT INTO `client_form_default` VALUES (0,62,'New York',30);
INSERT INTO `client_form_default` VALUES (0,62,'Paris',31);
INSERT INTO `client_form_default` VALUES (0,62,'Port of Spain',32);
INSERT INTO `client_form_default` VALUES (0,62,'Port-au-Prince',33);
INSERT INTO `client_form_default` VALUES (0,62,'Pretoria',34);
INSERT INTO `client_form_default` VALUES (0,62,'Riyadh',35);
INSERT INTO `client_form_default` VALUES (0,62,'Rome',36);
INSERT INTO `client_form_default` VALUES (0,62,'Sao Paulo',37);
INSERT INTO `client_form_default` VALUES (0,62,'Seattle',38);
INSERT INTO `client_form_default` VALUES (0,62,'Seoul',39);
INSERT INTO `client_form_default` VALUES (0,62,'Singapore',40);
INSERT INTO `client_form_default` VALUES (0,62,'Sydney',41);
INSERT INTO `client_form_default` VALUES (0,62,'Taipei',42);
INSERT INTO `client_form_default` VALUES (0,62,'Tel Aviv',43);
INSERT INTO `client_form_default` VALUES (0,62,'Tokyo',44);
INSERT INTO `client_form_default` VALUES (0,62,'Vienna',45);
INSERT INTO `client_form_default` VALUES (0,62,'Warsaw',46);


DROP TABLE IF EXISTS `client_form_field_access`;
CREATE TABLE `client_form_field_access` (
  `access_id` int(11) unsigned NOT NULL auto_increment,
  `role_id` INT(11) NULL DEFAULT NULL,
  `field_id` int(11) unsigned default NULL,
  `client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `status` enum('R','F') NOT NULL default 'R' COMMENT 'R=read only, F=full access',
  PRIMARY KEY  (`access_id`),
  CONSTRAINT `FK_client_form_field_access_1` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_client_form_field_access_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE NO ACTION ON DELETE NO ACTION,
  CONSTRAINT `FK_client_form_field_access_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `client_form_order`;
CREATE TABLE `client_form_order` (
  `order_id` int(11) unsigned NOT NULL auto_increment,
  `group_id` int(11) unsigned NOT NULL,
  `field_id` int(11) unsigned not NULL,
  `use_full_row` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `field_order` INT(3) UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY  (`order_id`),
  CONSTRAINT `FK_client_form_order_1` FOREIGN KEY (`group_id`) REFERENCES `client_form_groups` (`group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_client_form_order_2` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `client_form_order` VALUES (0,1,154,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,155,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,156,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,157,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,158,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,159,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,160,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,161,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,1,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,3,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,4,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,5,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,6,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,7,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,8,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,9,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,10,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,11,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,12,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,13,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,14,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,15,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,16,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,17,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,18,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,19,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,20,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,21,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,22,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,23,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,25,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,26,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,27,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,28,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,29,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,30,'N',0);
INSERT INTO `client_form_order` VALUES (0,4,31,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,32,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,33,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,34,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,35,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,36,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,37,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,38,'N',0);
INSERT INTO `client_form_order` VALUES (0,3,39,'N',0);
INSERT INTO `client_form_order` VALUES (0,5,40,'N',0);
INSERT INTO `client_form_order` VALUES (0,5,41,'N',0);
INSERT INTO `client_form_order` VALUES (0,5,42,'N',0);
INSERT INTO `client_form_order` VALUES (0,5,43,'N',0);
INSERT INTO `client_form_order` VALUES (0,6,44,'N',0);
INSERT INTO `client_form_order` VALUES (0,6,45,'N',0);
INSERT INTO `client_form_order` VALUES (0,6,46,'N',0);
INSERT INTO `client_form_order` VALUES (0,6,47,'N',0);
INSERT INTO `client_form_order` VALUES (0,7,48,'N',0);
INSERT INTO `client_form_order` VALUES (0,7,49,'N',0);
INSERT INTO `client_form_order` VALUES (0,7,50,'N',0);
INSERT INTO `client_form_order` VALUES (0,7,51,'N',0);
INSERT INTO `client_form_order` VALUES (0,7,52,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,53,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,54,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,55,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,57,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,58,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,63,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,59,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,60,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,61,'N',0);
INSERT INTO `client_form_order` VALUES (0,2,62,'N',0);
INSERT INTO `client_form_order` VALUES (0,8,64,'N',0);
INSERT INTO `client_form_order` VALUES (0,8,65,'N',0);
INSERT INTO `client_form_order` VALUES (0,8,66,'N',0);
INSERT INTO `client_form_order` VALUES (0,1,67,'N',0);

DROP TABLE IF EXISTS `client_types`;
CREATE TABLE `client_types` (
  `client_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `form_version_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `email_template_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `client_type_name` VARCHAR(100) NULL DEFAULT NULL,
  `client_type_needs_ia` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `client_type_employer_sponsorship` ENUM('Y','N') NOT NULL DEFAULT 'N',
	PRIMARY KEY (`client_type_id`),
	CONSTRAINT `FK_client_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_client_types_FormVersion` FOREIGN KEY (`form_version_id`) REFERENCES `FormVersion` (`FormVersionId`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `FK_client_types_templates` FOREIGN KEY (`email_template_id`) REFERENCES `templates` (`template_id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `client_types` (`company_id`, `client_type_name`, `client_type_needs_ia`)
SELECT c.company_id, 'Client', 'Y' FROM company as c;

DROP TABLE IF EXISTS `client_types_kinds`;
CREATE TABLE `client_types_kinds` (
	`client_type_id` INT(11) UNSIGNED NOT NULL,
    `member_type_id` INT(2) UNSIGNED NOT NULL,
	INDEX `FK_client_types_kinds_client_types` (`client_type_id`),
	CONSTRAINT `FK_client_types_kinds_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_client_types_kinds_client_types_2` FOREIGN KEY (`member_type_id`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `client_types_kinds` (client_type_id, member_type_id)
SELECT client_type_id, 8 FROM client_types;

DROP TABLE IF EXISTS `u_folders`;
CREATE TABLE `u_folders` (
  `folder_id` int(11) unsigned NOT NULL auto_increment,
  `parent_id` int(11) unsigned default NULL,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `author_id` BIGINT(20) NULL DEFAULT NULL,
  `folder_name` char(255) default NULL,
  `upd_date` datetime default NULL,
  `type` enum('D','C','F','T','ST','CD','SD','SDR','STR') NOT NULL default 'D' COMMENT 'D=dir, C=correspondence, F=submisions(forms), T=templates, ST=Shared Templates CD=Client Documents, SD=Shared Workspace, SDR=Root Shared Workspace,STR=Root Shared Templates',
  PRIMARY KEY  (`folder_id`),
  CONSTRAINT `FK_u_folders_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_folders_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* Default Folders */
INSERT INTO `u_folders` VALUES (1,0,NULL,1,'Correspondence',NOW(),'C');
INSERT INTO `u_folders` VALUES (2,0,NULL,1,'Submissions',NOW(),'F');
INSERT INTO `u_folders` VALUES (3,0,NULL,1,'Client Uploads',NOW(),'CD');
INSERT INTO `u_folders` VALUES (4,0,NULL,1,'My Documents',NOW(),'D');
INSERT INTO `u_folders` VALUES (5,0,NULL,1,'Shared Workspace',NOW(),'SDR');
INSERT INTO `u_folders` VALUES (6,0,NULL,1,'My Templates',NOW(),'T');
INSERT INTO `u_folders` VALUES (7,0,NULL,1,'Shared Templates',NOW(),'STR');

/* Also create default folders for default company 0 - used by superadmin users */
INSERT INTO `u_folders` VALUES (NULL, 0, 0, 1, 'Submissions', NOW(), 'F');
INSERT INTO `folder_access` VALUES (NULL, LAST_INSERT_ID(), 5, 'RW');

INSERT INTO `u_folders` VALUES (NULL, 0, 0, 1, 'Client Uploads', NOW(), 'CD');
INSERT INTO `folder_access` VALUES (NULL, LAST_INSERT_ID(), 5, 'RW');

INSERT INTO `u_folders` VALUES (NULL, 0, 0, 1, 'My Documents', NOW(), 'D');
INSERT INTO `folder_access` VALUES (NULL, LAST_INSERT_ID(), 5, 'RW');

INSERT INTO `u_folders` VALUES (NULL, 0, 0, 1, 'Shared Workspace', NOW(), 'SDR');
INSERT INTO `folder_access` VALUES (NULL, LAST_INSERT_ID(), 5, 'RW');

INSERT INTO `u_folders` VALUES (NULL, 0, 0, 1, 'My Templates', NOW(), 'T');
INSERT INTO `folder_access` VALUES (NULL, LAST_INSERT_ID(), 5, 'RW');

INSERT INTO `u_folders` VALUES (NULL, 0, 0, 1, 'Shared Templates', NOW(), 'STR');
INSERT INTO `folder_access` VALUES (NULL, LAST_INSERT_ID(), 5, 'RW');


DROP TABLE IF EXISTS `u_assigned_deposits`;
CREATE TABLE `u_assigned_deposits` (
  `deposit_id` int(11) NOT NULL auto_increment,
  `author_id` BIGINT(20) NULL DEFAULT NULL,
  `company_ta_id` int(11) NOT NULL,
  `trust_account_id` int(11) default NULL,
  `deposit` double(12,2) default 0,
  `member_id` bigint(20) default NULL,
  `special_transaction` tinytext,
  `special_transaction_id` tinyint(3) unsigned default NULL,
  `date_of_event` datetime default NULL,
  `description` tinytext,
  `notes` varchar(255) default NULL,
  PRIMARY KEY  (`deposit_id`),
  CONSTRAINT `FK_u_assigned_deposits_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_assigned_deposits_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_assigned_deposits_u_trust_account` FOREIGN KEY (`trust_account_id`) REFERENCES `u_trust_account` (`trust_account_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_assigned_deposits_members_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_assigned_withdrawals`;
CREATE TABLE `u_assigned_withdrawals` (
  `withdrawal_id` int(11) NOT NULL auto_increment,
  `author_id` BIGINT(20) NULL DEFAULT NULL,
  `company_ta_id` int(11) NOT NULL,
  `trust_account_id` int(11) default NULL,
  `withdrawal` double(12,2) default 0,
  `invoice_id` int(11) default NULL,
  `special_transaction` tinytext,
  `special_transaction_id` tinyint(3) unsigned default NULL,
  `returned_payment_member_id` bigint(20) default NULL,
  `destination_account_id` int(11) default NULL,
  `destination_account_other` tinytext,
  `date_of_event` datetime default NULL,
  `notes` varchar(255) default NULL,
  PRIMARY KEY  (`withdrawal_id`),
  KEY `company_ta_id` (`company_ta_id`),
  KEY `trust_account_id` (`trust_account_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `returned_payment_member_id` (`returned_payment_member_id`),
  CONSTRAINT `FK_company_ta_id` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `u_invoice` (`invoice_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_returned_payment_member_id` FOREIGN KEY (`returned_payment_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_trust_account_id` FOREIGN KEY (`trust_account_id`) REFERENCES `u_trust_account` (`trust_account_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_assigned_withdrawals_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_deposit_types`;
CREATE TABLE `u_deposit_types` (
  `dtl_id` int(11) NOT NULL auto_increment,
  `company_id` BIGINT(20) NOT NULL,
  `author_id` BIGINT(20) NULL DEFAULT NULL,
  `name` tinytext,
  `unique_name` varchar(255) default NULL,
  `amount` double(8,2) default '0.00',
  `order` tinyint(3) unsigned default 0,
  `locked` enum('Y','N') default 'N',
  PRIMARY KEY  (`dtl_id`),
  CONSTRAINT `FK_u_deposit_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_deposit_types_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `u_deposit_types` VALUES (0,0,1,'Bank Error','bank_error',0,0,'Y');
INSERT INTO `u_deposit_types` VALUES (0,0,1,'Interest Earned','interest',0,1,'Y');

DROP TABLE IF EXISTS `u_destination_types`;
CREATE TABLE `u_destination_types` (
  `destination_account_id` int(11) NOT NULL auto_increment,
  `company_id` BIGINT(20) NOT NULL,
  `author_id` BIGINT(20) NULL DEFAULT NULL,
  `name` tinytext,
  `order` tinyint(3) unsigned default 0,
  PRIMARY KEY  (`destination_account_id`),
  CONSTRAINT `FK_u_destination_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_destination_types_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_import_transactions`;
CREATE TABLE `u_import_transactions` (
  `import_transaction_id` int(11) NOT NULL auto_increment,
  `company_ta_id` int(11) NOT NULL,
  `author_id` int(11) default NULL,
  `dt_start` date default NULL,
  `dt_end` date default NULL,
  `bankid` INT(11) NULL,
  `import_datetime` datetime default NULL,
  `records` int(4) default NULL,
  `filename` char(255) default NULL,
  PRIMARY KEY  (`import_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_invoice`;
CREATE TABLE `u_invoice` (
  `invoice_id` int(11) NOT NULL auto_increment,
  `member_id` bigint(20) default NULL,
  `company_ta_id` int(11) NOT NULL,
  `author_id` int(11) default NULL,
  `invoice_num` varchar(255) NOT NULL,
  `cheque_num` varchar(255) NOT NULL,
  `amount` double(12,2) NOT NULL,
  `transfer_from_company_ta_id` int(11) DEFAULT NULL,
  `transfer_from_amount` double(12,2) DEFAULT NULL,
  `destination_account_id` int(11) default NULL,
  `destination_account_other` tinytext,
  `date_of_invoice` date NOT NULL,
  `date_of_creation` datetime NOT NULL,
  `notes` varchar(255) NOT NULL,
  PRIMARY KEY  (`invoice_id`),
  CONSTRAINT `FK_u_invoice_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_log`;
CREATE TABLE `u_log` (
  `log_id` int(11) NOT NULL auto_increment,
  `trust_account_id` int(11) NOT NULL,
  `action_id` int(10) NOT NULL,
  `author_id` int(10) default NULL,
  `date_of_event` datetime default NULL,
  PRIMARY KEY  (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_payment`;
CREATE TABLE `u_payment` (
  `payment_id` int(11) NOT NULL auto_increment,
  `payment_schedule_id` INT(11) NULL DEFAULT NULL,
  `member_id` BIGINT(20) DEFAULT NULL,
  `author_id` BIGINT(20) NULL DEFAULT NULL,
  `company_ta_id` int(11) NOT NULL,
  `invoice_number` int(11) default NULL,
  `trust_account_id` int(11) default NULL,
  `deposit` double(12,2) default 0,
  `withdrawal` double(12,2) default 0,
  `description` tinytext,
  `payment_made_by` tinytext,
  `date_of_event` datetime default NULL,
  `gst` FLOAT(13,4) default NULL,
  `gst_province_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `gst_tax_label` CHAR(255) DEFAULT '',
  `notes` varchar(255) NOT NULL,
  PRIMARY KEY  (`payment_id`),
  KEY `company_ta_id` (`company_ta_id`),
  KEY `trust_account_id` (`trust_account_id`),
  CONSTRAINT `FK_company_ta_id_2_u_payment` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_trust_account_id_2_u_payment` FOREIGN KEY (`trust_account_id`) REFERENCES `u_trust_account` (`trust_account_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_payment_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_author_id_u_payment` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE SET NULL ON DELETE SET NULL,
  CONSTRAINT `FK_u_payment_u_payment_schedule` FOREIGN KEY (`payment_schedule_id`) REFERENCES `u_payment_schedule` (`payment_schedule_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_payment_schedule`;
CREATE TABLE `u_payment_schedule` (
  `payment_schedule_id` int(11) NOT NULL auto_increment,
  `member_id` bigint(20) default NULL,
  `description` tinytext,
  `amount` double(12,2) NOT NULL,
  `based_on_date` date default NULL,
  `based_on_profile_date_field` INT(11) UNSIGNED NULL DEFAULT NULL,
  `based_on_account` int(3) default NULL,
  `status` tinyint(3) default '0',
  `gst` FLOAT(13,4) default '0.00',
  `gst_province_id` INT(11) UNSIGNED NULL DEFAULT NULL,
  `gst_tax_label` CHAR(255) DEFAULT '',
  PRIMARY KEY  (`payment_schedule_id`),
  CONSTRAINT `FK_u_payment_schedule_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_trust_account`;
CREATE TABLE `u_trust_account` (
  `trust_account_id` int(11) NOT NULL auto_increment,
  `company_ta_id` int(11) NOT NULL,
  `import_id` int(11) default NULL,
  `fit` char(40) default NULL,
  `date_from_bank` date default NULL,
  `description` tinytext,
  `deposit` double(12,2) default NULL,
  `withdrawal` double(12,2) default NULL,
  `balance` double(12,2) default NULL,
  `notes` tinytext,
  `payment_made_by` tinytext,
  `purpose` tinytext,
  PRIMARY KEY  (`trust_account_id`),
  CONSTRAINT `FK_u_trust_account_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_variable`;
CREATE TABLE `u_variable` (
  `name` varchar(48) NOT NULL default '',
  `value` longtext NOT NULL,
  PRIMARY KEY  (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `u_sms` (
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`number` VARCHAR(20) NOT NULL,
	`message` VARCHAR(200) NOT NULL,
	`email` VARCHAR(200) NOT NULL,
	`attempts` TINYINT(1) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `u_variable` VALUES ('dateFormatFull','M d, Y');
INSERT INTO `u_variable` VALUES ('dateFormatShort','m/d/Y');
INSERT INTO `u_variable` VALUES ('datetimeFormatFull','H:i M d, Y');
INSERT INTO `u_variable` VALUES ('GST','5.00');
INSERT INTO `u_variable` VALUES ('support_request_count','100000');

INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_discount_label', 'Subscribe to the annual plan, and receive <span style="color: #f00;">33%</span> off the regular pricing.');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_free_users', '3');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_fee_annual', '799');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_fee_annual_discount', '200');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_fee_monthly', '99');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_fee_monthly_discount', '0');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_license_annual', '150');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_before_exp_license_monthly', '15');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_discount_label', 'Subscribe to the annual plan, and receive <span style="color: #f00;">16%</span> off the regular pricing.');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_free_users', '3');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_fee_annual', '999');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_fee_annual_discount', '0');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_fee_monthly', '99');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_fee_monthly_discount', '0');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_license_annual', '150');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('trial_after_exp_license_monthly', '15');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_storage_1_gb_monthly', '5');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_storage_1_gb_annual', '50.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_training', '125.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('free_storage_lite', '2');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('free_storage_pro', '10');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('free_storage_ultimate', '50');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('user_included_lite', '1');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('user_included_pro', '1');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('user_included_ultimate', '1');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('users_add_over_limit_lite', '0');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('users_add_over_limit_pro', '1');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('users_add_over_limit_ultimate', '1');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_license_user_monthly', '15.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_license_user_annual', '150.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_license_user_monthly', '15.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_license_user_annual', '150.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_license_user_monthly', '15.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_license_user_annual', '150.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_package_monthly', '69.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_package_yearly', '699.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_lite_package_2_years', '1275.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_package_monthly', '99.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_package_yearly', '999.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_pro_package_2_years', '1799.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_package_monthly', '129.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_package_yearly', '1299.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_ultimate_package_2_years', '2380.00');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('cutting_of_service_days', '30');
INSERT INTO `u_variable` (`name`, `value`) VALUES ('last_charge_failed_show_days', '5');
INSERT INTO `u_variable` VALUES ('noc_url_details', 'http://www5.hrsdc.gc.ca/NOC/English/NOC/2011/Profile.aspx?val=0&val1=XXXX');
INSERT INTO `u_variable` VALUES ('noc_url_prevailing', 'http://www.workingincanada.gc.ca/report-eng.do?area=25565&lang=eng&noc=XXXX&action=final&backurl=http%3A%2F&ln=n&s=1#wages');
INSERT INTO `u_variable` VALUES ('noc_url_jobs', 'http://www.workingincanada.gc.ca/report-eng.do?area=25565&lang=eng&noc=XXXX&action=final&backurl=http%3A%2F&ln=n&s=0#report_tabs_container2');
INSERT INTO `u_variable` VALUES ('noc_url_outlook', 'http://www.workingincanada.gc.ca/report-eng.do?area=25565&lang=eng&noc=XXXX&action=final&backurl=http%3A%2F&ln=n&s=2#report_tabs_container2');
INSERT INTO `u_variable` VALUES ('noc_url_education_job_requirements', 'http://www.workingincanada.gc.ca/report-eng.do?area=25565&lang=eng&noc=XXXX&action=final&backurl=http%3A%2F&ln=n&s=3#report_tabs_container2');


DROP TABLE IF EXISTS `u_withdrawal_types`;
CREATE TABLE `u_withdrawal_types` (
  `wtl_id` int(11) NOT NULL auto_increment,
  `company_id` BIGINT(20) NOT NULL,
  `author_id` int(11) default NULL,
  `name` tinytext,
  `unique_name` varchar(255) default NULL,
  `amount` double(8,2) default '0.00',
  `order` tinyint(3) unsigned default 0,
  `locked` enum('Y','N') default 'N',
  PRIMARY KEY  (`wtl_id`),
  CONSTRAINT `FK_u_withdrawal_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `u_withdrawal_types` VALUES (0,0,1,'Banking Fees','fees',0,0,'Y');

DROP TABLE IF EXISTS `clients_import`;
CREATE TABLE `clients_import` (
	`id` INT(10) UNSIGNED NULL AUTO_INCREMENT,
	`company_id` BIGINT(20) NOT NULL,
	`creator_id` BIGINT(20) NOT NULL,
	`file_name` VARCHAR(255) NOT NULL,
	`mapping` TEXT NOT NULL,
	`step` TINYINT(3) UNSIGNED NOT NULL,
	PRIMARY KEY (`id`),
	CONSTRAINT `FK1_company_id_to_clients_import` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE NO ACTION ON DELETE CASCADE,
	CONSTRAINT `FK2_creator_id_to_members` FOREIGN KEY (`creator_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

DROP TABLE IF EXISTS `country_master`;
CREATE TABLE `country_master` (
  `countries_id` int(11) NOT NULL auto_increment,
  `countries_name` varchar(64) NOT NULL default '',
  `countries_iso_code_2` varchar(2) NOT NULL default '',
  `countries_iso_code_3` varchar(3) NOT NULL default '',
  PRIMARY KEY  (`countries_id`),
  KEY `IDX_COUNTRIES_NAME` (`countries_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `states`;
CREATE TABLE `states` (
	`state_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`country_id` INT(11) NOT NULL,
	`state_name` VARCHAR(250) NOT NULL,
  `state_order` INT(11) NOT NULL default 0,
  PRIMARY KEY  (`state_id`),
	INDEX `FK_states_country_master` (`country_id`),
	CONSTRAINT `FK_states_country_master` FOREIGN KEY (`country_id`) REFERENCES `country_master` (`countries_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `states` (`country_id`, `state_name`, `state_order`) VALUES
/* Canadian States */
  (38, 'Alberta', 0),
  (38, 'British Columbia', 1),
  (38, 'Manitoba', 2),
  (38, 'New Brunswick', 3),
  (38, 'Newfoundland and Labrador', 4),
  (38, 'Northwest Territories', 5),
  (38, 'Nova Scotia', 6),
  (38, 'Nunavut', 7),
  (38, 'Ontario', 8),
  (38, 'Prince Edward Island', 9),
  (38, 'Quebec', 10),
  (38, 'Saskatchewan', 11),
  (38, 'Yukon', 12),

/* Australian States */
  (13, 'Queensland', 0),
  (13, 'New South Wales', 1),
  (13, 'Victoria', 2),
  (13, 'Australian Capital Territory', 3),
  (13, 'South Australia', 4),
  (13, 'Western Australia', 5),
  (13, 'Northern Territory', 6),
  (13, 'Tasmania', 7);

/* TASKS */
DROP TABLE IF EXISTS `u_tasks`;
CREATE TABLE IF NOT EXISTS `u_tasks` (
  `task_id` INT(11) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` BIGINT(20) DEFAULT NULL,
  `company_id` BIGINT(20) NULL DEFAULT NULL,
  `client_type` ENUM('client','prospect') NOT NULL DEFAULT 'client',
  `task` text,
  `type` enum('S','C','B','P') DEFAULT 'S',
  `number` int(11) unsigned DEFAULT NULL,
  `days` enum('CALENDAR','BUSINESS') DEFAULT 'CALENDAR',
  `ba` enum('BEFORE','AFTER') DEFAULT 'AFTER',
  `prof` int(11) unsigned DEFAULT NULL,
  `due_on` date DEFAULT NULL,
  `create_date` datetime DEFAULT NULL,
  `author_id` bigint(20) DEFAULT NULL,
  `notify_client` enum('Y','N') DEFAULT 'N',
  `is_due` enum('Y','N') NOT NULL DEFAULT 'N',
  `completed` ENUM('Y','N') NOT NULL DEFAULT 'N',
  `sms_processed` tinyint(4) NOT NULL DEFAULT '0',
  `deadline` DATE NULL DEFAULT NULL,
  `flag` tinyint(4) DEFAULT 0,
  `auto_task_type` TINYINT(4) NULL DEFAULT NULL COMMENT 'null - not auto-task, 1 - Payment is due, 2 - Client mark a form as Complete, 3 - Client uploads Documents , 10 - Based on a date in the "Client\'s Profile", 11 - Based on Case Status on Profile Info',
  `from` CHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  CONSTRAINT `FK_u_tasks_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_tasks_assigned_to`;
CREATE TABLE IF NOT EXISTS `u_tasks_assigned_to` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(11) unsigned NOT NULL,
  `member_id` bigint(20) DEFAULT NULL,
  `to_cc` enum('to','cc') NOT NULL DEFAULT 'to',
  PRIMARY KEY (`id`),
  INDEX `FK_u_tasks_assigned_to_u_tasks` (`task_id`),
  CONSTRAINT `FK_u_tasks_assigned_to_u_tasks` FOREIGN KEY (`task_id`) REFERENCES `u_tasks` (`task_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_tasks_assigned_to_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_tasks_messages`;
CREATE TABLE IF NOT EXISTS `u_tasks_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(11) unsigned NOT NULL,
  `member_id` bigint(20) DEFAULT NULL,
  `message` text NOT NULL,
  `timestamp` int(10) NOT NULL,
  `officio_said` TINYINT NOT NULL DEFAULT '0',
  `from_template` TINYINT(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  CONSTRAINT `FK_u_tasks_messages_u_tasks` FOREIGN KEY (`task_id`) REFERENCES `u_tasks` (`task_id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_u_tasks_messages_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `u_tasks_priority`;
CREATE TABLE `u_tasks_priority` (
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`task_id` INT(11) unsigned NOT NULL,
	`member_id` BIGINT(20) NOT NULL,
    `priority` ENUM('low','regular','medium','high','critical') NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `FK_u_tasks_priority_u_tasks` (`task_id`),
	INDEX `FK_u_tasks_priority_members` (`member_id`),
	CONSTRAINT `FK_u_tasks_priority_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_u_tasks_priority_u_tasks` FOREIGN KEY (`task_id`) REFERENCES `u_tasks` (`task_id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

CREATE TABLE `u_tasks_read` (
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`task_id` INT(11) UNSIGNED NOT NULL,
	`member_id` BIGINT(20) NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `FK__u_tasks` (`task_id`),
	INDEX `FK__members` (`member_id`),
	CONSTRAINT `FK__u_tasks` FOREIGN KEY (`task_id`) REFERENCES `u_tasks` (`task_id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK__members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
/* TASKS */

DROP TABLE IF EXISTS `rss_black_list`;
CREATE TABLE IF NOT EXISTS `rss_black_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/* The list is available here: https://en.wikipedia.org/wiki/ISO_3166-1 */
INSERT INTO `country_master` VALUES (1,'Afghanistan','AF','AFG');
INSERT INTO `country_master` VALUES (2,'Albania','AL','ALB');
INSERT INTO `country_master` VALUES (3,'Algeria','DZ','DZA');
INSERT INTO `country_master` VALUES (4,'American Samoa','AS','ASM');
INSERT INTO `country_master` VALUES (5,'Andorra','AD','AND');
INSERT INTO `country_master` VALUES (6,'Angola','AO','AGO');
INSERT INTO `country_master` VALUES (7,'Anguilla','AI','AIA');
INSERT INTO `country_master` VALUES (8,'Antarctica','AQ','ATA');
INSERT INTO `country_master` VALUES (9,'Antigua and Barbuda','AG','ATG');
INSERT INTO `country_master` VALUES (10,'Argentina','AR','ARG');
INSERT INTO `country_master` VALUES (11,'Armenia','AM','ARM');
INSERT INTO `country_master` VALUES (12,'Aruba','AW','ABW');
INSERT INTO `country_master` VALUES (13,'Australia','AU','AUS');
INSERT INTO `country_master` VALUES (14,'Austria','AT','AUT');
INSERT INTO `country_master` VALUES (15,'Azerbaijan','AZ','AZE');
INSERT INTO `country_master` VALUES (16,'Bahamas','BS','BHS');
INSERT INTO `country_master` VALUES (17,'Bahrain','BH','BHR');
INSERT INTO `country_master` VALUES (18,'Bangladesh','BD','BGD');
INSERT INTO `country_master` VALUES (19,'Barbados','BB','BRB');
INSERT INTO `country_master` VALUES (20,'Belarus','BY','BLR');
INSERT INTO `country_master` VALUES (21,'Belgium','BE','BEL');
INSERT INTO `country_master` VALUES (22,'Belize','BZ','BLZ');
INSERT INTO `country_master` VALUES (23,'Benin','BJ','BEN');
INSERT INTO `country_master` VALUES (24,'Bermuda','BM','BMU');
INSERT INTO `country_master` VALUES (25,'Bhutan','BT','BTN');
INSERT INTO `country_master` VALUES (26,'Bolivia','BO','BOL');
INSERT INTO `country_master` VALUES (27,'Bosnia and Herzegowina','BA','BIH');
INSERT INTO `country_master` VALUES (28,'Botswana','BW','BWA');
INSERT INTO `country_master` VALUES (29,'Bouvet Island','BV','BVT');
INSERT INTO `country_master` VALUES (30,'Brazil','BR','BRA');
INSERT INTO `country_master` VALUES (31,'British Indian Ocean Territory','IO','IOT');
INSERT INTO `country_master` VALUES (32,'Brunei Darussalam','BN','BRN');
INSERT INTO `country_master` VALUES (33,'Bulgaria','BG','BGR');
INSERT INTO `country_master` VALUES (34,'Burkina Faso','BF','BFA');
INSERT INTO `country_master` VALUES (35,'Burundi','BI','BDI');
INSERT INTO `country_master` VALUES (36,'Cambodia','KH','KHM');
INSERT INTO `country_master` VALUES (37,'Cameroon','CM','CMR');
INSERT INTO `country_master` VALUES (38,'Canada','CA','CAN');
INSERT INTO `country_master` VALUES (39,'Cape Verde','CV','CPV');
INSERT INTO `country_master` VALUES (40,'Cayman Islands','KY','CYM');
INSERT INTO `country_master` VALUES (41,'Central African Republic','CF','CAF');
INSERT INTO `country_master` VALUES (42,'Chad','TD','TCD');
INSERT INTO `country_master` VALUES (43,'Chile','CL','CHL');
INSERT INTO `country_master` VALUES (44,'China','CN','CHN');
INSERT INTO `country_master` VALUES (45,'Christmas Island','CX','CXR');
INSERT INTO `country_master` VALUES (46,'Cocos (Keeling) Islands','CC','CCK');
INSERT INTO `country_master` VALUES (47,'Colombia','CO','COL');
INSERT INTO `country_master` VALUES (48,'Comoros','KM','COM');
INSERT INTO `country_master` VALUES (49,'Congo','CG','COG');
INSERT INTO `country_master` VALUES (50,'Cook Islands','CK','COK');
INSERT INTO `country_master` VALUES (51,'Costa Rica','CR','CRI');
INSERT INTO `country_master` VALUES (52,'Cote D\'Ivoire','CI','CIV');
INSERT INTO `country_master` VALUES (53,'Croatia','HR','HRV');
INSERT INTO `country_master` VALUES (54,'Cuba','CU','CUB');
INSERT INTO `country_master` VALUES (55,'Cyprus','CY','CYP');
INSERT INTO `country_master` VALUES (56,'Czech Republic','CZ','CZE');
INSERT INTO `country_master` VALUES (57,'Denmark','DK','DNK');
INSERT INTO `country_master` VALUES (58,'Djibouti','DJ','DJI');
INSERT INTO `country_master` VALUES (59,'Dominica','DM','DMA');
INSERT INTO `country_master` VALUES (60,'Dominican Republic','DO','DOM');
INSERT INTO `country_master` VALUES (61,'East Timor','TP','TMP');
INSERT INTO `country_master` VALUES (62,'Ecuador','EC','ECU');
INSERT INTO `country_master` VALUES (63,'Egypt','EG','EGY');
INSERT INTO `country_master` VALUES (64,'El Salvador','SV','SLV');
INSERT INTO `country_master` VALUES (65,'Equatorial Guinea','GQ','GNQ');
INSERT INTO `country_master` VALUES (66,'Eritrea','ER','ERI');
INSERT INTO `country_master` VALUES (67,'Estonia','EE','EST');
INSERT INTO `country_master` VALUES (68,'Ethiopia','ET','ETH');
INSERT INTO `country_master` VALUES (69,'Falkland Islands (Malvinas)','FK','FLK');
INSERT INTO `country_master` VALUES (70,'Faroe Islands','FO','FRO');
INSERT INTO `country_master` VALUES (71,'Fiji','FJ','FJI');
INSERT INTO `country_master` VALUES (72,'Finland','FI','FIN');
INSERT INTO `country_master` VALUES (73,'France','FR','FRA');
INSERT INTO `country_master` VALUES (74,'France, Metropolitan','FX','FXX');
INSERT INTO `country_master` VALUES (75,'French Guiana','GF','GUF');
INSERT INTO `country_master` VALUES (76,'French Polynesia','PF','PYF');
INSERT INTO `country_master` VALUES (77,'French Southern Territories','TF','ATF');
INSERT INTO `country_master` VALUES (78,'Gabon','GA','GAB');
INSERT INTO `country_master` VALUES (79,'Gambia','GM','GMB');
INSERT INTO `country_master` VALUES (80,'Georgia','GE','GEO');
INSERT INTO `country_master` VALUES (81,'Germany','DE','DEU');
INSERT INTO `country_master` VALUES (82,'Ghana','GH','GHA');
INSERT INTO `country_master` VALUES (83,'Gibraltar','GI','GIB');
INSERT INTO `country_master` VALUES (84,'Greece','GR','GRC');
INSERT INTO `country_master` VALUES (85,'Greenland','GL','GRL');
INSERT INTO `country_master` VALUES (86,'Grenada','GD','GRD');
INSERT INTO `country_master` VALUES (87,'Guadeloupe','GP','GLP');
INSERT INTO `country_master` VALUES (88,'Guam','GU','GUM');
INSERT INTO `country_master` VALUES (89,'Guatemala','GT','GTM');
INSERT INTO `country_master` VALUES (90,'Guinea','GN','GIN');
INSERT INTO `country_master` VALUES (91,'Guinea-bissau','GW','GNB');
INSERT INTO `country_master` VALUES (92,'Guyana','GY','GUY');
INSERT INTO `country_master` VALUES (93,'Haiti','HT','HTI');
INSERT INTO `country_master` VALUES (94,'Heard and Mc Donald Islands','HM','HMD');
INSERT INTO `country_master` VALUES (95,'Honduras','HN','HND');
INSERT INTO `country_master` VALUES (96,'Hong Kong','HK','HKG');
INSERT INTO `country_master` VALUES (97,'Hungary','HU','HUN');
INSERT INTO `country_master` VALUES (98,'Iceland','IS','ISL');
INSERT INTO `country_master` VALUES (99,'India','IN','IND');
INSERT INTO `country_master` VALUES (100,'Indonesia','ID','IDN');
INSERT INTO `country_master` VALUES (101,'Iran','IR','IRN');
INSERT INTO `country_master` VALUES (102,'Iraq','IQ','IRQ');
INSERT INTO `country_master` VALUES (103,'Ireland','IE','IRL');
INSERT INTO `country_master` VALUES (104,'Israel','IL','ISR');
INSERT INTO `country_master` VALUES (105,'Italy','IT','ITA');
INSERT INTO `country_master` VALUES (106,'Jamaica','JM','JAM');
INSERT INTO `country_master` VALUES (107,'Japan','JP','JPN');
INSERT INTO `country_master` VALUES (108,'Jordan','JO','JOR');
INSERT INTO `country_master` VALUES (109,'Kazakhstan','KZ','KAZ');
INSERT INTO `country_master` VALUES (110,'Kenya','KE','KEN');
INSERT INTO `country_master` VALUES (111,'Kiribati','KI','KIR');
INSERT INTO `country_master` VALUES (112,'Korea, North','KP','PRK');
INSERT INTO `country_master` VALUES (113,'Korea, South','KR','KOR');
INSERT INTO `country_master` VALUES (114,'Kuwait','KW','KWT');
INSERT INTO `country_master` VALUES (115,'Kyrgyzstan','KG','KGZ');
INSERT INTO `country_master` VALUES (116,'Lao People\'s Democratic Republic','LA','LAO');
INSERT INTO `country_master` VALUES (117,'Latvia','LV','LVA');
INSERT INTO `country_master` VALUES (118,'Lebanon','LB','LBN');
INSERT INTO `country_master` VALUES (119,'Lesotho','LS','LSO');
INSERT INTO `country_master` VALUES (120,'Liberia','LR','LBR');
INSERT INTO `country_master` VALUES (121,'Libyan Arab Jamahiriya','LY','LBY');
INSERT INTO `country_master` VALUES (122,'Liechtenstein','LI','LIE');
INSERT INTO `country_master` VALUES (123,'Lithuania','LT','LTU');
INSERT INTO `country_master` VALUES (124,'Luxembourg','LU','LUX');
INSERT INTO `country_master` VALUES (125,'Macau','MO','MAC');
INSERT INTO `country_master` VALUES (126,'Macedonia, The Former Yugoslav Republic of','MK','MKD');
INSERT INTO `country_master` VALUES (127,'Madagascar','MG','MDG');
INSERT INTO `country_master` VALUES (128,'Malawi','MW','MWI');
INSERT INTO `country_master` VALUES (129,'Malaysia','MY','MYS');
INSERT INTO `country_master` VALUES (130,'Maldives','MV','MDV');
INSERT INTO `country_master` VALUES (131,'Mali','ML','MLI');
INSERT INTO `country_master` VALUES (132,'Malta','MT','MLT');
INSERT INTO `country_master` VALUES (133,'Marshall Islands','MH','MHL');
INSERT INTO `country_master` VALUES (134,'Martinique','MQ','MTQ');
INSERT INTO `country_master` VALUES (135,'Mauritania','MR','MRT');
INSERT INTO `country_master` VALUES (136,'Mauritius','MU','MUS');
INSERT INTO `country_master` VALUES (137,'Mayotte','YT','MYT');
INSERT INTO `country_master` VALUES (138,'Mexico','MX','MEX');
INSERT INTO `country_master` VALUES (139,'Micronesia, Federated States of','FM','FSM');
INSERT INTO `country_master` VALUES (140,'Moldova, Republic of','MD','MDA');
INSERT INTO `country_master` VALUES (141,'Monaco','MC','MCO');
INSERT INTO `country_master` VALUES (142,'Mongolia','MN','MNG');
INSERT INTO `country_master` VALUES (143,'Montserrat','MS','MSR');
INSERT INTO `country_master` VALUES (144,'Morocco','MA','MAR');
INSERT INTO `country_master` VALUES (145,'Mozambique','MZ','MOZ');
INSERT INTO `country_master` VALUES (146,'Myanmar','MM','MMR');
INSERT INTO `country_master` VALUES (147,'Namibia','NA','NAM');
INSERT INTO `country_master` VALUES (148,'Nauru','NR','NRU');
INSERT INTO `country_master` VALUES (149,'Nepal','NP','NPL');
INSERT INTO `country_master` VALUES (150,'Netherlands','NL','NLD');
INSERT INTO `country_master` VALUES (151,'Netherlands Antilles','AN','ANT');
INSERT INTO `country_master` VALUES (152,'New Caledonia','NC','NCL');
INSERT INTO `country_master` VALUES (153,'New Zealand','NZ','NZL');
INSERT INTO `country_master` VALUES (154,'Nicaragua','NI','NIC');
INSERT INTO `country_master` VALUES (155,'Niger','NE','NER');
INSERT INTO `country_master` VALUES (156,'Nigeria','NG','NGA');
INSERT INTO `country_master` VALUES (157,'Niue','NU','NIU');
INSERT INTO `country_master` VALUES (158,'Norfolk Island','NF','NFK');
INSERT INTO `country_master` VALUES (159,'Northern Mariana Islands','MP','MNP');
INSERT INTO `country_master` VALUES (160,'Norway','NO','NOR');
INSERT INTO `country_master` VALUES (161,'Oman','OM','OMN');
INSERT INTO `country_master` VALUES (162,'Pakistan','PK','PAK');
INSERT INTO `country_master` VALUES (163,'Palau','PW','PLW');
INSERT INTO `country_master` VALUES (164,'Panama','PA','PAN');
INSERT INTO `country_master` VALUES (165,'Papua New Guinea','PG','PNG');
INSERT INTO `country_master` VALUES (166,'Paraguay','PY','PRY');
INSERT INTO `country_master` VALUES (167,'Peru','PE','PER');
INSERT INTO `country_master` VALUES (168,'Philippines','PH','PHL');
INSERT INTO `country_master` VALUES (169,'Pitcairn','PN','PCN');
INSERT INTO `country_master` VALUES (170,'Poland','PL','POL');
INSERT INTO `country_master` VALUES (171,'Portugal','PT','PRT');
INSERT INTO `country_master` VALUES (172,'Puerto Rico','PR','PRI');
INSERT INTO `country_master` VALUES (173,'Qatar','QA','QAT');
INSERT INTO `country_master` VALUES (174,'Reunion','RE','REU');
INSERT INTO `country_master` VALUES (175,'Romania','RO','ROM');
INSERT INTO `country_master` VALUES (176,'Russian Federation','RU','RUS');
INSERT INTO `country_master` VALUES (177,'Rwanda','RW','RWA');
INSERT INTO `country_master` VALUES (178,'Saint Kitts and Nevis','KN','KNA');
INSERT INTO `country_master` VALUES (179,'Saint Lucia','LC','LCA');
INSERT INTO `country_master` VALUES (180,'Saint Vincent and the Grenadines','VC','VCT');
INSERT INTO `country_master` VALUES (181,'Samoa','WS','WSM');
INSERT INTO `country_master` VALUES (182,'San Marino','SM','SMR');
INSERT INTO `country_master` VALUES (183,'Sao Tome and Principe','ST','STP');
INSERT INTO `country_master` VALUES (184,'Saudi Arabia','SA','SAU');
INSERT INTO `country_master` VALUES (185,'Senegal','SN','SEN');
INSERT INTO `country_master` VALUES (186,'Seychelles','SC','SYC');
INSERT INTO `country_master` VALUES (187,'Sierra Leone','SL','SLE');
INSERT INTO `country_master` VALUES (188,'Singapore','SG','SGP');
INSERT INTO `country_master` VALUES (189,'Slovakia (Slovak Republic)','SK','SVK');
INSERT INTO `country_master` VALUES (190,'Slovenia','SI','SVN');
INSERT INTO `country_master` VALUES (191,'Solomon Islands','SB','SLB');
INSERT INTO `country_master` VALUES (192,'Somalia','SO','SOM');
INSERT INTO `country_master` VALUES (193,'South Africa','ZA','ZAF');
INSERT INTO `country_master` VALUES (194,'South Georgia and the South Sandwich Islands','GS','SGS');
INSERT INTO `country_master` VALUES (195,'Spain','ES','ESP');
INSERT INTO `country_master` VALUES (196,'Sri Lanka','LK','LKA');
INSERT INTO `country_master` VALUES (197,'St. Helena','SH','SHN');
INSERT INTO `country_master` VALUES (198,'St. Pierre and Miquelon','PM','SPM');
INSERT INTO `country_master` VALUES (199,'Sudan','SD','SDN');
INSERT INTO `country_master` VALUES (200,'Suriname','SR','SUR');
INSERT INTO `country_master` VALUES (201,'Svalbard and Jan Mayen Islands','SJ','SJM');
INSERT INTO `country_master` VALUES (202,'Swaziland','SZ','SWZ');
INSERT INTO `country_master` VALUES (203,'Sweden','SE','SWE');
INSERT INTO `country_master` VALUES (204,'Switzerland','CH','CHE');
INSERT INTO `country_master` VALUES (205,'Syria','SY','SYR');
INSERT INTO `country_master` VALUES (206,'Taiwan','TW','TWN');
INSERT INTO `country_master` VALUES (207,'Tajikistan','TJ','TJK');
INSERT INTO `country_master` VALUES (208,'Tanzania, United Republic of','TZ','TZA');
INSERT INTO `country_master` VALUES (209,'Thailand','TH','THA');
INSERT INTO `country_master` VALUES (210,'Togo','TG','TGO');
INSERT INTO `country_master` VALUES (211,'Tokelau','TK','TKL');
INSERT INTO `country_master` VALUES (212,'Tonga','TO','TON');
INSERT INTO `country_master` VALUES (213,'Trinidad and Tobago','TT','TTO');
INSERT INTO `country_master` VALUES (214,'Tunisia','TN','TUN');
INSERT INTO `country_master` VALUES (215,'Turkey','TR','TUR');
INSERT INTO `country_master` VALUES (216,'Turkmenistan','TM','TKM');
INSERT INTO `country_master` VALUES (217,'Turks and Caicos Islands','TC','TCA');
INSERT INTO `country_master` VALUES (218,'Tuvalu','TV','TUV');
INSERT INTO `country_master` VALUES (219,'Uganda','UG','UGA');
INSERT INTO `country_master` VALUES (220,'Ukraine','UA','UKR');
INSERT INTO `country_master` VALUES (221,'United Arab Emirates','AE','ARE');
INSERT INTO `country_master` VALUES (222,'United Kingdom','GB','GBR');
INSERT INTO `country_master` VALUES (223,'United States','US','USA');
INSERT INTO `country_master` VALUES (224,'United States Minor Outlying Islands','UM','UMI');
INSERT INTO `country_master` VALUES (225,'Uruguay','UY','URY');
INSERT INTO `country_master` VALUES (226,'Uzbekistan','UZ','UZB');
INSERT INTO `country_master` VALUES (227,'Vanuatu','VU','VUT');
INSERT INTO `country_master` VALUES (228,'Vatican City State (Holy See)','VA','VAT');
INSERT INTO `country_master` VALUES (229,'Venezuela','VE','VEN');
INSERT INTO `country_master` VALUES (230,'Vietnam','VN','VNM');
INSERT INTO `country_master` VALUES (231,'Virgin Islands (British)','VG','VGB');
INSERT INTO `country_master` VALUES (232,'Virgin Islands (U.S.)','VI','VIR');
INSERT INTO `country_master` VALUES (233,'Wallis and Futuna Islands','WF','WLF');
INSERT INTO `country_master` VALUES (234,'Western Sahara','EH','ESH');
INSERT INTO `country_master` VALUES (235,'Yemen','YE','YEM');
INSERT INTO `country_master` VALUES (236,'Yugoslavia','YU','YUG');
INSERT INTO `country_master` VALUES (237,'Zaire','ZR','ZAR');
INSERT INTO `country_master` VALUES (238,'Zambia','ZM','ZMB');
INSERT INTO `country_master` VALUES (239,'Zimbabwe','ZW','ZWE');
INSERT INTO `country_master` VALUES (240,'Palestine','PS','PSE');
INSERT INTO `country_master` VALUES (NULL,'Aland Islands','AX','ALA');
INSERT INTO `country_master` VALUES (NULL,'Bonaire','BQ','BES');
INSERT INTO `country_master` VALUES (NULL,'Curacao','CW','CUW');
INSERT INTO `country_master` VALUES (NULL,'Guernsey','GG','GGY');
INSERT INTO `country_master` VALUES (NULL,'Isle of Man','IM','IMN');
INSERT INTO `country_master` VALUES (NULL,'Jersey','JE','JEY');
INSERT INTO `country_master` VALUES (NULL,'Montenegro','ME','MNE');
INSERT INTO `country_master` VALUES (NULL,'Saint Barthelemy','BL','BLM');
INSERT INTO `country_master` VALUES (NULL,'Saint Martin (French part)','MF','MAF');
INSERT INTO `country_master` VALUES (NULL,'Serbia','RS','SRB');
INSERT INTO `country_master` VALUES (NULL,'Sint Maarten (Dutch part)','SX','SXM');

CREATE TABLE `members_last_passwords` (
    `member_id` BIGINT(20) NOT NULL,
    `password` VARCHAR(200) NOT NULL,
    `timestamp` INT(11) UNSIGNED NOT NULL,
    INDEX `FK_members_last_passwords_members` (`member_id`),
    CONSTRAINT `FK_members_last_passwords_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
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
ENGINE=InnoDB;

DROP TABLE IF EXISTS `zoho_keys`;
CREATE TABLE `zoho_keys` (
  `zoho_key` varchar(255) NOT NULL default '',
  `zoho_key_status` ENUM('enabled', 'disabled') NOT NULL DEFAULT 'enabled',
  PRIMARY KEY  (`zoho_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `zoho_keys` (`zoho_key`, `zoho_key_status`) VALUES ('ba52082120340f887665ed87a2637f3c', 'enabled');