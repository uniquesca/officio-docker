<?php

use Phinx\Migration\AbstractMigration;

class AddFreeClients extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `prospects`
	        ADD COLUMN `free_clients` INT(11) UNSIGNED NULL DEFAULT 0 AFTER `free_users`;");

        $this->execute("ALTER TABLE `company_details`
	        ADD COLUMN `free_clients` INT(11) UNSIGNED NULL DEFAULT 0 AFTER `free_users`;");

        $this->execute("ALTER TABLE `company_invoice`
	        ADD COLUMN `free_clients` INT(11) UNSIGNED NULL DEFAULT 0 AFTER `free_users`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `prospects` DROP COLUMN `free_clients`;");
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `free_clients`;");
        $this->execute("ALTER TABLE `company_invoice` DROP COLUMN `free_clients`;");
    }
}