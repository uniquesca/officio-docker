<?php

use Phinx\Migration\AbstractMigration;

class DeleteNotActiveClientFileStatusRecords extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `client_form_data` WHERE `value` <> 'Active' AND `field_id` IN (SELECT `field_id` FROM `client_form_fields` WHERE `company_field_id` = 'Client_file_status');");
    }

    public function down()
    {
    }
}