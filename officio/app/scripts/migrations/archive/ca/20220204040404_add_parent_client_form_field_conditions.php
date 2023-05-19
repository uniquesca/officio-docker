<?php

use Officio\Migration\AbstractMigration;

class AddParentClientFormFieldConditions extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD COLUMN `parent_field_condition_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `field_condition_id`;");
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD CONSTRAINT `FK_client_form_field_conditions_client_form_field_conditions` FOREIGN KEY (`parent_field_condition_id`) REFERENCES `client_form_field_conditions` (`field_condition_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_field_conditions` DROP FOREIGN KEY `FK_client_form_field_conditions_client_form_field_conditions`;");
        $this->execute("ALTER TABLE `client_form_field_conditions` DROP COLUMN `parent_field_condition_id`;");
    }
}
