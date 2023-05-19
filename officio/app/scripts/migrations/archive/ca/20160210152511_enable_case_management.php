<?php

use Officio\Migration\AbstractMigration;

class EnableCaseManagement extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `enable_case_management` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `advanced_search_rows_max_count`;");
        $this->execute("UPDATE company_details SET enable_case_management = 'Y';");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `enable_case_management`;");
    }
}
