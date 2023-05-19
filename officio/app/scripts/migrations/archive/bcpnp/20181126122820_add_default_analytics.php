<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddDefaultAnalytics extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        try {
            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'admin-view');

            $ruleParentId = $db->fetchOne($select);

            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'default-searches-view');

            $searchRuleId = $db->fetchOne($select);

            if (empty($ruleParentId) || empty($searchRuleId)) {
                throw new Exception('Main parent rule not found.');
            }

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id'   => $ruleParentId,
                    'module_id'        => 'superadmin',
                    'rule_description' => 'Default Analytics',
                    'rule_check_id'    => 'manage-default-analytics',
                    'superadmin_only'  => 1,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 0,
                )
            );
            $analyticsRuleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $analyticsRuleId,
                    'module_id'          => 'superadmin',
                    'resource_id'        => 'manage-default-analytics',
                    'resource_privilege' => '',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $analyticsRuleId,
                    'package_detail_description' => 'Default Analytics',
                    'visible'                    => 1,
                )
            );

            $db->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                        SELECT a.role_id, $analyticsRuleId
                        FROM acl_role_access AS a
                        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                        WHERE a.rule_id = $searchRuleId");


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

            $arrRuleIds = array('manage-default-analytics');

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