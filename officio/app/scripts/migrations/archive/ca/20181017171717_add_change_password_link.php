<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddChangePasswordLink extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        try {
            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'staff-tabs-view');

            $ruleParentId = $db->fetchOne($select);

            if (empty($ruleParentId)) {
                throw new Exception('Main parent rule not found.');
            }

            $db->insert(
                'acl_modules',
                array(
                    'module_id'   => 'profile',
                    'module_name' => 'User Profile',
                )
            );

            $userProfileRuleId = 500;
            $db->insert(
                'acl_rules',
                array(
                    'rule_id'          => $userProfileRuleId,
                    'rule_parent_id'   => $ruleParentId,
                    'module_id'        => 'profile',
                    'rule_description' => 'User Profile',
                    'rule_check_id'    => 'user-profile-view',
                    'superadmin_only'  => 0,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 34,
                )
            );

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $userProfileRuleId,
                    'module_id'          => 'profile',
                    'resource_id'        => 'index',
                    'resource_privilege' => '',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $userProfileRuleId,
                    'package_detail_description' => 'User Profile',
                    'visible'                    => 1,
                )
            );

            $db->query("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, $userProfileRuleId FROM `acl_roles` as r WHERE r.role_type IN ('superadmin', 'admin', 'user');");

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

            $arrRuleIds = array('user-profile-view');

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id IN (?)', $arrRuleIds)
            );

            $db->delete(
                'acl_modules',
                $db->quoteInto('module_id = ?', 'profile')
            );
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}