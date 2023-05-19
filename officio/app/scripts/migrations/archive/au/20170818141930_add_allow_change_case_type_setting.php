<?php

use Phinx\Migration\AbstractMigration;

class AddAllowChangeCaseTypeSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `company_details`
            ADD COLUMN `allow_change_case_type` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `allow_multiple_advanced_search_tabs`;"
        );
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `company_details`
	        DROP COLUMN `allow_change_case_type`;"
        );
    }
}