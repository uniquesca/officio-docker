<?php

use Officio\Migration\AbstractMigration;

class UpdateQnrFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires_fields` ADD COLUMN `q_field_show_in_qnr` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `q_field_show_in_prospect_profile`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_questionnaires_fields` DROP COLUMN `q_field_show_in_qnr`;");
    }
}
