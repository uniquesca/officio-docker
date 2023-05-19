<?php

use Phinx\Migration\AbstractMigration;

class AddSirsMissingFields extends AbstractMigration
{
    public function up()
    {
        // Create new fields
        $this->execute(
            "
            INSERT INTO `client_form_fields` (company_id, company_field_id, type, label, maxlength, encrypted, required, disabled, blocked)
              SELECT `company_id`, 'sirsJobOfferCity', 1, 'City/Town', 0, 'N', 'N', 'N', 'N' FROM `company` UNION
              SELECT `company_id`, 'sirsJobOfferPostalCode', 1, 'Postal/Zip Code', 0, 'N', 'N', 'N', 'N' FROM `company`;

            SELECT (@rownum := MAX(`field_order`)) FROM `client_form_order`;
            INSERT INTO `client_form_order` (`group_id`, `field_id`, `use_full_row`, `field_order`)
              SELECT `cfg`.`group_id`, `cff`.`field_id`, 'N', (@rownum := @rownum + 1)
              FROM `client_form_fields` as `cff`
                LEFT OUTER JOIN `client_form_groups` AS `cfg` ON `cff`.`company_id` = `cfg`.`company_id`
                LEFT OUTER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
              WHERE (`cff`.`company_field_id` IN (
                'sirsJobOfferCity',
                'sirsJobOfferPostalCode'
              )) AND (`cfg`.`title` = 'Job Offer') AND (`ct`.`client_type_name` = 'Skills Immigration Registration');

            INSERT INTO `client_form_field_access` (`role_id`, `field_id`, `client_type_id`, `status`)
              SELECT `ar`.`role_id`, `cff`.`field_id`, `ct`.`client_type_id`, 'F'
              FROM `client_form_fields` as `cff`
                LEFT OUTER JOIN `client_form_groups` AS `cfg` ON `cff`.`company_id` = `cfg`.`company_id`
                LEFT OUTER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                LEFT OUTER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cff`.`company_id`
              WHERE
                `cff`.`company_field_id` IN (
                  'sirsJobOfferCity',
                  'sirsJobOfferPostalCode'
                ) AND
                `cfg`.`title` = 'Job Offer' AND
                `ct`.`client_type_name` = 'Skills Immigration Registration' AND
                `ar`.`role_name` = 'Admin';

            INSERT INTO FormSynField (FieldName) VALUES
              ('syncA_App_Job_WorkLocationCity'),
              ('syncA_App_Job_WorkLocationPostal');

            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'sirsJobOfferCity' FROM `FormSynField` WHERE `FieldName` = 'syncA_App_Job_WorkLocationCity' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'sirsJobOfferPostalCode' FROM `FormSynField` WHERE `FieldName` = 'syncA_App_Job_WorkLocationPostal';
        "
        );
    }

    public function down()
    {
        // Dropping fields data, access and group order
        $this->execute(
            "
            DELETE cfd, cffa, cfo
            FROM client_form_fields cff
              LEFT JOIN  client_form_data cfd ON cff.field_id = cfd.field_id
              LEFT JOIN client_form_field_access cffa ON cff.field_id = cffa.field_id
              LEFT JOIN client_form_order cfo ON cfo.field_id = cff.field_id
            WHERE cff.company_field_id IN (
                'sirsJobOfferCity',
                'sirsJobOfferPostalCode'
            );

            DELETE FROM FormMap
            WHERE ToProfileFieldId IN (
              'sirsJobOfferCity',
              'sirsJobOfferPostalCode'
            );

            DELETE FROM FormSynField
            WHERE FieldName IN (
              'syncA_App_Job_WorkLocationCity',
              'syncA_App_Job_WorkLocationPostal'
            );

            DELETE
            FROM client_form_fields
            WHERE company_field_id IN (
              'sirsJobOfferCity',
              'sirsJobOfferPostalCode'
            );
        "
        );
    }
}
