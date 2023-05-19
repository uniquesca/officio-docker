<?php

use Officio\Migration\AbstractMigration;

class AddDependantColumnMiddleName extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `middle_name` VARCHAR(255) NULL DEFAULT NULL AFTER `lName`;");
    }

    public function down()
    {
        $this->execute('ALTER TABLE `client_form_dependents` DROP COLUMN `middle_name`;');
    }
}