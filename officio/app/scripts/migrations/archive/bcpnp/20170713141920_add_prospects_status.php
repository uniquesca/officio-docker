<?php

use Phinx\Migration\AbstractMigration;

class AddProspectsStatus extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();


        $db->query("ALTER TABLE `company_prospects` ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT '1' AFTER `notes`;");
        $db->query("ALTER TABLE `company_prospects` ADD COLUMN `did_not_arrive` VARCHAR(255) NULL DEFAULT NULL AFTER `status`;");

        // Add "referred by" field
        $db->insert(
            'company_questionnaires_fields',
            array(
                'q_field_unique_id'                => 'qf_did_not_arrive',
                'q_section_id'                     => 1,
                'q_field_type'                     => 'combo_custom',
                'q_field_required'                 => 'N',
                'q_field_show_in_prospect_profile' => 'Y',
                'q_field_show_in_qnr'              => 'N',
                'q_field_show_please_select'       => 'N',
                'q_field_order'                    => 131,
            )
        );
        $fieldId = $db->lastInsertId('company_questionnaires_fields');

        $this->execute("INSERT INTO `company_questionnaires_fields_templates` (`q_id`, `q_field_id`, `q_field_label`, `q_field_prospect_profile_label`) 
            SELECT q_id, $fieldId, 'Did Not Arrive (DNA)', 'Did Not Arrive (DNA)' FROM company_questionnaires;
        ");
        $this->execute("INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) 
            SELECT q_id, $fieldId, 'Did not arrive', 0 FROM company_questionnaires;
        ");
        $this->execute("INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) 
            SELECT q_id, $fieldId, 'Lost interest', 1 FROM company_questionnaires;
        ");
        $this->execute("INSERT INTO `company_questionnaires_fields_custom_options` (`q_id`, `q_field_id`, `q_field_custom_option_label`, `q_field_custom_option_order`) 
            SELECT q_id, $fieldId, 'No reply', 2 FROM company_questionnaires;
        ");

        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $db->query("ALTER TABLE `company_prospects`	DROP COLUMN `status`;");
        $db->query("ALTER TABLE `company_prospects`	DROP COLUMN `did_not_arrive`;");
        $db->query("DELETE FROM company_questionnaires_fields WHERE q_field_unique_id = 'qf_did_not_arrive';");

        $db->commit();
    }
}