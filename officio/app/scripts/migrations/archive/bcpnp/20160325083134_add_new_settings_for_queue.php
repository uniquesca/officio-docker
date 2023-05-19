<?php

use Phinx\Migration\AbstractMigration;

class AddNewSettingsForQueue extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members_queues` ADD COLUMN `queue_show_active_cases` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `queue_columns`");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_queues` DROP COLUMN `queue_show_active_cases`");
    }
}