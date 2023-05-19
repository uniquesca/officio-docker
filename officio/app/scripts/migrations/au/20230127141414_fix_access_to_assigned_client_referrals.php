<?php

use Officio\Migration\AbstractMigration;

class FixAccessToAssignedClientReferrals extends AbstractMigration
{
    private function _getParentRuleId($checkId)
    {
        $statement = $this->getQueryBuilder()
            ->select('rule_id')
            ->from('acl_rules')
            ->where(['rule_check_id' => $checkId])
            ->execute();

        $parentRuleId = false;

        $row = $statement->fetch();
        if (count($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        return $parentRuleId;
    }

    public function up()
    {
        $this->execute(sprintf("UPDATE `acl_rule_details` SET `rule_id` = %d WHERE `resource_privilege` = 'get-assigned-client-referrals';", $this->_getParentRuleId('clients-view')));
    }

    public function down()
    {
        $this->execute(sprintf("UPDATE `acl_rule_details` SET `rule_id` = %d WHERE `resource_privilege` = 'get-assigned-client-referrals';", $this->_getParentRuleId('clients-profile-edit')));
    }
}