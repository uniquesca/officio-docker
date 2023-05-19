<?php

use Officio\Migration\AbstractMigration;

class AddCaseTypeCaseReferenceAsColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_types` ADD COLUMN `client_type_case_reference_as` VARCHAR(100) NULL DEFAULT NULL AFTER `client_type_name`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_types` DROP COLUMN `client_type_case_reference_as`;");
    }
}