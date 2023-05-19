<?php

use Phinx\Migration\AbstractMigration;

class UpdateClientFormFieldsTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ALTER `company_id` DROP DEFAULT;");
        $this->execute("ALTER TABLE `client_form_fields` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AFTER `field_id`;");
        $this->execute("ALTER TABLE `client_form_fields` ADD CONSTRAINT `FK_client_form_fields_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields` DROP FOREIGN KEY `FK_client_form_fields_company`;");
        $this->execute("ALTER TABLE `client_form_fields` DROP INDEX `FK_client_form_fields_company`;");
        $this->execute("ALTER TABLE `client_form_fields` CHANGE COLUMN `company_id` `company_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `field_id`;");
    }
}