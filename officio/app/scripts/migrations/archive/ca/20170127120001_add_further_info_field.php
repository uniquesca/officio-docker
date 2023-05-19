<?php

use Phinx\Migration\AbstractMigration;

class AddFurtherInfoField extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        // Add "Further Information" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id' => 'qf_further_information',
                'q_section_id' => 1,
                'q_field_type' => 'textarea',
                'q_field_required' => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select' => 'N',
                'q_field_use_in_search' => 'Y',
                'q_field_order' => 130,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $this->execute(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) SELECT q_id, $fieldId, 'Further Information:', 'Further Information:' FROM company_questionnaires;"
        );
    }

    public function down()
    {
        $this->execute("DELETE FROM company_questionnaires_fields_templates WHERE q_field_id = (SELECT q_field_id FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_further_information');");
        $this->execute("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_further_information';");
    }
}