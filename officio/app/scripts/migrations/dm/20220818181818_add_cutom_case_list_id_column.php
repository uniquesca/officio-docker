<?php

use Officio\Migration\AbstractMigration;

class AddCutomCaseListIdColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_categories` ADD COLUMN `client_status_custom_list_id` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `client_status_list_id`;");
        $this->execute("ALTER TABLE `client_categories` ADD CONSTRAINT `FK_client_categories_client_statuses_lists_2` FOREIGN KEY (`client_status_custom_list_id`) REFERENCES `client_statuses_lists` (`client_status_list_id`) ON UPDATE CASCADE ON DELETE SET NULL;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_categories` DROP FOREIGN KEY `FK_client_categories_client_statuses_lists_2`, DROP COLUMN `client_status_custom_list_id`;");
    }
}