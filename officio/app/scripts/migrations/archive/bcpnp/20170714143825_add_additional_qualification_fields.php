<?php

use Phinx\Migration\AbstractMigration;

class AddAdditionalQualificationFields extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $db->query("UPDATE company_questionnaires_fields SET q_field_order = q_field_order + 2 WHERE q_section_id = 5 && q_field_order > 9;");
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_additional_qualification',
                'q_section_id'                     => 5,
                'q_field_type'                     => 'checkbox',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 10,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Additional qualification:', 'Additional qualification:' 
            FROM company_questionnaires;
        ");

        $db->query("INSERT INTO `company_questionnaires_fields_options` (`q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
            ($fieldId, 'yes', 'N', 0);           
        ");
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $db->query("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) 
            SELECT q_id, $fieldOptionId, '', 'Y' FROM company_questionnaires;
        ");


        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_additional_qualification_list',
                'q_section_id'                     => 5,
                'q_field_type'                     => 'textarea',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 11,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Additional qualification list:', 'Additional qualification list:' 
            FROM company_questionnaires;
        ");

        // Add spouse's "Additional qualification" fields
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_spouse_additional_qualification',
                'q_section_id'                     => 5,
                'q_field_type'                     => 'checkbox',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 18,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Additional qualification:', 'Additional qualification:' 
            FROM company_questionnaires;
        ");

        $db->query("INSERT INTO `company_questionnaires_fields_options` (`q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
            ($fieldId, 'yes', 'N', 0);           
        ");
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $db->query("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) 
            SELECT q_id, $fieldOptionId, '', 'Y' FROM company_questionnaires;
        ");


        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_spouse_additional_qualification_list',
                'q_section_id'                     => 5,
                'q_field_type'                     => 'textarea',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 19,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Additional qualification list:', 'Additional qualification list:' 
            FROM company_questionnaires;
        ");

        //Insert "Institution Name" field and related FOR SPOUSE
        $db->query("UPDATE company_questionnaires_fields SET q_field_order = q_field_order + 1 WHERE q_section_id = 5 && q_field_order > 14;");
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_spouse_institution_name',
                'q_section_id'                     => 5,
                'q_field_type'                     => 'textfield',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 15,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Name of Institution:', 'Name of Institution:' 
            FROM company_questionnaires;
        ");


        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $db->query("UPDATE company_questionnaires_fields SET q_field_order = q_field_order - 1 WHERE q_section_id = 5 && q_field_order > 14;");
        $db->query("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_education_spouse_institution_name';");

        $db->query("UPDATE company_questionnaires_fields SET q_field_order = q_field_order - 2 WHERE q_section_id = 5 && q_field_order > 9;");
        $db->query("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id IN('qf_education_additional_qualification', 'qf_education_additional_qualification_list', 'qf_education_spouse_additional_qualification', 'qf_education_spouse_additional_qualification_list');");



        $db->commit();
    }
}