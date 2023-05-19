<?php

use Phinx\Migration\AbstractMigration;

class IntroduceDecisionOn extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "           
            CALL `createCaseField` ('decision_made_on', 8, 'Decision Made On', 0, 'N', 'N', 'Decision Rationale', 'Business Immigration Registration', NULL);
           
            CALL `putCaseFieldIntoGroup` ('decision_made_on', 'Decision Rationale', 'Business Immigration Application', NULL);
            CALL `putCaseFieldIntoGroup` ('decision_made_on', 'Decision Rationale', 'Skills Immigration Registration', NULL);
            CALL `putCaseFieldIntoGroup` ('decision_made_on', 'Decision Rationale', 'Skills Immigration Application', NULL);
        "
        );
    }

    public function down()
    {
        $this->execute(
            "
            DELETE cfd, cffa, cfo
            FROM client_form_fields cff
              LEFT JOIN  client_form_data cfd ON cff.field_id = cfd.field_id
              LEFT JOIN client_form_field_access cffa ON cff.field_id = cffa.field_id
              LEFT JOIN client_form_order cfo ON cfo.field_id = cff.field_id
            WHERE cff.company_field_id IN (
                'decision_made_on'
            );

            DELETE
            FROM client_form_fields
            WHERE company_field_id IN (
                'decision_made_on'
            );
        "
        );
    }
}