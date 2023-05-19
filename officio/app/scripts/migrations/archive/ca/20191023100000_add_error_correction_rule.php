<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class addErrorCorrectionRule extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'Financial Transactions: Error Correction',
                'rule_check_id'      => 'clients-accounting-ft-error-correction',
                'resource_privilege' => 'index',
            ),
        );

        return $arrRules;
    }

    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        try {
            $select = $db->select()
                ->from(array('r' => 'acl_rules'), 'rule_id')
                ->where('r.rule_check_id = ?', 'clients-accounting-view');

            $accountingRuleId = $db->fetchOne($select);

            if (empty($accountingRuleId)) {
                throw new Exception('Accounting rule not found.');
            }

            $arrRules = $this->getNewRules();

            $db->beginTransaction();

            $order = 8;
            foreach ($arrRules as $arrRuleInfo) {
                $db->insert(
                    'acl_rules',
                    array(
                        'rule_parent_id'   => $accountingRuleId,
                        'module_id'        => 'clients',
                        'rule_description' => $arrRuleInfo['rule_description'],
                        'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => $order++,
                    )
                );
                $ruleId = $db->lastInsertId('acl_rules');

                $db->insert(
                    'acl_rule_details',
                    array(
                        'rule_id'            => $ruleId,
                        'module_id'          => 'clients',
                        'resource_id'        => 'accounting',
                        'resource_privilege' => $arrRuleInfo['resource_privilege'],
                        'rule_allow'         => 1,
                    )
                );

                $db->insert(
                    'packages_details',
                    array(
                        'package_id'                 => 2,
                        'rule_id'                    => $ruleId,
                        'package_detail_description' => "Client's Accounting - " . $arrRuleInfo['rule_description'],
                        'visible'                    => 1,
                    )
                );

                $db->query(
                    "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                            SELECT a.role_id, $ruleId
                            FROM acl_role_access AS a
                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                            WHERE a.rule_id = $accountingRuleId"
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
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $arrRules = $this->getNewRules();

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
    }
}
