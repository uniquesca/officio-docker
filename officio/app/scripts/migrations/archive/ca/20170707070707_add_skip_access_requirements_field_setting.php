<?php

use Officio\Migration\AbstractMigration;

class AddSkipAccessRequirementsFieldSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `skip_access_requirements` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `blocked`;");
        $this->execute("ALTER TABLE `applicant_form_fields` ADD COLUMN `skip_access_requirements` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `blocked`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `applicant_form_fields` DROP COLUMN `skip_access_requirements`");
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `skip_access_requirements`");
    }
}
