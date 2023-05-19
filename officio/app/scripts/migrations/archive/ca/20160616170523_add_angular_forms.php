<?php

use Officio\Migration\AbstractMigration;

class AddAngularForms extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (140, 'forms', 'angular-forms', 'save');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (140, 'forms', 'angular-forms', 'load');");

        $this->execute("ALTER TABLE `FormMap` ADD COLUMN `parent_member_type` INT(2) UNSIGNED NOT NULL DEFAULT '3' AFTER `form_map_type`;");

        $this->execute("ALTER TABLE `FormMap` ADD CONSTRAINT `FK_formMap_members_types` FOREIGN KEY (`parent_member_type`) REFERENCES `members_types` (`member_type_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='lName';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='address_1';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='address_2';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='city';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='state';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='zip_code';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='country';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=8 WHERE  `parent_member_type`=3 AND `ToProfileFieldId`='phone_main';");
/*
        $this->execute("INSERT INTO `FormSynField` (`SynFieldId`, `FieldName`) VALUES
                        (1034, 'syncA_entity_name'),
                        (1035, 'syncA_trading_name'),
                        (1036, 'syncA_australian_business_number'),
                        (1037, 'syncA_australian_company_number'),
                        (1038, 'syncA_australian_registered_body_number'),
                        (1039, 'syncA_australian_securities_exchange_code'),
                        (1040, 'syncA_operational_since'),
                        (1041, 'syncA_industry_type'),
                        (1042, 'syncA_business_email'),
                        (1043, 'syncA_nomination_occupation'),
                        (1044, 'syncA_business_fax_number'),
                        (1045, 'syncA_business_address_1'),
                        (1046, 'syncA_business_address_2'),
                        (1047, 'syncA_business_town'),
                        (1048, 'syncA_business_state'),
                        (1049, 'syncA_business_postcode'),
                        (1050, 'syncA_business_country'),
                        (1051, 'syncA_business_phone_number');");

        $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 1034, 'main_applicant', 1034, 'main_applicant', 'lName', NULL, 7),
                        ('main_applicant', 1035, 'main_applicant', 1035, 'main_applicant', 'trading_name', NULL, 7),
                        ('main_applicant', 1036, 'main_applicant', 1036, 'main_applicant', 'australian_business_number', NULL, 7),
                        ('main_applicant', 1037, 'main_applicant', 1037, 'main_applicant', 'australian_company_number', NULL, 7),
                        ('main_applicant', 1038, 'main_applicant', 1038, 'main_applicant', 'australian_registered_body_number', NULL, 7),
                        ('main_applicant', 1039, 'main_applicant', 1039, 'main_applicant', 'australian_securities_exchange_code', NULL, 7),
                        ('main_applicant', 1040, 'main_applicant', 1040, 'main_applicant', 'operational_since', NULL, 7),
                        ('main_applicant', 1041, 'main_applicant', 1041, 'main_applicant', 'industry_type', NULL, 7),
                        ('main_applicant', 1042, 'main_applicant', 1042, 'main_applicant', 'emailAddress', NULL, 7),
                        ('main_applicant', 1043, 'main_applicant', 1043, 'main_applicant', 'nomination_occupation', NULL, 7),
                        ('main_applicant', 1044, 'main_applicant', 1044, 'main_applicant', 'fax_w', NULL, 7),
                        ('main_applicant', 1045, 'main_applicant', 1045, 'main_applicant', 'address_1', NULL, 7),
                        ('main_applicant', 1046, 'main_applicant', 1046, 'main_applicant', 'address_2', NULL, 7),
                        ('main_applicant', 1047, 'main_applicant', 1047, 'main_applicant', 'city', NULL, 7),
                        ('main_applicant', 1048, 'main_applicant', 1048, 'main_applicant', 'state', NULL, 7),
                        ('main_applicant', 1049, 'main_applicant', 1049, 'main_applicant', 'zip_code', NULL, 7),
                        ('main_applicant', 1050, 'main_applicant', 1050, 'main_applicant', 'country', NULL, 7),
                        ('main_applicant', 1051, 'main_applicant', 1051, 'main_applicant', 'phone_main', NULL, 7);");*/

    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=140 AND `module_id`='forms' AND `resource_id`='angular-forms' AND `resource_privilege`='save';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=140 AND `module_id`='forms' AND `resource_id`='angular-forms' AND `resource_privilege`='load';");

        $this->execute("ALTER TABLE `FormMap`
            DROP FOREIGN KEY `FK_formMap_members_types`;"
        );

        $this->execute("ALTER TABLE `FormMap`
            DROP COLUMN `parent_member_type`;"
        );

        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='lName';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='address_1';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='address_2';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='city';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='state';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='zip_code';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='country';");
        $this->execute("UPDATE `FormMap` SET `parent_member_type`=3 WHERE  `parent_member_type`=8 AND `ToProfileFieldId`='phone_main';");

//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1034 AND `FieldName`='syncA_entity_name';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1035 AND `FieldName`='syncA_trading_name';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1036 AND `FieldName`='syncA_australian_business_number';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1037 AND `FieldName`='syncA_australian_company_number';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1038 AND `FieldName`='syncA_australian_registered_body_number';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1039 AND `FieldName`='syncA_australian_securities_exchange_code';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1040 AND `FieldName`='syncA_operational_since';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1041 AND `FieldName`='syncA_industry_type';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1042 AND `FieldName`='syncA_business_email';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1043 AND `FieldName`='syncA_nomination_occupation';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1044 AND `FieldName`='syncA_business_fax_number';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1045 AND `FieldName`='syncA_business_address_1';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1046 AND `FieldName`='syncA_business_address_2';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1047 AND `FieldName`='syncA_business_town';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1048 AND `FieldName`='syncA_business_state';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1049 AND `FieldName`='syncA_business_postcode';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1050 AND `FieldName`='syncA_business_country';");
//        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1051 AND `FieldName`='syncA_business_phone_number';");
//
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1034 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1034 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='lName';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1035 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1035 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='trading_name';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1036 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1036 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='australian_business_number';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1037 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1037 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='australian_company_number';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1038 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1038 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='australian_registered_body_number';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1039 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1039 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='australian_securities_exchange_code';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1040 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1040 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='operational_since';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1041 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1041 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='industry_type';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1042 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1042 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='emailAddress';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1043 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1043 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='nomination_occupation';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1044 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1044 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='fax_w';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1045 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1045 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='address_1';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1046 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1046 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='address_2';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1047 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1047 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='city';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1048 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1048 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='state';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1049 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1049 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='zip_code';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1050 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1050 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='country';");
//        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1051 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1051 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='phone_main';");

    }
}