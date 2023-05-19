<?php

use Officio\Migration\AbstractMigration;

class UpdateSectionTemplateName extends AbstractMigration
{
    public function up()
    {
        $arrLables = [
            'Is your job qualified for social security?' => 'Does your job qualify for social security?',
        ];
        foreach ($arrLables as $key => $value) {
            $this->getQueryBuilder()->update('company_questionnaires_fields_templates')
                ->set([
                    'q_field_label'                  => $value,
                    'q_field_prospect_profile_label' => ''
                ])
                ->where(['q_field_prospect_profile_label' => $key])
                ->execute();
        }
        $arrProfileLables = [
            'Own business' => 'Own a business',
        ];
        foreach ($arrProfileLables as $key => $value) {
            $this->getQueryBuilder()->update('company_questionnaires_fields_templates')
                ->set([
                    'q_field_prospect_profile_label' => $value
                ])
                ->where(['q_field_prospect_profile_label' => $key])
                ->execute();
        }
        $this->getQueryBuilder()->update('company_questionnaires_sections_templates')
            ->set([
                'q_section_template_name'    => 'PREVIOUS AND FUTURE VISIT(S)',
                'q_section_prospect_profile' => 'Previous and Future Visit(s)'
            ])
            ->where(['q_section_template_name' => 'Previous And Future Visit(s)'])
            ->execute();
    }

    public function down()
    {
    }
}
