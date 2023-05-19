<?php

use Phinx\Migration\AbstractMigration;

class Pnp700 extends AbstractMigration
{
    public function up()
    {
        // Add ARR WP Signed date mapping
        $this->execute(
            "           
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ei_wp_client_signed_on'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_App_WorkPermit_DateSigned';
        "
        );
    }

    public function down()
    {
    }
}