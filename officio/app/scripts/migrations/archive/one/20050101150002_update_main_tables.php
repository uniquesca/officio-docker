<?php

use Phinx\Migration\AbstractMigration;

class UpdateMainTables extends AbstractMigration
{
    public function up()
    {
        // Took 23s on local server...

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=10 AND `module_id`='clients' AND `resource_id`='profile' AND `resource_privilege`='image-field';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=10 AND `module_id`='clients' AND `resource_id`='profile' AND `resource_privilege`='delete-image-field';");

        $this->execute("ALTER TABLE `acl_role_access` DROP FOREIGN KEY `FK_acl_role_access_1`;");
        $this->execute("ALTER TABLE `acl_role_access` ADD CONSTRAINT `FK_acl_role_access_1` FOREIGN KEY (`rule_id`) REFERENCES `acl_rules` (`rule_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `searches` CHANGE COLUMN `search_type` `search_type` ENUM('clients','contacts') NOT NULL DEFAULT 'clients' AFTER `search_id`;");

        $this->execute("ALTER TABLE `default_searches` CHANGE COLUMN `default_search_type` `default_search_type` ENUM('clients','contacts') NOT NULL DEFAULT 'clients' AFTER `default_search`;");
        
        
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `employers_module_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle for the Employers module' AFTER `time_tracker_enabled`;");
        
        $this->execute("DROP TABLE IF EXISTS `client_types`;");
        $this->execute("CREATE TABLE `client_types` (
          `client_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `company_id` BIGINT(20) NULL DEFAULT NULL,
          `client_type_name` VARCHAR(100) NULL DEFAULT NULL,
        	PRIMARY KEY (`client_type_id`),
        	CONSTRAINT `FK_client_types_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        
        $this->execute("INSERT INTO `client_types` (`company_id`, `client_type_name`) SELECT c.company_id, 'Client' FROM company as c;");
        
        $this->execute("ALTER TABLE `clients` ADD COLUMN `client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `member_id`;");
        
        $this->execute("ALTER TABLE `clients` ADD CONSTRAINT `FK_clients_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        
        $this->execute("ALTER TABLE `members` ADD COLUMN `login_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `status`;");
        
        $this->execute("UPDATE `members` SET `login_enabled`='Y';");
        $this->execute("UPDATE `members` SET `login_enabled`='N' WHERE userType IN (3, 7, 8, 9, 10);");
        
        $this->execute("CREATE TABLE `access_logs` (
        	`log_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        	`log_section` VARCHAR(100) NULL DEFAULT NULL,
        	`log_action` VARCHAR(100) NULL DEFAULT NULL,
        	`log_description` VARCHAR(255) NULL DEFAULT NULL,
        	`log_company_id` BIGINT(20) NULL DEFAULT NULL,
        	`log_created_by` BIGINT(20) NULL DEFAULT NULL,
        	`log_created_on` DATETIME NOT NULL,
        	`log_action_applied_to` BIGINT(20) NULL DEFAULT NULL,
        	`log_ip` VARCHAR(39) NULL DEFAULT NULL,
        	PRIMARY KEY (`log_id`),
        	CONSTRAINT `FK_access_logs_company` FOREIGN KEY (`log_company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_access_logs_members` FOREIGN KEY (`log_created_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_access_logs_members_2` FOREIGN KEY (`log_action_applied_to`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    public function down()
    {
    }
}