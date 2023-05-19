<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddAnalytics extends AbstractMigration
{
    private function getNewRules($for = 'applicants')
    {
        $arrRules = array(
            array(
                'rule_description' => 'Add',
                'rule_check_id' => $for . '-analytics-add',
                'module_id' => 'applicants',
                'resource_id' => 'analytics',
                'resource_privilege' => array('add')
            ),
            array(
                'rule_description'   => 'Edit',
                'rule_check_id'      => $for . '-analytics-edit',
                'module_id'          => 'applicants',
                'resource_id'        => 'analytics',
                'resource_privilege' => array('edit')
            ),
            array(
                'rule_description'   => 'Delete',
                'rule_check_id'      => $for . '-analytics-delete',
                'module_id'          => 'applicants',
                'resource_id'        => 'analytics',
                'resource_privilege' => array('delete')
            ),
            array(
                'rule_description'   => 'Export',
                'rule_check_id'      => $for . '-analytics-export',
                'module_id'          => 'applicants',
                'resource_id'        => 'analytics',
                'resource_privilege' => array('export')
            ),
            array(
                'rule_description'   => 'Print',
                'rule_check_id'      => $for . '-analytics-print',
                'module_id'          => 'applicants',
                'resource_id'        => 'analytics',
                'resource_privilege' => array('print')
            ),
        );

        return $arrRules;
    }

    public function up()
    {
        try {
            $this->query("CREATE TABLE `analytics` (
                `analytics_id` INT(11) NOT NULL AUTO_INCREMENT,
                `analytics_type` ENUM('applicants', 'contacts') NOT NULL DEFAULT 'applicants',
                `company_id` BIGINT(20) NULL DEFAULT NULL,
                `analytics_name` CHAR(255) NULL DEFAULT NULL,
                `analytics_params` TEXT NULL,
                PRIMARY KEY (`analytics_id`),
                INDEX `FK_analytics_company` (`company_id`),
                CONSTRAINT `FK_analytics_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB");

            $arrToCreate = array('applicants', 'contacts');

            foreach ($arrToCreate as $createFor) {
                $statement = $this->getQueryBuilder()
                    ->select(array('rule_id'))
                    ->from(array('acl_rules'))
                    ->where(
                        [
                            'rule_check_id' => $createFor === 'applicants' ? 'clients-view' : 'contacts-view'
                        ]
                    )
                    ->execute();

                $ruleParentId = false;
                $row = $statement->fetch();
                if (!empty($row)) {
                    $ruleParentId =  $row[array_key_first($row)];
                }

                if (empty($ruleParentId)) {
                    throw new Exception('Main parent rule not found.');
                }

                $statement = $this->getQueryBuilder()
                    ->insert(
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
                            'rule_parent_id'   => $ruleParentId,
                            'module_id'        => 'applicants',
                            'rule_description' => 'Analytics',
                            'rule_check_id'    => $createFor . '-analytics-view',
                            'superadmin_only'  => 0,
                            'crm_only'         => 'N',
                            'rule_visible'     => 1,
                            'rule_order'       => 21,
                        ]
                    )
                    ->execute();

                $analyticsRuleId = $statement->lastInsertId('acl_rules');

                $arrRuleDetails = array('load-list', 'get-analytics-data');
                foreach ($arrRuleDetails as $resource) {
                    $this->table('acl_rule_details')
                        ->insert([
                            [
                                'rule_id'            => $analyticsRuleId,
                                'module_id'          => 'applicants',
                                'resource_id'        => 'analytics',
                                'resource_privilege' => $resource,
                                'rule_allow'         => 1,
                            ]
                        ])
                        ->saveData();
                }

                $this->table('packages_details')
                    ->insert([
                        [
                            'package_id'                 => 1,
                            'rule_id'                    => $analyticsRuleId,
                            'package_detail_description' => 'Analytics',
                            'visible'                    => 1,
                        ]
                    ])
                    ->saveData();

                $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                            SELECT a.role_id, $analyticsRuleId
                                            FROM acl_role_access AS a
                                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                            WHERE a.rule_id = $ruleParentId");

                $arrRules = $this->getNewRules($createFor);

                $order = 0;
                foreach ($arrRules as $arrRuleInfo) {
                    $statement = $this->getQueryBuilder()
                        ->insert(
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
                                'rule_parent_id'   => $analyticsRuleId,
                                'module_id'        => $arrRuleInfo['module_id'],
                                'rule_description' => $arrRuleInfo['rule_description'],
                                'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                                'superadmin_only'  => 0,
                                'crm_only'         => 'N',
                                'rule_visible'     => 1,
                                'rule_order'       => $order++,
                            ]
                        )
                        ->execute();
                    $ruleId = $statement->lastInsertId('acl_rules');

                    foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                        $this->table('acl_rule_details')
                            ->insert([
                                [
                                    'rule_id'            => $ruleId,
                                    'module_id'          => $arrRuleInfo['module_id'],
                                    'resource_id'        => $arrRuleInfo['resource_id'],
                                    'resource_privilege' => $resourcePrivilege,
                                    'rule_allow'         => 1,
                                ]
                            ])
                            ->saveData();
                    }

                    $this->table('packages_details')
                        ->insert([
                            [
                                'package_id'                 => 1,
                                'rule_id'                    => $ruleId,
                                'package_detail_description' => $arrRuleInfo['rule_description'],
                                'visible'                    => 1,
                            ]
                        ])
                        ->saveData();

                    $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                                SELECT a.role_id, $ruleId
                                                FROM acl_role_access AS a
                                                LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                                WHERE a.rule_id = $ruleParentId"
                    );
                }
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
            $arrRuleIds  = array();
            $arrToCreate = array('applicants', 'contacts');
            foreach ($arrToCreate as $createFor) {
                $arrRuleIds[] = $createFor . '-analytics-view';

                $arrRules = $this->getNewRules($createFor);
                foreach ($arrRules as $arrRuleInfo) {
                    $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
                }
            }

            $this->getQueryBuilder()
                ->delete('acl_rules')
                ->where(function ($exp) use ($arrRuleIds) {
                    return $exp
                        ->in('rule_check_id', $arrRuleIds);
                })
                ->execute();

            $this->query('DROP TABLE `analytics`');
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}
