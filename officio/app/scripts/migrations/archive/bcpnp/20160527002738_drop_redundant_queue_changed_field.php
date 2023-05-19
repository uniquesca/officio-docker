<?php

use Phinx\Migration\AbstractMigration;

class DropRedundantQueueChangedField extends AbstractMigration
{

    public function up()
    {
        $this->execute(
            "
          DELETE aff, afd, afo
          FROM applicant_form_fields aff
            LEFT OUTER JOIN applicant_form_data afd ON afd.applicant_field_id = aff.applicant_field_id
            LEFT OUTER JOIN applicant_form_order afo ON afo.applicant_field_id = aff.applicant_field_id
          WHERE aff.applicant_field_unique_id = 'entered_queue_on' AND aff.type <> 'office_change_date_time';
          "
        );
    }

    public function down()
    {
    }

}
