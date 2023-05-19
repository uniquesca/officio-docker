<?php

use Officio\Migration\AbstractMigration;

class MakeComboOptionsDeletable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_default` ADD COLUMN `deleted` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `order`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_default` DROP COLUMN `deleted`;");
    }
}
