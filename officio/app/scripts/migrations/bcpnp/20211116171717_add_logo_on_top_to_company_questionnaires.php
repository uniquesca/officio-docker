<?php

use Officio\Migration\AbstractMigration;

class AddLogoOnTopToCompanyQuestionnaires extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires` ADD COLUMN `q_logo_on_top` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `q_script_analytics_on_completion`;");
        $this->execute("ALTER TABLE `company_questionnaires` ADD COLUMN `q_button_color` char(6) NOT NULL DEFAULT 'FF6600' AFTER `q_section_text_color`;");
    }

    public function down()
    {
        $this->table('company_questionnaires')
            ->removeColumn('q_logo_on_top')
            ->removeColumn('q_button_color')
            ->save();
    }
}