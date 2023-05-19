<?php

use Phinx\Migration\AbstractMigration;

class FixClientFieldsOrder extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `clients` ADD COLUMN `applicant_type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `client_type_id`;");
        $this->execute("ALTER TABLE `clients` ADD CONSTRAINT `FK_clients_applicant_types` FOREIGN KEY (`applicant_type_id`) REFERENCES `applicant_types` (`applicant_type_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
    }
}