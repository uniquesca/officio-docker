<?php

use Officio\Migration\AbstractMigration;

class FixFieldsForeignKey extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `client_form_fields` DROP FOREIGN KEY `FK_client_form_fields_client_form_fields`;');
        $this->execute('ALTER TABLE `client_form_fields` ADD CONSTRAINT `FK_client_form_fields_client_form_fields` FOREIGN KEY (`parent_field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `client_form_fields` DROP FOREIGN KEY `FK_client_form_fields_client_form_fields`;');
        $this->execute('ALTER TABLE `client_form_fields` ADD CONSTRAINT `FK_client_form_fields_client_form_fields` FOREIGN KEY (`parent_field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
    }
}
