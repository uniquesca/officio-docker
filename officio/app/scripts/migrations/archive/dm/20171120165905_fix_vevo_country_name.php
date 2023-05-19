<?php

use Phinx\Migration\AbstractMigration;

class FixVevoCountryName extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `country_master` set `countries_name` = 'Trinidad and Tobago' WHERE `countries_name` = 'Trinidad and TobagoTrinidad and TobagoTrinidad and Tobago' AND `type` = 'vevo';");
    }

    public function down()
    {
        $this->execute("UPDATE `country_master` set `countries_name` = 'Trinidad and TobagoTrinidad and TobagoTrinidad and Tobago' WHERE `countries_name` = 'Trinidad and Tobago' AND `type` = 'vevo';");
    }
}