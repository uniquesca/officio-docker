<?php

use Phinx\Migration\AbstractMigration;

class AddStudyAdmissionField extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        // Add "Further Information" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_study_have_admission',
                'q_section_id'                     => 1,
                'q_field_type'                     => 'combo',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 14,
            )
        );
        
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Do you have admission from a school or post-secondary institution in Canada?', 'Do you have admission from a school or post-secondary institution in Canada?' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'yes',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $db->query("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
                SELECT q_id, " . $fieldOptionId . ", 'Yes', 'Y' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'no',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $db->query("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
                SELECT q_id, " . $fieldOptionId . ", 'No', 'Y' FROM company_questionnaires;");
    }

    public function down()
    {
        $this->execute("DELETE FROM company_questionnaires_fields_templates WHERE q_field_id = (SELECT q_field_id FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_study_have_admission');");
        $this->execute("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_study_have_admission';");
    }
}