<?php

use Officio\Migration\AbstractMigration;

class AddDocumentsChecklistRuleDetail extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select(array('rule_id'))
            ->from(array('acl_rules'))
            ->where(
                [
                    'rule_check_id' => 'client-documents-checklist-view'
                ]
            )
            ->execute();

        $row = $statement->fetch();

        $ruleParentId = false;
        if (!empty($row)) {
            $ruleParentId = $row[array_key_first($row)];
        }

        if (empty($ruleParentId)) {
            throw new Exception('Main parent rule not found.');
        }

        $this->table('acl_rule_details')
            ->insert([
                [
                    'rule_id'            => $ruleParentId,
                    'module_id'          => 'documents',
                    'resource_id'        => 'checklist',
                    'resource_privilege' => 'get-family-members',
                ]
            ])
            ->saveData();
    }

    public function down()
    {
        $statement = $this->getQueryBuilder()
            ->select(array('rule_id'))
            ->from(array('acl_rules'))
            ->where(
                [
                    'rule_check_id' => 'client-documents-checklist-view'
                ]
            )
            ->execute();

        $row = $statement->fetch();

        $ruleParentId = false;
        if (!empty($row)) {
            $ruleParentId = $row[array_key_first($row)];
        }

        if (empty($ruleParentId)) {
            throw new Exception('Main parent rule not found.');
        }

        $this->getQueryBuilder()
            ->delete('acl_rule_details')
            ->where(
                [
                    'rule_id'            => $ruleParentId,
                    'module_id'          => 'documents',
                    'resource_id'        => 'checklist',
                    'resource_privilege' => 'get-family-members'
                ]
            )
            ->execute();
    }
}
