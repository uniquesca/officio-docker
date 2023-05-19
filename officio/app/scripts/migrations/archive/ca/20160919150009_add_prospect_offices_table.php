<?php

use Phinx\Migration\AbstractMigration;

class AddProspectOfficesTable extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `company_prospects_divisions` (
        	`prospect_id` BIGINT(20) NOT NULL,
        	`office_id` INT(11) UNSIGNED NOT NULL,
        	INDEX `FK_company_prospects_divisions_1` (`prospect_id`),
        	INDEX `FK_company_prospects_divisions_2` (`office_id`),
        	CONSTRAINT `FK_company_prospects_divisions_divisions` FOREIGN KEY (`office_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_company_prospects_divisions_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
        );

        $this->execute("UPDATE company_prospects SET office_id = NULL WHERE office_id NOT IN (SELECT division_id FROM divisions);");

        $this->execute(
            "INSERT IGNORE INTO `company_prospects_divisions`
        (`prospect_id`, `office_id`)
        SELECT p.prospect_id, p.office_id
        FROM `company_prospects` as p
        WHERE p.office_id IS NOT NULL;"
        );

        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `office_id`;");
        $this->execute(
            "ALTER TABLE `company_prospects`
        	CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `prospect_id`,
        	ADD CONSTRAINT `FK_company_prospects_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;
        "
        );
    }

    public function down()
    {
        $this->execute('ALTER TABLE `company_prospects` DROP FOREIGN KEY `FK_company_prospects_company`;');
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `office_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `preferred_language`;");
        $this->execute("UPDATE company_prospects AS cp, company_prospects_divisions AS cpd SET cp.office_id = cpd.office_id WHERE cpd.prospect_id = cp.prospect_id;");
        $this->execute('DROP TABLE `company_prospects_divisions`;');
    }
}