<?php

use Phinx\Migration\AbstractMigration;

class UpdateLanguageFieldsOrder extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_use_in_search`, `q_field_order`) VALUES
        (180, 'qf_language_your_label', 5, 'label', 'N', 'Y', 'N',  'N', 1)
        "
        );

        $this->execute(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 180, 'Main Applicant', 'Main Applicant' FROM company_questionnaires;
        "
        );

        $this->execute(
            "INSERT INTO `company_questionnaires_fields` (`q_field_id`, `q_field_unique_id`, `q_section_id`, `q_field_type`, `q_field_required`, `q_field_show_in_prospect_profile`, `q_field_show_please_select`, `q_field_use_in_search`, `q_field_order`) VALUES
        (181, 'qf_language_spouse_label', 5, 'label', 'N', 'Y', 'N',  'N', 24)
        "
        );

        $this->execute(
            "INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`)
        SELECT q_id, 181, 'Spouse', 'Spouse' FROM company_questionnaires;
        "
        );

        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = q_field_order + 1 WHERE q_field_id BETWEEN 136 AND 157");

        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = q_field_order + 2 WHERE q_field_id BETWEEN 158 AND 179");
    }

    public function down()
    {
        $this->execute('DELETE FROM company_questionnaires_fields_templates WHERE q_field_id = 180;');
        $this->execute('DELETE FROM company_questionnaires_fields WHERE q_field_id = 180;');

        $this->execute('DELETE FROM company_questionnaires_fields_templates WHERE q_field_id = 181;');
        $this->execute('DELETE FROM company_questionnaires_fields WHERE q_field_id = 181;');

        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = q_field_order - 1 WHERE q_field_id BETWEEN 136 AND 157");

        $this->execute("UPDATE company_questionnaires_fields SET q_field_order = q_field_order - 2 WHERE q_field_id BETWEEN 158 AND 179");
    }
}