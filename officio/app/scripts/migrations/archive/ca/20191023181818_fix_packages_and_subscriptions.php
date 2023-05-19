<?php

use Officio\Migration\AbstractMigration;

class FixPackagesAndSubscriptions extends AbstractMigration
{
    public function up()
    {
        // Company website
        $this->execute("UPDATE `packages_details` SET `package_id`=2 WHERE `rule_id`=300;");

        // Switch all companies from "ultimate plus" to the "ultimate" subscription
        $this->execute("UPDATE `company_details` SET `subscription`='ultimate' WHERE `subscription`='ultimate_plus'");

        // Switch all companies from "starter" to the "lite" subscription
        $this->execute("UPDATE `company_details` SET `subscription`='lite' WHERE `subscription`='starter'");


        // Delete extra/old subscriptions
        $this->execute("DELETE FROM `subscriptions` WHERE `subscription_id` IN ('starter', 'pro13', 'ultimate_plus')");
    }

    public function down()
    {
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id`=300;");
    }
}
