<?php

use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddProspectNewLanguageFields extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        try {
            $db->beginTransaction();

            $arrFieldsMapping = array(
                array(
                    'id'    => 'qf_language_english_done',
                    'label' => 'Have you done any English language test?'
                ),

                array(
                    'id'    => 'qf_language_spouse_english_done',
                    'label' => 'Has your spouse/common-law partner done any English language test?'
                )
            );

            foreach ($arrFieldsMapping as $arrFieldInfo) {
                $select  = $db->select()
                    ->from('company_questionnaires_fields', 'q_field_id')
                    ->where('q_field_unique_id = ?', $arrFieldInfo['id']);
                $fieldId = $db->fetchOne($select);

                // Rename this field
                $db->update(
                    'company_questionnaires_fields_templates',
                    array(
                        'q_field_label'                  => $arrFieldInfo['label'],
                        'q_field_prospect_profile_label' => $arrFieldInfo['label'],
                    ),
                    $db->quoteInto('q_field_id = ?', $fieldId, 'INT')
                );


                // Rename Yes to IELTS
                $select      = $db->select()
                    ->from('company_questionnaires_fields_options', 'q_field_option_id')
                    ->where('q_field_id = ?', $fieldId, 'INT')
                    ->where('q_field_option_unique_id = ?', 'yes');
                $yesOptionId = $db->fetchOne($select);

                $db->update(
                    'company_questionnaires_fields_options',
                    array('q_field_option_unique_id' => 'ielts'),
                    $db->quoteInto('q_field_option_id = ?', $yesOptionId, 'INT')
                );

                $db->update(
                    'company_questionnaires_fields_options_templates',
                    array('q_field_option_label' => 'IELTS'),
                    $db->quoteInto('q_field_option_id = ?', $yesOptionId, 'INT')
                );

                // Update all prospects from "Not Sure" to "No"
                $select = $db->select()
                    ->from('company_questionnaires_fields_options', 'q_field_option_id')
                    ->where('q_field_id = ?', $fieldId, 'INT')
                    ->where('q_field_option_unique_id = ?', 'no');

                $noOptionId = $db->fetchOne($select);

                $select = $db->select()
                    ->from('company_questionnaires_fields_options', 'q_field_option_id')
                    ->where('q_field_id = ?', $fieldId, 'INT')
                    ->where('q_field_option_unique_id = ?', 'not_sure');

                $notSureOptionId = $db->fetchOne($select);

                $db->update(
                    'company_prospects_data',
                    array('q_value' => $noOptionId),
                    $db->quoteInto('q_field_id = ?', $fieldId, 'INT') .
                    $db->quoteInto(' AND q_value = ?', $notSureOptionId)
                );

                // Change order of "No" - to show at the bottom
                $db->update(
                    'company_questionnaires_fields_options',
                    array('q_field_option_order' => 2),
                    $db->quoteInto('q_field_option_id = ?', $noOptionId, 'INT')
                );


                // Delete "Not Sure"
                $db->delete(
                    'company_questionnaires_fields_options',
                    $db->quoteInto('q_field_option_id = ?', $notSureOptionId, 'INT')
                );

                // Add CELPIP option
                $db->insert(
                    'company_questionnaires_fields_options',
                    array(
                        'q_field_id'               => $fieldId,
                        'q_field_option_unique_id' => 'celpip',
                        'q_field_option_selected'  => 'N',
                        'q_field_option_order'     => 1
                    )
                );
                $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

                $db->query(
                    "INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
                SELECT q_id, " . $fieldOptionId . ", 'CELPIP', 'Y' FROM company_questionnaires;"
                );
            }


            // Add fields for CELPIP option
            $arrFieldsMapping = array(
                array(
                    'id'    => 'qf_language_english_celpip_label',
                    'label' => 'English (CELPIP)',
                    'type'  => 'label',
                    'order' => 8
                ),

                array(
                    'id'    => 'qf_language_english_celpip_score_speak',
                    'label' => 'Speak',
                    'type'  => 'combo',
                    'order' => 9
                ),

                array(
                    'id'    => 'qf_language_english_celpip_score_read',
                    'label' => 'Read',
                    'type'  => 'combo',
                    'order' => 10
                ),

                array(
                    'id'    => 'qf_language_english_celpip_score_write',
                    'label' => 'Write',
                    'type'  => 'combo',
                    'order' => 11
                ),

                array(
                    'id'    => 'qf_language_english_celpip_score_listen',
                    'label' => 'Listen',
                    'type'  => 'combo',
                    'order' => 12
                ),

                // Spouse fields
                array(
                    'id'    => 'qf_language_spouse_english_celpip_label',
                    'label' => 'English (CELPIP)',
                    'type'  => 'label',
                    'order' => 25
                ),

                array(
                    'id'    => 'qf_language_spouse_english_celpip_score_speak',
                    'label' => 'Speak',
                    'type'  => 'combo',
                    'order' => 26
                ),

                array(
                    'id'    => 'qf_language_spouse_english_celpip_score_read',
                    'label' => 'Read',
                    'type'  => 'combo',
                    'order' => 27
                ),

                array(
                    'id'    => 'qf_language_spouse_english_celpip_score_write',
                    'label' => 'Write',
                    'type'  => 'combo',
                    'order' => 28
                ),

                array(
                    'id'    => 'qf_language_spouse_english_celpip_score_listen',
                    'label' => 'Listen',
                    'type'  => 'combo',
                    'order' => 29
                ),
            );

            foreach ($arrFieldsMapping as $arrFieldInfo) {
                $db->insert(
                    'company_questionnaires_fields',
                    array(
                        'q_field_unique_id'                => $arrFieldInfo['id'],
                        'q_section_id'                     => 5,
                        'q_field_type'                     => $arrFieldInfo['type'],
                        'q_field_required'                 => 'N',
                        'q_field_show_in_prospect_profile' => 'Y',
                        'q_field_show_please_select'       => $arrFieldInfo['type'] == 'combo' ? 'Y' : 'N',
                        'q_field_use_in_search'            => $arrFieldInfo['type'] == 'combo' ? 'Y' : 'N',
                        'q_field_order'                    => $arrFieldInfo['order']
                    )
                );
                $fieldId = $db->lastInsertId('company_questionnaires_fields');

                $db->query(
                    "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
                SELECT q_id, " . $fieldId . ", '" . $arrFieldInfo['label'] . "', '" . $arrFieldInfo['label'] . "' FROM company_questionnaires;"
                );

                if ($arrFieldInfo['type'] == 'combo') {
                    for ($i = 12; $i >= 1; $i--) {
                        // Add options for this new field
                        $db->insert(
                            'company_questionnaires_fields_options',
                            array(
                                'q_field_id'               => $fieldId,
                                'q_field_option_unique_id' => 'level_' . $i,
                                'q_field_option_selected'  => 'N',
                                'q_field_option_order'     => 12 - $i
                            )
                        );
                        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

                        $db->query(
                            "INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
                    SELECT q_id, " . $fieldOptionId . ", 'Level " . $i . "', 'Y' FROM company_questionnaires;"
                        );
                    }
                }
            }

            // Sort other fields
            $arrNewOrderMapping = array(
                'qf_language_english_general_label'        => 13,
                'qf_language_english_general_score_speak'  => 14,
                'qf_language_english_general_score_read'   => 15,
                'qf_language_english_general_score_write'  => 16,
                'qf_language_english_general_score_listen' => 17,

                'qf_language_spouse_label'                      => 18,
                'qf_language_spouse_english_done'               => 19,
                'qf_language_spouse_english_ielts_scores_label' => 20,
                'qf_language_spouse_english_ielts_score_speak'  => 21,
                'qf_language_spouse_english_ielts_score_read'   => 22,
                'qf_language_spouse_english_ielts_score_write'  => 23,
                'qf_language_spouse_english_ielts_score_listen' => 24,
            );

            foreach ($arrNewOrderMapping as $fieldId => $fieldOrder) {
                $db->update(
                    'company_questionnaires_fields',
                    array('q_field_order' => $fieldOrder),
                    $db->quoteInto('q_field_unique_id = ?', $fieldId)
                );
            }

            $db->query(
                $db->quoteInto(
                    'UPDATE company_questionnaires_fields SET q_field_order = q_field_order + 10 WHERE q_field_unique_id IN (?)',
                    array(
                        'qf_language_spouse_english_general_label',
                        'qf_language_spouse_english_general_score_speak',
                        'qf_language_spouse_english_general_score_read',
                        'qf_language_spouse_english_general_score_write',
                        'qf_language_spouse_english_general_score_listen',
                        'qf_language_french_done',
                        'qf_language_french_tef_scores_label',
                        'qf_language_french_tef_score_speak',
                        'qf_language_french_tef_score_read',
                        'qf_language_french_tef_score_write',
                        'qf_language_french_tef_score_listen',
                        'qf_language_french_general_label',
                        'qf_language_french_general_score_speak',
                        'qf_language_french_general_score_read',
                        'qf_language_french_general_score_write',
                        'qf_language_french_general_score_listen',
                        'qf_language_spouse_french_done',
                        'qf_language_spouse_french_tef_scores_label',
                        'qf_language_spouse_french_tef_score_speak',
                        'qf_language_spouse_french_tef_score_read',
                        'qf_language_spouse_french_tef_score_write',
                        'qf_language_spouse_french_tef_score_listen',
                        'qf_language_spouse_french_general_label',
                        'qf_language_spouse_french_general_score_speak',
                        'qf_language_spouse_french_general_score_read',
                        'qf_language_spouse_french_general_score_write',
                        'qf_language_spouse_french_general_score_listen'
                    )
                )
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->delete(
            'company_questionnaires_fields',
            $db->quoteInto('q_field_unique_id LIKE ?', "%qf_language_english_celpip_%")
        );

        $db->delete(
            'company_questionnaires_fields',
            $db->quoteInto('q_field_unique_id LIKE ?', "%qf_language_spouse_english_celpip_%")
        );

        $arrFieldsMapping = array(
            array(
                'id'    => 'qf_language_english_done',
                'label' => 'Have you done any English language test?'
            ),

            array(
                'id'    => 'qf_language_spouse_english_done',
                'label' => 'Has your spouse/common-law partner done any English language test?'
            )
        );

        foreach ($arrFieldsMapping as $arrFieldInfo) {
            $select  = $db->select()
                ->from('company_questionnaires_fields', 'q_field_id')
                ->where('q_field_unique_id = ?', $arrFieldInfo['id']);
            $fieldId = $db->fetchOne($select);

            $db->delete(
                'company_questionnaires_fields_options',
                $db->quoteInto('q_field_id = ?', $fieldId) .
                $db->quoteInto(' AND q_field_option_unique_id = ?', 'celpip')
            );
        }
    }
}