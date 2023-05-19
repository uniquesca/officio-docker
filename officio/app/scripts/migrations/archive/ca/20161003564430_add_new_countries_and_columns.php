<?php

use Officio\Migration\AbstractMigration;

class AddNewCountriesAndColumns extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `country_master`
                            ADD COLUMN `immi_code_3` VARCHAR(5) NOT NULL DEFAULT '' AFTER `countries_iso_code_3`,
                            ADD COLUMN `immi_code_4` VARCHAR(5) NOT NULL DEFAULT '' AFTER `immi_code_3`,
                            ADD COLUMN `immi_code_num` VARCHAR(5) NOT NULL DEFAULT '' AFTER `immi_code_4`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `country_master`
                            DROP COLUMN `immi_code_3`,
                            DROP COLUMN `immi_code_4`,
                            DROP COLUMN `immi_code_num`;");
    }
}

