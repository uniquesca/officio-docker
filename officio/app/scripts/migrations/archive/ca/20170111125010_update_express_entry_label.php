<?php

use Phinx\Migration\AbstractMigration;

class UpdateExpressEntryLabel extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "UPDATE company_questionnaires_fields_templates SET q_field_label='Have you previously submitted an Express Entry application?', q_field_prospect_profile_label='Have you previously submitted an Express Entry application?' where q_field_id=135"
        );
    }

    public function down()
    {
        $this->execute(
            "UPDATE company_questionnaires_fields_templates SET q_field_label='Have you previously submitted an Express Entry application? application?', q_field_prospect_profile_label='Have you previously submitted an Express Entry application? application?' where q_field_id=135"
        );
    }
}