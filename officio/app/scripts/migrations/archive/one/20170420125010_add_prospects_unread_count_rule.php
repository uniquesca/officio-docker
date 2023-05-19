<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddProspectsUnreadCountRule extends AbstractMigration
{
    public function up()
    {
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'prospects-view');

        $parentId = $db->fetchOne($select);

        if (empty($parentId)) {
            throw new Exception('There is no prospects rule.');
        }

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentId, 'prospects', 'index', 'get-prospects-unread-counts');");

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'marketplace-view');

        $marketplaceParentId = $db->fetchOne($select);

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($marketplaceParentId, 'prospects', 'index', 'get-prospects-unread-counts');");

        $this->execute("ALTER TABLE `company_prospects`
            ADD INDEX `company_id` (`company_id`),
            ADD INDEX `status` (`status`),
            ADD INDEX `qualified` (`qualified`);
        ");

        $this->execute("ALTER TABLE `company_prospects_divisions`
	        ADD INDEX `prospect_id_office_id` (`prospect_id`, `office_id`);
        ");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE  `resource_privilege` = 'get-prospects-unread-counts' AND `module` = 'prospects';");
        $this->execute("ALTER TABLE `company_prospects_divisions` DROP INDEX `prospect_id_office_id`;");
        $this->execute("ALTER TABLE `company_prospects`
            DROP INDEX `company_id`,
            DROP INDEX `status`,
            DROP INDEX `qualified`;
        ");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}