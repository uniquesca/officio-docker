<?php

use Officio\Migration\AbstractMigration;

class CheckSameAddressForDependents extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `client_form_dependents` SET `main_applicant_address_is_the_same` = 'Y';");
    }

    public function down()
    {
    }
}