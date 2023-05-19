<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddImmiFunctionality extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'index', 'submit-immi');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'index', 'stop-immi-submission');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 140 AND `resource_privilege` = 'submit-immi';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 140 AND `resource_privilege` = 'stop-immi-submission';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}