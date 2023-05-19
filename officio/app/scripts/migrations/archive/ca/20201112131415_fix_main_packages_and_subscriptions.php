<?php

use Officio\Migration\AbstractMigration;

class FixMainPackagesAndSubscriptions extends AbstractMigration
{

    protected $clearCache = true;

    public function up()
    {
        // Trust Account
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id` IN (101, 102, 103, 104, 105, 110);");

        // Client's TimeTracker
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id` IN (80, 81, 82, 83, 84, 85);");

        // Marketplace Profiles
        $this->execute("UPDATE `packages_details` SET `package_id`=3 WHERE `rule_id` IN (SELECT rule_id FROM `acl_rules` WHERE rule_check_id = 'manage-marketplace');");
    }

    public function down()
    {
        $this->execute("UPDATE `packages_details` SET `package_id`=2 WHERE `rule_id` IN (101, 102, 103, 104, 105, 110);");
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id` IN (80, 81, 82, 83, 84, 85);");
        $this->execute("UPDATE `packages_details` SET `package_id`=1 WHERE `rule_id` IN (SELECT rule_id FROM `acl_rules` WHERE rule_check_id = 'manage-marketplace');");
    }
}