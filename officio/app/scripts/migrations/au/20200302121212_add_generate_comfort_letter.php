<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddGenerateComfortLetter extends AbstractMigration
{

    protected $clearCache = true;

    public function up()
    {
        try {
            $this->getAdapter()->beginTransaction();

            $builder = $this->getQueryBuilder();
            $statement = $builder
                ->select(['rule_id'])
                ->from('acl_rules')
                ->where(['rule_check_id' => 'clients-view'])
                ->execute();

            $parentRuleId = false;
            $row = $statement->fetch();
            if (count($row)) {
                $parentRuleId =  $row[array_key_first($row)];
            }

            if (empty($parentRuleId)) {
                throw new Exception('Parent rule not found.');
            }

            $builder = $this->getQueryBuilder();
            $statement = $builder->insert(
                [
                    'rule_parent_id',
                    'module_id',
                    'rule_description',
                    'rule_check_id',
                    'superadmin_only',
                    'crm_only',
                    'rule_visible',
                    'rule_order',
                ]
            )
                ->into('acl_rules')
                ->values(
                    [
                        'rule_parent_id'   => $parentRuleId,
                        'module_id'        => 'applicants',
                        'rule_description' => 'Generate Comfort Letter',
                        'rule_check_id'    => 'generate-pdf-letter',
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 24,
                    ]
                )
                ->execute();

            $ruleId = $statement->lastInsertId('acl_rules');

            $builder = $this->getQueryBuilder();
            $builder->insert(
                [
                    'rule_id',
                    'module_id',
                    'resource_id',
                    'resource_privilege',
                    'rule_allow',
                ]
            )
                ->into('acl_rule_details')
                ->values(
                    [
                        'rule_id'            => $ruleId,
                        'module_id'          => 'applicants',
                        'resource_id'        => 'profile',
                        'resource_privilege' => 'generate-pdf-letter',
                        'rule_allow'         => 1,
                    ]
                )
                ->execute();

            $builder = $this->getQueryBuilder();
            $builder->insert(
                [
                    'rule_id',
                    'module_id',
                    'resource_id',
                    'resource_privilege',
                    'rule_allow',
                ]
            )
                ->into('acl_rule_details')
                ->values(
                    [
                        'rule_id'            => $ruleId,
                        'module_id'          => 'applicants',
                        'resource_id'        => 'profile',
                        'resource_privilege' => 'get-letter-templates-by-type',
                        'rule_allow'         => 1,
                    ]
                )
                ->execute();

            $builder = $this->getQueryBuilder();
            $builder->insert(
                [
                    'package_id',
                    'rule_id',
                    'package_detail_description',
                    'visible',
                ]
            )
                ->into('packages_details')
                ->values(
                    [
                        'package_id'                 => 1,
                        'rule_id'                    => $ruleId,
                        'package_detail_description' => 'Generate Comfort Letter',
                        'visible'                    => 1,
                    ]
                )
                ->execute();

            $this->query("ALTER TABLE `company_details` ADD COLUMN `templates_settings` TEXT NULL COMMENT 'Templates settings (e.g. comfort letter)' AFTER `case_number_settings`;");

            $this->getAdapter()->commitTransaction();
        } catch (\Exception $e) {
            $this->getAdapter()->rollbackTransaction();
            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
           /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $builder = $this->getQueryBuilder();
            $builder
                ->delete('acl_rules')
                ->where(['rule_check_id' => 'generate-pdf-letter'])
                ->execute();

            $this->query("ALTER TABLE `company_details` DROP COLUMN `templates_settings`;");
        } catch (\Exception $e) {
            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
           /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }
}
