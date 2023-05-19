<?php

use Phinx\Migration\AbstractMigration;

class AddDmPaymentDescriptionsVariables extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `u_variable` (`name`, `value`) VALUES 
            ('description_dm_certificate_of_naturalization_fee', 'Fee for Certificate of Naturalization'),
            ('description_dm_due_diligence_fee', 'Due Diligence Fee'),
            ('description_dm_expedited_passport_issue_fee', 'Expedited Passport Issue Fee'),
            ('description_dm_government_fund_fee', 'Government Fund'),
            ('description_dm_processing_fee', 'Processing Fee'),
            ('description_dm_real_estate_fee', 'Real Estate'),
            ('description_dm_system_access_fee', 'System Access Fee');"
        );
    }

    public function down()
    {
        $this->execute("DELETE FROM `u_variable` WHERE `name` LIKE 'description_dm%';");
    }
}
