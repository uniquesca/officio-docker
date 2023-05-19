<?php

use Officio\Service\Company;
use Phinx\Migration\AbstractMigration;

class UpdateDefaultLabels extends AbstractMigration
{
    public function up()
    {
        // Took 19s on local server...
        $this->execute("UPDATE `client_form_fields` SET `label`='Active Case', `type` = 7 WHERE  `company_field_id`='Client_file_status';");
        $this->execute("DELETE FROM `client_form_default` WHERE `field_id` IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`='Client_file_status');");
        $this->execute("DELETE FROM `client_form_data` WHERE `value` != 'Active' AND `field_id` IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`='Client_file_status');");
        $this->execute('ALTER TABLE `company_details`
                	ADD COLUMN `default_label_office` VARCHAR(255) NULL DEFAULT NULL AFTER `subscription`,
                	ADD COLUMN `default_label_trust_account` VARCHAR(255) NULL DEFAULT NULL AFTER `default_label_office`;');

        try {
            /** @var Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            /** @var Company $oCompany */
            $oCompany = Zend_Registry::get('serviceManager')->get(Company::class);
            $defaultOfficeLabel = $oCompany->getDefaultLabel('office');
            $defaultTALabel     = $oCompany->getDefaultLabel('trust_account');

            $db->update(
                'company_details',
                array(
                    'default_label_office'        => $defaultOfficeLabel,
                    'default_label_trust_account' => $defaultTALabel,
                )
            );
        } catch (\Exception $e) {
            echo 'Fatal error' . print_r($e->getTraceAsString(), 1);
        }
    }

    public function down()
    {
    }
}