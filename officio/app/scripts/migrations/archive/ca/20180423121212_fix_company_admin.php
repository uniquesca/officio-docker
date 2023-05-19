<?php

use Officio\Migration\AbstractMigration;

class FixCompanyAdmin extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company` SET `admin_id` = NULL WHERE admin_id NOT IN (SELECT member_id FROM members)");
        $this->execute("ALTER TABLE `company` CHANGE COLUMN `admin_id` `admin_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_type_id`;");
        $this->execute("ALTER TABLE `company` ADD CONSTRAINT `FK_company_members` FOREIGN KEY (`admin_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");
        $this->execute("ALTER TABLE `company_prospects` DROP INDEX `company_id`, ADD INDEX `FK_company_prospects_company` (`company_id`);");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company` DROP FOREIGN KEY `FK_company_members`;");
        $this->execute("ALTER TABLE `company` CHANGE COLUMN `admin_id` `admin_id` INT(11) NULL DEFAULT NULL AFTER `company_type_id`;");
        $this->execute("ALTER TABLE `company_prospects` DROP INDEX `FK_company_prospects_company`, ADD INDEX `company_id` (`company_id`);");
    }
}