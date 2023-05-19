<?php

use Phinx\Migration\AbstractMigration;

class addWalkthoughHelpType extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `faq` CHANGE COLUMN `content_type` `content_type` ENUM('text','video','walkthrough') NOT NULL DEFAULT 'text' COLLATE 'utf8_general_ci' AFTER `featured`;");
        $this->execute("ALTER TABLE `faq` ADD COLUMN `inlinemanual_topic_id` VARCHAR(50) NULL DEFAULT NULL AFTER `client_view`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `faq` DROP COLUMN `inlinemanual_topic_id`;");
        $this->execute("ALTER TABLE `faq` CHANGE COLUMN `content_type` `content_type` ENUM('text','video') NOT NULL DEFAULT 'text' COLLATE 'utf8_general_ci' AFTER `featured`;");
    }
}
