<?php

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;

class AddNtnpRepresentativeFieldsMappings extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `FormSynField` (`SynFieldId`, `FieldName`) VALUES
                        (1153, 'syncA_representative_first_name'),
                        (1154, 'syncA_representative_last_name'),
                        (1155, 'syncA_representative_organization'),
                        (1156, 'syncA_representative_phone'),
                        (1157, 'syncA_representative_phone_alt'),
                        (1158, 'syncA_representative_email'),
                        (1159, 'syncA_representative_city'),
                        (1160, 'syncA_representative_state'),
                        (1161, 'syncA_representative_postal_code'),
                        (1162, 'syncA_representative_country'),
                        (1163, 'syncA_representative_address_line');"
        );

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 1153, 'main_applicant', 1153, 'main_applicant', 'applicant_representative_given_name', NULL, 8),
                        ('main_applicant', 1154, 'main_applicant', 1154, 'main_applicant', 'applicant_representative_family_name', NULL, 8),
                        ('main_applicant', 1155, 'main_applicant', 1155, 'main_applicant', 'applicant_representative_name_of_firm', NULL, 8),
                        ('main_applicant', 1156, 'main_applicant', 1156, 'main_applicant', 'applicant_representative_primary_phone', NULL, 8),
                        ('main_applicant', 1157, 'main_applicant', 1157, 'main_applicant', 'applicant_representative_secondary_phone', NULL, 8),
                        ('main_applicant', 1158, 'main_applicant', 1158, 'main_applicant', 'applicant_representative_email_address', NULL, 8),
                        ('main_applicant', 1159, 'main_applicant', 1159, 'main_applicant', 'applicant_representative_city', NULL, 8),
                        ('main_applicant', 1160, 'main_applicant', 1160, 'main_applicant', 'applicant_representative_state', NULL, 8),
                        ('main_applicant', 1161, 'main_applicant', 1161, 'main_applicant', 'applicant_representative_postal', NULL, 8),
                        ('main_applicant', 1162, 'main_applicant', 1162, 'main_applicant', 'applicant_representative_country', NULL, 8),
                        ('main_applicant', 1163, 'main_applicant', 1163, 'main_applicant', 'applicant_representative_address_line', NULL, 8);"
        );

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 1153, 'main_applicant', 1153, 'main_applicant', 'employer_representative_given_name', NULL, 7),
                        ('main_applicant', 1154, 'main_applicant', 1154, 'main_applicant', 'employer_representative_family_name', NULL, 7),
                        ('main_applicant', 1155, 'main_applicant', 1155, 'main_applicant', 'employer_representative_name_of_firm', NULL, 7),
                        ('main_applicant', 1156, 'main_applicant', 1156, 'main_applicant', 'employer_representative_primary_phone', NULL, 7),
                        ('main_applicant', 1157, 'main_applicant', 1157, 'main_applicant', 'employer_representative_secondary_phone', NULL, 7),
                        ('main_applicant', 1158, 'main_applicant', 1158, 'main_applicant', 'employer_representative_email_address', NULL, 7),
                        ('main_applicant', 1159, 'main_applicant', 1159, 'main_applicant', 'employer_representative_city', NULL, 7),
                        ('main_applicant', 1160, 'main_applicant', 1160, 'main_applicant', 'employer_representative_state', NULL, 7),
                        ('main_applicant', 1161, 'main_applicant', 1161, 'main_applicant', 'employer_representative_postal', NULL, 7),
                        ('main_applicant', 1162, 'main_applicant', 1162, 'main_applicant', 'employer_representative_country', NULL, 7),
                        ('main_applicant', 1163, 'main_applicant', 1163, 'main_applicant', 'employer_representative_address_line', NULL, 7);"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1153 AND `FieldName`='syncA_representative_first_name';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1154 AND `FieldName`='syncA_representative_last_name';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1155 AND `FieldName`='syncA_representative_organization';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1156 AND `FieldName`='syncA_representative_phone';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1157 AND `FieldName`='syncA_representative_phone_alt';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1158 AND `FieldName`='syncA_representative_email';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1159 AND `FieldName`='syncA_representative_city';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1160 AND `FieldName`='syncA_representative_state';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1161 AND `FieldName`='syncA_representative_postal_code';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1162 AND `FieldName`='syncA_representative_country';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1163 AND `FieldName`='syncA_representative_address_line';");

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1153 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1153 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_given_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1154 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1154 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_family_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1155 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1155 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_name_of_firm';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1156 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1156 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_primary_phone';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1157 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1157 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_secondary_phone';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1158 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1158 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_email_address';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1159 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1159 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_city';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1160 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1160 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_state';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1161 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1161 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_postal';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1162 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1162 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_country';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1163 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1163 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='applicant_representative_address_line';"
        );

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1153 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1153 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_given_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1154 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1154 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_family_name';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1155 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1155 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_name_of_firm';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1156 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1156 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_primary_phone';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1157 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1157 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_secondary_phone';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1158 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1158 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_email_address';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1159 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1159 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_city';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1160 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1160 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_state';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1161 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1161 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_postal';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1162 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1162 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_country';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1163 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1163 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employer_representative_address_line';"
        );
    }
}