<?php

use Phinx\Migration\AbstractMigration;

class NewPublicUformsv2AclRule extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'access-to-default';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found for public access.');
        }

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'uforms-v2',
                    'resource_id'        => 'index',
                    'resource_privilege' => 'new-prototype',
                    'rule_allow'         => 1
                ]
            ]
        )->save();


        $rule = $this->fetchRow("SELECT rule_id, rule_allow FROM acl_rule_details WHERE module_id = 'forms' AND resource_id = 'index' AND resource_privilege = 'assign';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found.');
        }

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'forms',
                    'resource_id'        => 'index',
                    'resource_privilege' => 'assign-officio-form',
                    'rule_allow'         => $rule['rule_allow']
                ],
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'forms',
                    'resource_id'        => 'index',
                    'resource_privilege' => 'load-setting',
                    'rule_allow'         => $rule['rule_allow']
                ],
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'forms',
                    'resource_id'        => 'index',
                    'resource_privilege' => 'load-form-setting',
                    'rule_allow'         => $rule['rule_allow']
                ],
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'forms',
                    'resource_id'        => 'index',
                    'resource_privilege' => 'save-form-setting',
                    'rule_allow'         => $rule['rule_allow']
                ]
            ]
        )->save();
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'uforms-v2';");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'forms' AND resource_privilege IN ('assign-officio-form', 'load-setting', 'load-form-setting', 'save-form-setting');");
    }
}