<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Service\Log;

class AddAnalytics extends AbstractMigration
{
    private function getNewRules($for = 'applicants')
    {
        $arrRules = array(
            array(
                'rule_description'   => 'Add',
                'rule_check_id'      => $for . '-analytics-add',
                'module_id'          => 'applicants',
                'resource_id'        => 'analytics',
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
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        try {
            $db->query(
                "CREATE TABLE `analytics` (
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
            ENGINE=InnoDB"
            );

            $arrToCreate = array('applicants', 'contacts');

            foreach ($arrToCreate as $createFor) {
                $select = $db->select()
                    ->from(array('acl_rules'), array('rule_id'))
                    ->where('rule_check_id = ?', $createFor === 'applicants' ? 'clients-view' : 'contacts-view');

                $ruleParentId = $db->fetchOne($select);

                if (empty($ruleParentId)) {
                    throw new Exception('Main parent rule not found.');
                }

                $db->insert(
                    'acl_rules',
                    array(
                        'rule_parent_id'   => $ruleParentId,
                        'module_id'        => 'applicants',
                        'rule_description' => 'Analytics',
                        'rule_check_id'    => $createFor . '-analytics-view',
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 21,
                    )
                );
                $analyticsRuleId = $db->lastInsertId('acl_rules');

                $arrRuleDetails = array('load-list', 'get-analytics-data');
                foreach ($arrRuleDetails as $resource) {
                    $db->insert(
                        'acl_rule_details',
                        array(
                            'rule_id'            => $analyticsRuleId,
                            'module_id'          => 'applicants',
                            'resource_id'        => 'analytics',
                            'resource_privilege' => $resource,
                            'rule_allow'         => 1,
                        )
                    );
                }

                $db->insert(
                    'packages_details',
                    array(
                        'package_id'                 => 1,
                        'rule_id'                    => $analyticsRuleId,
                        'package_detail_description' => 'Analytics',
                        'visible'                    => 1,
                    )
                );

                $db->query(
                    "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                            SELECT a.role_id, $analyticsRuleId
                                            FROM acl_role_access AS a
                                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                            WHERE a.rule_id = $ruleParentId"
                );

                $arrRules = $this->getNewRules($createFor);

                $order = 0;
                foreach ($arrRules as $arrRuleInfo) {
                    $db->insert(
                        'acl_rules',
                        array(
                            'rule_parent_id'   => $analyticsRuleId,
                            'module_id'        => $arrRuleInfo['module_id'],
                            'rule_description' => $arrRuleInfo['rule_description'],
                            'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                            'superadmin_only'  => 0,
                            'crm_only'         => 'N',
                            'rule_visible'     => 1,
                            'rule_order'       => $order++,
                        )
                    );
                    $ruleId = $db->lastInsertId('acl_rules');

                    foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                        $db->insert(
                            'acl_rule_details',
                            array(
                                'rule_id'            => $ruleId,
                                'module_id'          => $arrRuleInfo['module_id'],
                                'resource_id'        => $arrRuleInfo['resource_id'],
                                'resource_privilege' => $resourcePrivilege,
                                'rule_allow'         => 1,
                            )
                        );
                    }

                    $db->insert(
                        'packages_details',
                        array(
                            'package_id'                 => 1,
                            'rule_id'                    => $ruleId,
                            'package_detail_description' => $arrRuleInfo['rule_description'],
                            'visible'                    => 1,
                        )
                    );

                    $db->query(
                        "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                                SELECT a.role_id, $ruleId
                                                FROM acl_role_access AS a
                                                LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                                WHERE a.rule_id = $ruleParentId"
                    );
                }
            }


            $db->commit();


            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            Acl::clearCache($cache);
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $arrRuleIds  = array();
            $arrToCreate = array('applicants', 'contacts');
            foreach ($arrToCreate as $createFor) {
                $arrRuleIds[] = $createFor . '-analytics-view';

                $arrRules = $this->getNewRules($createFor);
                foreach ($arrRules as $arrRuleInfo) {
                    $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
                }
            }

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id IN (?)', $arrRuleIds)
            );

            $db->query('DROP TABLE `analytics`');
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}