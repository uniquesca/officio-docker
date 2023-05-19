<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddNewFieldTypes extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_custom_height`, `field_type_use_for`) VALUES
            (38, 'case_internal_id', 'Case Internal Id', 'Y', 'Y', 'N', 'case'),
            (39, 'applicant_internal_id', 'Applicant Internal Id', 'Y', 'Y', 'N', 'all');");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=38 AND `field_type_id`=39;");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");
    }
}