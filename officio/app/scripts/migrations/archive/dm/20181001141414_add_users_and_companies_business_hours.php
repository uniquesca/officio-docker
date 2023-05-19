<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Service\Log;

class AddUsersAndCompaniesBusinessHours extends AbstractMigration
{
    private function getCompanyNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'View Workdays',
                'rule_check_id'      => 'manage-company-business-hours-workdays-view',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('load-workdays-data')
            ),
            array(
                'rule_description'   => 'Update Workdays',
                'rule_check_id'      => 'manage-company-business-hours-workdays-update',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('save-workdays-data')
            ),
            array(
                'rule_description'   => 'View Holidays',
                'rule_check_id'      => 'manage-company-business-hours-holidays-view',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-view')
            ),
            array(
                'rule_description'   => 'Add Holidays',
                'rule_check_id'      => 'manage-company-business-hours-holidays-add',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-add')
            ),
            array(
                'rule_description'   => 'Edit Holidays',
                'rule_check_id'      => 'manage-company-business-hours-holidays-edit',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-edit')
            ),
            array(
                'rule_description'   => 'Delete Holidays',
                'rule_check_id'      => 'manage-company-business-hours-holidays-delete',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-delete')
            ),
        );

        return $arrRules;
    }

    private function getUserNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'View Workdays',
                'rule_check_id'      => 'manage-members-business-hours-workdays-view',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('load-workdays-data')
            ),
            array(
                'rule_description'   => 'Update Workdays',
                'rule_check_id'      => 'manage-members-business-hours-workdays-update',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('save-workdays-data')
            ),
            array(
                'rule_description'   => 'View Holidays',
                'rule_check_id'      => 'manage-members-business-hours-holidays-view',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-view')
            ),
            array(
                'rule_description'   => 'Add Holidays',
                'rule_check_id'      => 'manage-members-business-hours-holidays-add',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-add')
            ),
            array(
                'rule_description'   => 'Edit Holidays',
                'rule_check_id'      => 'manage-members-business-hours-holidays-edit',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-edit')
            ),
            array(
                'rule_description'   => 'Delete Holidays',
                'rule_check_id'      => 'manage-members-business-hours-holidays-delete',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-business-hours',
                'resource_privilege' => array('holidays-delete')
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
            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'manage-members-view-details');

            $ruleParentId = $db->fetchOne($select);

            if (empty($ruleParentId)) {
                throw new Exception('Main parent rule not found.');
            }

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id'   => $ruleParentId,
                    'module_id'        => 'superadmin',
                    'rule_description' => 'Business Hours',
                    'rule_check_id'    => 'manage-members-business-hours',
                    'superadmin_only'  => 0,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 0,
                )
            );
            $businessScheduleRuleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $businessScheduleRuleId,
                    'module_id'          => 'superadmin',
                    'resource_id'        => 'manage-business-hours',
                    'resource_privilege' => 'index',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $businessScheduleRuleId,
                    'package_detail_description' => 'Business Hours',
                    'visible'                    => 1,
                )
            );

            $db->query(
                "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                            SELECT a.role_id, $businessScheduleRuleId
                                            FROM acl_role_access AS a
                                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                            WHERE a.rule_id = $ruleParentId"
            );

            $arrRules = $this->getUserNewRules();

            $order = 0;
            foreach ($arrRules as $arrRuleInfo) {
                $db->insert(
                    'acl_rules',
                    array(
                        'rule_parent_id'   => $businessScheduleRuleId,
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


            // COMPANY RULES
            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'manage-company-edit');

            $ruleParentId = $db->fetchOne($select);

            if (empty($ruleParentId)) {
                throw new Exception('Main parent rule not found.');
            }

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id'   => $ruleParentId,
                    'module_id'        => 'superadmin',
                    'rule_description' => 'Business Hours',
                    'rule_check_id'    => 'manage-company-business-hours',
                    'superadmin_only'  => 0,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 0,
                )
            );
            $businessScheduleRuleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $businessScheduleRuleId,
                    'module_id'          => 'superadmin',
                    'resource_id'        => 'manage-business-hours',
                    'resource_privilege' => 'index',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $businessScheduleRuleId,
                    'package_detail_description' => 'Business Hours',
                    'visible'                    => 1,
                )
            );

            $db->query(
                "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                            SELECT a.role_id, $businessScheduleRuleId
                                            FROM acl_role_access AS a
                                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                            WHERE a.rule_id = $ruleParentId"
            );

            $arrRules = $this->getCompanyNewRules();

            $order = 0;
            foreach ($arrRules as $arrRuleInfo) {
                $db->insert(
                    'acl_rules',
                    array(
                        'rule_parent_id'   => $businessScheduleRuleId,
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

            $this->execute(
                "CREATE TABLE `business_hours_workdays` (
                    `member_id` BIGINT(20) NULL DEFAULT NULL,
                    `company_id` BIGINT(20) NULL DEFAULT NULL,
                    
                    `monday_time_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
                    `monday_time_from` TIME NULL DEFAULT NULL,
                    `monday_time_to` TIME NULL DEFAULT NULL,
                    `tuesday_time_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
                    `tuesday_time_from` TIME NULL DEFAULT NULL,
                    `tuesday_time_to` TIME NULL DEFAULT NULL,        	
                    `wednesday_time_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
                    `wednesday_time_from` TIME NULL DEFAULT NULL,
                    `wednesday_time_to` TIME NULL DEFAULT NULL,
                    `thursday_time_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
                    `thursday_time_from` TIME NULL DEFAULT NULL,
                    `thursday_time_to` TIME NULL DEFAULT NULL,
                    `friday_time_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
                    `friday_time_from` TIME NULL DEFAULT NULL,
                    `friday_time_to` TIME NULL DEFAULT NULL,
                    `saturday_time_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
                    `saturday_time_from` TIME NULL DEFAULT NULL,
                    `saturday_time_to` TIME NULL DEFAULT NULL,
                    `sunday_time_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
                    `sunday_time_from` TIME NULL DEFAULT NULL,
                    `sunday_time_to` TIME NULL DEFAULT NULL,
                    
                    INDEX `FK_business_hours_time_members` (`member_id`),
                    INDEX `FK_business_hours_time_company` (`company_id`),
                    CONSTRAINT `FK_business_hours_time_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT `FK_business_hours_time_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COMMENT='Business Hours (time) for companies or users'
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB"
            );

            $this->execute(
                "CREATE TABLE `business_hours_holidays` (
            	`holiday_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            	`member_id` BIGINT(20) NULL DEFAULT NULL,
            	`company_id` BIGINT(20) NULL DEFAULT NULL,
            	`holiday_name` VARCHAR(255) NOT NULL,
            	`holiday_date_from` DATE NOT NULL,
            	`holiday_date_to` DATE NULL DEFAULT NULL,
            	INDEX `FK_business_hours_holidays_id` (`holiday_id`),
            	INDEX `FK_business_hours_holidays_members` (`member_id`),
            	INDEX `FK_business_hours_holidays_company` (`company_id`),
            	CONSTRAINT `FK_business_hours_holidays_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            	CONSTRAINT `FK_business_hours_holidays_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COMMENT='Business Hours (holidays) for companies or users'
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB"
            );

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

            $arrRuleIds = array('manage-members-business-hours', 'manage-company-business-hours');

            $arrRules = $this->getUserNewRules();
            foreach ($arrRules as $arrRuleInfo) {
                $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
            }

            $arrRules = $this->getCompanyNewRules();
            foreach ($arrRules as $arrRuleInfo) {
                $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
            }

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id IN (?)', $arrRuleIds)
            );

            $this->execute('DROP TABLE `business_hours_holidays`;');
            $this->execute('DROP TABLE `business_hours_workdays`;');
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}