<?php

use Officio\Migration\AbstractMigration;

class RenameLibya extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `country_master` SET `countries_name`='Libya' WHERE `countries_name`='Libyan Arab Jamahiriya';");
    }

    public function down()
    {
        $this->execute("UPDATE `country_master` SET `countries_name`='Libyan Arab Jamahiriya' WHERE `countries_name`='Libya';");
    }
}
