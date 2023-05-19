<?php

use Phinx\Migration\AbstractMigration;

class AddSpecialAnnouncements extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members` ADD COLUMN `show_special_announcements` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `lName`;");
        $this->execute("ALTER TABLE `members` ADD COLUMN `special_announcements_viewed_on` DATETIME NULL AFTER `show_special_announcements`;");
        $this->execute("ALTER TABLE `news` ADD COLUMN `show_on_homepage` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `create_date`;");
        $this->execute("ALTER TABLE `news` ADD COLUMN `is_special_announcement` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `show_on_homepage`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members` DROP COLUMN `special_announcements_viewed_on`;");
        $this->execute("ALTER TABLE `members` DROP COLUMN `show_special_announcements`;");
        $this->execute("ALTER TABLE `news` DROP COLUMN `is_special_announcement`;");
        $this->execute("ALTER TABLE `news` DROP COLUMN `show_on_homepage`;");
    }
}