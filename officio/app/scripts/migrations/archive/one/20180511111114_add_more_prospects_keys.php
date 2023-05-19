<?php

use Phinx\Migration\AbstractMigration;

class AddMoreProspectsKeys extends AbstractMigration
{
    public function up()
    {
        $this->execute('UPDATE company_invoice SET prospect_id = NULL WHERE prospect_id NOT IN (SELECT prospect_id FROM prospects);');
        $this->execute('ALTER TABLE `company_invoice` ADD CONSTRAINT `FK_company_invoice_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `company` DROP FOREIGN KEY `FK_company_country_master`;');
        $this->execute("ALTER TABLE `company` CHANGE COLUMN `country` `country` INT(6) NOT NULL DEFAULT '0' AFTER `state`");
    }
}