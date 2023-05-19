<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddVisaSurvey extends AbstractMigration
{
    public function up()
    {
        try {
            $this->query("
                CREATE TABLE `client_form_dependents_visa_survey` (
                    `visa_survey_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `member_id` BIGINT(20) NOT NULL,
                    `dependent_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                    `visa_country_id` INT(11) NULL DEFAULT NULL,
                    `visa_number` CHAR(255) NOT NULL,
                    `visa_issue_date` DATE NULL DEFAULT NULL,
                    `visa_expiry_date` DATE NULL DEFAULT NULL,
                    INDEX `visa_survey_id` (`visa_survey_id`),
                    INDEX `FK_client_form_dependents_visa_survey_dependents` (`dependent_id`),
                    INDEX `FK_client_form_dependents_visa_survey_members` (`member_id`),
                    INDEX `FK_client_form_dependents_visa_survey_country_master` (`visa_country_id`),
                    CONSTRAINT `FK_client_form_dependents_visa_survey_country_master` FOREIGN KEY (`visa_country_id`) REFERENCES `country_master` (`countries_id`) ON UPDATE CASCADE ON DELETE SET NULL,
                    CONSTRAINT `FK_client_form_dependents_visa_survey_dependents` FOREIGN KEY (`dependent_id`) REFERENCES `client_form_dependents` (`dependent_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT `FK_client_form_dependents_visa_survey_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB
            ");
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $this->query('DROP TABLE IF EXISTS `client_form_dependents_visa_survey`;');
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}