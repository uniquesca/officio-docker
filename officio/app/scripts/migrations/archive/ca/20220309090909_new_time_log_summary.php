<?php

use Officio\Migration\AbstractMigration;

class NewTimeLogSummary extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select('rule_id')
            ->from('acl_rules')
            ->where(['rule_check_id' => 'staff-tabs-view'])
            ->execute();

        $parentRuleId = false;

        $row = $statement->fetch();
        if (!empty($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        // Create a new rule
        $arrRuleDetails = array(
            'rule_parent_id'   => $parentRuleId,
            'module_id'        => 'clients',
            'rule_description' => 'Time Log Summary',
            'rule_check_id'    => 'clients-time-log-summary',
            'superadmin_only'  => 0,
            'crm_only'         => 'N',
            'rule_visible'     => 1,
            'rule_order'       => 45,
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
                'module_id'          => 'clients',
                'resource_id'        => 'time-tracker',
                'resource_privilege' => 'time-log-summary-load',
                'rule_allow'         => 1,
            ],
            [
                'rule_id'            => $ruleId,
                'module_id'          => 'clients',
                'resource_id'        => 'time-tracker',
                'resource_privilege' => 'time-log-summary-export',
                'rule_allow'         => 1,
            ],
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
            'package_detail_description' => 'Time Log Summary',
            'visible'                    => 1,
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrPackageDetails))
            ->into('packages_details')
            ->values($arrPackageDetails)
            ->execute();

        // Allow access to all admin roles
        $this->query(
            "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                    SELECT r.role_parent_id, $ruleId
                    FROM acl_roles AS r
                    WHERE r.role_type IN ('admin')"
        );
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rules WHERE rule_check_id = 'clients-time-log-summary'");
    }
}