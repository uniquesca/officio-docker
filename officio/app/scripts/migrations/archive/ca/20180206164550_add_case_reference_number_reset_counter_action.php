<?php

use Officio\Migration\AbstractMigration;

class AddCaseReferenceNumberResetCounterAction extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('2229', 'superadmin', 'manage-company', 'case-number-settings-reset-counter');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=2229 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='case-number-settings-reset-counter';");
    }
}