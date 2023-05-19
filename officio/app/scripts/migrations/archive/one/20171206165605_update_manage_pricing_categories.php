<?php

use Phinx\Migration\AbstractMigration;

class UpdateManagePricingCategories extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `pricing_category_details`
	        DROP FOREIGN KEY `FK_pricing_category_details_pricing_categories`;");

        $this->execute("ALTER TABLE `company_details`
	        DROP FOREIGN KEY `FK_company_details_pricing_categories`;");

        $this->execute("ALTER TABLE `prospects`
	        DROP FOREIGN KEY `FK_prospects_pricing_categories`;");

        $this->execute("ALTER TABLE `pricing_categories`
	        CHANGE COLUMN `pricing_category_id` `pricing_category_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
	        CHANGE COLUMN `expiry_date` `expiry_date` DATE NULL DEFAULT NULL AFTER `name`;");

        $this->execute("ALTER TABLE `pricing_category_details`
            ADD CONSTRAINT `FK_pricing_category_details_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON DELETE CASCADE ON UPDATE CASCADE;");

        $this->execute("ALTER TABLE `company_details`
            ADD CONSTRAINT `FK_company_details_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON UPDATE SET NULL ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `prospects`
            ADD CONSTRAINT `FK_prospects_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON UPDATE SET NULL ON DELETE SET NULL;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `pricing_category_details`
	        DROP FOREIGN KEY `FK_pricing_category_details_pricing_categories`;");

        $this->execute("ALTER TABLE `company_details`
	        DROP FOREIGN KEY `FK_company_details_pricing_categories`;");

        $this->execute("ALTER TABLE `prospects`
	        DROP FOREIGN KEY `FK_prospects_pricing_categories`;");

        $this->execute("ALTER TABLE `pricing_categories`
	        CHANGE COLUMN `pricing_category_id` `pricing_category_id` INT(11) unsigned DEFAULT NULL,
	        CHANGE COLUMN `expiry_date` `expiry_date` DATETIME NULL DEFAULT NULL AFTER `name`;");

        $this->execute("ALTER TABLE `pricing_category_details`
            ADD CONSTRAINT `FK_pricing_category_details_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON DELETE CASCADE ON UPDATE CASCADE;");

        $this->execute("ALTER TABLE `company_details`
            ADD CONSTRAINT `FK_company_details_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON UPDATE SET NULL ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `prospects`
            ADD CONSTRAINT `FK_prospects_pricing_categories` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`pricing_category_id`) ON UPDATE SET NULL ON DELETE SET NULL;");
    }
}