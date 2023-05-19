<?php

use Officio\Migration\AbstractMigration;

class AddReleaseFileNumberAcl extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select(['rule_id'])
            ->from(['acl_rules'])
            ->where(['rule_check_id' => 'clients-profile-edit'])
            ->execute();

        $row = $statement->fetch();

        $parentRuleId = false;
        if (count($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        $arrInsert = [
            'rule_id'            => $parentRuleId,
            'module_id'          => 'applicants',
            'resource_id'        => 'profile',
            'resource_privilege' => 'release-case-number',
            'rule_allow'         => 1,
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrInsert))
            ->into('acl_rule_details')
            ->values($arrInsert)
            ->execute();
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `resource_privilege`='release-case-number';");
    }
}