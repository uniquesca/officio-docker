<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class DeleteGetImageAclRuleDetails extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `resource_privilege`='get-image';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        
    }
}
