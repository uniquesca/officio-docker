<?php

use Phinx\Migration\AbstractMigration;

class FirstSectionReorderFields extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $uniqueIds = array(
            'qf_age',
            'qf_country_of_citizenship',
            'qf_country_of_residence',
            'qf_email',
            'qf_email_confirmation',
            'qf_phone',
            'qf_current_address_visa_type',
            'qf_applied_for_visa_before',
            'qf_visa_refused_or_cancelled',
            'qf_marital_status',
            'qf_applicant_have_criminal_convictions',
            'qf_applicant_health_or_care_concerns'
        );

        foreach ($uniqueIds as $key => $uniqueId) {
            $db->query("UPDATE company_questionnaires_fields SET q_field_order = q_field_order + $key  WHERE  q_field_unique_id = '$uniqueId' && q_section_id = 1 && q_field_order = 3;");
        }


        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $uniqueIds = array(
            'qf_age',
            'qf_country_of_citizenship',
            'qf_country_of_residence',
            'qf_email',
            'qf_email_confirmation',
            'qf_phone',
            'qf_current_address_visa_type',
            'qf_applied_for_visa_before',
            'qf_visa_refused_or_cancelled',
            'qf_marital_status',
            'qf_applicant_have_criminal_convictions',
            'qf_applicant_health_or_care_concerns'
        );

        foreach ($uniqueIds as $key => $uniqueId) {
            $db->query("UPDATE company_questionnaires_fields SET q_field_order = 3  WHERE  q_field_unique_id = '$uniqueId' && q_section_id = 1;");
        }

        $db->commit();
    }
}