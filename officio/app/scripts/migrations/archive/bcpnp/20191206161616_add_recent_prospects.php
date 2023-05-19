<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddRecentProspects extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('200', 'prospects', 'index', 'get-recent-prospects');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='get-recent-prospects';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}
