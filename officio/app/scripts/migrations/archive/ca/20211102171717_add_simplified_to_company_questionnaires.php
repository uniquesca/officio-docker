<?php

use Officio\Migration\AbstractMigration;

class AddSimplifiedToCompanyQuestionnaires extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires` ADD COLUMN `q_simplified` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `q_script_analytics_on_completion`;");
        $this->execute("ALTER TABLE `company_questionnaires_sections_templates` ADD COLUMN `q_section_hidden` ENUM('Y','N') NOT NULL DEFAULT 'N';");
        $this->execute("ALTER TABLE `company_questionnaires_fields_templates` ADD COLUMN `q_field_hidden` ENUM('Y','N') NOT NULL DEFAULT 'N';");
    }

    public function down()
    {
        $this->table('company_questionnaires')
            ->removeColumn('q_simplified')
            ->save();

        $this->table('company_questionnaires_sections_templates')
            ->removeColumn('q_section_hidden')
            ->save();

        $this->table('company_questionnaires_fields_templates')
            ->removeColumn('q_field_hidden')
            ->save();
    }
}