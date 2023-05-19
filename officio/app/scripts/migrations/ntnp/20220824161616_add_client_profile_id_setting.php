<?php

use Officio\Common\Json;
use Officio\Migration\AbstractMigration;

class AddClientProfileIdSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `client_profile_id_settings` TEXT NULL DEFAULT NULL COMMENT 'Client Profile ID generation settings' AFTER `invoice_number_settings`;");

        // Prefill default settings
        $arrCompanies = $this->fetchAll('SELECT * FROM company');
        foreach ($arrCompanies as $arrCompanyInfo) {
            $arrSet = [
                'enabled'    => 0,
                'start_from' => '0001',
                'format'     => '{client_id_sequence}',
            ];

            $this->getQueryBuilder()
                ->update('company_details')
                ->set('client_profile_id_settings', Json::encode($arrSet))
                ->where(['company_id' => $arrCompanyInfo['company_id']])
                ->execute();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `client_profile_id_settings`;");
    }
}
