<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddExtraRulesSuperadminDefaultSearches extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1090, 'templates', 'index', 'get-email-template');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1090, 'applicants', 'search', 'load-search');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1090, 'applicants', 'search', 'save-search');");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1090 AND `module_id`='applicants' AND `resource_id`='search' AND `resource_privilege`='save-search';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1090 AND `module_id`='applicants' AND `resource_id`='search' AND `resource_privilege`='load-search';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1090 AND `module_id`='templates' AND `resource_id`='index' AND `resource_privilege`='get-email-template';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}