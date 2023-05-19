<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class IntroduceKeyStaffApplication extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "           
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 7, MAX(FormId) + 1, '', '2017-01-18 00:00:00', '2017-01-18 00:00:00.pdf', '21kb', '2017-01-18 00:00:00', 1, 'Key Staff Application Form', '', ''
            FROM FormVersion;
            
            INSERT INTO client_types (`company_id`, `form_version_id`, `client_type_name`, `client_type_needs_ia`, `client_type_employer_sponsorship`)
            SELECT company_id, 7, 'Key Staff Application', 'Y', 'N'
            FROM company;
            
            INSERT INTO `client_types_kinds` (`client_type_id`, `member_type_id`)
              SELECT `ct`.`client_type_id`, `mt`.`member_type_id`
              FROM `client_types` AS `ct`
                LEFT OUTER JOIN `members_types` as `mt` ON `mt`.`member_type_name` = 'individual'
              WHERE `ct`.`client_type_name` = 'Key Staff Application';
              
            -- Copy groups from EI Immigration Registration
            INSERT INTO `client_form_groups` (`company_id`, `client_type_id`, `title`, `order`, `cols_count`, `collapsed`, `regTime`, `assigned`)
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Not Assigned', 110, 3, 'N', UNIX_TIMESTAMP(), 'U'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Key Staff Application'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Dependants', 1, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Key Staff Application'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Case Details', 0, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Key Staff Application'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Decision Rationale', 4, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Key Staff Application'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration';          

            -- Add fields into groups
            SET @rownum = 0;
            INSERT INTO `client_form_order` (`group_id`, `field_id`, `use_full_row`, `field_order`)
              SELECT `cfg1`.`group_id`, `cff1`.`field_id`, 'N', @rownum := @rownum + 1
              FROM `client_form_order` AS `cff`
                INNER JOIN `client_form_fields` AS `cff1` ON `cff1`.`field_id` = `cff`.`field_id`
                INNER JOIN `client_form_groups` AS `cfg` ON `cff`.`group_id` = `cfg`.`group_id`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `client_form_groups` AS `cfg1` ON `cfg1`.`title` = `cfg`.`title` AND `cfg1`.`group_id` <> `cfg`.`group_id` AND `cfg`.`company_id` = `cfg1`.`company_id`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_id` = `cfg1`.`client_type_id` AND `ct1`.`client_type_name` = 'Key Staff Application'
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Business Immigration Registration');

            -- Add group access to admin
            INSERT INTO `client_form_group_access` (`role_id`, `group_id`, `status`)
              SELECT `ar`.`role_id`, `cfg`.`group_id`, 'F'
              FROM `client_form_groups` as `cfg`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cfg`.`company_id`
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Key Staff Application') AND (`ar`.`role_name` = 'Admin');

            -- Grant access to moved fields
            INSERT INTO `client_form_field_access` (`role_id`, `field_id`, `client_type_id`, `status`)
              SELECT `ar`.`role_id`, `cfo`.`field_id`, `ct`.`client_type_id`, 'F'
              FROM `client_form_groups` as `cfg`
                INNER JOIN `client_form_order` AS `cfo` ON `cfo`.`group_id` = `cfg`.`group_id`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cfg`.`company_id`
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Key Staff Application') AND (`ar`.`role_name` = 'Admin');
        "
        );

        $this->execute(
            "
          INSERT INTO `FormSynField` (`FieldName`) VALUES
          ('syncA_ExtUsr_Lname'),
          ('syncA_ExtUsr_Fname');
            
          INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'last_name' 
            FROM `FormSynField` WHERE `FieldName` = 'syncA_ExtUsr_Lname'
               
            UNION
               
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'first_name' 
            FROM `FormSynField` WHERE `FieldName` = 'syncA_ExtUsr_Fname';
        "
        );

        $this->execute(
            "
            INSERT INTO company_default_options (company_id, default_option_type, default_option_name, default_option_abbreviation)
            SELECT c.company_id, 'categories', 'keyStaff::application', 'BCEKS'
            FROM company c;
            
            INSERT INTO `divisions` (`company_id`, `name`, `order`)
            SELECT company_id, 'Key Staff Intake', 5
            FROM company;
        "
        );

        $this->execute(
            "           
            CALL `createCaseField` ('ks_principal_app_file_number', 1, 'Principal Application Reference #', 0, 'N', 'N', 'Case Details', 'Key Staff Application', null);
            CALL `createCaseField` ('ei_app_ks_file_number', 1, 'Key Staff File Number', 0, 'N', 'N', 'Case Details', 'Business Immigration Application', null);
            CALL `createCaseField` ('ei_app_ks_email', 1, 'Key Staff Email', 0, 'N', 'N', 'Case Details', 'Business Immigration Application', null);
            CALL `createCaseField` ('ei_app_ks_submitted_on', 1, 'Key Staff Application Submitted On', 0, 'N', 'N', 'Case Details', 'Business Immigration Application', null);
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute(
            "
            DELETE cfd, cffa, cfo
            FROM client_form_fields cff
              LEFT JOIN  client_form_data cfd ON cff.field_id = cfd.field_id
              LEFT JOIN client_form_field_access cffa ON cff.field_id = cffa.field_id
              LEFT JOIN client_form_order cfo ON cfo.field_id = cff.field_id
            WHERE cff.company_field_id IN (
                'ks_principal_app_file_number',
                'ei_app_ks_file_number',
                'ei_app_ks_email',
                'ei_app_ks_submitted_on'
            );

            DELETE
            FROM client_form_fields
            WHERE company_field_id IN (
                'ks_principal_app_file_number',
                'ei_app_ks_file_number',
                'ei_app_ks_email',
                'ei_app_ks_submitted_on'
            );

            DELETE FROM company_default_options WHERE default_option_abbreviation IN ('BCEKS');
            
            DELETE FROM client_types WHERE client_type_name = 'Key Staff Application';
            
            DELETE FROM FormVersion WHERE FileName = 'Key Staff Application Form';
            
            DELETE FROM divisions WHERE `name` = 'Key Staff Intake';
        "
        );
    }
}