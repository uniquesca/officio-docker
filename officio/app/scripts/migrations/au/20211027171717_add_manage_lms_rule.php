<?php

use Officio\Migration\AbstractMigration;

class AddManageLmsRule extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select('rule_id')
            ->from('acl_rules')
            ->where(['rule_check_id' => 'staff-tabs-view'])
            ->execute();

        $parentRuleId = false;

        $row = $statement->fetch();
        if (count($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        // Create a new rule
        $arrRuleDetails = array(
            'rule_parent_id'   => $parentRuleId,
            'module_id'        => 'officio',
            'rule_description' => 'Officio Studio Module',
            'rule_check_id'    => 'lms-view',
            'superadmin_only'  => 0,
            'crm_only'         => 'N',
            'rule_visible'     => 1,
            'rule_order'       => 40,
        );

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrRuleDetails))
            ->into('acl_rules')
            ->values($arrRuleDetails)
            ->execute();

        $ruleId = $statement->lastInsertId('acl_rules');


        // Add rule details (access to we'll have to)
        $arrRules = [
            [
                'rule_id'            => $ruleId,
                'module_id'          => 'officio',
                'resource_id'        => 'index',
                'resource_privilege' => 'generate-lms-url',
                'rule_allow'         => 1,
            ],
            [
                'rule_id'            => $ruleId,
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-members',
                'resource_privilege' => 'enable-lms-user',
                'rule_allow'         => 1,
            ]
        ];

        foreach ($arrRules as $arrRule) {
            $this->getQueryBuilder()
                ->insert(array_keys($arrRule))
                ->into('acl_rule_details')
                ->values($arrRule)
                ->execute();
        }


        // Add this new to the package
        $arrPackageDetails = [
            'package_id'                 => 1,
            'rule_id'                    => $ruleId,
            'package_detail_description' => 'Officio Studio Module',
            'visible'                    => 1,
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrPackageDetails))
            ->into('packages_details')
            ->values($arrPackageDetails)
            ->execute();

        // Allow access to all user/admin roles that have access to the "Staff tabs"
        $this->query(
            "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                SELECT r.role_parent_id, $ruleId
                FROM acl_roles AS r
                WHERE r.role_type IN ('user', 'admin')"
        );
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rules WHERE rule_check_id = 'lms-view'");
    }
}