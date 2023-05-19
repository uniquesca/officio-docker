<?php

use Officio\Migration\AbstractMigration;

class AddFreeClientsToPricingDetails extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `pricing_category_details` ADD COLUMN `free_clients` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `free_storage`;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `pricing_category_details` DROP COLUMN `free_clients`;');
    }
}