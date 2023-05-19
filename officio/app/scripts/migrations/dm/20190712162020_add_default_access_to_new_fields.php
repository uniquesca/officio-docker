<?php

use Clients\Service\Clients;
use Officio\Migration\AbstractMigration;

class AddDefaultAccessToNewFields extends AbstractMigration
{
    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        // ******* APPLICANTS' fields default access
        $this->execute(
            "CREATE TABLE `applicant_form_fields_access_default` (
                `applicant_field_id` INT(11) UNSIGNED NOT NULL,
                `role_id` INT(11) NULL DEFAULT NULL,
                `access` ENUM('R','F') NOT NULL DEFAULT 'R' COMMENT 'R=read only, F=full access',
                `updated_on` DATETIME NULL DEFAULT NULL,
                INDEX `FK_applicant_form_fields_access_default_acl_roles` (`role_id`),
                INDEX `FK_applicant_form_fields_access_default_applicant_form_fields` (`applicant_field_id`),
                CONSTRAINT `FK_applicant_form_fields_access_default_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_applicant_form_fields_access_default_applicant_form_fields` FOREIGN KEY (`applicant_field_id`) REFERENCES `applicant_form_fields` (`applicant_field_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COMMENT='Default access for applicant fields and created/future role'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB");

        // ******* CASES' fields default access
        $this->execute(
            "CREATE TABLE `client_form_fields_access_default` (
                `client_field_id` INT(11) UNSIGNED NOT NULL,
                `role_id` INT(11) NULL DEFAULT NULL,
                `access` ENUM('R','F') NOT NULL DEFAULT 'R' COMMENT 'R=read only, F=full access',
                `updated_on` DATETIME NULL DEFAULT NULL,
                INDEX `FK_client_form_fields_access_default_acl_roles` (`role_id`),
                INDEX `FK_client_form_fields_access_default_applicant_form_fields` (`client_field_id`),
                CONSTRAINT `FK_client_form_fields_access_default_acl_roles` FOREIGN KEY (`role_id`) REFERENCES `acl_roles` (`role_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_client_form_fields_access_default_client_form_fields` FOREIGN KEY (`client_field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COMMENT='Default access for cases fields and created/future role'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB");

        /** @var Clients $clients */
        $clients = $serviceManager->get(Clients::class);
        $clients->getFields()->createDefaultFieldsAccessForCompany();
    }

    public function down()
    {
        $this->execute("DROP TABLE `applicant_form_fields_access_default`;");
        $this->execute("DROP TABLE `client_form_fields_access_default`;");
    }
}