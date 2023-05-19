<?php

use Phinx\Migration\AbstractMigration;
use Officio\Service\Log;

class AddVisaSurvey extends AbstractMigration
{
    public function up()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            // A sample, how to add the required field

            /*
             INSERT INTO `client_form_fields` (`field_id`, `company_id`, `company_field_id`, `type`, `label`, `maxlength`, `encrypted`, `required`, `required_for_submission`, `disabled`, `blocked`, `multiple_values`, `skip_access_requirements`, `custom_height`, `min_value`, `max_value`) VALUES
             (120000, 4, 'third_country_visa', 3, 'Third Country Visa', 0, 'N', 'N', 'N', 'N', 'N', 'N', 'N', 0, NULL, NULL);

             INSERT INTO `client_form_default` (`form_default_id`, `field_id`, `value`, `order`) VALUES (NULL, 120000, 'Yes', 0);
             INSERT INTO `client_form_default` (`form_default_id`, `field_id`, `value`, `order`) VALUES (NULL, 120000, 'No', 1);

            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9829, 120000, 'N', 15);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9835, 120000, 'N', 19);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9854, 120000, 'N', 10);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9865, 120000, 'N', 10);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9874, 120000, 'N', 94);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9877, 120000, 'N', 82);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9884, 120000, 'N', 15);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9844, 120000, 'N', 10);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9868, 120000, 'N', 83);
            INSERT INTO `client_form_order` (`order_id`, `group_id`, `field_id`, `use_full_row`, `field_order`) VALUES (NULL, 9826, 120000, 'N', 10);


             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1079, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1080, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1081, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1082, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1083, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1084, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1085, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1086, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1087, 'F');
             INSERT INTO `client_form_field_access` (`access_id`, `role_id`, `field_id`, `client_type_id`, `status`) VALUES (NULL, 4841, 120000, 1088, 'F');
            */

            $db->query(
                "
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
            "
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->query('DROP TABLE IF EXISTS `client_form_dependents_visa_survey`;');
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}