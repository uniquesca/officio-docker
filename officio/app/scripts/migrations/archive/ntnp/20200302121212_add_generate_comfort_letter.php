<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddGenerateComfortLetter extends AbstractMigration
{
    public function up()
    {
        try {
            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $select = $db->select()
                ->from(array('r' => 'acl_rules'), 'rule_id')
                ->where('r.rule_check_id = ?', 'clients-view');

            $parentRuleId = $db->fetchOne($select);

            if (empty($parentRuleId)) {
                throw new Exception('Parent rule not found.');
            }

            $db->insert(
                'acl_rules',
                array(
                    'rule_parent_id'   => $parentRuleId,
                    'module_id'        => 'applicants',
                    'rule_description' => 'Generate Comfort Letter',
                    'rule_check_id'    => 'generate-pdf-letter',
                    'superadmin_only'  => 0,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 24,
                )
            );

            $ruleId = $db->lastInsertId('acl_rules');

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $ruleId,
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'generate-pdf-letter',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'acl_rule_details',
                array(
                    'rule_id'            => $ruleId,
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'get-letter-templates-by-type',
                    'rule_allow'         => 1,
                )
            );

            $db->insert(
                'packages_details',
                array(
                    'package_id'                 => 1,
                    'rule_id'                    => $ruleId,
                    'package_detail_description' => 'Generate Comfort Letter',
                    'visible'                    => 1,
                )
            );

            $db->query("ALTER TABLE `company_details` ADD COLUMN `templates_settings` TEXT NULL COMMENT 'Templates settings (e.g. comfort letter)' AFTER `case_number_settings`;");

            $db->commit();

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
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
                $db->quoteInto('rule_check_id = ?', 'generate-pdf-letter')
            );

            $db->query("ALTER TABLE `company_details` DROP COLUMN `templates_settings`;");

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }

        } catch (\Exception $e) {
           /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }
}
