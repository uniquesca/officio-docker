<?php

use Officio\Migration\AbstractMigration;

class AddSameAddressDependantColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `main_applicant_address_is_the_same` ENUM('Y','N') DEFAULT 'N' AFTER `photo`;");
    }

    public function down()
    {
        $this->execute('ALTER TABLE `client_form_dependents` DROP COLUMN `main_applicant_address_is_the_same`;');
    }
}