<?php

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;

class AddNtnpApplicantFieldsMappings extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `FormSynField` (`SynFieldId`, `FieldName`) VALUES
                        (1103, 'syncA_Ext_FirstName'),
                        (1104, 'syncA_Ext_LastName'),
                        (1105, 'syncA_Gender'),
                        (1106, 'syncA_BirthPlaceCity'),
                        (1107, 'syncA_BirthPlaceCountry'),
                        (1108, 'syncA_Citizenship'),
                        (1109, 'syncA_PassportNo'),
                        (1110, 'syncA_PassportIssueDate'),
                        (1111, 'syncA_PassportExpiryDate'),
                        (1112, 'syncA_ResAddrLine'),
                        (1113, 'syncA_ResCity'),
                        (1114, 'syncA_ResProvince'),
                        (1115, 'syncA_ResCountry'),
                        (1116, 'syncA_ResPostal'),
                        (1117, 'syncA_Telephone'),
                        (1118, 'syncA_Cellular'),
                        (1119, 'syncA_MailingAddrLine'),
                        (1120, 'syncA_MailingCity'),
                        (1121, 'syncA_MailingProvince'),
                        (1122, 'syncA_MailingCountry'),
                        (1123, 'syncA_MailingPostal'),
                        (1124, 'syncA_ExpressEntryProfileNumber'),
                        (1125, 'syncA_SubmissionExpireDate'),
                        (1126, 'syncA_CandidateFirstName'),
                        (1127, 'syncA_CandidateLastName');"
        );

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 1103, 'main_applicant', 1103, 'main_applicant', 'first_name', NULL, 8),
                        ('main_applicant', 1104, 'main_applicant', 1104, 'main_applicant', 'last_name', NULL, 8),
                        ('main_applicant', 4, 'main_applicant', 4, 'main_applicant', 'DOB', NULL, 8),
                        ('main_applicant', 1105, 'main_applicant', 1105, 'main_applicant', 'gender', NULL, 8),
                        ('main_applicant', 1106, 'main_applicant', 1106, 'main_applicant', 'city_of_birth', NULL, 8),
                        ('main_applicant', 1107, 'main_applicant', 1107, 'main_applicant', 'country_of_birth', NULL, 8),
                        ('main_applicant', 1108, 'main_applicant', 1108, 'main_applicant', 'country_of_citizenship', NULL, 8),
                        ('main_applicant', 1109, 'main_applicant', 1109, 'main_applicant', 'passport_number', NULL, 8),
                        ('main_applicant', 1110, 'main_applicant', 1110, 'main_applicant', 'passport_issue_date', NULL, 8),
                        ('main_applicant', 1111, 'main_applicant', 1111, 'main_applicant', 'passport_expiry_date', NULL, 8),
                        ('main_applicant', 1112, 'main_applicant', 1112, 'main_applicant', 'address_line_1', NULL, 8),
                        ('main_applicant', 1113, 'main_applicant', 1113, 'main_applicant', 'city', NULL, 8),
                        ('main_applicant', 1114, 'main_applicant', 1114, 'main_applicant', 'state', NULL, 8),
                        ('main_applicant', 1115, 'main_applicant', 1115, 'main_applicant', 'country', NULL, 8),
                        ('main_applicant', 1116, 'main_applicant', 1116, 'main_applicant', 'zip_code', NULL, 8),
                        ('main_applicant', 1117, 'main_applicant', 1117, 'main_applicant', 'phone', NULL, 8),
                        ('main_applicant', 1118, 'main_applicant', 1118, 'main_applicant', 'mobile_phone', NULL, 8),
                        ('main_applicant', 135, 'main_applicant', 135, 'main_applicant', 'email', NULL, 8),
                        ('main_applicant', 1119, 'main_applicant', 1119, 'main_applicant', 'applicant_mailing_address_line', NULL, 8),
                        ('main_applicant', 1120, 'main_applicant', 1120, 'main_applicant', 'applicant_mailing_town', NULL, 8),
                        ('main_applicant', 1121, 'main_applicant', 1121, 'main_applicant', 'applicant_mailing_province', NULL, 8),
                        ('main_applicant', 1122, 'main_applicant', 1122, 'main_applicant', 'applicant_mailing_country', NULL, 8),
                        ('main_applicant', 1123, 'main_applicant', 1123, 'main_applicant', 'applicant_mailing_postal', NULL, 8),
                        ('main_applicant', 1124, 'main_applicant', 1124, 'main_applicant', 'ee_profile_number', NULL, 8),
                        ('main_applicant', 1125, 'main_applicant', 1125, 'main_applicant', 'ee_expiry_date', NULL, 8),
                        ('main_applicant', 1126, 'main_applicant', 1126, 'main_applicant', 'first_name', NULL, 8),
                        ('main_applicant', 1127, 'main_applicant', 1127, 'main_applicant', 'last_name', NULL, 8);"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1103 AND `FieldName`='syncA_Ext_FirstName';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1104 AND `FieldName`='syncA_Ext_LastName';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1105 AND `FieldName`='syncA_Gender';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1106 AND `FieldName`='syncA_BirthPlaceCity';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1107 AND `FieldName`='syncA_BirthPlaceCountry';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1108 AND `FieldName`='syncA_Citizenship';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1109 AND `FieldName`='syncA_PassportNo';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1110 AND `FieldName`='syncA_PassportIssueDate';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1111 AND `FieldName`='syncA_PassportExpiryDate';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1112 AND `FieldName`='syncA_ResAddrLine';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1113 AND `FieldName`='syncA_ResCity';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1114 AND `FieldName`='syncA_ResProvince';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1115 AND `FieldName`='syncA_ResCountry';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1116 AND `FieldName`='syncA_ResPostal';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1117 AND `FieldName`='syncA_Telephone';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1118 AND `FieldName`='syncA_Cellular';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1119 AND `FieldName`='syncA_MailingAddrLine';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1120 AND `FieldName`='syncA_MailingCity';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1121 AND `FieldName`='syncA_MailingProvince';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1122 AND `FieldName`='syncA_MailingCountry';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1123 AND `FieldName`='syncA_MailingPostal';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1124 AND `FieldName`='syncA_ExpressEntryProfileNumber';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1125 AND `FieldName`='syncA_SubmissionExpireDate';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1126 AND `FieldName`='syncA_CandidateFirstName';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1127 AND `FieldName`='syncA_CandidateLastName';");

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=4 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=4 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='DOB';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=135 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=135 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='email';"
        );

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1103 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1103 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='first_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1104 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1104 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='last_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1105 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1105 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='gender';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1106 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1106 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='city_of_birth';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1107 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1107 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='country_of_birth';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1108 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1108 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='country_of_citizenship';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1109 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1109 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='passport_number';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1110 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1110 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='passport_issue_date';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1111 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1111 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='passport_expiry_date';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1112 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1112 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='address_line_1';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1113 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1113 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='city';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1114 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1114 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='state';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1115 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1115 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='country';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1116 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1116 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='zip_code';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1117 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1117 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='phone';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1118 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1118 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='mobile_phone';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1119 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1119 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_mailing_address_line';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1120 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1120 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_mailing_town';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1121 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1121 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_mailing_province';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1122 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1122 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_mailing_country';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1123 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1123 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_mailing_postal';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1124 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1124 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='ee_profile_number';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1125 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1125 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='ee_expiry_date';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1126 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1126 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='first_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1127 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1127 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='last_name';"
        );
    }
}