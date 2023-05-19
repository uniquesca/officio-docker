<?php

use Phinx\Migration\AbstractMigration;

class AddFaqSectionKey extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `faq` CHANGE COLUMN `faq_section_id` `faq_section_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `faq_id`;');
        $this->execute('ALTER TABLE `faq` ADD CONSTRAINT `FK_faq_faq_sections` FOREIGN KEY (`faq_section_id`) REFERENCES `faq_sections` (`faq_section_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `faq` DROP FOREIGN KEY `FK_faq_faq_sections`;');
        $this->execute('ALTER TABLE `faq` CHANGE COLUMN `faq_section_id` `faq_section_id` INT(3) UNSIGNED NULL DEFAULT NULL AFTER `faq_id`;');
    }
}