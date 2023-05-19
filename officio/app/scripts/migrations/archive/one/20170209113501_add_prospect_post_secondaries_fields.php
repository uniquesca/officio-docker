<?php

use Phinx\Migration\AbstractMigration;

class AddProspectPostSecondariesFields extends AbstractMigration
{
    public function up()
    {
        // Update foreign keys
        $this->execute("ALTER TABLE `company_questionnaires_fields_templates` DROP FOREIGN KEY `FK_company_questionnaires_fields_templates_1`, DROP FOREIGN KEY `FK_company_questionnaires_fields_templates_2`;");
        $this->execute("ALTER TABLE `company_questionnaires_fields_templates` ADD CONSTRAINT `FK_company_questionnaires_fields_templates_1` FOREIGN KEY (`q_id`) REFERENCES `company_questionnaires` (`q_id`) ON UPDATE CASCADE ON DELETE CASCADE, ADD CONSTRAINT `FK_company_questionnaires_fields_templates_2` FOREIGN KEY (`q_field_id`) REFERENCES `company_questionnaires_fields` (`q_field_id`) ON UPDATE CASCADE ON DELETE CASCADE;");


        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select  = $db->select()
            ->from('company_questionnaires_fields', 'q_field_id')
            ->where('q_field_unique_id = ?', 'qf_study_previously_studied');
        $fieldId = $db->fetchOne($select);

        $db->update(
            'company_questionnaires_fields_templates',
            array(
                'q_field_label'                  => 'Have you completed any post-secondary studies in Canada?',
                'q_field_prospect_profile_label' => 'Post-secondaries in Canada:',
            ),
            $db->quoteInto('q_field_id = ?', $fieldId, 'INT')
        );

        // Create new field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_studied_in_canada_period',
                'q_section_id'                     => 4,
                'q_field_type'                     => 'combo',
                'q_field_required'                 => 'Y',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 14,
            )
        );
        $newFieldId = $db->lastInsertId('company_questionnaires_fields');

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, " . $newFieldId . ", 'Post-secondary studies period:', 'Post-secondary studies period:' FROM company_questionnaires;");


        // Add options for this new field
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => '1_year',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", '1 year', 'Y' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => '2_years',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1
            )
        );
        $option2Years = $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", '2 years', 'Y' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => '3_years',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 2
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", '3 years or more', 'Y' FROM company_questionnaires;");



        // Set new combo if Yes was selected
        $select = $db->select()
            ->from('company_questionnaires_fields_options', 'q_field_option_id')
            ->where('q_field_id = ?', $fieldId, 'INT')
            ->where('q_field_option_unique_id = ?', 'yes');

        $yesOptionId = $db->fetchOne($select);

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, " . $newFieldId . ", " . $option2Years . " FROM company_prospects_data WHERE q_field_id = " . $fieldId . " AND q_value = " . $yesOptionId . ";");



        // **************
        // Now for spouse
        // **************
        $select  = $db->select()
            ->from('company_questionnaires_fields', 'q_field_id')
            ->where('q_field_unique_id = ?', 'qf_education_spouse_previously_studied');
        $fieldId = $db->fetchOne($select);

        $db->update(
            'company_questionnaires_fields_templates',
            array(
                'q_field_label'                  => 'Has your spouse or common-law spouse completed any post-secondary studies in Canada?',
                'q_field_prospect_profile_label' => 'Post-secondaries in Canada:',
            ),
            $db->quoteInto('q_field_id = ?', $fieldId, 'INT')
        );

        // Create new field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_spouse_studied_in_canada_period',
                'q_section_id'                     => 4,
                'q_field_type'                     => 'combo',
                'q_field_required'                 => 'Y',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 15,
            )
        );
        $newFieldId = $db->lastInsertId('company_questionnaires_fields');

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, " . $newFieldId . ", 'Spouse or common-law spouse post-secondary studies period:', 'Post-secondary studies period:' FROM company_questionnaires;");


        // Add options for this new field
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => '1_year',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", '1 year', 'Y' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => '2_years',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1
            )
        );
        $option2Years = $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", '2 years', 'Y' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => '3_years',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 2
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", '3 years or more', 'Y' FROM company_questionnaires;");



        // Set new combo if Yes was selected
        $select = $db->select()
            ->from('company_questionnaires_fields_options', 'q_field_option_id')
            ->where('q_field_id = ?', $fieldId, 'INT')
            ->where('q_field_option_unique_id = ?', 'yes');

        $yesOptionId = $db->fetchOne($select);

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, " . $newFieldId . ", " . $option2Years . " FROM company_prospects_data WHERE q_field_id = " . $fieldId . " AND q_value = " . $yesOptionId . ";");

        // Update order for previously created fields
        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 16 WHERE q_field_unique_id = 'qf_education_bachelor_degree_name'");
        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 17 WHERE q_field_unique_id = 'qf_education_spouse_bachelor_degree_name'");
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->delete(
            'company_questionnaires_fields',
            $db->quoteInto('q_field_unique_id IN (?)', array('qf_education_studied_in_canada_period', 'qf_education_spouse_studied_in_canada_period'))
        );

        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 14 WHERE q_field_unique_id = 'qf_education_bachelor_degree_name'");
        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 15 WHERE q_field_unique_id = 'qf_education_spouse_bachelor_degree_name'");
    }
}