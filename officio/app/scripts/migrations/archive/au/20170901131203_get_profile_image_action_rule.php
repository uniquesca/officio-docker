<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class GetProfileImageActionRule extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='get-profile-image' WHERE `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='get-dependent-image';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='get-dependent-image' WHERE `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='get-profile-image';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}