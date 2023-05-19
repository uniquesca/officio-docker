<?php

use Officio\Migration\AbstractMigration;

class UpdateClientDependentsGender extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `client_form_dependents` SET `sex` = NULL;");
    }

    public function down()
    {
    }
}