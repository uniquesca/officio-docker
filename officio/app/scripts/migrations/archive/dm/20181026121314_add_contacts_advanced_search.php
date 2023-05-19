<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddContactsAdvancedSearch extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'Export',
                'rule_check_id'      => 'contacts-advanced-search-export',
                'module_id'          => 'applicants',
                'resource_id'        => 'search',
                'resource_privilege' => array('export-to-excel')
            ),
            array(
                'rule_description'   => 'Print',
                'rule_check_id'      => 'contacts-advanced-search-print',
                'module_id'          => 'applicants',
                'resource_id'        => 'search',
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
            $select = $db->select()
                ->from(array('acl_rules'), array('rule_id'))
                ->where('rule_check_id = ?', 'contacts-view');

            $ruleParentId = $db->fetchOne($select);

            if (empty($ruleParentId)) {
                throw new Exception('Main parent rule not found.');
            }

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id'   => $ruleParentId,
                    'module_id'        => 'clients',
                    'rule_description' => 'Advanced search',
                    'rule_check_id'    => 'contacts-advanced-search-run',
                    'superadmin_only'  => 0,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 0,
                )
            );
            $advancedSearchRuleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $advancedSearchRuleId,
                    'module_id'          => 'applicants',
                    'resource_id'        => 'search',
                    'resource_privilege' => 'run-search',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $advancedSearchRuleId,
                    'package_detail_description' => 'Contacts Advanced search',
                    'visible'                    => 1,
                )
            );

            $db->query(
                "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                            SELECT a.role_id, $advancedSearchRuleId
                                            FROM acl_role_access AS a
                                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                            WHERE a.rule_id = $ruleParentId"
            );

            $arrRules = $this->getNewRules();

            $order = 0;
            foreach ($arrRules as $arrRuleInfo) {
                $db->insert(
                    'acl_rules',
                    array(
                        'rule_parent_id'   => $advancedSearchRuleId,
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

            $arrRuleIds = array('contacts-advanced-search-run');

            $arrRules = $this->getNewRules();
            foreach ($arrRules as $arrRuleInfo) {
                $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
            }

            $db->delete(
                'acl_rules',
                $db->quoteInto('rule_check_id IN (?)', $arrRuleIds)
            );
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}