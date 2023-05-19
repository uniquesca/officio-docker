<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class FixCompanyExport extends AbstractMigration
{
    public function up()
    {
        $this->getAdapter()->beginTransaction();

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('acl_rules'), array('rule_id'))
            ->where('rule_check_id = ?', 'allow-export');

        $ruleId = $db->fetchOne($select);

        $this->execute("UPDATE `acl_rules` SET `rule_description`='Export clients, prospects, etc.', `superadmin_only` = '0', `rule_visible` = '1' WHERE  `rule_id`= $ruleId;");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES ('1', $ruleId, 'Company export', '1');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);

        $this->getAdapter()->commitTransaction();
    }

    public function down()
    {
        $this->getAdapter()->beginTransaction();

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('acl_rules'), array('rule_id'))
            ->where('rule_check_id = ?', 'allow-export');

        $ruleId = $db->fetchOne($select);

        $this->execute("UPDATE `acl_rules` SET `rule_description`='Allow export', `superadmin_only` = '1', `rule_visible` = '0' WHERE  `rule_id`= $ruleId;");
        $this->execute("DELETE FROM `packages_details` WHERE  `package_id`=1 AND `rule_id`= $ruleId AND `package_detail_description`='Company export';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);

        $this->getAdapter()->commitTransaction();
    }
}