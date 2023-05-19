<?php

use Officio\Migration\AbstractMigration;

class AddMultipleValuesFieldSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `multiple_values` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `blocked`;");
        $this->execute("ALTER TABLE `applicant_form_fields` ADD COLUMN `multiple_values` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `blocked`;");
    }

    public function down()
    {

        $this->execute("ALTER TABLE `applicant_form_fields` DROP COLUMN `multiple_values`");
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `multiple_values`");
    }
}