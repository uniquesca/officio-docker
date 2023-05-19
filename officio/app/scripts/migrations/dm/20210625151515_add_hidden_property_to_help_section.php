<?php

use Phinx\Migration\AbstractMigration;

class AddHiddenPropertyToHelpSection extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_is_hidden` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `section_show_as_heading`");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_is_hidden`;");
    }
}