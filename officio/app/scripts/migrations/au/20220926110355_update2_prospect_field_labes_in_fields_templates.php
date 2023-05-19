<?php

use Officio\Migration\AbstractMigration;

class Update2ProspectFieldLabesInFieldsTemplates extends AbstractMigration
{
    public function up()
    {
        $arrLables = [
            'Is your job qualified for social security?' => 'Does your job qualify for social security?'
        ];

        foreach ($arrLables as $key => $newLabel) {
            $this->getQueryBuilder()
                ->update('company_questionnaires_fields_templates')
                ->set([
                    'q_field_label' => $newLabel,
                    'q_field_prospect_profile_label' => $newLabel
                ])
                ->where(['q_field_label' => $key])
                ->execute();
        }

        $arrLables = [
            'Do you own this business?' => 'Owns a business',
        ];

        foreach ($arrLables as $key => $newLabel) {
            $this->getQueryBuilder()
                ->update('company_questionnaires_fields_templates')
                ->set([
                    'q_field_prospect_profile_label' => $newLabel
                ])
                ->where(['q_field_label' => $key])
                ->execute();
        }
    }

    public function down()
    {
    }
}
