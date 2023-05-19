<?php

use Phinx\Migration\AbstractMigration;

class AddDefaultPricingSubscriptionTerm extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `pricing_categories`
	        ADD COLUMN `default_subscription_term` ENUM('annual','monthly') NOT NULL DEFAULT 'annual' AFTER `key_string`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `pricing_categories` DROP COLUMN `default_subscription_term`");
    }
}