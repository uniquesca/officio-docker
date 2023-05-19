<?php

use Officio\Migration\AbstractMigration;

class MoveClientLoginAclToLite extends AbstractMigration
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
        $this->execute(sprintf("UPDATE `packages_details` SET `package_id`=1 WHERE  `rule_id`=%d;", $this->_getParentRuleId('clients-employer-client-login')));
        $this->execute(sprintf("UPDATE `packages_details` SET `package_id`=1 WHERE  `rule_id`=%d;", $this->_getParentRuleId('clients-individual-client-login')));
    }

    public function down()
    {
        $this->execute(sprintf("UPDATE `packages_details` SET `package_id`=2 WHERE  `rule_id`=%d;", $this->_getParentRuleId('clients-employer-client-login')));
        $this->execute(sprintf("UPDATE `packages_details` SET `package_id`=2 WHERE  `rule_id`=%d;", $this->_getParentRuleId('clients-individual-client-login')));
    }
}
