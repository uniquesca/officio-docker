<?php

use Phinx\Migration\AbstractMigration;

class AddDocumentsChecklistRuleDetail extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('acl_rules'), array('rule_id'))
            ->where('rule_check_id = ?', 'client-documents-checklist-view');

        $ruleParentId = $db->fetchOne($select);

        if (empty($ruleParentId)) {
            throw new Exception('Main parent rule not found.');
        }

        $db->insert(
            'acl_rule_details',
            array(
                'rule_id'            => $ruleParentId,
                'module_id'          => 'documents',
                'resource_id'        => 'checklist',
                'resource_privilege' => 'get-family-members',
            )
        );
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('acl_rules'), array('rule_id'))
            ->where('rule_check_id = ?', 'client-documents-checklist-view');

        $ruleParentId = $db->fetchOne($select);

        if (empty($ruleParentId)) {
            throw new Exception('Main parent rule not found.');
        }

        $db->delete(
            'acl_rule_details',
            $db->quoteInto('rule_id = ? AND module_id = ? AND resource_id = ? AND resource_privilege = ?', array($ruleParentId, 'documents', 'checklist', 'get-family-members'))
        );
    }
}
