<?php

use Officio\Migration\AbstractMigration;

class AdditionalMappings extends AbstractMigration
{
    protected $clearAclCache = true;

    public function up()
    {
        $this->execute("
            INSERT INTO FormSynField (FieldName) VALUES
              ('syncA_FamMembers'),
              ('syncA_App_FamMembers');
        ");

        $this->execute("
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'famMembers' FROM `FormSynField` WHERE `FieldName` = 'syncA_FamMembers' UNION
              SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'famMembers' FROM `FormSynField` WHERE `FieldName` = 'syncA_App_FamMembers';
        ");
    }

    public function down()
    {

    }
}