<?php

use Phinx\Migration\AbstractMigration;

class FixFIleNumberReservations extends AbstractMigration
{
    public function up()
    {
        $this->query("ALTER TABLE `file_number_reservations` ALTER `company_id` DROP DEFAULT;");
        $this->query("ALTER TABLE `file_number_reservations` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AFTER `id`;");
        $this->query("ALTER TABLE `file_number_reservations` ADD CONSTRAINT `FK_file_number_reservations_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->query("ALTER TABLE `file_number_reservations` DROP FOREIGN KEY `FK_file_number_reservations_company`;");
        $this->query("ALTER TABLE `file_number_reservations` ALTER `company_id` DROP DEFAULT;");
        $this->query("ALTER TABLE `file_number_reservations` CHANGE COLUMN `company_id` `company_id` INT(11) NOT NULL AFTER `id`;");
    }
}