<?php

use Officio\Migration\AbstractMigration;

class AddReplacingGeneralPricingOption extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `pricing_categories` ADD COLUMN `replacing_general` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `default_subscription_term`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `pricing_categories` DROP COLUMN `replacing_general`");
    }
}