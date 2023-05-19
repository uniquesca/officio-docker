<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class UpdateQnrFields extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $this->execute("ALTER TABLE `company_questionnaires_fields` ADD COLUMN `q_field_show_in_qnr` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `q_field_show_in_prospect_profile`;");


        // Set new order because of the new added fields
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='50' WHERE `q_field_unique_id` = 'qf_area_of_interest';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='51' WHERE `q_field_unique_id` = 'qf_area_of_interest_other1';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='52' WHERE `q_field_unique_id` = 'qf_date_permanent_residency_obtained';");

        // Add "referred by" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_referred_by',
                'q_section_id'                     => 1,
                'q_field_type'                     => 'combo_custom',
                'q_field_required'                 => 'Y',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_order'                    => 100,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Where did you hear about us?', 'Referred by:' FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) SELECT q_id, $fieldId, 'Google', 0 FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) SELECT q_id, $fieldId, 'Website', 1 FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) SELECT q_id, $fieldId, 'Friend', 2 FROM company_questionnaires;");
        $this->execute("INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) SELECT q_id, $fieldId, 'Presentation', 3 FROM company_questionnaires;");


        // Add "Initial interview date" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_initial_interview_date',
                'q_section_id'                     => 1,
                'q_field_type'                     => 'date',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_order'                    => 120,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Date of initial interview:', 'Date of initial interview:' FROM company_questionnaires;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='10' WHERE `q_field_unique_id` = 'qf_applied_for_visa_before';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='11' WHERE `q_field_unique_id` = 'qf_visa_refused_or_cancelled';");

        // Add "'Does any applicant have any criminal convictions?" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_applicant_have_criminal_convictions',
                'q_section_id'                     => 1,
                'q_field_type'                     => 'radio',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_order'                    => 12,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Does any applicant have any criminal convictions?', 'Does any applicant have any criminal convictions?' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'yes',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'Yes', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'no',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'No', 'Y' FROM company_questionnaires;");

        // Add "Does any applicant have health or care concerns?" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_applicant_health_or_care_concerns',
                'q_section_id'                     => 1,
                'q_field_type'                     => 'radio',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_order'                    => 15,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Does any applicant have health or care concerns?', 'Does any applicant have health or care concerns?' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'yes',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'Yes', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'no',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'No', 'Y' FROM company_questionnaires;");

        // Add "Further Information" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_further_information',
                'q_section_id'                     => 1,
                'q_field_type'                     => 'textarea',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_order'                    => 130,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Further Information:', 'Further Information:' FROM company_questionnaires;");

        // Rename IELTS field label and name
        $this->execute("UPDATE `company_questionnaires_fields_templates` SET `q_field_label`='Have you taken an English test in last 36 months?' WHERE  `q_field_id`=33 AND q_field_label = 'Have you taken an IELTS test in last 36 months?';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_language_have_taken_test_on_english' WHERE  `q_field_id`=33 AND `q_section_id`=6;");


        $this->execute("UPDATE `company_questionnaires_fields_templates` SET `q_field_label`='Has your partner/spouse taken an English test in last 36 months?' WHERE  `q_field_id`=58 AND q_field_label = 'Has your partner/spouse taken an IELTS test in last 36 months?';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_language_spouse_have_taken_test_on_english' WHERE  `q_field_id`=58 AND `q_section_id`=6;");

        // Add "Language test type" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_language_type_of_test',
                'q_section_id'                     => 6,
                'q_field_type'                     => 'combo',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_order'                    => 3,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Test Type:', 'Test Type:' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'ielts',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0,
            )
        );
        $fieldOptionIELTSId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionIELTSId, 'IELTS', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'pte',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'PTE', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'toefl',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 2,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'TOEFL iBT', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'cae',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 3,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'CAE', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'oet',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 4,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'OET', 'Y' FROM company_questionnaires;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='4' WHERE `q_field_unique_id` = 'qf_language_date_of_test';");

        // Automatically select "IELTS" in the "test type" compo if "Have you taken an English test in last 36 months?" is "Yes"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`) SELECT prospect_id, $fieldId, $fieldOptionIELTSId FROM company_prospects_data WHERE q_field_id = 33 AND q_value = '29';");

        // Add "Language test type" field (for spouse)
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_language_spouse_type_of_test',
                'q_section_id'                     => 6,
                'q_field_type'                     => 'combo',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_order'                    => 11,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Test Type:', 'Test Type:' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'ielts',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0,
            )
        );
        $fieldOptionIELTSId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionIELTSId, 'IELTS', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'pte',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'PTE', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'toefl',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 2,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'TOEFL iBT', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'cae',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 3,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'CAE', 'Y' FROM company_questionnaires;");
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $fieldId,
                'q_field_option_unique_id' => 'oet',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 4,
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'OET', 'Y' FROM company_questionnaires;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='12' WHERE `q_field_unique_id` = 'qf_language_spouse_date_of_test';");

        // Automatically select "IELTS" in the "test type" compo if "Has your partner/spouse taken an English test in last 36 months?" is "Yes"
        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`) SELECT prospect_id, $fieldId, $fieldOptionIELTSId FROM company_prospects_data WHERE q_field_id = 58 AND q_value = '46';");

        // Change combo field type to the text one
        $arrUpdateFields = array(
            'qf_language_listening_score',
            'qf_language_reading_score',
            'qf_language_writing_score',
            'qf_language_speaking_score',
            'qf_language_spouse_listening_score',
            'qf_language_spouse_reading_score',
            'qf_language_spouse_writing_score',
            'qf_language_spouse_speaking_score',
            'qf_language_overall_score',
            'qf_language_spouse_overall_score'
        );
        $this->execute($db->quoteInto("UPDATE `company_questionnaires_fields` SET `q_field_type`='textfield' WHERE  `q_field_unique_id` IN (?);", $arrUpdateFields));

        // Change all saved options ids to their readable values
        $select = $db->select()
            ->from(array('d' => 'company_prospects_data'))
            ->joinInner(array('f' => 'company_questionnaires_fields'), 'f.q_field_id = d.q_field_id', '')
            ->joinLeft(array('o' => 'company_questionnaires_fields_options'), 'o.q_field_option_id = d.q_value', 'q_field_option_unique_id')
            ->where('f.q_field_unique_id IN (?)', $arrUpdateFields)
            ->where('d.q_value != ?', '');

        $arrDataForConversion = $db->fetchAll($select);
        foreach ($arrDataForConversion as $arrRowForConversion) {
            $db->update(
                'company_prospects_data',
                array('q_value' => $arrRowForConversion['q_field_option_unique_id']),
                $db->quoteInto('prospect_id = ?', $arrRowForConversion['prospect_id'], 'INT') . ' AND ' . $db->quoteInto('q_field_id = ?', $arrRowForConversion['q_field_id'], 'INT')
            );
        }


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        // Change back all saved readable values to options ids
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $arrUpdateFields = array(
            'qf_language_listening_score',
            'qf_language_reading_score',
            'qf_language_writing_score',
            'qf_language_speaking_score',
            'qf_language_spouse_listening_score',
            'qf_language_spouse_reading_score',
            'qf_language_spouse_writing_score',
            'qf_language_spouse_speaking_score',
            'qf_language_overall_score',
            'qf_language_spouse_overall_score'
        );

        $select = $db->select()
            ->from(array('d' => 'company_prospects_data'))
            ->joinInner(array('f' => 'company_questionnaires_fields'), 'f.q_field_id = d.q_field_id', '')
            ->joinLeft(array('o' => 'company_questionnaires_fields_options'), 'o.q_field_id = d.q_field_id AND o.q_field_option_unique_id = d.q_value', 'q_field_option_id')
            ->where('f.q_field_unique_id IN (?)', $arrUpdateFields)
            ->where('d.q_value != ?', '');

        $arrDataForConversion = $db->fetchAll($select);
        foreach ($arrDataForConversion as $arrRowForConversion) {
            $db->update(
                'company_prospects_data',
                array('q_value' => $arrRowForConversion['q_field_option_id']),
                $db->quoteInto('prospect_id = ?', $arrRowForConversion['prospect_id'], 'INT') . ' AND ' . $db->quoteInto('q_field_id = ?', $arrRowForConversion['q_field_id'], 'INT')
            );
        }

        $this->execute($db->quoteInto("UPDATE `company_questionnaires_fields` SET `q_field_type`='combo' WHERE  `q_field_unique_id` IN (?);", $arrUpdateFields));

        $this->execute("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id IN ('qf_language_spouse_type_of_test', 'qf_language_type_of_test', 'qf_further_information', 'qf_applicant_health_or_care_concerns', 'qf_applicant_have_criminal_convictions', 'qf_initial_interview_date', 'qf_referred_by')");

        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_language_spouse_have_taken_ielts' WHERE `q_field_unique_id` = 'qf_language_spouse_have_taken_test_on_english';");
        $this->execute("UPDATE `company_questionnaires_fields_templates` SET `q_field_label`='Has your partner/spouse taken an IELTS test in last 36 months?' WHERE  `q_field_id`=58 AND q_field_label = 'Has your partner/spouse taken an English test in last 36 months?';");

        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_language_have_taken_ielts' WHERE `q_field_unique_id` = 'qf_language_have_taken_test_on_english';");
        $this->execute("UPDATE `company_questionnaires_fields_templates` SET `q_field_label`='Have you taken an IELTS test in last 36 months?' WHERE  `q_field_id`=33 AND q_field_label = 'Have you taken an English test in last 36 months?';");

        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='18' WHERE `q_field_unique_id` = 'qf_area_of_interest';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='19' WHERE `q_field_unique_id` = 'qf_area_of_interest_other1';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='20' WHERE `q_field_unique_id` = 'qf_date_permanent_residency_obtained';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='3' WHERE `q_field_unique_id` = 'qf_language_date_of_test';");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`='11' WHERE `q_field_unique_id` = 'qf_language_spouse_date_of_test';");

        $this->execute("ALTER TABLE `company_questionnaires_fields` DROP COLUMN `q_field_show_in_qnr`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}