<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddGenerateComfortLetter extends AbstractMigration
{
    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select('rule_id')
                ->from(array('r' => 'acl_rules'))
                ->where(
                    [
                        'r.rule_check_id' => 'clients-view'
                    ]
                )
                ->execute();

            $parentRuleId = false;
            $row = $statement->fetch();
            if (!empty($row)) {
                $parentRuleId =  $row[array_key_first($row)];
            }

            if (empty($parentRuleId)) {
                throw new Exception('Parent rule not found.');
            }

            $statement =  $this->getQueryBuilder()
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
                        'rule_parent_id'   => $parentRuleId,
                        'module_id'        => 'applicants',
                        'rule_description' => 'Generate Comfort Letter',
                        'rule_check_id'    => 'generate-pdf-letter',
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 24,
                    )
                )
                ->execute();

            $ruleId = $statement->lastInsertId('acl_rules');

            $this->table('acl_rule_details')
                ->insert([
                    [
                        'rule_id'            => $ruleId,
                        'module_id'          => 'applicants',
                        'resource_id'        => 'profile',
                        'resource_privilege' => 'generate-pdf-letter',
                        'rule_allow'         => 1,
                    ]
                ])
            ->saveData();

            $this->table('acl_rule_details')
                ->insert([
                    [
                        'rule_id'            => $ruleId,
                        'module_id'          => 'applicants',
                        'resource_id'        => 'profile',
                        'resource_privilege' => 'get-letter-templates-by-type',
                        'rule_allow'         => 1,
                    ]
                ])
                ->saveData();

            $this->table('packages_details')
                ->insert([
                    [
                        'package_id'                 => 1,
                        'rule_id'                    => $ruleId,
                        'package_detail_description' => 'Generate Comfort Letter',
                        'visible'                    => 1,
                    ]
                ])
                ->saveData();

            $this->query("ALTER TABLE `company_details` ADD COLUMN `templates_settings` TEXT NULL COMMENT 'Templates settings (e.g. comfort letter)' AFTER `case_number_settings`;");
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
                        'rule_check_id' => 'generate-pdf-letter'
                    ]
                )
                ->execute();

            $this->query("ALTER TABLE `company_details` DROP COLUMN `templates_settings`;");
        } catch (\Exception $e) {
           /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}