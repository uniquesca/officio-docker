<?php

use Phinx\Migration\AbstractMigration;

class AddColumnToCompanyDetails extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `company_details`
            ADD COLUMN `allow_multiple_advanced_search_tabs` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `allow_export`;"
        );
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `company_details`
	      DROP COLUMN `allow_multiple_advanced_search_tabs`;"
        );
    }
}