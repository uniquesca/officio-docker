<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ChecklistReassignFile extends AbstractMigration
{
    public function up()
    {
        try {
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

            $statement = $this->getQueryBuilder()
                ->insert(
                    array(
                        'rule_parent_id',
                        'module_id',
                        'rule_description',
                        'rule_check_id',
                        'superadmin_only',
                        'crm_only',
                        'rule_visible',
                        'rule_order'
                    )
                )
                ->into('acl_rules')
                ->values(
                    array(
                        'rule_parent_id'   => $ruleParentId,
                        'module_id'        => 'documents',
                        'rule_description' => 'Reassign File',
                        'rule_check_id'    => 'client-documents-checklist-reassign',
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 4
                    )
                )
                ->execute();

            $mainRuleId = $statement->lastInsertId('acl_rules');

            $this->table('acl_rule_details')
                ->insert([
                    [
                        'rule_id'            => $mainRuleId,
                        'module_id'          => 'documents',
                        'resource_id'        => 'checklist',
                        'resource_privilege' => 'reassign',
                        'rule_allow'         => 1,
                    ]
                ])
                ->saveData();

            $this->table('packages_details')
                ->insert([
                    array(
                        'package_id'                 => 1,
                        'rule_id'                    => $mainRuleId,
                        'package_detail_description' => 'Reassign File',
                        'visible'                    => 1,
                    )
                ])
                ->saveData();

            $booDocumentsChecklistEnabled = !empty(self::getService('config')['site_version']['documents_checklist_enabled']);
            if ($booDocumentsChecklistEnabled) {
                $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                    SELECT a.role_id, $mainRuleId
                                    FROM acl_role_access AS a
                                    LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                    WHERE a.rule_id = $ruleParentId"
                );
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $this->getQueryBuilder()
                ->delete('acl_rules')
                ->where(
                    [
                        'rule_check_id' => 'client-documents-checklist-reassign'
                    ]
                )
                ->execute();
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}