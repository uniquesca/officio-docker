<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroduceNewFormsFields extends AbstractMigration
{
    public function up()
    {
        // Final Report fields
        $this->execute("CALL `createCaseGroup` ('Final Report', 3, 'Business Immigration Application');");
        $this->execute("CALL `createCaseField` ('frr_company_name', 1, 'Company Legal Name', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_CompanyName');");
        $this->execute("CALL `createCaseField` ('frr_company_oper_name', 1, 'Company Operating Name', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_OperatingName');");
        $this->execute("CALL `createCaseField` ('frr_company_phone', 1, 'Company Phone', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessPhone');");
        $this->execute("CALL `createCaseField` ('frr_company_email', 1, 'Company Email', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessEmail');");
        $this->execute("CALL `createCaseField` ('frr_company_website', 1, 'Company Website', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessWebsite');");
        $this->execute("CALL `createCaseField` ('frr_company_commenced', 1, 'Business Commencement Date', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_businessCommencementDate');");
        $this->execute("CALL `createCaseField` ('frr_company_addr', 1, 'Company Mailing Address', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessMailAddr');");
        $this->execute("CALL `createCaseField` ('frr_company_city', 1, 'Company Mailing City', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessMailCity');");
        $this->execute("CALL `createCaseField` ('frr_company_postal', 1, 'Company Mailing Postal Code', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessMailPostal');");
        $this->execute("CALL `createCaseField` ('frr_company_alt_addr', 1, 'Company Address', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessAltMailAddr');");
        $this->execute("CALL `createCaseField` ('frr_company_alt_city', 1, 'Company City', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessAltMailCity');");
        $this->execute("CALL `createCaseField` ('frr_company_alt_postal', 1, 'Company Postal Code', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessAltMailPostal');");
        $this->execute("CALL `createCaseField` ('frr_company_naics', 1, 'Business NAICS', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_Business_NAICS');");
        $this->execute("CALL `createCaseField` ('frr_company_own_type', 1, 'Business Ownership Type', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_BusinessOwnershipType');");
        $this->execute("CALL `createCaseField` ('frr_job_title', 1, 'Applicant Job Title', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_Reg_JobTitle');");
        $this->execute("CALL `createCaseField` ('frr_company_employees', 1, 'Number of company employees', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_NumberOfEmployee');");
        $this->execute("CALL `createCaseField` ('frr_pnp_file_number', 1, 'BC PNP File Number', 0, 'N', 'N', 'Final Report', 'Business Immigration Application', 'syncA_Final_PrevPNPFileNum');");

        $this->execute(
            "
            INSERT INTO `FormSynField` (`FieldName`) VALUES
              ('syncA_Final_ResAddrLine'),
              ('syncA_Final_ResCity'),
              ('syncA_Final_ResPostal'),
              ('syncA_Final_MailAddr'),
              ('syncA_Final_MailCity'),
              ('syncA_Final_MailPostal');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_addr' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_ResAddrLine'
                   
                UNION
                   
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_city' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_ResCity'
                
                UNION
                
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_postal_code' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_ResPostal'
                   
                UNION
                   
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'address_1' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_MailAddr'
                
                UNION
                
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'city' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_MailCity'
                
                UNION
                
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'postal_code' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_MailPostal';
        "
        );

        /** @var StorageInterface $cache */
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
                'frr_company_name',
                'frr_company_oper_name',
                'frr_company_phone',
                'frr_company_email',
                'frr_company_website',
                'frr_company_commenced',
                'frr_company_addr',
                'frr_company_city',
                'frr_company_postal',
                'frr_company_alt_addr',
                'frr_company_alt_city',
                'frr_company_alt_postal',
                'frr_company_naics',
                'frr_company_own_type',
                'frr_job_title',
                'frr_company_employees',
                'frr_pnp_file_number'
            );

            DELETE FROM FormMap
            WHERE ToProfileFieldId IN (
                'syncA_Final_CompanyName',
                'syncA_Final_OperatingName',
                'syncA_Final_BusinessPhone',
                'syncA_Final_BusinessEmail',
                'syncA_Final_BusinessWebsite',
                'syncA_Final_businessCommencementDate',
                'syncA_Final_BusinessMailAddr',
                'syncA_Final_BusinessMailCity',
                'syncA_Final_BusinessMailPostal',
                'syncA_Final_BusinessAltMailAddr',
                'syncA_Final_BusinessAltMailCity',
                'syncA_Final_BusinessAltMailPostal',
                'syncA_Final_Business_NAICS',
                'syncA_Final_BusinessOwnershipType',
                'syncA_Final_Reg_JobTitle',
                'syncA_Final_NumberOfEmployee',
                'syncA_Final_PrevPNPFileNum',
                'syncA_Final_ResAddrLine',
                'syncA_Final_ResCity',
                'syncA_Final_ResPostal',
                'syncA_Final_MailAddr',
                'syncA_Final_MailCity',
                'syncA_Final_MailPostal',
                'syncA_Final_PrevPNPFileNum'
            );

            DELETE FROM FormSynField
            WHERE FieldName IN (
                'frr_company_name',
                'frr_company_oper_name',
                'frr_company_phone',
                'frr_company_email',
                'frr_company_website',
                'frr_company_commenced',
                'frr_company_addr',
                'frr_company_city',
                'frr_company_postal',
                'frr_company_alt_addr',
                'frr_company_alt_city',
                'frr_company_alt_postal',
                'frr_company_naics',
                'frr_company_own_type',
                'frr_job_title',
                'frr_company_employees',
                'frr_pnp_file_number'
            );
            
            DELETE
            FROM client_form_fields
            WHERE company_field_id IN (
                'frr_company_name',
                'frr_company_oper_name',
                'frr_company_phone',
                'frr_company_email',
                'frr_company_website',
                'frr_company_commenced',
                'frr_company_addr',
                'frr_company_city',
                'frr_company_postal',
                'frr_company_alt_addr',
                'frr_company_alt_city',
                'frr_company_alt_postal',
                'frr_company_naics',
                'frr_company_own_type',
                'frr_job_title',
                'frr_company_employees',
                'frr_pnp_file_number'
            );
        "
        );
    }
}