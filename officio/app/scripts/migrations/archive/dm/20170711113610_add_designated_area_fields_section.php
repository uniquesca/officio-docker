<?php

use Phinx\Migration\AbstractMigration;

class AddDesignatedAreaFieldsSection extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        //Fix bug when delete section - change "No Action" into "Cascade"
        $db->query("ALTER TABLE `company_questionnaires_fields` DROP FOREIGN KEY `FK_company_questionnaires_fields_1`;");
        $db->query(
            "ALTER TABLE `company_questionnaires_fields`
	        ADD CONSTRAINT `FK_company_questionnaires_fields_1` FOREIGN KEY (`q_section_id`) REFERENCES `company_questionnaires_sections` (`q_section_id`) ON UPDATE CASCADE ON DELETE CASCADE;
	    "
        );

        //Insert "Institution Name" field and related
        $db->query("UPDATE company_questionnaires_fields SET q_field_order = q_field_order + 1 WHERE q_section_id = 5 && q_field_order > 3;");
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_education_institution_name',
                'q_section_id'                     => 5,
                'q_field_type'                     => 'textfield',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 4,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Name of Institution:', 'Name of Institution:' 
            FROM company_questionnaires;
        "
        );

        //Insert "Sponsorhsip by Eligible Relative in Designated Area" section and related
        $db->query("UPDATE company_questionnaires_sections SET q_section_order = q_section_order + 1 WHERE q_section_order = 11 && q_section_step = 2;");
        $db->query(
            " INSERT INTO `company_questionnaires_sections` (`q_section_id`, `q_section_step`, `q_section_order`) VALUES
            (13, 2, 11);
        "
        );
        $db->query(
            "INSERT INTO `company_questionnaires_sections_templates` (`q_id`, `q_section_id`, `q_section_template_name`, `q_section_prospect_profile`)
            SELECT q_id, 13, 'SPONSORSHIP BY ELIGIBLE RELATIVE IN DESIGNATED AREA', 'SPONSORSHIP BY ELIGIBLE RELATIVE IN DESIGNATED AREA' FROM company_questionnaires;
        "
        );

        //Relative designated area radio
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_relative_designated_area',
                'q_section_id'                     => 13,
                'q_field_type'                     => 'radio',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 0,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Do you have a relative in designated area who is willing to sponsor you?', 'Do you have a relative in designated area who is willing to sponsor you?' 
            FROM company_questionnaires;
        "
        );
        $db->query(
            "INSERT INTO `company_questionnaires_fields_options` (`q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
            ($fieldId, 'yes', 'N', 0);           
        "
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $db->query("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'Yes', 'Y' FROM company_questionnaires;");
        $db->query(
            "INSERT INTO `company_questionnaires_fields_options` (`q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
            ($fieldId, 'no', 'N', 1);           
        "
        );
        $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
        $db->query("INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) SELECT q_id, $fieldOptionId, 'No', 'Y' FROM company_questionnaires;");

        //Nature of relationship combo
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_relationship_nature',
                'q_section_id'                     => 13,
                'q_field_type'                     => 'combo',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'Y',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 1,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Nature of relationship:', 'Nature of relationship:' 
            FROM company_questionnaires;
        "
        );

        $arrComboOptions = array(
            array(
                'q_field_option_unique_id' => 'child',
                'q_field_option_template'  => 'Child'
            ),

            array(
                'q_field_option_unique_id' => 'parent',
                'q_field_option_template'  => 'Parent'
            ),

            array(
                'q_field_option_unique_id' => 'brother',
                'q_field_option_template'  => 'Brother'
            ),

            array(
                'q_field_option_unique_id' => 'sister',
                'q_field_option_template'  => 'Sister'
            ),

            array(
                'q_field_option_unique_id' => 'niece',
                'q_field_option_template'  => 'Niece'
            ),

            array(
                'q_field_option_unique_id' => 'nephew',
                'q_field_option_template'  => 'Nephew'
            ),

            array(
                'q_field_option_unique_id' => 'aunt',
                'q_field_option_template'  => 'Aunt'
            ),

            array(
                'q_field_option_unique_id' => 'uncle',
                'q_field_option_template'  => 'Uncle'
            ),

            array(
                'q_field_option_unique_id' => 'grandparent',
                'q_field_option_template'  => 'Grandparent'
            ),

            array(
                'q_field_option_unique_id' => 'first_cousin',
                'q_field_option_template'  => 'First cousin'
            ),
        );

        foreach ($arrComboOptions as $key => $option) {
            $uniqueId       = $option['q_field_option_unique_id'];
            $optionTemplate = $option['q_field_option_template'];
            $db->query(
                "INSERT INTO `company_questionnaires_fields_options` (`q_field_id`, `q_field_option_unique_id`, `q_field_option_selected`, `q_field_option_order`) VALUES
                ($fieldId, '$uniqueId', 'N', $key);           
            "
            );
            $fieldOptionId = $db->lastInsertId('company_questionnaires_fields_options');
            $db->query(
                "INSERT INTO `company_questionnaires_fields_options_templates` (`q_id`, `q_field_option_id`, `q_field_option_label`, `q_field_option_visible`) 
                SELECT q_id, $fieldOptionId, '$optionTemplate', 'Y' FROM company_questionnaires;
            "
            );
        }

        //Postcode where your relative lives
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_relative_postcode',
                'q_section_id'                     => 13,
                'q_field_type'                     => 'textfield',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_please_select'       => 'N',
                'q_field_use_in_search'            => 'Y',
                'q_field_order'                    => 2,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');
        $db->query(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
            SELECT q_id, $fieldId, 'Postcode where your relative lives:', 'Postcode where your relative lives:' 
            FROM company_questionnaires;
        "
        );

        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        //Delete "Institution Name" field and related
        $db->query("DELETE FROM company_questionnaires_fields_templates WHERE q_field_id = (SELECT q_field_id FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_education_institution_name');");
        $db->query("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_education_institution_name';");
        $db->query("UPDATE company_questionnaires_fields SET q_field_order = q_field_order - 1 WHERE q_field_order > 3");

        //Delete "Sponsorship by Eligible Relative in Designated Area" section and related
        $db->query("DELETE FROM company_questionnaires_sections WHERE q_section_id = 13 && q_section_step = 2");
        $db->query("UPDATE company_questionnaires_sections SET q_section_order = q_section_order - 1 WHERE q_section_id = 11 && q_section_step = 2;");

        $db->commit();
    }
}