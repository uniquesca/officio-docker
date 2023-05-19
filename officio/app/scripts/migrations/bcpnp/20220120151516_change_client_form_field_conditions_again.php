<?php

use Officio\Migration\AbstractMigration;

class ChangeClientFormFieldConditionsAgain extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $this->execute("DELETE FROM client_form_field_conditions");
        $this->execute("ALTER TABLE `client_form_field_conditions`
            DROP FOREIGN KEY `FK_client_form_field_conditions_client_form_default`,
            DROP FOREIGN KEY `FK_client_form_field_conditions_client_categories`,
            DROP FOREIGN KEY `FK_client_form_field_conditions_client_statuses`,
            DROP COLUMN `field_option_id`,
            DROP COLUMN `field_option_client_category_id`,
            DROP COLUMN `field_option_client_status_id`;");

        $this->execute("ALTER TABLE `client_form_field_conditions` CHANGE COLUMN `field_option_value` `field_option_value` TEXT NULL AFTER `field_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD COLUMN `field_option_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `field_id`;");
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD COLUMN `field_option_client_category_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `field_option_id`;");
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD COLUMN `field_option_client_status_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `field_option_client_category_id`;");
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD CONSTRAINT `FK_client_form_field_conditions_client_form_default` FOREIGN KEY (`field_option_id`) REFERENCES `client_form_default` (`form_default_id`) ON UPDATE CASCADE ON DELETE CASCADE");
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD CONSTRAINT `FK_client_form_field_conditions_client_categories` FOREIGN KEY (`field_option_client_category_id`) REFERENCES `client_categories` (`client_category_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->execute("ALTER TABLE `client_form_field_conditions` ADD CONSTRAINT `FK_client_form_field_conditions_client_statuses` FOREIGN KEY (`field_option_client_status_id`) REFERENCES `client_statuses` (`client_status_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->execute("ALTER TABLE `client_form_field_conditions` CHANGE COLUMN `field_option_value` `field_option_value` VARCHAR(128) NULL DEFAULT NULL AFTER `field_option_client_status_id`;");
    }
}
