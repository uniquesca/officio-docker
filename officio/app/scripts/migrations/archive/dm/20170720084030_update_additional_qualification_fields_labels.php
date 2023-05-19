<?php

use Phinx\Migration\AbstractMigration;

class UpdateAdditionalQualificationFieldsLabels extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $select      = $db->select()
            ->from('company_questionnaires_fields', 'q_field_id')
            ->where("q_field_unique_id IN ('qf_education_additional_qualification', 'qf_education_spouse_additional_qualification')");
        $checkboxIds = $db->fetchCol($select);

        foreach ($checkboxIds as $checkboxId) {
            $db->query("UPDATE company_questionnaires_fields_templates SET q_field_label = 'I have additional qualifications:', q_field_prospect_profile_label = 'I have additional qualifications:' WHERE q_field_id = $checkboxId;");
        }

        $select     = $db->select()
            ->from('company_questionnaires_fields', 'q_field_id')
            ->where("q_field_unique_id IN ('qf_education_additional_qualification_list', 'qf_education_spouse_additional_qualification_list')");
        $texareaIds = $db->fetchCol($select);

        foreach ($texareaIds as $texareaId) {
            $db->query("UPDATE company_questionnaires_fields_templates SET q_field_label = 'Please list additional qualifications:', q_field_prospect_profile_label = 'Please list additional qualifications:' WHERE q_field_id = $texareaId;");
        }

        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $select      = $db->select()
            ->from('company_questionnaires_fields', 'q_field_id')
            ->where("q_field_unique_id IN ('qf_education_additional_qualification', 'qf_education_spouse_additional_qualification')");
        $checkboxIds = $db->fetchCol($select);

        foreach ($checkboxIds as $checkboxId) {
            $db->query("UPDATE company_questionnaires_fields_templates SET q_field_label = 'Additional qualification:', q_field_prospect_profile_label = 'Additional qualification:' WHERE q_field_id = $checkboxId;");
        }

        $select     = $db->select()
            ->from('company_questionnaires_fields', 'q_field_id')
            ->where("q_field_unique_id IN ('qf_education_additional_qualification_list', 'qf_education_spouse_additional_qualification_list')");
        $texareaIds = $db->fetchCol($select);

        foreach ($texareaIds as $texareaId) {
            $db->query("UPDATE company_questionnaires_fields_templates SET q_field_label = 'Additional qualification list:', q_field_prospect_profile_label = 'Additional qualification list:' WHERE q_field_id = $texareaId;");
        }

        $db->commit();
    }
}