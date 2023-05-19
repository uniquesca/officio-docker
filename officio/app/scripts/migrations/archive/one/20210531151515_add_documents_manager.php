<?php

use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;

class addDocumentsManager extends AbstractMigration
{
    public function up()
    {
        // For all logged in users and company admins
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'index-view';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found.');
        }

        $parentRuleId = $rule['rule_id'];
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentRuleId, 'documents', 'manager', '');");


        // For superadmins
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'admin-view';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found.');
        }

        $parentRuleId = $rule['rule_id'];
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentRuleId, 'documents', 'manager', '');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'documents' AND resource_id = 'manager'");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}
