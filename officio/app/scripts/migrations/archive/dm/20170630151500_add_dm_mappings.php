<?php

use Phinx\Migration\AbstractMigration;

class AddDmMappings extends AbstractMigration
{
    public function up()
    {
        $this->query("INSERT INTO FormSynField(FieldName) VALUES ('DM_4_email');");
        $this->query("
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'country_of_birth' FROM `FormSynField` WHERE `FieldName` = 'syncA_POB_country' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'country_of_residence' FROM `FormSynField` WHERE `FieldName` = 'syncA_country_of_residence' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'country_of_citizenship' FROM `FormSynField` WHERE `FieldName` = 'syncA_country_of_citizenship' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'passport_issuing_country' FROM `FormSynField` WHERE `FieldName` = 'syncA_passport_country_of_issue' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'passport_issue_date' FROM `FormSynField` WHERE `FieldName` = 'syncA_passport_issue_date' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'passport_expiry_date' FROM `FormSynField` WHERE `FieldName` = 'syncA_passport_expiry_date' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'email_1' FROM `FormSynField` WHERE `FieldName` = 'DM_4_email' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'passport_number' FROM `FormSynField` WHERE `FieldName` = 'syncA_passport_number';              
        ");
    }

    public function down()
    {
    }
}