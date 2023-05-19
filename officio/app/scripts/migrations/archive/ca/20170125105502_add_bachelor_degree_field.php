<?php

use Phinx\Migration\AbstractMigration;

class AddBachelorDegreeField extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=4 WHERE  `q_field_unique_id`='qf_education_diploma_name' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=5 WHERE  `q_field_unique_id`='qf_education_spouse_diploma_name' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=6 WHERE  `q_field_unique_id`='qf_education_area_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=7 WHERE  `q_field_unique_id`='qf_education_spouse_area_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=8 WHERE  `q_field_unique_id`='qf_education_country_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=9 WHERE  `q_field_unique_id`='qf_education_spouse_country_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=10 WHERE `q_field_unique_id`='qf_education_institute_type' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=11 WHERE `q_field_unique_id`='qf_education_spouse_institute_type' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=12 WHERE `q_field_unique_id`='qf_study_previously_studied' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=13 WHERE `q_field_unique_id`='qf_education_spouse_previously_studied' AND `q_section_id`=4;");


        $this->execute(
            "INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_use_in_search`, `q_field_order`) VALUES
                      (182, 'qf_education_bachelor_degree_name', 4, 'textfield', 'N', 'Y', 'N',  'Y', 14)"
        );
        $this->execute(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
                      SELECT q_id, 182, 'Name of bachelor\'s degree', 'Name of bachelor\'s degree' FROM company_questionnaires;"
        );
        $this->execute(
            "INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_use_in_search`, `q_field_order`) VALUES
                      (183, 'qf_education_spouse_bachelor_degree_name', 4, 'textfield', 'N', 'Y', 'N',  'Y', 15)"
        );
        $this->execute(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
                      SELECT q_id, 183, 'Name of spouse\'s bachelor\'s degree', 'Name of spouse\'s bachelor\'s degree' FROM company_questionnaires;"
        );
    }

    public function down()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=6 WHERE  `q_field_unique_id`='qf_education_diploma_name' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=7 WHERE  `q_field_unique_id`='qf_education_spouse_diploma_name' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=8 WHERE  `q_field_unique_id`='qf_education_area_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=9 WHERE  `q_field_unique_id`='qf_education_spouse_area_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=10 WHERE `q_field_unique_id`='qf_education_country_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=10 WHERE `q_field_unique_id`='qf_education_spouse_country_of_studies' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=11 WHERE `q_field_unique_id`='qf_education_institute_type' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=12 WHERE `q_field_unique_id`='qf_education_spouse_institute_type' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=13 WHERE `q_field_unique_id`='qf_study_previously_studied' AND `q_section_id`=4;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=14 WHERE `q_field_unique_id`='qf_education_spouse_previously_studied' AND `q_section_id`=4;");


        $this->execute("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id='qf_education_bachelor_degree_name';");
        $this->execute("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id='qf_education_spouse_bachelor_degree_name';");
    }
}