<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddNewFieldType extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `field_types` ADD COLUMN `field_type_with_custom_height` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `field_type_with_default_value`;");

        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_can_be_encrypted`, `field_type_with_custom_height`) VALUES
            (36, 'html_editor', 'HTML Editor', 'Y', 'Y', 'Y');");

        $this->execute("UPDATE `field_types` SET `field_type_with_custom_height`='Y' WHERE  `field_type_id`=11;");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("ALTER TABLE `client_form_fields`
	        ADD COLUMN `custom_height` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `blocked`;");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        ADD COLUMN `custom_height` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `blocked`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `applicant_form_fields` DROP COLUMN `custom_height`");

        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `custom_height`");

        $this->execute("ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("UPDATE `field_types` SET `field_type_with_custom_height`='N' WHERE  `field_type_id`=11;");

        $this->execute("ALTER TABLE `field_types` DROP COLUMN `field_type_with_custom_height`");

        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=36;");
    }
}