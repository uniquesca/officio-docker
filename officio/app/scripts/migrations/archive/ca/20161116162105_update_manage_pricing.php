<?php

use Officio\Migration\AbstractMigration;

class UpdateManagePricing extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `pricing_categories` (
                          `pricing_category_id` int(11) unsigned DEFAULT NULL,
                          `name` varchar(255) NOT NULL,
                          `expiry_date` datetime DEFAULT NULL,
                          `key_string` varchar(255) DEFAULT NULL,
                          KEY `pricing_category_id` (`pricing_category_id`)
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;");

        $this->execute("INSERT INTO `pricing_categories` (`pricing_category_id`, `name`, `expiry_date`, `key_string`) VALUES
                            (1, 'General', NULL, ''),
                            (2, 'Promotion 1', '2016-12-30 00:00:00', 'Dec2016');");

        $this->execute("CREATE TABLE `pricing_category_details` (
                          `pricing_category_detail_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `pricing_category_id` int(11) unsigned DEFAULT NULL,
                          `package_id` int(11) NOT NULL,
                          `price_storage_1_gb_annual` double NOT NULL,
                          `price_storage_1_gb_monthly` double NOT NULL,
                          `price_license_user_annual` double NOT NULL,
                          `price_license_user_monthly` double NOT NULL,
                          `price_package_2_years` double NOT NULL,
                          `price_package_monthly` double NOT NULL,
                          `price_package_yearly` double NOT NULL,
                          `users_add_over_limit` int(11) NOT NULL,
                          `user_included` int(11) NOT NULL,
                          `free_storage` int(11) NOT NULL,
                          PRIMARY KEY (`pricing_category_detail_id`),
                          KEY `FK_pricing_category_details_packages` (`package_id`),
                          KEY `FK_pricing_category_details_pricing_categories` (`pricing_category_id`),
                          CONSTRAINT `FK_pricing_category_details_packages` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                          CONSTRAINT `FK_pricing_category_details_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON DELETE CASCADE ON UPDATE CASCADE
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;");

        // TODO: check pricing
        $this->execute("INSERT INTO `pricing_category_details` (`pricing_category_detail_id`, `pricing_category_id`, `package_id`, `price_storage_1_gb_annual`, `price_storage_1_gb_monthly`, `price_license_user_annual`, `price_license_user_monthly`, `price_package_2_years`, `price_package_monthly`, `price_package_yearly`, `users_add_over_limit`, `user_included`, `free_storage`) VALUES
                            (1, 1, 1, 5, 0.5, 699, 69, 1275, 69, 699, 0, 1, 2),
                            (2, 1, 2, 5, 0.5, 699, 69, 1799, 99, 999, 1, 1, 10),
                            (3, 1, 3, 5, 0.5, 999, 99, 2380, 129, 1299, 1, 1, 50),
                            (4, 2, 1, 5, 0.5, 399, 39, 1275, 69, 699, 0, 1, 2),
                            (5, 2, 2, 5, 0.5, 399, 39, 1799, 99, 999, 1, 2, 10),
                            (6, 2, 3, 5, 0.5, 399, 39, 2380, 129, 1299, 1, 3, 50);");

        $this->execute("ALTER TABLE `prospects`
                            ADD COLUMN `pricing_category_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `free_storage`,
                            ADD CONSTRAINT `FK_prospects_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON UPDATE SET NULL ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `company_details`
                            ADD COLUMN `pricing_category_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `enable_case_management`,
                            ADD CONSTRAINT `FK_company_details_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON UPDATE SET NULL ON DELETE SET NULL;");
    }

    public function down()
    {

        $this->execute("ALTER TABLE `company_details` DROP FOREIGN KEY `FK_company_details_pricing_categories`;");
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `pricing_category_id`;");

        $this->execute("ALTER TABLE `prospects` DROP FOREIGN KEY `FK_prospects_pricing_categories`;");
        $this->execute("ALTER TABLE `prospects` DROP COLUMN `pricing_category_id`;");

        $this->execute("DROP TABLE `pricing_category_details`;");

        $this->execute("DROP TABLE `pricing_categories`;");
    }
}