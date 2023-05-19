<?php

use Officio\Migration\AbstractMigration;

class AddNewsReadOn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members` ADD COLUMN `news_read_on` DATETIME NULL DEFAULT NULL AFTER `password_change_date`");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members` DROP COLUMN `news_read_on`;");
    }
}