<?php

use Phinx\Migration\AbstractMigration;

class AddDemocraticCongoSouthSudanCountries extends AbstractMigration
{
    public function up()
    {
        $this->execute('INSERT INTO `country_master` (`countries_name`, `countries_iso_code_2`, `countries_iso_code_3`) VALUES (\'Democratic Congo\', \'CD\', \'COD\');');
        $this->execute('INSERT INTO `country_master` (`countries_name`, `countries_iso_code_2`, `countries_iso_code_3`) VALUES (\'South Sudan\', \'SS\', \'SSD\');');
    }

    public function down()
    {
        $this->execute("DELETE FROM `country_master` WHERE `countries_name` IN ('Democratic Congo', 'South Sudan');");
    }
}
