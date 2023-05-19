<?php

use Officio\Migration\AbstractMigration;

class AddMoreQueueSettings extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members_queues`
            CHANGE COLUMN `queue_columns` `queue_individual_columns` TEXT NULL DEFAULT NULL COLLATE 'utf8_general_ci' AFTER `queue_member_selected_queues`,
            CHANGE COLUMN `queue_show_active_cases` `queue_individual_show_active_cases` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `queue_individual_columns`,
            ADD COLUMN `queue_employer_columns` TEXT NULL DEFAULT NULL AFTER `queue_individual_show_active_cases`,
            ADD COLUMN `queue_employer_show_active_cases` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `queue_employer_columns`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_queues`
            CHANGE COLUMN `queue_individual_columns` `queue_columns` TEXT NULL DEFAULT NULL COLLATE 'utf8_general_ci' AFTER `queue_member_selected_queues`,
            CHANGE COLUMN `queue_individual_show_active_cases` `queue_show_active_cases` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `queue_columns`,
            DROP COLUMN `queue_employer_columns`,
            DROP COLUMN `queue_employer_show_active_cases`;");
    }
}
