<?php

use Phinx\Migration\AbstractMigration;

class AddFileNumberKey extends AbstractMigration
{
    public function up()
    {
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