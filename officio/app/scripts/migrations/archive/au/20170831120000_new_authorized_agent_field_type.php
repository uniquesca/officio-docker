<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class NewAuthorizedAgentFieldType extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_custom_height`, `field_type_use_for`) VALUES
            (43, 'authorized_agents', 'Authorized Agent', 'Y', 'Y', 'N', 'case');"
        );

        $this->execute(
            "ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo','reference','authorized_agents') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo','reference') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;"
        );

        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=43;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}
