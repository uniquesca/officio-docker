<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class UpdateVevoCountries extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `applicant_form_fields` SET `type` = 'text' WHERE `applicant_field_unique_id` = 'country_of_passport';");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo', 'reference', 'authorized_agents') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=44;");

        $this->execute("ALTER TABLE `country_master`
	        ADD COLUMN `synonyms` TEXT NULL AFTER `type`;");

        $this->execute("UPDATE `country_master` SET `synonyms`='a:3:{i:0;s:2:\"uk\";i:1;s:7:\"britain\";i:2;s:2:\"gb\";}' WHERE  `countries_id` IN (738, 739, 740, 741, 742);");
        $this->execute("UPDATE `country_master` SET `synonyms`='a:3:{i:0;s:3:\"usa\";i:1;s:2:\"us\";i:2;s:7:\"america\";}' WHERE  `countries_id` = 745;");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES
                        (11, 'applicants', 'profile', 'get-vevo-country-suggestions'),
                        (13, 'applicants', 'profile', 'get-vevo-country-suggestions'),
                        (401, 'applicants', 'profile', 'get-vevo-country-suggestions'),
                        (403, 'applicants', 'profile', 'get-vevo-country-suggestions');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_custom_height`, `field_type_use_for`) VALUES
            (44, 'country_vevo', 'Country VEVO', 'Y', 'Y', 'N', 'all');");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo', 'reference', 'authorized_agents', 'country_vevo') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("UPDATE `applicant_form_fields` SET `type` = 'country_vevo' WHERE `applicant_field_unique_id` = 'country_of_passport';");

        $this->execute("ALTER TABLE `country_master` DROP COLUMN `synonyms`;");

        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'applicants' AND `resource_id` = 'profile' AND `resource_privilege` = 'get-vevo-country-suggestions';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}