<?php

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;

class AddNtnpEmployerFieldsMappings extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `FormSynField` (`SynFieldId`, `FieldName`) VALUES
                        (1128, 'syncA_CompanyName'),
                        (1129, 'syncA_OperatingAs'),
                        (1130, 'syncA_WorkCompWebsite'),
                        (1131, 'syncA_Owner'),
                        (1132, 'syncA_TypeOfCompany'),
                        (1133, 'syncA_DateEstablished'),
                        (1134, 'syncA_NumberOfEmployees'),
                        (1135, 'syncA_NumberOfForeignEmployees'),
                        (1136, 'syncA_PublicPrivateCompany'),
                        (1137, 'syncA_PrimaryLanguageOfBusiness'),
                        (1138, 'syncA_BusAddrLine'),
                        (1139, 'syncA_BusCity'),
                        (1140, 'syncA_BusProvince'),
                        (1141, 'syncA_BusCountry'),
                        (1142, 'syncA_BusPostal'),
                        (1143, 'syncA_BusMailingAddrLine'),
                        (1144, 'syncA_BusMailingCity'),
                        (1145, 'syncA_BusMailingProvince'),
                        (1146, 'syncA_BusMailingCountry'),
                        (1147, 'syncA_BusMailingPostal'),
                        (1148, 'syncA_ContactName'),
                        (1149, 'syncA_ContactTitle'),
                        (1150, 'syncA_ContactEmailAddress'),
                        (1151, 'syncA_ContactPhoneNumber'),
                        (1152, 'syncA_ContactFaxNumber');"
        );

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 1128, 'main_applicant', 1128, 'main_applicant', 'entity_name', NULL, 7),
                        ('main_applicant', 1129, 'main_applicant', 1129, 'main_applicant', 'employer_operating_as', NULL, 7),
                        ('main_applicant', 1130, 'main_applicant', 1130, 'main_applicant', 'employer_company_website', NULL, 7),
                        ('main_applicant', 1131, 'main_applicant', 1131, 'main_applicant', 'employer_company_owners', NULL, 7),
                        ('main_applicant', 1132, 'main_applicant', 1132, 'main_applicant', 'type_of_company', NULL, 7),
                        ('main_applicant', 1133, 'main_applicant', 1133, 'main_applicant', 'employer_date_company_established', NULL, 7),
                        ('main_applicant', 1134, 'main_applicant', 1134, 'main_applicant', 'employer_number_of_employees', NULL, 7),
                        ('main_applicant', 1135, 'main_applicant', 1135, 'main_applicant', 'employer_number_of_foreign_workers', NULL, 7),
                        ('main_applicant', 1136, 'main_applicant', 1136, 'main_applicant', 'employer_company_type', NULL, 7),
                        ('main_applicant', 1137, 'main_applicant', 1137, 'main_applicant', 'employer_primary_language_of_business', NULL, 7),
                        ('main_applicant', 1138, 'main_applicant', 1138, 'main_applicant', 'employer_address_line', NULL, 7),
                        ('main_applicant', 1139, 'main_applicant', 1139, 'main_applicant', 'employer_town', NULL, 7),
                        ('main_applicant', 1140, 'main_applicant', 1140, 'main_applicant', 'employer_province', NULL, 7),
                        ('main_applicant', 1141, 'main_applicant', 1141, 'main_applicant', 'employer_country', NULL, 7),
                        ('main_applicant', 1142, 'main_applicant', 1142, 'main_applicant', 'employer_postal', NULL, 7),
                        ('main_applicant', 1143, 'main_applicant', 1143, 'main_applicant', 'employer_mailing_address_line', NULL, 7),
                        ('main_applicant', 1144, 'main_applicant', 1144, 'main_applicant', 'employer_mailing_town', NULL, 7),
                        ('main_applicant', 1145, 'main_applicant', 1145, 'main_applicant', 'employer_mailing_province', NULL, 7),
                        ('main_applicant', 1146, 'main_applicant', 1146, 'main_applicant', 'employer_mailing_country', NULL, 7),
                        ('main_applicant', 1147, 'main_applicant', 1147, 'main_applicant', 'employer_mailing_postal', NULL, 7),
                        ('main_applicant', 1148, 'main_applicant', 1148, 'main_applicant', 'employer_contact', NULL, 7),
                        ('main_applicant', 1149, 'main_applicant', 1149, 'main_applicant', 'employer_contact_title', NULL, 7),
                        ('main_applicant', 1150, 'main_applicant', 1150, 'main_applicant', 'employer_contact_email', NULL, 7),
                        ('main_applicant', 1151, 'main_applicant', 1151, 'main_applicant', 'employer_contact_phone', NULL, 7),
                        ('main_applicant', 1152, 'main_applicant', 1152, 'main_applicant', 'employer_contact_fax', NULL, 7);"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1128 AND `FieldName`='syncA_CompanyName';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1129 AND `FieldName`='syncA_OperatingAs';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1130 AND `FieldName`='syncA_WorkCompWebsite';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1131 AND `FieldName`='syncA_Owner';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1132 AND `FieldName`='syncA_TypeOfCompany';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1133 AND `FieldName`='syncA_DateEstablished';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1134 AND `FieldName`='syncA_NumberOfEmployees';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1135 AND `FieldName`='syncA_NumberOfForeignEmployees';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1136 AND `FieldName`='syncA_PublicPrivateCompany';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1137 AND `FieldName`='syncA_PrimaryLanguageOfBusiness';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1138 AND `FieldName`='syncA_BusAddrLine';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1139 AND `FieldName`='syncA_BusCity';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1140 AND `FieldName`='syncA_BusProvince';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1141 AND `FieldName`='syncA_BusCountry';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1142 AND `FieldName`='syncA_BusPostal';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1143 AND `FieldName`='syncA_BusMailingAddrLine';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1144 AND `FieldName`='syncA_BusMailingCity';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1145 AND `FieldName`='syncA_BusMailingProvince';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1146 AND `FieldName`='syncA_BusMailingCountry';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1147 AND `FieldName`='syncA_BusMailingPostal';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1148 AND `FieldName`='syncA_ContactName';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1149 AND `FieldName`='syncA_ContactTitle';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1150 AND `FieldName`='syncA_ContactEmailAddress';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1151 AND `FieldName`='syncA_ContactPhoneNumber';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1152 AND `FieldName`='syncA_ContactFaxNumber';");

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1128 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1128 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='entity_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1129 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1129 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_operating_as';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1130 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1130 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_company_website';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1131 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1131 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_company_owners';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1132 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1132 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='type_of_company';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1133 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1133 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_date_company_established';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1134 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1134 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_number_of_employees';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1135 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1135 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_number_of_foreign_workers';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1136 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1136 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_company_type';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1137 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1137 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_primary_language_of_business';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1138 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1138 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_address_line';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1139 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1139 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_town';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1140 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1140 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_province';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1141 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1141 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_country';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1142 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1142 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_postal';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1143 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1143 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_mailing_address_line';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1144 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1144 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_mailing_town';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1145 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1145 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_mailing_province';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1146 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1146 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_mailing_country';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1147 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1147 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_mailing_postal';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1148 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1148 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_contact';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1149 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1149 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_contact_title';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1150 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1150 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_contact_email';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1151 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1151 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_contact_phone';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1152 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1152 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_contact_fax';"
        );
    }
}