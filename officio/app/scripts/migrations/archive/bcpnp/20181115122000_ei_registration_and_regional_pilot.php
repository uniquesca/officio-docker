<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class EiRegistrationAndRegionalPilot extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 11, FormId, '', CURRENT_TIMESTAMP(), 'EI_Registration_Form_v3.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EI Registration Form v3', '', ''
            FROM FormVersion
            WHERE FileName = 'EI Registration Form';
        "
        );

        $this->execute(
            "
            UPDATE client_types_forms ctf
            INNER JOIN client_types ct ON ct.client_type_id = ctf.client_type_id 
            SET ctf.form_version_id = 11 
            WHERE ct.client_type_name = 'Business Immigration Registration';
        "
        );

        $this->execute(
            "
            UPDATE company_default_options
            SET default_option_name = 'basic::registration'
            WHERE default_option_name = 'business::registration';
        "
        );

        $this->execute(
            "
            UPDATE company_default_options
            SET default_option_name = 'basic::application'
            WHERE default_option_name = 'business::application';
        "
        );

        $this->execute(
            "
            UPDATE company_default_options
            SET default_option_name = 'basic::reviewRequest'
            WHERE default_option_name = 'business::reviewRequest';
        "
        );

        $this->execute(
            "
            UPDATE company_default_options
            SET default_option_name = 'basic::reviewRequest'
            WHERE default_option_name = 'business::reviewRequest';
        "
        );

        $this->execute(
            "
            INSERT INTO company_default_options (`company_id`, `default_option_type`, `default_option_name`, `default_option_abbreviation`, `default_option_order`)
                SELECT company_id, 'categories', 'regional-pilot::registration', 'BCER', 0
                FROM company
                
                UNION
                
                SELECT company_id, 'categories', 'regional-pilot::application', 'BCEA', 0
                FROM company
                
                UNION
                
                SELECT company_id, 'categories', 'regional-pilot::reviewRequest', 'BCERR', 0
                FROM company;
        "
        );

        $this->execute(
            "
            INSERT INTO `FormSynField` (`FieldName`) VALUES
            ('syncA_Ext_Category'),
            ('syncA_Ext_LastName'),
            ('syncA_Ext_FirstName'),
            ('syncA_Ext_DateOfBirth'),
            ('syncA_Ext_CitizenshipCountry'),
            ('syncA_Ext_Phone'),
            ('syncA_Ext_Email'),
            ('BCPNP_ResAddrLine'),
            ('BCPNP_ResCity'),
            ('BCPNP_ResProvince'),
            ('BCPNP_ResCountry'),
            ('BCPNP_ResPostal'),
            ('syncA_InvestmentTotal'),
            ('syncA_BusPropType'),
            ('syncA_BusOwnerPercent'),
            ('syncA_JobsSectionTotal'),
            ('syncA_BusMunicipalityBasic'),
            ('syncA_BusMunicipalityPilot'),
            ('syncA_BusRegionDistBasic'),
            ('syncA_BusRegionDistPilot'),
            ('syncA_BusNAICS'),
            ('syncA_BusRegionalNAICS'),
            ('syncA_BusRoleNOC'),
            ('syncA_SpouseLastName'),
            ('syncA_SpouseFirstName');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'phone', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_Ext_Phone';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'email', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_Ext_Email';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'last_name', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_Ext_LastName';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'first_name', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_Ext_FirstName';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'DOB', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_Ext_DateOfBirth';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'country_of_passport', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_Ext_CitizenshipCountry';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_addr', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'BCPNP_ResAddrLine';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_city', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'BCPNP_ResCity';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_province', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'BCPNP_ResProvince';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_county', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'BCPNP_ResCountry';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'res_postal_code', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'BCPNP_ResPostal';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusPropType'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusPropType';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'Partner_InvestTotal'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_InvestmentTotal';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusOwnerPercent'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusOwnerPercent';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'JobsTotal'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_JobsSectionTotal';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusRegionDist'
            FROM `FormSynField`
            WHERE `FieldName` IN ('syncA_BusRegionDistBasic', 'syncA_BusRegionDistPilot');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusMunicipality'
            FROM `FormSynField`
            WHERE `FieldName` IN ('syncA_BusMunicipalityBasic', 'syncA_BusMunicipalityPilot');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusNAICS'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusNAICS';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusNAICS'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusRegionalNAICS';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusRoleNOC'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusRoleNOC';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'spouse_last_name', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_SpouseLastName';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `parent_member_type`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'spouse_first_name', 8
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_SpouseFirstName';
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