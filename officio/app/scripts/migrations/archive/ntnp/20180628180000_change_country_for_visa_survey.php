<?php

use Phinx\Migration\AbstractMigration;
use Officio\Service\Log;

class ChangeCountryForVisaSurvey extends AbstractMigration
{
    public function up()
    {
        try {
            $this->execute('ALTER TABLE `client_form_dependents_visa_survey` DROP FOREIGN KEY `FK_client_form_dependents_visa_survey_country_master`;');
            $this->execute('UPDATE `client_form_dependents_visa_survey` SET `visa_country_id` = NULL;');
            $this->execute("ALTER TABLE `client_form_dependents_visa_survey` CHANGE COLUMN `visa_country_id` `visa_country_id` ENUM('US','UK','Schengen','Canada') NULL DEFAULT NULL AFTER `dependent_id`;");
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $this->execute('ALTER TABLE `client_form_dependents_visa_survey` CHANGE COLUMN `visa_country_id` `visa_country_id` INT(11) NULL DEFAULT NULL AFTER `dependent_id`;');
            $this->execute('UPDATE `client_form_dependents_visa_survey` SET `visa_country_id` = NULL WHERE visa_country_id = 0;');
            $this->execute(
                'ALTER TABLE `client_form_dependents_visa_survey` ADD CONSTRAINT `FK_client_form_dependents_visa_survey_country_master` FOREIGN KEY (`visa_country_id`) REFERENCES `country_master` (`countries_id`) ON UPDATE CASCADE ON DELETE CASCADE;'
            );
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}