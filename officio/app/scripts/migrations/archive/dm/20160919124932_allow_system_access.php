<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AllowSystemAccess extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rules` SET `superadmin_only`='1', `rule_visible`='1' WHERE  `rule_id`=1130;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rules` SET `superadmin_only`='0', `rule_visible`='0' WHERE  `rule_id`=1130;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}