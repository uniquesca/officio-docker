<?php

use Phinx\Migration\AbstractMigration;

class RenameLanguageOptions extends AbstractMigration
{

    public function up()
    {
        $arrMapping = array(
            'english' => array(
                'fields' => array(
                    'qf_language_english_general_score_speak',
                    'qf_language_english_general_score_read',
                    'qf_language_english_general_score_write',
                    'qf_language_english_general_score_listen',

                    'qf_language_spouse_english_general_score_speak',
                    'qf_language_spouse_english_general_score_read',
                    'qf_language_spouse_english_general_score_write',
                    'qf_language_spouse_english_general_score_listen',
                ),

                'labels' => array(
                    'native_proficiency' => 'Advanced/Native Proficiency (CLB 9+)',
                    'upper_intermediate' => 'Upper Intermediate (CLB 8)',
                    'intermediate'       => 'Intermediate (CLB 7)',
                    'lower_intermediate' => 'Lower Intermediate (CLB 5)',
                    'basic'              => 'Basic (CLB 3)',
                    'not_at_all'         => 'Not at all (CLB 1)'
                )
            ),

            'french' => array(
                'fields' => array(
                    'qf_language_french_general_score_speak',
                    'qf_language_french_general_score_read',
                    'qf_language_french_general_score_write',
                    'qf_language_french_general_score_listen',

                    'qf_language_spouse_french_general_score_speak',
                    'qf_language_spouse_french_general_score_read',
                    'qf_language_spouse_french_general_score_write',
                    'qf_language_spouse_french_general_score_listen',
                ),

                'labels' => array(
                    'native_proficiency' => 'Advanced/Native Proficiency (NCLC 9+)',
                    'upper_intermediate' => 'Upper Intermediate (NCLC 8)',
                    'intermediate'       => 'Intermediate (NCLC 7)',
                    'lower_intermediate' => 'Lower Intermediate (NCLC 5)',
                    'basic'              => 'Basic (NCLC 3)',
                    'not_at_all'         => 'Not at all (NCLC 1)'
                )
            ),
        );


        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        foreach ($arrMapping as $arrMappingRow) {
            $select = $db->select()
                ->from('company_questionnaires_fields', 'q_field_id')
                ->where('q_field_unique_id IN (?)', $arrMappingRow['fields']);

            $arrFieldIds = $db->fetchCol($select);

            if (!empty($arrFieldIds)) {
                foreach ($arrMappingRow['labels'] as $optionId => $label) {
                    $select = $db->select()
                        ->from('company_questionnaires_fields_options', 'q_field_option_id')
                        ->where('q_field_id IN (?)', $arrFieldIds, 'INT')
                        ->where('q_field_option_unique_id = ?', $optionId);

                    $arrOptionIds = $db->fetchCol($select);

                    $db->update(
                        'company_questionnaires_fields_options_templates',
                        array(
                            'q_field_option_label' => $label
                        ),
                        $db->quoteInto('q_field_option_id IN (?)', $arrOptionIds)
                    );
                }
            }
        }
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->query(
            "UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Advanced/Native Proficiency' WHERE q_field_option_label = 'Advanced/Native Proficiency (NCLC 9+)' OR q_field_option_label = 'Advanced/Native Proficiency (CLB 9+)';"
        );
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Upper Intermediate' WHERE q_field_option_label = 'Upper Intermediate (NCLC 8)' OR q_field_option_label = 'Upper Intermediate (CLB 8)';");
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Intermediate' WHERE q_field_option_label = 'Intermediate (NCLC 7)' OR q_field_option_label = 'Intermediate (CLB 7)';");
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Lower Intermediate' WHERE q_field_option_label = 'Lower Intermediate (NCLC 5)' OR q_field_option_label = 'Lower Intermediate (CLB 5)';");
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Basic' WHERE q_field_option_label = 'Basic (NCLC 3)' OR q_field_option_label = 'Basic (CLB 3)';");
        $db->query("UPDATE company_questionnaires_fields_options_templates SET q_field_option_label = 'Not at all' WHERE q_field_option_label = 'Not at all (NCLC 1)' OR q_field_option_label = 'Not at all (CLB 1)';");
    }
}