<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class addAccountingEmailRules extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        try {
            $db->beginTransaction();

            $select = $db->select()
                ->from(array('r' => 'acl_rules'), 'rule_id')
                ->where('r.rule_check_id = ?', 'clients-accounting-email-accounting');

            $accountingRuleId = $db->fetchOne($select);

            if (empty($accountingRuleId)) {
                throw new Exception('Accounting rule not found.');
            }

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $accountingRuleId,
                    'module_id'          => 'mail',
                    'resource_id'        => 'index',
                    'resource_privilege' => 'send',
                    'rule_allow'         => 1,
                )
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
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'clients-accounting-email-accounting');

        $accountingRuleId = $db->fetchOne($select);

        if (!empty($accountingRuleId)) {
            $db->delete(
                'acl_rule_details',
                $db->quoteInto('rule_id = ? AND module_id = "mail" AND resource_id = "index" AND resource_privilege = "send"', $accountingRuleId, 'INT')
            );
        }

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}
