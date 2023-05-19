<?php

use Officio\Migration\AbstractMigration;

class UpdateVevoCountries extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `country_master` ADD COLUMN `synonyms` TEXT NULL AFTER `type`;");

        $this->execute("UPDATE `country_master` SET `synonyms`='a:3:{i:0;s:2:\"uk\";i:1;s:7:\"britain\";i:2;s:2:\"gb\";}' WHERE  `countries_id` IN (738, 739, 740, 741, 742);");
        $this->execute("UPDATE `country_master` SET `synonyms`='a:3:{i:0;s:3:\"usa\";i:1;s:2:\"us\";i:2;s:7:\"america\";}' WHERE  `countries_id` = 745;");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES
                        (11, 'applicants', 'profile', 'get-vevo-country-suggestions'),
                        (13, 'applicants', 'profile', 'get-vevo-country-suggestions'),
                        (401, 'applicants', 'profile', 'get-vevo-country-suggestions'),
                        (403, 'applicants', 'profile', 'get-vevo-country-suggestions');");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `country_master` DROP COLUMN `synonyms`;");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'applicants' AND `resource_id` = 'profile' AND `resource_privilege` = 'get-vevo-country-suggestions';");
    }
}