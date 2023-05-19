<?php

use Officio\Migration\AbstractMigration;

class NewLinkCaseToCase extends AbstractMigration
{
    public function up()
    {
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'clients-profile-edit';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found for public access.');
        }

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'link-case-to-case',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'unassign-case',
                    'rule_allow'         => 1
                ]
            ]
        )->save();
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'applicants' AND resource_privilege = 'link-case-to-case';");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'applicants' AND resource_privilege = 'unassign-case';");
    }
}