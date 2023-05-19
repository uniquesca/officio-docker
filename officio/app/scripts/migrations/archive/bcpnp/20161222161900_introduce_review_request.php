<?php

use Phinx\Migration\AbstractMigration;

class IntroduceReviewRequest extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "           
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 6, MAX(FormId) + 1, '', '2016-12-23 00:00:00', '2016-12-23 00:00:00.pdf', '21kb', '2016-12-23 00:00:00', 1, 'Review Request Form', '', ''
            FROM FormVersion;
            
            INSERT INTO client_types (`company_id`, `form_version_id`, `client_type_name`, `client_type_needs_ia`, `client_type_employer_sponsorship`)
            SELECT company_id, 6, 'Review Request', 'Y', 'N'
            FROM company;
            
            INSERT INTO `client_types_kinds` (`client_type_id`, `member_type_id`)
              SELECT `ct`.`client_type_id`, `mt`.`member_type_id`
              FROM `client_types` AS `ct`
                LEFT OUTER JOIN `members_types` as `mt` ON `mt`.`member_type_name` = 'individual'
              WHERE `ct`.`client_type_name` = 'Review Request';
              
            -- Copy groups from EI Immigration Registration
            INSERT INTO `client_form_groups` (`company_id`, `client_type_id`, `title`, `order`, `cols_count`, `collapsed`, `regTime`, `assigned`)
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Not Assigned', 110, 3, 'N', UNIX_TIMESTAMP(), 'U'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Review Request'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Dependants', 1, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Review Request'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Case Details', 0, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Review Request'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Payment Details', 2, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Review Request'
              WHERE `ct`.`client_type_name` = 'Business Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Decision Rationale', 4, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Review Request'
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
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_id` = `cfg1`.`client_type_id` AND `ct1`.`client_type_name` = 'Review Request'
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Payment Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Business Immigration Registration');

            -- Add group access to admin
            INSERT INTO `client_form_group_access` (`role_id`, `group_id`, `status`)
              SELECT `ar`.`role_id`, `cfg`.`group_id`, 'F'
              FROM `client_form_groups` as `cfg`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cfg`.`company_id`
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Payment Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Review Request') AND (`ar`.`role_name` = 'Admin');

            -- Grant access to moved fields
            INSERT INTO `client_form_field_access` (`role_id`, `field_id`, `client_type_id`, `status`)
              SELECT `ar`.`role_id`, `cfo`.`field_id`, `ct`.`client_type_id`, 'F'
              FROM `client_form_groups` as `cfg`
                INNER JOIN `client_form_order` AS `cfo` ON `cfo`.`group_id` = `cfg`.`group_id`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cfg`.`company_id`
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Payment Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Review Request') AND (`ar`.`role_name` = 'Admin');
        "
        );

        $this->execute(
            "
            INSERT INTO company_default_options (company_id, default_option_type, default_option_name, default_option_abbreviation)
            SELECT c.company_id, 'categories', 'business::reviewRequest', 'BCERR'
            FROM company c;
            
            INSERT INTO company_default_options (company_id, default_option_type, default_option_name, default_option_abbreviation)
            SELECT c.company_id, 'categories', 'entry-level::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'express-heath-care::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'express-intl-grad::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'express-intl-postgrad::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'express-skilled::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'heath-care::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'intl-grad::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'intl-postgrad::reviewRequest', 'BCSRR'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'northeast::reviewRequest', 'BCSRR'
            FROM company c;
            
            INSERT INTO `divisions` (`company_id`, `name`, `order`)
            SELECT company_id, 'Review Request Intake', 5
            FROM company;
        "
        );

        $this->execute(
            "
            CALL `createCaseGroup` ('Request Details', 3, 'Review Request');
            
            CALL `createCaseField` ('rev_req_file_number', 1, 'PNP File Number', 0, 'N', 'N', 'Request Details', 'Review Request', 'syncA_Request_PnpFileNumber');
            CALL `createCaseField` ('rev_req_decision_date', 1, 'Date of Decision Notice', 0, 'N', 'N', 'Request Details', 'Review Request', 'syncA_Request_DescisionDate');
            CALL `createCaseField` ('rev_req_rep_authorized', 1, 'Representative Authorized', 0, 'N', 'N', 'Request Details', 'Review Request', 'syncA_Request_AuthRep');
            CALL `createCaseField` ('rev_req_grounds', 11, 'Explanation of Grounds for Review', 0, 'N', 'N', 'Request Details', 'Review Request', 'syncA_App_BusDesc');
        "
        );
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
                'rev_req_file_number',
                'rev_req_decision_date',
                'rev_req_rep_authorized',
                'rev_req_grounds'
            );

            DELETE FROM FormMap
            WHERE ToProfileFieldId IN (
                'rev_req_file_number',
                'rev_req_decision_date',
                'rev_req_rep_authorized',
                'rev_req_grounds'
            );

            DELETE FROM FormSynField
            WHERE FieldName IN (
                'syncA_Request_PnpFileNumber',
                'syncA_Request_DescisionDate',
                'syncA_Request_AuthRep',
                'syncA_App_BusDesc'
            );

            DELETE
            FROM client_form_fields
            WHERE company_field_id IN (
                'rev_req_file_number',
                'rev_req_decision_date',
                'rev_req_rep_authorized',
                'rev_req_grounds'
            );

            DELETE FROM company_default_options WHERE default_option_abbreviation IN ('BCERR', 'BCSRR');
            
            DELETE FROM client_types WHERE client_type_name = 'Review Request';
            
            DELETE FROM FormVersion WHERE FileName = 'Review Request Form';
            
            DELETE FROM divisions WHERE `name` = 'Review Request Intake';
        "
        );
    }
}