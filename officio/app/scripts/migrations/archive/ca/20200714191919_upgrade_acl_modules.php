<?php

use Officio\Migration\AbstractMigration;

class UpgradeAclModules extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_modules` SET `module_id`='officio' WHERE `module_id`='default';");
        $this->execute("UPDATE `acl_rules` SET `module_id`='officio' WHERE `module_id`='default';");
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='officio' WHERE `module_id`='default';");

        $this->execute("INSERT INTO `acl_modules` (`module_id`, `module_name`) VALUES ('companywizard', 'Company Wizard (allows companies registration)');");

        $this->execute("UPDATE `acl_modules` SET `module_id`='mailer' WHERE `module_id`='mail';");
        $this->execute("UPDATE `acl_rules` SET `module_id`='mailer' WHERE `module_id`='mail';");
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='mailer' WHERE `module_id`='mail';");
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='mail' WHERE `module_id`='mailer';");
        $this->execute("UPDATE `acl_rules` SET `module_id`='mail' WHERE `module_id`='mailer';");
        $this->execute("UPDATE `acl_modules` SET `module_id`='mail' WHERE `module_id`='mailer';");

        $this->execute("DELETE FROM `acl_modules` WHERE  `module_id`='companywizard';");

        $this->execute("UPDATE `acl_modules` SET `module_id`='default' WHERE `module_id`='officio';");
        $this->execute("UPDATE `acl_rules` SET `module_id`='default' WHERE `module_id`='officio';");
        $this->execute("UPDATE `acl_rule_details` SET `module_id`='default' WHERE `module_id`='officio';");
    }
}
