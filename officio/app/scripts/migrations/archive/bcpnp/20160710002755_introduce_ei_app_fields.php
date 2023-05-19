<?php

use Phinx\Migration\AbstractMigration;

class IntroduceEiAppFields extends AbstractMigration
{

    public function up()
    {
        $this->execute(
            "
        
            CALL `createCaseGroup` ('Business Plan', 3, 'Business Immigration Application');
            
            CALL `createCaseField` ('ei_app_bus_desc', 11, 'Business Type Description', 0, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_BusDesc');
            CALL `createCaseField` ('ei_app_jobs_maint', 1, 'Total Jobs Maintained', 10, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_JobsMaintained');
            CALL `createCaseField` ('ei_app_jobs_created', 1, 'Total Jobs Created', 10, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_TotalJobs');
            CALL `createCaseField` ('ei_app_bus_is_franchise', 1, 'Franchise Business', 5, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_Bus_franchise');
            CALL `createCaseField` ('ei_app_bus_is_farm', 1, 'Farm/Agricultural Business', 5, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_Bus_farm');
            CALL `createCaseField` ('ei_app_bus_is_local_partner', 1, 'Partnership with a local partner(s)', 5, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_Bus_local_partner');
            CALL `createCaseField` ('ei_app_bus_is_co_pnp_partner', 1, 'Partnership with a BC PNP co-applicant(s)', 5, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_Bus_co_partner');
            CALL `createCaseField` ('ei_app_bus_key_staff', 1, 'Proposing a foreign key staff', 5, 'N', 'N', 'Business Plan', 'Business Immigration Application', 'syncA_App_Bus_key_staff');  
            CALL `createCaseField` ('ei_app_has_active_app', 1, 'Has Active Application', 10, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_ActiveApplication');
            CALL `createCaseField` ('ei_app_has_pnp_active_app', 1, 'Has Active Application with BC PNP', 10, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_PreviousApp');
            CALL `createCaseField` ('ei_app_active_app_details', 11, 'Details About Other Active Application', 0, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_CurPrevApplicationsDetails');
            CALL `createCaseField` ('ei_app_cur_res_country', 4, 'Current Country of Residence', 0, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_CurResCountry');
            CALL `createCaseField` ('visitorID', 1, 'Visitor CIC ID/UCI', 20, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_VisitorID');
            CALL `createCaseField` ('ei_app_legal_actions_taken', 1, 'Has Legal Actions Taken', 5, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_LegalAffectWorth');
            CALL `createCaseField` ('ei_app_personal_bankruptcy', 1, 'Has Declared Personal Bankruptcy', 5, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_Bankruptcy');
            CALL `createCaseField` ('ei_app_business_bankruptcy', 1, 'Has Declared Business Bankruptcy', 5, 'N', 'N', 'Case Details', 'Business Immigration Application', 'syncA_App_BusBankruptcy');

            CALL `createIAField` ('birth_country', 'country', 'Country of Birth', 'N', 'N', 'Applicant Info', 'syncA_App_BCountry');
            CALL `createIAField` ('marital_status', 'text', 'Marital Status', 'N', 'N', 'Applicant Info', 'syncA_App_MaritalStatus');
            CALL `createIAField` ('has_children', 'text', 'Has Dependent Children', 'N', 'N', 'Applicant Info', 'syncA_App_HaveDepChildren');
            
            CALL `createIAField` ('phone_secondary_bcpnp', 'phone', 'Secondary Phone Number', 'N', 'N', 'Applicant Contact Info', 'BCPNP_App_Phone2');
            CALL `createIAField` ('phone_business_bcpnp', 'phone', 'Business Phone Number', 'N', 'N', 'Applicant Contact Info', 'BCPNP_App_PhoneB');
            CALL `createIAField` ('res_addr_diff', 'text', 'Residential Address is Different From Mailing Address', 'N', 'N', 'Applicant Contact Info', 'syncA_App_ResAddrDiff');
            CALL `createIAField` ('res_addr', 'text', 'Residential Address', 'N', 'N', 'Applicant Contact Info', 'syncA_App_ResAddrLine');
            CALL `createIAField` ('res_city', 'text', 'Residential City', 'N', 'N', 'Applicant Contact Info', 'syncA_App_ResCity');
            CALL `createIAField` ('res_province', 'text', 'Residential Province/State', 'N', 'N', 'Applicant Contact Info', 'syncA_App_ResProvince');
            CALL `createIAField` ('res_country', 'country', 'Residential Country', 'N', 'N', 'Applicant Contact Info', 'syncA_App_ResCountry');
            CALL `createIAField` ('res_postal_code', 'text', 'Residential Postal/Zip code', 'N', 'N', 'Applicant Contact Info', 'syncA_App_ResPostal');
            
            CALL `createIAGroup` ('Spouse Information', 4, 'N');
            CALL `createIAField` ('spouse_last_name', 'text', 'Last Name', 'N', 'N', 'Spouse Information', 'syncA_App_Spouse_Lname');
            CALL `createIAField` ('spouse_first_name', 'text', 'First Name', 'N', 'N', 'Spouse Information', 'syncA_App_Spouse_Fname');
            CALL `createIAField` ('spouse_dob', 'date', 'Date of Birth', 'N', 'N', 'Spouse Information', 'syncA_App_Spouse_DOB');
            CALL `createIAField` ('spouse_birthplace', 'country', 'Birth Place', 'N', 'N', 'Spouse Information', 'syncA_App_Spouse_BirthPlace');
            CALL `createIAField` ('spouse_sex', 'text', 'Sex', 'N', 'N', 'Spouse Information', 'syncA_App_Spouse_Sex');
            CALL `createIAField` ('spouse_citizenship', 'country', 'Citizenship', 'N', 'N', 'Spouse Information', 'syncA_App_Spouse_Citizenship');
            
            CALL `putCaseFieldIntoGroup` ('intendedResidence', 'Case Details', 'Business Immigration Application', 'syncA_App_IntendedResidence'); 
            CALL `putCaseFieldIntoGroup` ('inCanada', 'Case Details', 'Business Immigration Application', 'syncA_App_InCanada'); 
            CALL `putCaseFieldIntoGroup` ('inCanadaStatus', 'Case Details', 'Business Immigration Application', 'syncA_App_CanadaStatus'); 
            CALL `putCaseFieldIntoGroup` ('studyPermitID', 'Case Details', 'Business Immigration Application', 'syncA_App_StudentClientID'); 
            CALL `putCaseFieldIntoGroup` ('workPermitID', 'Case Details', 'Business Immigration Application', '');
            
            CALL `putCaseFieldIntoGroup` ('BusPropType', 'Business Plan', 'Business Immigration Application', 'syncA_App_BusPropType'); 
            CALL `putCaseFieldIntoGroup` ('BusOwnerPercent', 'Business Plan', 'Business Immigration Application', ''); 
            CALL `putCaseFieldIntoGroup` ('BusRegionDist', 'Business Plan', 'Business Immigration Application', 'syncA_App_BusRegionDist'); 
            CALL `putCaseFieldIntoGroup` ('BusMunicipality', 'Business Plan', 'Business Immigration Application', 'syncA_App_BusMunicipality'); 
            CALL `putCaseFieldIntoGroup` ('BusNAICS', 'Business Plan', 'Business Immigration Application', 'syncA_App_BusNAICS'); 
            CALL `putCaseFieldIntoGroup` ('BusRoleNOC', 'Business Plan', 'Business Immigration Application', 'syncA_App_BusRoleNOC'); 
            CALL `putCaseFieldIntoGroup` ('PropInvest_Total', 'Business Plan', 'Business Immigration Application', 'syncA_App_TotalPInvestment'); 
            
            INSERT INTO FormSynField (FieldName) VALUES ('syncA_ExtUsr_FirstCitizen');
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'country_of_citizenship'
              FROM `FormSynField` 
              WHERE `FieldName` = 'syncA_ExtUsr_FirstCitizen';
        "
        );
    }

    public function down()
    {
    }

}
