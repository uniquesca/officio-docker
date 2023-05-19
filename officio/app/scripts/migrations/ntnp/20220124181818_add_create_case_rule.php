<?php

use Officio\Migration\AbstractMigration;

class AddCreateCaseRule extends AbstractMigration
{
    protected $clearAclCache = true;

    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select('rule_id')
            ->from('acl_rules')
            ->where(['rule_check_id' => 'clients-profile-edit'])
            ->execute();

        $parentRuleId = false;
        $row          = $statement->fetch();
        if (count($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        $arrRuleDetails = [
            'rule_id'            => $parentRuleId,
            'module_id'          => 'applicants',
            'resource_id'        => 'profile',
            'resource_privilege' => 'create-case',
            'rule_allow'         => 1,
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrRuleDetails))
            ->into('acl_rule_details')
            ->values($arrRuleDetails)
            ->execute();
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='create-case';");
    }
}
