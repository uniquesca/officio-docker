<?php

use Officio\Migration\AbstractMigration;

class AddTaDetailedDescription extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_ta` ADD COLUMN `detailed_description` TEXT NULL DEFAULT NULL AFTER `name`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_ta` DROP COLUMN `detailed_description`;");
    }
}
