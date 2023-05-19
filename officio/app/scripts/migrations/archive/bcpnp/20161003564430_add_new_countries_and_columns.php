<?php

use Phinx\Migration\AbstractMigration;

class AddNewCountriesAndColumns extends AbstractMigration
{
    public function up()
    {

        /*$this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Bonaire, saint eustatius and saba');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('British overseas territories citizen');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Cabo verde');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Curacao');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('England');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Gaza strip');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Guernsey');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Isle of man');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Kosovo');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Montenegro');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('New guinea');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('New hebrides');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Saint barthelemy');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Saint martin');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Scotland');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Serbia');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Sint maarten (dutch part)');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Timor-leste');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Wales');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('West bank');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('BRIT DEPEND TERR CIT');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('BRITISH WEST INDIES');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Burma');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Channel Islands');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Czechoslovakia');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Jersey');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Northen Ireland');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Pleasent Island');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Saint Helena, Ascension and Tristan Da Cunha');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Tahiti');");
        $this->execute("INSERT INTO `country_master` (`countries_name`) VALUES ('Tibet');");*/

        $this->execute("ALTER TABLE `country_master`
                            ADD COLUMN `immi_code_3` VARCHAR(5) NOT NULL DEFAULT '' AFTER `countries_iso_code_3`,
                            ADD COLUMN `immi_code_4` VARCHAR(5) NOT NULL DEFAULT '' AFTER `immi_code_3`,
                            ADD COLUMN `immi_code_num` VARCHAR(5) NOT NULL DEFAULT '' AFTER `immi_code_4`;");

    }

    public function down()
    {

        /*$this->execute("DELETE FROM `country_master` WHERE  `countries_id`=246;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=244;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=245;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=246;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=247;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=248;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=249;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=250;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=251;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=252;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=253;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=254;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=255;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=256;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=257;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=258;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=259;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=260;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=261;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=262;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=263;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=264;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=265;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=266;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=267;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=268;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=269;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=270;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=271;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=272;");
        $this->execute("DELETE FROM `country_master` WHERE  `countries_id`=273;");*/


        $this->execute("ALTER TABLE `country_master`
                            DROP COLUMN `immi_code_3`,
                            DROP COLUMN `immi_code_4`,
                            DROP COLUMN `immi_code_num`;");

    }
}

