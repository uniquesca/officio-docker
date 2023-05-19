<?php

use Phinx\Migration\AbstractMigration;

class AddProspectNocField extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select  = $db->select()
            ->from('company_questionnaires_fields', 'q_field_id')
            ->where('q_field_unique_id = ?', 'qf_work_offer_of_employment');
        $fieldId = $db->fetchOne($select);

        $db->update(
            'company_questionnaires_fields_templates',
            array(
                'q_field_label'                  => 'Arranged employment:',
                'q_field_prospect_profile_label' => 'Arranged employment:',
            ),
            $db->quoteInto('q_field_id = ?', $fieldId, 'INT')
        );

        // Create new field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_work_noc',
                'q_section_id'                     => 6,
                'q_field_type'                     => 'combo',
                'q_field_required'                 => 'Y',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 7,
            )
        );
        $newFieldId = $db->lastInsertId('company_questionnaires_fields');

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, " . $newFieldId . ", 'NOC:', 'NOC:' FROM company_questionnaires;");


        // Add options for this new field
        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => 'noc_00',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 0
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", 'NOC 00', 'Y' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => 'noc_0_a_b',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 1
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", 'NOC 0, A, B', 'Y' FROM company_questionnaires;");

        $db->insert(
            'company_questionnaires_fields_options',
            array(
                'q_field_id'               => $newFieldId,
                'q_field_option_unique_id' => 'noc_not_sure',
                'q_field_option_selected'  => 'N',
                'q_field_option_order'     => 2
            )
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');

        $this->execute("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`)
        SELECT q_id, " . $fieldOptionId . ", 'Not sure', 'Y' FROM company_questionnaires;");


        // Set new combo if Yes was selected
        $select = $db->select()
            ->from('company_questionnaires_fields_options', 'q_field_option_id')
            ->where('q_field_id = ?', $fieldId, 'INT')
            ->where('q_field_option_unique_id = ?', 'yes');

        $yesOptionId = $db->fetchOne($select);

        $this->execute("INSERT INTO `company_prospects_data` (`prospect_id`, `q_field_id`, `q_value`)
        SELECT prospect_id, " . $newFieldId . ", " . $fieldOptionId . " FROM company_prospects_data WHERE q_field_id = " . $fieldId . " AND q_value = " . $yesOptionId . ";");


        // Update order for previously created fields
        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 8 WHERE q_field_unique_id = 'qf_certificate_of_qualification'");
        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 9 WHERE q_field_unique_id = 'qf_nomination_certificate'");
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->delete(
            'company_questionnaires_fields',
            $db->quoteInto('q_field_unique_id IN (?)', array('qf_work_noc'))
        );

        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 7 WHERE q_field_unique_id = 'qf_certificate_of_qualification'");
        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = 8 WHERE q_field_unique_id = 'qf_nomination_certificate'");
    }
}