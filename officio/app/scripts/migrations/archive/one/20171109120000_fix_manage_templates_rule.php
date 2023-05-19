<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class FixManageTemplatesRule extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_check_id`='manage-templates' WHERE `rule_id`=1100;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_check_id`='faq-view' WHERE `rule_id`=1100;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}