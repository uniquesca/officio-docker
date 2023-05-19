<?php

use Phinx\Migration\AbstractMigration;

class AddDmPaymentVariables extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_variable`
	        CHANGE COLUMN `name` `name` VARCHAR(255) NOT NULL DEFAULT '' FIRST;");

        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES 
            ('price_dm_certificate_of_naturalization_fee', '750.00'),
            ('price_dm_due_diligence_fee_dependent', '4000.00'),
            ('price_dm_due_diligence_fee_main_applicant', '7500.00'),
            ('price_dm_due_diligence_fee_spouse', '7500.00'),
            ('price_dm_expedited_passport_issue_fee', '1200.00'),
            ('price_dm_government_fund_fee_additional_dependent', '50000.00'),
            ('price_dm_government_fund_fee_main_and_spouse', '175000.00'),
            ('price_dm_government_fund_fee_single_applicant', '100000.00'),
            ('price_dm_government_fund_fee_up_to_4_persons', '200000.00'),
            ('price_dm_government_fund_fee_up_to_5_persons', '200000.00'),
            ('price_dm_processing_fee', '3000.00'),
            ('price_dm_real_estate_fee_additional_dependent_over_18_years', '25000.00'),
            ('price_dm_real_estate_fee_additional_dependent_under_18_years', '20000.00'),
            ('price_dm_real_estate_fee_single_applicant', '50000.00'),
            ('price_dm_real_estate_fee_up_to_4_persons', '75000.00'),
            ('price_dm_real_estate_fee_up_to_6_persons', '100000.00'),
            ('price_dm_system_access_fee', '1000.00');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `u_variable` WHERE `name` LIKE 'price_dm%';");

        $this->execute("ALTER TABLE `u_variable`
	        CHANGE COLUMN `name` `name` VARCHAR(48) NOT NULL DEFAULT '' FIRST;");
    }
}
