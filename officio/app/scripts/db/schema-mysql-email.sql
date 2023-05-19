/*
Yura:
Serj:
Artem:

ALTER TABLE `eml_messages` ADD COLUMN `is_downloaded` TINYINT(1) UNSIGNED DEFAULT 1 AFTER `body_html`;
ALTER TABLE `eml_accounts` ADD COLUMN `inc_only_headers` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `inc_leave_messages`;

Yura:
Serj:
Artem:
ALTER TABLE `eml_accounts` ADD COLUMN `inc_fetch_from_date` INT(11) NOT NULL DEFAULT '0' AFTER `inc_only_headers`;
*/

DROP TABLE IF EXISTS `eml_accounts`;
CREATE TABLE `eml_accounts` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`member_id` BIGINT(20) NOT NULL,
	`is_default` ENUM('Y','N') NOT NULL DEFAULT 'N',
	`email` VARCHAR(255) NOT NULL,
	`friendly_name` VARCHAR(255) NULL DEFAULT NULL,
	`auto_check` enum('Y','N') NOT NULL DEFAULT 'N',	
	`auto_check_every` int(11) NOT NULL DEFAULT '0',
	`signature` TEXT NULL,
	`inc_enabled` ENUM('Y','N') NOT NULL DEFAULT 'Y',
	`inc_type` ENUM('pop3','imap') NOT NULL DEFAULT 'pop3',
	`inc_login` VARCHAR(128) NULL DEFAULT NULL,
	`inc_password` VARCHAR(128) NULL DEFAULT NULL,
	`inc_host` VARCHAR(255) NULL DEFAULT NULL,
	`inc_port` VARCHAR(6) NULL DEFAULT NULL,
	`inc_ssl` ENUM('','ssl','tls') NULL DEFAULT '',
	`inc_leave_messages` ENUM('Y','N') NOT NULL DEFAULT 'Y',
	`inc_only_headers` enum('Y','N') NOT NULL DEFAULT 'N',
	`inc_fetch_from_date` INT(11) NOT NULL DEFAULT '0',
	`out_use_own` ENUM('Y','N') NOT NULL DEFAULT 'N',
	`out_auth_required` ENUM('Y','N') NOT NULL DEFAULT 'Y',
	`out_login` VARCHAR(128) NULL DEFAULT NULL,
	`out_password` VARCHAR(128) NULL DEFAULT NULL,
	`out_host` VARCHAR(255) NULL DEFAULT NULL,
	`out_port` VARCHAR(6) NULL DEFAULT NULL,
	`out_ssl` ENUM('','ssl','tls') NOT NULL DEFAULT '',
	`last_manual_check` int(11) NOT NULL DEFAULT '0',
	`last_mass_mail` int(11) NOT NULL DEFAULT '0',	
	`per_page` INT(3) UNSIGNED NOT NULL DEFAULT '25',
	`timezone` varchar(255) NOT NULL DEFAULT 'America/New_York',
	`last_rabbit_push` INT(11) UNSIGNED NULL DEFAULT NULL,
	`last_rabbit_pull` INT(11) UNSIGNED NULL DEFAULT NULL,
	`is_checking` INT(1) UNSIGNED NULL DEFAULT '0',
	`checking_status` VARCHAR(255) NULL DEFAULT NULL,
  `delimiter` VARCHAR(1) NULL DEFAULT NULL,

	PRIMARY KEY (`id`),
	CONSTRAINT `FK_eml_accounts_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;

insert into `eml_accounts`(`member_id`, `email`, `inc_enabled`, `inc_host`, `inc_port`, `inc_login`, `inc_password`) values 
    (2, '0x2c@mail.ru', 'Y', 'pop.mail.ru', '110', '0x2c@mail.ru', '123456');

CREATE TABLE `eml_cron` (
	`id` INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
	`accounts_count` INT(11) UNSIGNED NOT NULL,
	`start` INT(11) UNSIGNED NOT NULL,
	PRIMARY KEY (`Id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

CREATE TABLE `eml_cron_accounts` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`cron_id` INT(11) UNSIGNED NULL DEFAULT NULL,
	`account_id` INT(11) UNSIGNED NULL DEFAULT NULL,
	`start` INT(11) UNSIGNED NULL DEFAULT NULL,
	`end` INT(11) UNSIGNED NULL DEFAULT NULL,
	`status` TEXT NULL,
	PRIMARY KEY (`id`),
	INDEX `account_id` (`account_id`),
	INDEX `FK_eml_cron_accounts_eml_cron` (`cron_id`),
	CONSTRAINT `FK_eml_cron_accounts_eml_cron` FOREIGN KEY (`cron_id`) REFERENCES `eml_cron` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
	CONSTRAINT `FK_eml_cron_accounts_eml_accounts` FOREIGN KEY (`account_id`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

DROP TABLE IF EXISTS `eml_sample_server_settings`;
CREATE TABLE `eml_sample_server_settings` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NULL DEFAULT NULL,
	`type` ENUM('pop3','imap','smtp') NOT NULL DEFAULT 'pop3',
	`host` VARCHAR(255) NULL DEFAULT NULL,
	`port` VARCHAR(6) NULL DEFAULT NULL,
	`ssl` ENUM('','ssl','tls') NULL DEFAULT '',
	PRIMARY KEY (`id`)
) ENGINE=INNODB DEFAULT CHARSET=utf8;

INSERT INTO `eml_sample_server_settings` (`name`, `type`, `host`, `port`, `ssl`) VALUES
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
  ('Hotmail', 'smtp', 'smtp.live.com', '587', 'tls')
;

DROP TABLE IF EXISTS `eml_deleted_messages`;
CREATE TABLE `eml_deleted_messages` (
  `id_account` INT(11) UNSIGNED NOT NULL,
  `uid` VARCHAR(255) NOT NULL,
  INDEX `id_account` (`id_account`),
  INDEX `uid` (`uid`),
  CONSTRAINT `FK_eml_deleted_messages_eml_accounts` FOREIGN KEY (`id_account`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `eml_folders`;
CREATE TABLE `eml_folders` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_parent` INT(11) UNSIGNED DEFAULT '0',
  `level` int(11) default 0,
  `order` int(11) default NULL,
  `id_account` INT(11) UNSIGNED NOT NULL,
  `id_folder` VARCHAR(128) DEFAULT '0',
  `label` VARCHAR(255) NOT NULL,
  `full_path` TEXT,
  `selectable` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
  `id_mapping_folder` INT(11) UNSIGNED NULL DEFAULT '0',
  `visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY  (`id`),
  CONSTRAINT `FK_eml_folders_eml_accounts` FOREIGN KEY (`id_account`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;	
	
DROP TABLE IF EXISTS `eml_messages`;
CREATE TABLE `eml_messages` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `uid` VARCHAR(255) NOT NULL,
  `id_account` INT(11) UNSIGNED NOT NULL,
  `id_folder` INT(11) UNSIGNED NOT NULL,
  `from` TEXT NULL DEFAULT NULL,
  `to` TEXT NULL DEFAULT NULL,
  `cc` TEXT NULL DEFAULT NULL,
  `bcc` TEXT NULL DEFAULT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `sent_date` INT(11) DEFAULT NULL,
  `has_attachments` TINYINT(1) UNSIGNED NULL DEFAULT '0',
  `size` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
  `seen` TINYINT(1) UNSIGNED NULL DEFAULT '0',
  `priority` TINYINT(4) DEFAULT '3',
  `x_spam` TINYINT(1) UNSIGNED NULL DEFAULT '0',
  `replied` TINYINT(1) UNSIGNED NULL DEFAULT '0',
  `forwarded` TINYINT(1) UNSIGNED NULL DEFAULT '0',
  `flag` TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `body_html` LONGTEXT,
  `is_downloaded` TINYINT(1) UNSIGNED DEFAULT '1',
  PRIMARY KEY  (`id`),
  KEY `fldr_id` (`id_folder`),
  KEY `acc_id` (`id_account`),
  CONSTRAINT `FK_eml_messages_eml_folders` FOREIGN KEY (`id_folder`) REFERENCES `eml_folders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `FK_eml_messages_eml_accounts` FOREIGN KEY (`id_account`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `eml_attachments`;
CREATE TABLE `eml_attachments` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
	`id_message` BIGINT(20) NOT NULL,
	`path` TEXT NULL,
	`original_file_name` VARCHAR(255) NULL DEFAULT NULL,
	`size` INT(11) NULL DEFAULT NULL,
	`part_info` TEXT NOT NULL,
	`is_downloaded` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1',
	PRIMARY KEY (`id`),
	INDEX `msg_id` (`id_message`),
	CONSTRAINT `FK_eml_attachments` FOREIGN KEY (`id_message`) REFERENCES `eml_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=INNODB DEFAULT CHARSET=utf8;

INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (160, 'mail', 'settings', '', 1);
