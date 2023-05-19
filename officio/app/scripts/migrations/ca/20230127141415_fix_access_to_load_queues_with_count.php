<?php

use Officio\Migration\AbstractMigration;

class FixAccessToLoadQueuesWithCount extends AbstractMigration
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
        $this->execute(sprintf("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (%d, 'applicants', 'queue', 'load-queues-with-count', 1);", $this->_getParentRuleId('prospects-view')));
        $this->execute(sprintf("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (%d, 'applicants', 'queue', 'load-queues-with-count', 1);", $this->_getParentRuleId('marketplace-view')));
    }

    public function down()
    {
        $this->execute(sprintf("DELETE FROM `acl_rule_details` WHERE `rule_id` = %d AND `resource_privilege` = 'load-queues-with-count';", $this->_getParentRuleId('prospects-view')));
        $this->execute(sprintf("DELETE FROM `acl_rule_details` WHERE `rule_id` = %d AND `resource_privilege` = 'load-queues-with-count';", $this->_getParentRuleId('marketplace-view')));
    }
}