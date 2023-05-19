<?php

use Officio\Migration\AbstractMigration;

class AddStarterSubscription extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `subscriptions` (`subscription_id`, `subscription_name`, `subscription_hidden`, `subscription_order`) VALUES
                ('starter', 'OfficioStarter', 'N', 0);"
        );


        $this->execute("INSERT INTO `subscriptions_packages` (`subscription_id`, `package_id`) VALUES ('starter', 1)");

        // Copy settings from Lite subscription
        $this->execute(
            "INSERT INTO `pricing_category_details` (
                `pricing_category_id`, 
                `subscription_id`, 
                `price_storage_1_gb_annual`, 
                `price_storage_1_gb_monthly`, 
                `price_license_user_annual`, 
                `price_license_user_monthly`, 
                `price_package_2_years`, 
                `price_package_monthly`,
                `price_package_yearly`,
                `users_add_over_limit`, 
                `user_included`, 
                `free_storage`,
                `free_clients`
            ) 
            SELECT 
                `pricing_category_id`, 
                'starter',
                `price_storage_1_gb_annual`, 
                `price_storage_1_gb_monthly`, 
                `price_license_user_annual`, 
                `price_license_user_monthly`, 
                `price_package_2_years`, 
                `price_package_monthly`,
                `price_package_yearly`,
                `users_add_over_limit`, 
                `user_included`, 
                `free_storage`,
                10
            FROM pricing_category_details WHERE `subscription_id` = 'lite';");

        $this->execute('ALTER TABLE `company_details` ADD CONSTRAINT `FK_company_details_subscriptions` FOREIGN KEY (`subscription`) REFERENCES `subscriptions` (`subscription_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
        $this->execute('ALTER TABLE `prospects` ADD CONSTRAINT `FK_prospects_subscriptions` FOREIGN KEY (`package_type`) REFERENCES `subscriptions` (`subscription_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `prospects` DROP FOREIGN KEY `FK_prospects_subscriptions`;');
        $this->execute('ALTER TABLE `company_details` DROP FOREIGN KEY `FK_company_details_subscriptions`;');
        $this->execute("DELETE FROM `subscriptions` WHERE `subscription_id`='starter'");
    }
}