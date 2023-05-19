<?php

use Officio\Migration\AbstractMigration;

class AddDependantsPlaceOfBirthColumnAndMore extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `place_of_birth` VARCHAR(255) NULL DEFAULT NULL AFTER `country_of_birth`;");
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `spouse_name` VARCHAR(255) NULL DEFAULT NULL AFTER `lName`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_dependents` DROP COLUMN `place_of_birth`,  DROP COLUMN `spouse_name`;");
    }
}