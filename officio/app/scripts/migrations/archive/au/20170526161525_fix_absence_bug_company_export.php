<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class FixAbsenceBugCompanyExport extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='index' WHERE  `rule_id`=1049 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='' WHERE  `rule_id`=1049 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='index';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}