<?php

use Phinx\Migration\AbstractMigration;

class AddQnrSearchByColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires_fields`	ADD COLUMN `q_field_use_in_search` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `q_field_show_please_select`;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  `q_field_type` IN ('checkbox', 'label');");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  q_field_unique_id LIKE 'qf_job_%'");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  q_field_unique_id LIKE 'qf_language_%'");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  q_field_unique_id IN ('qf_email_confirmation')");
    }

    public function down()
    {
        $this->execute('ALTER TABLE `company_questionnaires_fields` DROP COLUMN `q_field_use_in_search`;');
    }
}