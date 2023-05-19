<?php

use Phinx\Migration\AbstractMigration;

class UpdateMoreProspectSearchColumns extends AbstractMigration
{
    private function getFields()
    {
        $arrFields = array(
            'qf_education_studied_in_canada_period',
            'qf_education_spouse_studied_in_canada_period',
            'qf_work_temporary_worker',
            'qf_work_years_worked',
            'qf_work_currently_employed',
            'qf_work_noc',
            'qf_family_have_blood_relative',
            'qf_job_duration',
            'qf_job_location',
            'qf_job_province',
            'qf_job_presently_working',
            'qf_job_qualified_for_social_security',
            'qf_job_employment_type'
        );

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
    }
}