<?php

use Phinx\Migration\AbstractMigration;

class AddMarketplaceFields extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `company_prospects_invited` (
        	`company_id` BIGINT(20) NOT NULL,
        	`prospect_id` BIGINT(20) NOT NULL,
        	`invited_on` DATETIME NOT NULL,
        	UNIQUE INDEX `company_id_prospect_id` (`company_id`, `prospect_id`),
        	INDEX `FK_company_prospects_invited_company_prospects` (`prospect_id`),
        	CONSTRAINT `FK_company_prospects_invited_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_company_prospects_invited_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB"
        );

        $this->execute(
            "CREATE TABLE `company_prospects_converted` (
        	`prospect_id` BIGINT(20) NOT NULL,
        	`member_id` BIGINT(20) NOT NULL,
        	`company_invoice_id` INT(11) UNSIGNED DEFAULT NULL,
        	`converted_on` DATETIME NOT NULL,
        	UNIQUE INDEX `prospect_id_member_id_company_invoice_id` (`prospect_id`, `member_id`, `company_invoice_id`),
        	INDEX `FK_company_prospects_converted_invoice` (`company_invoice_id`),
        	INDEX `FK_company_prospects_converted_prospects` (`prospect_id`),
        	INDEX `FK_company_prospects_converted_members` (`member_id`),
        	CONSTRAINT `FK_company_prospects_converted_invoice` FOREIGN KEY (`company_invoice_id`) REFERENCES `company_invoice` (`company_invoice_id`) ON UPDATE CASCADE ON DELETE SET NULL,
        	CONSTRAINT `FK_company_prospects_converted_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_company_prospects_converted_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB"
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE `company_prospects_converted`;");
        $this->execute("DROP TABLE `company_prospects_invited`;");
    }
}