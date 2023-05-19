<?php

use Officio\Migration\AbstractMigration;

class AddImmiFunctionality extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'index', 'submit-immi');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'index', 'stop-immi-submission');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 140 AND `resource_privilege` = 'submit-immi';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 140 AND `resource_privilege` = 'stop-immi-submission';");
    }
}