<?php

use Officio\Migration\AbstractMigration;

class UpdateNocOptions extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "UPDATE company_questionnaires_fields_options_templates
            SET q_field_option_label = 'NOC TEER 00'
            where q_field_option_id = (
                SELECT q_field_option_id
                from company_questionnaires_fields_options
                where q_field_option_unique_id = 'noc_00'
            ) AND q_field_option_label = 'NOC 00';

            UPDATE company_questionnaires_fields_options_templates
            SET q_field_option_label = 'NOC TEER 0, 1, 2, or 3'
            where q_field_option_id = (
                SELECT q_field_option_id
                from company_questionnaires_fields_options
                where q_field_option_unique_id = 'noc_0_a_b'
            ) AND q_field_option_label = 'NOC 0, A, B';

            UPDATE company_questionnaires_fields_templates 
            SET q_field_label = 'NOC TEER/Code', q_field_prospect_profile_label = 'NOC TEER/Code'
            WHERE q_field_id IN (
                SELECT q_field_id FROM company_questionnaires_fields where q_field_unique_id IN ('qf_job_noc', 'qf_job_spouse_noc')
            );");
    }

    public function down()
    {
        $this->execute(
            "UPDATE company_questionnaires_fields_options_templates
            SET q_field_option_label = 'NOC 00'
            where q_field_option_id = (
                SELECT q_field_option_id
                from company_questionnaires_fields_options
                where q_field_option_unique_id = 'noc_00'
            ) AND q_field_option_label = 'NOC TEER 00';

            UPDATE company_questionnaires_fields_options_templates
            SET q_field_option_label = 'NOC 0, A, B'
            where q_field_option_id = (
                SELECT q_field_option_id
                from company_questionnaires_fields_options
                where q_field_option_unique_id = 'noc_0_a_b'
            ) AND q_field_option_label = 'NOC TEER 0, 1, 2, or 3';

            UPDATE company_questionnaires_fields_templates 
            SET q_field_label = 'NOC Code', q_field_prospect_profile_label = 'NOC Code'
            WHERE q_field_id IN (
                SELECT q_field_id FROM company_questionnaires_fields where q_field_unique_id IN ('qf_job_noc', 'qf_job_spouse_noc')
            );");
            
    }
}
