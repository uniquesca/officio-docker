<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class EiApplicationV2 extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 12, FormId, '', CURRENT_TIMESTAMP(), 'EI_Application_Form_v2.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EI Application Form v2', '', ''
            FROM FormVersion
            WHERE FileName = 'EI Application Form';
        "
        );

        $this->execute(
            "
            UPDATE client_types_forms ctf
            INNER JOIN client_types ct ON ct.client_type_id = ctf.client_type_id 
            SET ctf.form_version_id = 12 
            WHERE ct.client_type_name = 'Business Immigration Application';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormSynField` (`FieldName`) VALUES
            ('syncA_ActiveApplication'),
            ('syncA_PreviousApp'),
            ('syncA_CurPrevApplicationsDetails'),
            ('syncA_CurResCountry'),
            ('syncA_InCanada'),
            ('syncA_IntendedResidence'),
            ('syncA_CanadaStatus'),
            ('syncA_StudentClientID'),
            ('syncA_WorkPermitClientID'),
            ('syncA_BusDesc'),
            ('syncA_JobsMaintained'),
            ('syncA_JobsCreated'),
            ('syncA_IsBusinessFranchise'),
            ('syncA_IsBusinessFarmAgro'),
            ('syncA_IsBusinessPartnershipLocal'),
            ('syncA_IsBusinessPartnershipBC'),
            ('syncA_IsBusinessProposingKeyStaff'),
            ('syncA_TotalPInvestment'),
            ('syncA_SpouseDOB'),
            ('syncA_SpouseBirthPlace'),
            ('syncA_SpouseSex'),
            ('syncA_SpouseCitizenship');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_has_active_app'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_ActiveApplication';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_has_pnp_active_app'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_PreviousApp';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_active_app_details'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_CurPrevApplicationsDetails';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_cur_res_country'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_CurResCountry';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'visitorID'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_VisitorID';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'inCanada'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_InCanada';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'intendedResidence'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_IntendedResidence';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'inCanadaStatus'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_CanadaStatus';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'studyPermitID'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_StudentClientID';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'workPermitID'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_WorkPermitClientID';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_bus_desc'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusDesc';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_jobs_maint'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_JobsMaintained';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_jobs_created'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_JobsCreated';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_bus_is_franchise'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_IsBusinessFranchise';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_bus_is_farm'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_IsBusinessFarmAgro';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_bus_is_local_partner'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_IsBusinessPartnershipLocal';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_bus_is_co_pnp_partner'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_IsBusinessPartnershipBC';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_app_bus_key_staff'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_IsBusinessProposingKeyStaff';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'PropInvest_Total'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_TotalPInvestment';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'spouse_dob'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_SpouseDOB';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'spouse_birthplace'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_SpouseBirthPlace';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'spouse_sex'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_SpouseSex';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'spouse_citizenship'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_SpouseCitizenship';
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