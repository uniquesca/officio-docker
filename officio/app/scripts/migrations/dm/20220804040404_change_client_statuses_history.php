<?php

use Officio\Migration\AbstractMigration;

class ChangeClientStatusesHistory extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_statuses_history`
            CHANGE COLUMN `history_date` `history_checked_date` DATETIME NOT NULL AFTER `history_user_name`,
            ADD COLUMN `history_unchecked_date` DATETIME NULL AFTER `history_checked_date`;
        ");

        $this->execute("ALTER TABLE `client_statuses_history`
            CHANGE COLUMN `user_id` `history_checked_user_id` BIGINT(19) NULL DEFAULT NULL AFTER `history_client_status_name`,
            CHANGE COLUMN `history_user_name` `history_checked_user_name` VARCHAR(255) NULL DEFAULT NULL AFTER `history_checked_user_id`,
            DROP INDEX `FK_client_file_status_history_2`,
            ADD INDEX `FK_client_file_status_history_2` (`history_checked_user_id`) USING BTREE;
        ");

        $this->execute("ALTER TABLE `client_statuses_history`
            ADD COLUMN `history_unchecked_user_id` BIGINT(19) NULL DEFAULT NULL AFTER `history_checked_user_name`,
            ADD COLUMN `history_unchecked_user_name` VARCHAR(255) NULL DEFAULT NULL AFTER `history_unchecked_user_id`;
        ");

        $this->execute("ALTER TABLE `client_statuses_history` ADD CONSTRAINT `FK_client_statuses_history_members` FOREIGN KEY (`history_unchecked_user_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_statuses_history` DROP FOREIGN KEY `FK_client_statuses_history_members`;");

        $this->execute("ALTER TABLE `client_statuses_history`
            DROP COLUMN `history_unchecked_user_id`,
            DROP COLUMN `history_unchecked_user_name`;");

        $this->execute("ALTER TABLE `client_statuses_history`
            CHANGE COLUMN `history_checked_user_id` `user_id` BIGINT(19) NULL DEFAULT NULL AFTER `history_client_status_name`,
            CHANGE COLUMN `history_checked_user_name` `history_user_name` VARCHAR(255) NULL DEFAULT NULL AFTER `user_id`,
            DROP INDEX `FK_client_file_status_history_2`,
            ADD INDEX `FK_client_file_status_history_2` (`user_id`) USING BTREE;
        ");

        $this->execute("ALTER TABLE `client_statuses_history`
            CHANGE COLUMN `history_checked_date` `history_date` DATETIME NOT NULL AFTER `history_user_name`,
            DROP COLUMN `history_unchecked_date`;
        ");
    }
}