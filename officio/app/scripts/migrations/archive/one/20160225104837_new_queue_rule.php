<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class NewQueueRule extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('95', 'applicants', 'queue', 'run-and-get-main-info');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=95 AND `module_id`='applicants' AND `resource_id`='queue' AND `resource_privilege`='run-and-get-main-info';");
    }
}
