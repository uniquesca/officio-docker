<?php

use Officio\Migration\AbstractMigration;

class SetModulesForIaAndEmployerRoles extends AbstractMigration
{
    public function up()
    {
        $arrUpdateRules = [
            'employer_client' => [
                'index-view',
                'clients-view',
                'clients-profile-edit',
                'clients-employer-client-login',
                'client-documents-view',
                'forms-view',
                'user-profile-view',
                'clients-accounting-view',
                'clients-accounting-print'
            ],

            'individual_client' => [
                'index-view',
                'clients-view',
                'clients-profile-edit',
                'clients-individual-client-login',
                'client-documents-view',
                'forms-view',
                'user-profile-view',
                'clients-accounting-view',
                'clients-accounting-print'
            ]
        ];

        foreach ($arrUpdateRules as $roleType => $arrRules) {
            $this->execute("DELETE FROM acl_role_access WHERE role_id IN (SELECT role_parent_id FROM acl_roles WHERE role_type = '$roleType')");
            foreach ($arrRules as $textRuleId) {
                $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = '$textRuleId';");
                if (empty($rule['rule_id'])) {
                    throw new Exception("ACL rule $textRuleId not found.");
                }

                $this->execute(
                    sprintf(
                        "INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, %d FROM `acl_roles` as r WHERE r.role_type IN ('%s');",
                        $rule['rule_id'],
                        $roleType
                    )
                );
            }
        }
    }

    public function down()
    {
    }
}
