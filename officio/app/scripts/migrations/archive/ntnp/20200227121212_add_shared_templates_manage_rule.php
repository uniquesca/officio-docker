<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddSharedTemplatesManageRule extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'Manage Shared Templates',
                'rule_check_id'      => 'templates-manage-shared',
                'module_id'          => 'templates',
                'resource_id'        => 'index',
                'resource_privilege' => array('')
            ),
        );

        return $arrRules;
    }

    public function up()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'templates-manage');

            $ruleParentId = $db->fetchOne($select);

            if (empty($ruleParentId)) {
                throw new Exception('Manage Templates rule not found.');
            }

            $arrRules = $this->getNewRules();

            $db->beginTransaction();

            $db->update(
                'acl_rules',
                array(
                    'rule_description'=> 'Manage Own Templates'
                ),
                $db->quoteInto('rule_id = ? ', $ruleParentId, 'INT')
            );

            $order = 0;
            foreach ($arrRules as $arrRuleInfo) {
                $db->insert(
                    'acl_rules',
                    array(
                        'rule_parent_id'   => $ruleParentId,
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

                // Allow access to this new rule to all Admins for which the parent rule is checked
                $db->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                SELECT a.role_id, $ruleId
                                FROM acl_role_access AS a
                                LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                WHERE a.rule_id = $ruleParentId AND r.`role_type` = 'admin'");
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

            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'templates-manage');

            $ruleParentId = $db->fetchOne($select);

            if (!empty($ruleParentId)) {
                $db->update(
                    'acl_rules',
                    array(
                        'rule_description'=> 'Manage Templates'
                    ),
                    $db->quoteInto('rule_id = ? ', $ruleParentId, 'INT')
                );
            }

            $arrRules   = $this->getNewRules();
            $arrRuleIds = array();
            foreach ($arrRules as $arrRuleInfo) {
                $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
            }

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id IN (?)', $arrRuleIds)
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
