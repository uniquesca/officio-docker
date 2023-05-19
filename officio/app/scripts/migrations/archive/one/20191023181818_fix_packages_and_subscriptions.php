<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class FixPackagesAndSubscriptions extends AbstractMigration
{
    public function up()
    {
        // Prospects
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=200;");

        // Manage Prospects
        $this->execute("UPDATE `packages_details` SET `package_id`=2 WHERE `rule_id`=1190;");

        // Company website
        $this->execute("UPDATE `packages_details` SET `package_id`=2 WHERE `rule_id`=1400;");

        // Client Documents + Settings
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=60;");
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=61;");

        // Calendar
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=150;");

        // Mail
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=160;");

        // My docs
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=100;");

        // Trust Account
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=110;");

        // Client's Accounting
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=70;");
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=(SELECT rule_id FROM acl_rules WHERE rule_check_id = 'clients-accounting-ft-error-correction');");

        // Agents
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id`=120;");


        // Switch all companies from "ultimate plus" to the "ultimate" subscription
        $this->execute("UPDATE `company_details` SET `subscription`='ultimate' WHERE `subscription`='ultimate_plus'");

        // Switch all companies from "starter" to the "lite" subscription
        $this->execute("UPDATE `company_details` SET `subscription`='lite' WHERE `subscription`='starter'");


        // Delete extra/old subscriptions
        $this->execute("DELETE FROM `subscriptions` WHERE `subscription_id` IN ('starter', 'pro13', 'ultimate_plus')");


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("UPDATE `packages_details` SET `package_id`=4 WHERE `rule_id`=200;");
        $this->execute("UPDATE `packages_details` SET `package_id`=4 WHERE `rule_id`=1190;");
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id`=1400;");
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id`=60;");
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id`=61;");
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id`=150;");
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id`=160;");
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id`=100;");
        $this->execute("UPDATE `packages_details` SET `package_id`=2 WHERE `rule_id`=110;");
        $this->execute("UPDATE `packages_details` SET `package_id`=2 WHERE `rule_id`=70;");
        $this->execute("UPDATE `packages_details` SET `package_id`=2 WHERE `rule_id`=120;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}
