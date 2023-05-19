<?php

use Officio\Migration\AbstractMigration;

class AddAclRuleDetails extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1042, 'superadmin', 'manage-company', 'update-default-company-admin', 1);");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1042 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='update-default-company-admin';");
    }
}
