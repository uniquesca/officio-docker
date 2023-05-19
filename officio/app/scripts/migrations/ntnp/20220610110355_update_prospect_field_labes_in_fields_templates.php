<?php

use Officio\Migration\AbstractMigration;

class UpdateProspectFieldLabesInFieldsTemplates extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_questionnaires_fields_templates`
            SET `q_field_label`=SUBSTRING(`q_field_label`, 1, CHAR_LENGTH(`q_field_label`) - 1)
            WHERE (`q_field_label` LIKE '%:');");
        $this->execute("UPDATE `company_questionnaires_fields_templates`
            SET `q_field_prospect_profile_label`=SUBSTRING(`q_field_prospect_profile_label`, 1, CHAR_LENGTH(`q_field_prospect_profile_label`) - 1)
            WHERE (`q_field_prospect_profile_label` LIKE '%:');");
        $arrLables = [
            'Salutation'              => 'Title',
            'Date of birth'           => 'Date of Birth (DOB)',
            'Spouse\'s date of birth' => 'Spouse\'s date of birth (DOB)'
        ];
        foreach ($arrLables as $key => $value) {
            $this->getQueryBuilder()->update('company_questionnaires_fields_templates')
                ->set([
                    'q_field_label'                  => $value,
                    'q_field_prospect_profile_label' => ''
                ])
                ->where(['q_field_label' => $key])
                ->execute();
        }
        $this->getQueryBuilder()->update('company_questionnaires_sections_templates')
            ->set([
                'q_section_template_name'    => 'Previous And Future Visit(s)',
                'q_section_prospect_profile' => 'Previous And Future Visit(s)'
            ])
            ->where(['q_section_template_name' => 'Previous and the Future Visit(s)'])
            ->execute();
    }

    public function down()
    {
    }
}
