<?php

use Officio\Migration\AbstractMigration;

class AddMissingDependantUci extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `uci` VARCHAR(255) NULL DEFAULT NULL AFTER `passport_date`;");
    }

    public function down()
    {
        $this->execute('ALTER TABLE `client_form_dependents` DROP COLUMN `uci`;');
    }
}