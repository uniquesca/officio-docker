<?php

use Officio\Migration\AbstractMigration;

class addDocumentsManager extends AbstractMigration
{
    public function up()
    {
        // For all logged in users and company admins
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'index-view';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found.');
        }

        $parentRuleId = $rule['rule_id'];
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentRuleId, 'documents', 'manager', '');");


        // For superadmins
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'admin-view';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found.');
        }

        $parentRuleId = $rule['rule_id'];
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentRuleId, 'documents', 'manager', '');");
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'documents' AND resource_id = 'manager'");
    }
}
