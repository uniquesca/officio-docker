<?php

use Phinx\Migration\AbstractMigration;

class AddFileNumberKey extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `file_number_reservations` WHERE company_id = 0;");
        $this->execute("DELETE FROM `file_number_reservations` WHERE LENGTH(file_number) > 32;");
        $this->execute("ALTER TABLE `file_number_reservations` ALTER `file_number` DROP DEFAULT;");
        $this->execute("ALTER TABLE `file_number_reservations` CHANGE COLUMN `file_number` `file_number` VARCHAR(32) NOT NULL AFTER `company_id`;");
        $this->execute("ALTER TABLE `clients` ADD INDEX `fileNumber` (`fileNumber`);");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `file_number_reservations` ALTER `file_number` DROP DEFAULT;");
        $this->execute("ALTER TABLE `file_number_reservations` CHANGE COLUMN `file_number` `file_number` VARCHAR(255) NOT NULL AFTER `company_id`;");
        $this->execute("ALTER TABLE `clients` DROP INDEX `fileNumber`;");
    }
}