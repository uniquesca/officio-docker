<?php

use Officio\Migration\AbstractMigration;

class IntroduceParentClientFormGroupIdAndFieldId extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_groups`
            ADD COLUMN `parent_group_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `group_id`,
            ADD CONSTRAINT `FK_client_form_groups_client_form_groups` FOREIGN KEY (`parent_group_id`) REFERENCES `client_form_groups` (`group_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `client_form_fields`
            ADD COLUMN `parent_field_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `field_id`,
            ADD CONSTRAINT `FK_client_form_fields_client_form_fields` FOREIGN KEY (`parent_field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;
        ");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_groups` DROP FOREIGN KEY `FK_client_form_groups_client_form_groups`;");

        $this->execute("ALTER TABLE `client_form_groups` DROP COLUMN `parent_group_id`;");

        $this->execute("ALTER TABLE `client_form_fields` DROP FOREIGN KEY `FK_client_form_fields_client_form_fields`;");

        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `parent_field_id`;");
    }
}