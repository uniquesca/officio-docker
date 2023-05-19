<?php

use Officio\Migration\AbstractMigration;

class MakeSeveralCaseFieldsNotRequired extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $this->execute("UPDATE `client_form_fields` SET `required`='N' WHERE `company_field_id` IN ('registered_migrant_agent', 'accounting', 'processing', 'sales_and_marketing');");
    }

    public function down()
    {
    }
}
