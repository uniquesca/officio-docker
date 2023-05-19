<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddMoreCompanyRules extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'Define Authorised Agents',
                'rule_check_id'      => 'define-authorised-agents',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-divisions-groups',
                'resource_privilege' => array('')
            ),

            array(
                'rule_description'   => 'Define %office_label%',
                'rule_check_id'      => 'manage-offices',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-offices',
                'resource_privilege' => array('')
            ),

            array(
                'rule_description'   => 'Define Case Reference Number Settings',
                'rule_check_id'      => 'manage-case-reference-number-settings',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-company',
                'resource_privilege' => array('case-number-settings', 'case-number-settings-save')
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
                ->where('rule_check_id = ?', 'manage-company');

            $ruleParentId = $db->fetchOne($select);

            if (empty($ruleParentId)) {
                throw new Exception('Manage company rule not found.');
            }

            $arrRules = $this->getNewRules();

            $db->beginTransaction();

            $order = 4;
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

                $db->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                        SELECT a.role_id, $ruleId
                                        FROM acl_role_access AS a
                                        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                        WHERE a.rule_id = $ruleParentId");

                foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                    $db->delete(
                        'acl_rule_details',
                        $db->quoteInto('rule_id = ?', $ruleParentId, 'INT') .
                        $db->quoteInto(' AND module_id = ?', $arrRuleInfo['module_id']) .
                        $db->quoteInto(' AND resource_id = ?', $arrRuleInfo['resource_id']) .
                        $db->quoteInto(' AND resource_privilege = ?', $resourcePrivilege)
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

            $arrRules   = $this->getNewRules();
            $arrRuleIds = array();
            foreach ($arrRules as $arrRuleInfo) {
                $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
            }

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id IN (?)', $arrRuleIds)
            );

            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'manage-company');

            $ruleParentId = $db->fetchOne($select);

            foreach ($arrRules as $arrRuleInfo) {
                foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                    $db->insert(
                        'acl_rule_details',
                        array(
                            'rule_id'            => $ruleParentId,
                            'module_id'          => $arrRuleInfo['module_id'],
                            'resource_id'        => $arrRuleInfo['resource_id'],
                            'resource_privilege' => $resourcePrivilege,
                            'rule_allow'         => 1,
                        )
                    );
                }
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }
}