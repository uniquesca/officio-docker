<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class SiAddPostNom extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "           
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 13, MAX(FormId) + 1, '', '2018-12-11 00:00:00', '2018-12-11 00:00:00.pdf', '1', '2016-12-23 00:00:00', 1, 'Skills Post-Nomination Support Form', '', ''
            FROM FormVersion;
        "
        );

        $this->execute(
            "            
            INSERT INTO client_types (`company_id`, `client_type_name`, `client_type_needs_ia`, `client_type_employer_sponsorship`)
            SELECT company_id, 'Skills Post-Nomination Support', 'Y', 'N'
            FROM company;
        "
        );

        $this->execute(
            "            
            INSERT INTO client_types_forms (client_type_id, form_version_id)
            SELECT ct.client_type_id, 13
            FROM client_types ct
            WHERE ct.client_type_name = 'Skills Post-Nomination Support';
        "
        );

        $this->execute(
            "          
            INSERT INTO `client_types_kinds` (`client_type_id`, `member_type_id`)
              SELECT `ct`.`client_type_id`, `mt`.`member_type_id`
              FROM `client_types` AS `ct`
                LEFT OUTER JOIN `members_types` as `mt` ON `mt`.`member_type_name` = 'individual'
              WHERE `ct`.`client_type_name` = 'Skills Post-Nomination Support';
        "
        );

        $this->execute(
            "            
            INSERT INTO `client_form_groups` (`company_id`, `client_type_id`, `title`, `order`, `cols_count`, `collapsed`, `regTime`, `assigned`)
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Not Assigned', 110, 3, 'N', UNIX_TIMESTAMP(), 'U'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Skills Post-Nomination Support'
              WHERE `ct`.`client_type_name` = 'Skills Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Dependants', 1, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Skills Post-Nomination Support'
              WHERE `ct`.`client_type_name` = 'Skills Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Case Details', 0, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Skills Post-Nomination Support'
              WHERE `ct`.`client_type_name` = 'Skills Immigration Registration'
              
              UNION
              
              SELECT `ct1`.`company_id`, `ct1`.`client_type_id`, 'Decision Rationale', 4, 3, 'N', UNIX_TIMESTAMP(), 'A'
              FROM `client_types` AS `ct`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_name` = 'Skills Post-Nomination Support'
              WHERE `ct`.`client_type_name` = 'Skills Immigration Registration';          
        "
        );

        $this->execute(
            "      
            SET @rownum = 0;
            INSERT INTO `client_form_order` (`group_id`, `field_id`, `use_full_row`, `field_order`)
              SELECT `cfg1`.`group_id`, `cff1`.`field_id`, 'N', @rownum := @rownum + 1
              FROM `client_form_order` AS `cff`
                INNER JOIN `client_form_fields` AS `cff1` ON `cff1`.`field_id` = `cff`.`field_id`
                INNER JOIN `client_form_groups` AS `cfg` ON `cff`.`group_id` = `cfg`.`group_id`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `client_form_groups` AS `cfg1` ON `cfg1`.`title` = `cfg`.`title` AND `cfg1`.`group_id` <> `cfg`.`group_id` AND `cfg`.`company_id` = `cfg1`.`company_id`
                INNER JOIN `client_types` AS `ct1` ON `ct1`.`client_type_id` = `cfg1`.`client_type_id` AND `ct1`.`client_type_name` = 'Skills Post-Nomination Support'
              WHERE 
                    (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Decision Rationale')) AND 
                    (`ct`.`client_type_name` = 'Skills Immigration Registration') AND
                    (cff1.label NOT IN ('SIRS Expiration Date', 'SI Category Name'));
        "
        );

        $this->execute(
            "      
            INSERT INTO `client_form_group_access` (`role_id`, `group_id`, `status`)
              SELECT `ar`.`role_id`, `cfg`.`group_id`, 'F'
              FROM `client_form_groups` as `cfg`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cfg`.`company_id`
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Skills Post-Nomination Support') AND (`ar`.`role_name` = 'Admin');
        "
        );

        $this->execute(
            "      
            -- Grant access to moved fields
            INSERT INTO `client_form_field_access` (`role_id`, `field_id`, `client_type_id`, `status`)
              SELECT `ar`.`role_id`, `cfo`.`field_id`, `ct`.`client_type_id`, 'F'
              FROM `client_form_groups` as `cfg`
                INNER JOIN `client_form_order` AS `cfo` ON `cfo`.`group_id` = `cfg`.`group_id`
                INNER JOIN `client_types` AS `ct` ON `ct`.`client_type_id` = `cfg`.`client_type_id`
                INNER JOIN `acl_roles` AS `ar` ON `ar`.`company_id` = `cfg`.`company_id`
              WHERE (`cfg`.`title` IN ('Not Assigned', 'Dependants', 'Case Details', 'Decision Rationale')) AND (`ct`.`client_type_name` = 'Skills Post-Nomination Support') AND (`ar`.`role_name` = 'Admin');
        "
        );

        $this->execute(
            "    
            INSERT INTO company_default_options (company_id, default_option_type, default_option_name, default_option_abbreviation)
            SELECT c.company_id, 'categories', 'wpsl', 'BCSPN'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'nom-ext-basic', 'BCSPN'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'nom-ext-enh', 'BCSPN'
            FROM company c
            UNION
            SELECT c.company_id, 'categories', 'emp-change', 'BCSPN'
            FROM company c;
        "
        );

        $this->execute(
            "
            INSERT INTO `divisions` (division_group_id, `company_id`, `name`, `order`)
            SELECT dg.division_group_id, c.company_id, 'Post-Nom Support Intake', 5
            FROM company c
            INNER JOIN divisions_groups dg ON dg.company_id = c.company_id;
        "
        );

        // Creating case statuses
        $this->execute(
            "
            INSERT INTO `client_form_default` (`field_id`, `value`, `order`)      
                SELECT `field_id`, 'SI Post-Nom - Approved', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'

                UNION
                
                SELECT `field_id`, 'SI Post-Nom - Refused', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'

                UNION
                
                SELECT `field_id`, 'SI Post-Nom - Withdrawn', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status';
        "
        );

        $this->execute("CALL `createCaseField` ('postnom_category', 1, 'Post-nomination support type', 0, 'N', 'N', 'Case Details', 'Skills Post-Nomination Support', NULL);");

        $this->execute("CALL `createCaseGroup` ('Express Entry information', 4, 'Skills Post-Nomination Support');");
        $this->execute("CALL `createCaseField` ('postnom_ee_number', 1, 'EE Profile Number', 0, 'N', 'N', 'Express Entry information', 'Skills Post-Nomination Support', 'syncA_CurrentExpressEntryProfileNumber');");
        $this->execute("CALL `createCaseField` ('postnom_ee_expiry', 8, 'EE Profile Submission Expiry', 0, 'N', 'N', 'Express Entry information', 'Skills Post-Nomination Support', 'syncA_CurrentSubmissionExpireDate');");
        $this->execute("CALL `createCaseField` ('postnom_ee_js_validation_code', 1, 'Job Seeker Validation Code', 0, 'N', 'N', 'Express Entry information', 'Skills Post-Nomination Support', 'syncA_CurrentJobSeekerValidationCode');");
        $this->execute("CALL `createCaseField` ('postnom_ee_crs', 1, 'CRS', 0, 'N', 'N', 'Nominated application information', 'Express Entry information', 'syncA_CurrentComprehensiveRankingScore');");
        $this->execute("CALL `createCaseField` ('postnom_ee_prev_number', 1, 'Previous EE Profile Number', 0, 'N', 'N', 'Express Entry information', 'Skills Post-Nomination Support', 'syncA_ExpressEntryProfileNumber');");

        $this->execute("CALL `createCaseGroup` ('Nominated application information', 4, 'Skills Post-Nomination Support');");
        $this->execute(
            "CALL `createCaseField` ('postnom_application_received', 8, 'Nominated Application Received', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_Ext_ApplicationReceivedDate');"
        );
        $this->execute("CALL `createCaseField` ('postnom_nom_category', 1, 'Nominated Category', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_Ext_NominatedCategory');");
        $this->execute("CALL `createCaseField` ('postnom_nom_cert_num', 1, 'Nominated Certificate Number', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_Ext_NomCertNumber');");
        $this->execute("CALL `createCaseField` ('postnom_nom_file_number', 1, 'Nominated File Number', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_Ext_BCPNPFileNumber');");
        $this->execute("CALL `createCaseField` ('postnom_nominated_at', 8, 'Nomination Date', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_Ext_DateOfNomination');");
        $this->execute("CALL `createCaseField` ('postnom_nom_job_title', 1, 'Nominated Job Title', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_WorkJobTitle');");
        $this->execute("CALL `createCaseField` ('postnom_nom_noc', 1, 'Nominated NOC', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_WorkNOC');");
        $this->execute("CALL `createCaseField` ('postnom_nom_expires_at', 8, 'Nomination Expiry', 0, 'N', 'N', 'Nominated application information', 'Skills Post-Nomination Support', 'syncA_Ext_NominationExpiry');");

        $this->execute("CALL `createCaseGroup` ('New Company/Job Information', 5, 'Skills Post-Nomination Support');");
        $this->execute("CALL `createCaseField` ('postnom_employer_name', 1, 'SIRS Employer Legal Name', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkCompany');");
        $this->execute("CALL `createCaseField` ('postnom_employer_op_name', 1, 'SIRS Employer Operating Name', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkCompanyOperatingName');");
        $this->execute("CALL `createCaseField` ('postnom_job_title', 1, 'SIRS Job Title', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkJobTitle');");
        $this->execute("CALL `createCaseField` ('postnom_work_noc', 1, 'SIRC NOC', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkNOC');");
        $this->execute("CALL `createCaseField` ('postnom_annual_wage', 1, 'SIRS Annual Wage', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkAnnualWage');");
        $this->execute("CALL `createCaseField` ('postnom_offer_until', 8, 'Offer ends at', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkEmploymentEndDate');");
        $this->execute("CALL `createCaseField` ('postnom_contact_last_name', 1, 'SIRS Employer Contact Last Name', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkEmployerContactLastName');");
        $this->execute("CALL `createCaseField` ('postnom_contact_first_name', 1, 'SIRS Employer Contact Given Name', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkEmployerContactFirstName');");
        $this->execute("CALL `createCaseField` ('postnom_contact_title', 1, 'SIRS Employer Contact Title', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkEmployerContactTitle');");
        $this->execute("CALL `createCaseField` ('postnom_contact_phone', 1, 'SIRS Employer Contact Phone', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkEmployerContactPhone');");
        $this->execute("CALL `createCaseField` ('postnom_contact_email', 1, 'SIRS Employer Contact Email', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkEmployerContactEmail');");
        $this->execute(
            "CALL `createCaseField` ('postnom_employees_number', 1, 'SI Number of Employees in Company/Organization', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkEmployerFullTimeEmployees');"
        );
        $this->execute("CALL `createCaseField` ('postnom_work_address', 1, 'SI Work Address', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkLocationAddress');");
        $this->execute("CALL `createCaseField` ('postnom_work_city', 1, 'SI Work City', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkLocationCity');");
        $this->execute("CALL `createCaseField` ('postnom_work_postalcode', 1, 'SI Work Postal Code', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkLocationPostal');");
        $this->execute("CALL `createCaseField` ('postnom_work_phone', 1, 'SI Work Phone Number', 0, 'N', 'N', 'New Company/Job Information', 'Skills Post-Nomination Support', 'syncA_NewWorkLocationPhone');");

        $this->execute("CALL `createCaseGroup` ('Applicant status in Canada', 6, 'Skills Post-Nomination Support');");
        $this->execute("CALL `createCaseField` ('postnom_current_status', 1, 'Status in Canada', 0, 'N', 'N', 'Applicant status in Canada', 'Skills Post-Nomination Support', 'syncA_CanadaStatus');");
        $this->execute("CALL `createCaseField` ('postnom_cic_visitor_id', 1, 'Visitor CIC Client ID/UCI', 0, 'N', 'N', 'Applicant status in Canada', 'Skills Post-Nomination Support', 'syncA_VisitorID');");
        $this->execute("CALL `createCaseField` ('postnom_cic_student_id', 1, 'Student CIC Client ID/UCI', 0, 'N', 'N', 'Applicant status in Canada', 'Skills Post-Nomination Support', 'syncA_StudentClientID');");
        $this->execute("CALL `createCaseField` ('postnom_cic_wp_id', 1, 'Work Permit CIC Client ID/UCI', 0, 'N', 'N', 'Applicant status in Canada', 'Skills Post-Nomination Support', 'syncA_WorkPermitClientID');");
        $this->execute("CALL `createCaseField` ('postnom_visitor_status_expiry', 8, 'Visitor Status Expiry', 0, 'N', 'N', 'Applicant status in Canada', 'Skills Post-Nomination Support', 'syncA_VisitorDateValid');");
        $this->execute("CALL `createCaseField` ('postnom_student_status_expiry', 8, 'Student Status Expiry', 0, 'N', 'N', 'Applicant status in Canada', 'Skills Post-Nomination Support', 'syncA_StudentValid');");
        $this->execute("CALL `createCaseField` ('postnom_wp_status_expiry', 8, 'Work Permit Status Expiry', 0, 'N', 'N', 'Applicant status in Canada', 'Skills Post-Nomination Support', 'syncA_WorkPermitValid');");

        $this->execute(
            "
            INSERT INTO FormSynField (`FieldName`) VALUES 
              ('syncA_StudentValid'), 
              ('syncA_WorkPermitValid');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'postnom_cic_id'
                FROM `FormSynField`
                WHERE `FieldName` IN ('syncA_StudentClientID', 'syncA_WorkPermitClientID')
                
                UNION
                
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'postnom_status_expiry'
                FROM `FormSynField`
                WHERE `FieldName` IN ('syncA_StudentValid', 'syncA_WorkPermitValid');
        "
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
    }
}