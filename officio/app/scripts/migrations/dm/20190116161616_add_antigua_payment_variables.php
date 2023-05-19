<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Migration\AbstractMigration;

class AddAntiguaPaymentVariables extends AbstractMigration
{
    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES 
            ('price_antigua_national_development_fund_fee_main_applicant', '25000.00'),
            ('price_antigua_national_development_fund_fee_up_to_4_persons', '25000.00'),
            ('price_antigua_national_development_fund_fee_additional_dependent', '15000.00'),
            ('price_antigua_national_development_fund_contribution_main_applicant', '100000.00'),
            ('price_antigua_national_development_fund_contribution_up_to_4_persons', '100000.00'),
            ('price_antigua_national_development_fund_contribution_more_than_4_persons', '125000.00'),
                                                  
            ('price_antigua_real_estate_fee_main_applicant', '50000.00'),
            ('price_antigua_real_estate_fee_up_to_4_persons', '50000.00'),
            ('price_antigua_real_estate_fee_additional_dependent', '15000.00'),
            ('price_antigua_real_estate_contribution_main_applicant', '400000.00'),
            ('price_antigua_real_estate_contribution_up_to_4_persons', '400000.00'),
            ('price_antigua_real_estate_contribution_more_than_4_persons', '400000.00'),
            
            ('price_antigua_due_diligence_fee_main_applicant', '7500.00'),
            ('price_antigua_due_diligence_fee_spouse', '7500.00'),
            ('price_antigua_due_diligence_fee_dependent_18_and_over', '4000.00'),
            ('price_antigua_due_diligence_fee_dependent_12_to_17', '2000.00'),
            ('price_antigua_due_diligence_fee_dependent_parent_over_65', '4000.00'),
                                                  
            ('price_antigua_passport_fee', '300.00'),
            ('price_antigua_system_access_fee', '1000.00'),
            
            ('description_antigua_system_access_fee', 'System Access Fee'),
            ('description_antigua_passport_fee', 'Passport Fee'),
            ('description_antigua_due_diligence_fee', 'Due Diligence Fee'),
            ('description_antigua_processing_fee', 'Processing Fee'),
            ('description_antigua_development_fund_contribution', 'NDF Contribution'),
            ('description_antigua_real_estate_contribution', 'Real Estate Contribution')
            ;
        ");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        $cache->removeItem('settings');
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("DELETE FROM `u_variable` WHERE `name` LIKE 'price_antigua%';");
        $this->execute("DELETE FROM `u_variable` WHERE `name` LIKE 'description_antigua%';");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        $cache->removeItem('settings');
    }
}
