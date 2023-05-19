<?php

use Officio\Migration\AbstractMigration;

class AddRequiredForSubmissionFieldOption extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `required_for_submission` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `required`;");
        $this->execute("ALTER TABLE `applicant_form_fields` ADD COLUMN `required_for_submission` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `required`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `applicant_form_fields` DROP COLUMN `required_for_submission`");
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `required_for_submission`");
    }
}