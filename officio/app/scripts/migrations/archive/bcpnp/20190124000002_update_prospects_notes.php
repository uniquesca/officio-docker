<?php

use Phinx\Migration\AbstractMigration;

class UpdateProspectsNotes extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_prospects_notes` ADD COLUMN `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `prospect_id`;");
        $this->execute("ALTER TABLE `company_prospects_notes` ADD CONSTRAINT `FK_company_prospects_notes_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON DELETE CASCADE;");
        $this->execute("UPDATE company_prospects_notes, company_prospects SET company_prospects_notes.company_id = company_prospects.company_id WHERE company_prospects_notes.prospect_id = company_prospects.prospect_id;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects_notes` DROP FOREIGN KEY `FK_company_prospects_notes_company`;");
        $this->execute("ALTER TABLE `company_prospects_notes` DROP COLUMN `company_id`;");
    }
}