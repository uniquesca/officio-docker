<?php

use Phinx\Migration\AbstractMigration;

class AddAclAccessToXod extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'index', 'open-xod')");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'sync', 'save-xod')");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id`=140 AND `module_id`='forms' AND `resource_id`='index' AND `resource_privilege`='open-xod';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id`=140 AND `module_id`='forms' AND `resource_id`='sync' AND `resource_privilege`='save-xod';");
    }
}