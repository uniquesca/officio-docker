<?php

use Phinx\Migration\AbstractMigration;

class AddQnrSearchByColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  `q_field_type` IN ('checkbox', 'label');");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  q_field_unique_id IN ('qf_email_confirmation')");
    }

    public function down()
    {
    }
}