<?php

use Officio\Migration\AbstractMigration;

class NewPublicCalendarAclRule extends AbstractMigration
{
    public function up()
    {
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'access-to-default';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found for public access.');
        }

        $this->table('acl_rule_details')
            ->insert(
                [
                    [
                        'rule_id'            => $rule['rule_id'],
                        'module_id'          => 'calendar',
                        'resource_id'        => 'index',
                        'resource_privilege' => 'public',
                        'rule_allow'         => 1
                    ],
                    [
                        'rule_id'            => $rule['rule_id'],
                        'module_id'          => 'calendar',
                        'resource_id'        => 'index',
                        'resource_privilege' => 'get-public-application',
                        'rule_allow'         => 1
                    ]
                ]
            )->save();
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'calendar' AND resource_privilege IN ('public', 'get-public-application');");
    }
}