<?php

use Phinx\Migration\AbstractMigration;

class AddProspectSettings extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `company_prospects_settings` (
        	`company_id` BIGINT(20) NOT NULL,
        	`prospect_id` BIGINT(20) NOT NULL,
        	`viewed` ENUM('Y','N') NULL DEFAULT 'N',
        	`email_sent` ENUM('Y','N') NULL DEFAULT 'N',
        	UNIQUE INDEX `company_id_prospect_id` (`company_id`, `prospect_id`),
        	INDEX `FK_company_prospects_settings_company_prospects` (`prospect_id`),
        	CONSTRAINT `FK_company_prospects_settings_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_company_prospects_settings_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB"
        );

        $this->execute(
            "INSERT INTO `company_prospects_settings` (`company_id`, `prospect_id`, `viewed`, `email_sent`)
        SELECT company_id, prospect_id, viewed, email_sent FROM company_prospects WHERE company_id IS NOT NULL;"
        );

        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `viewed`;");
        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `email_sent`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `viewed` ENUM('Y','N') NULL DEFAULT 'N' AFTER `qualified`;");
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `email_sent` ENUM('Y','N') NULL DEFAULT 'N' AFTER `update_date`;");
        $this->execute("DROP TABLE `company_prospects_settings`;");
    }
}