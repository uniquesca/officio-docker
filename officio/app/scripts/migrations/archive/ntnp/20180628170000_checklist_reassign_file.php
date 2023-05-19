<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Service\Log;

class ChecklistReassignFile extends AbstractMigration
{
    public function up()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'client-documents-checklist-view');

            $ruleParentId = $db->fetchOne($select);

            if (empty($ruleParentId)) {
                throw new Exception('Main parent rule not found.');
            }

            $db->beginTransaction();

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id'   => $ruleParentId,
                    'module_id'        => 'documents',
                    'rule_description' => 'Reassign File',
                    'rule_check_id'    => 'client-documents-checklist-reassign',
                    'superadmin_only'  => 0,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 4,
                )
            );

            $mainRuleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $mainRuleId,
                    'module_id'          => 'documents',
                    'resource_id'        => 'checklist',
                    'resource_privilege' => 'reassign',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $mainRuleId,
                    'package_detail_description' => 'Reassign File',
                    'visible'                    => 1,
                )
            );

            $booDocumentsChecklistEnabled = !empty(Zend_Registry::get('serviceManager')->get('config')['site_version']['documents_checklist_enabled']);
            if ($booDocumentsChecklistEnabled) {
                $db->query(
                    "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                    SELECT a.role_id, $mainRuleId
                                    FROM acl_role_access AS a
                                    LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                    WHERE a.rule_id = $ruleParentId"
                );
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

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id = ?', 'client-documents-checklist-reassign')
            );

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            Acl::clearCache($cache);
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}