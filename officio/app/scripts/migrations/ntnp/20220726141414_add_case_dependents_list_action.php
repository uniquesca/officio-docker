<?php

use Officio\Migration\AbstractMigration;

class AddCaseDependentsListAction extends AbstractMigration
{
    protected $clearAclCache = true;

    public function up()
    {
        $this->query("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`)
                            SELECT `rule_id`, 'applicants', 'profile', 'get-case-dependents-list', 1
                            FROM acl_rules AS r
                            WHERE r.rule_check_id = 'clients-view'");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='get-case-dependents-list';");
    }
}