<?php

use Phinx\Migration\AbstractMigration;

class UpdateProspectSearchColumns extends AbstractMigration
{
    private function getFields($booToHide = true)
    {
        if ($booToHide) {
            $arrFields = array(
                'qf_salutation',
                'qf_age', 'qf_spouse_age',
                'qf_children_count', 'qf_children_age_1', 'qf_children_age_2', 'qf_children_age_3', 'qf_children_age_4', 'qf_children_age_5', 'qf_children_age_6',
                'qf_education_diploma_name', 'qf_education_spouse_diploma_name',
                'qf_education_area_of_studies', 'qf_education_spouse_area_of_studies',
                'qf_education_institute_type', 'qf_education_spouse_institute_type',
                'qf_study_previously_studied', 'qf_education_spouse_previously_studied',
                'qf_education_bachelor_degree_name', 'qf_education_spouse_bachelor_degree_name',
                'qf_work_leave_employment',
                'qf_family_relative_wish_to_sponsor', 'qf_family_sponsor_age', 'qf_family_employment_status', 'qf_family_sponsor_financially_responsible', 'qf_family_sponsor_income', 'qf_family_currently_fulltime_student', 'qf_family_been_fulltime_student',
                'qf_cat_have_experience', 'qf_cat_managerial_experience', 'qf_cat_staff_number', 'qf_cat_own_this_business', 'qf_cat_percentage_of_ownership', 'qf_cat_annual_sales', 'qf_cat_annual_net_income', 'qf_cat_net_assets',
                'qf_visit_previously_visited', 'qf_visit_previously_applied', 'qf_visit_preferred_destination', 'qf_visit_previously_submitted_express_entry',

                'qf_language_english_done',
                'qf_language_english_ielts_score_speak',
                'qf_language_english_ielts_score_read',
                'qf_language_english_ielts_score_write',
                'qf_language_english_ielts_score_listen',
                'qf_language_english_celpip_score_speak',
                'qf_language_english_celpip_score_read',
                'qf_language_english_celpip_score_write',
                'qf_language_english_celpip_score_listen',
                'qf_language_english_general_score_speak',
                'qf_language_english_general_score_read',
                'qf_language_english_general_score_write',
                'qf_language_english_general_score_listen',
                'qf_language_spouse_english_done',
                'qf_language_spouse_english_ielts_score_speak',
                'qf_language_spouse_english_ielts_score_read',
                'qf_language_spouse_english_ielts_score_write',
                'qf_language_spouse_english_ielts_score_listen',
                'qf_language_spouse_english_celpip_score_speak',
                'qf_language_spouse_english_celpip_score_read',
                'qf_language_spouse_english_celpip_score_write',
                'qf_language_spouse_english_celpip_score_listen',
                'qf_language_spouse_english_general_score_speak',
                'qf_language_spouse_english_general_score_read',
                'qf_language_spouse_english_general_score_write',
                'qf_language_spouse_english_general_score_listen',
                'qf_language_french_done',
                'qf_language_french_tef_score_speak',
                'qf_language_french_tef_score_read',
                'qf_language_french_tef_score_write',
                'qf_language_french_tef_score_listen',
                'qf_language_french_general_score_speak',
                'qf_language_french_general_score_read',
                'qf_language_french_general_score_write',
                'qf_language_french_general_score_listen',
                'qf_language_spouse_french_done',
                'qf_language_spouse_french_tef_score_speak',
                'qf_language_spouse_french_tef_score_read',
                'qf_language_spouse_french_tef_score_write',
                'qf_language_spouse_french_tef_score_listen',
                'qf_language_spouse_french_general_score_speak',
                'qf_language_spouse_french_general_score_read',
                'qf_language_spouse_french_general_score_write',
                'qf_language_spouse_french_general_score_listen',
            );
        } else {
            $arrFields = array(
                'qf_job_title',
                'qf_job_noc',
                'qf_job_duration',
                'qf_job_location',
                'qf_job_province',
                'qf_job_presently_working',
                'qf_job_qualified_for_social_security',
                'qf_job_employment_type'
            );
        }

        return $arrFields;
    }

    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->update(
            'company_questionnaires_fields',
            array('q_field_use_in_search' => 'N'),
            $db->quoteInto('q_field_unique_id IN (?)', $this->getFields())
        );

        $db->update(
            'company_questionnaires_fields',
            array('q_field_use_in_search' => 'Y'),
            $db->quoteInto('q_field_unique_id IN (?)', $this->getFields(false))
        );
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->update(
            'company_questionnaires_fields',
            array('q_field_use_in_search' => 'Y'),
            $db->quoteInto('q_field_unique_id IN (?)', $this->getFields())
        );
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='Y' WHERE  q_field_unique_id LIKE 'qf_language_%' AND q_field_type != 'label'");

        $db->update(
            'company_questionnaires_fields',
            array('q_field_use_in_search' => 'N'),
            $db->quoteInto('q_field_unique_id IN (?)', $this->getFields(false))
        );
    }
}