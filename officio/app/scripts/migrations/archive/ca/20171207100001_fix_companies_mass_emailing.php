<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class FixCompaniesMassEmailing extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES (1430, 1040, 'superadmin', 'Send Mass Email', 'mass-email', 1, 'N', 1, 0);"
        );
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1430, 'superadmin', 'manage-company', 'mass-email', 1)");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (3, 1430, 'Send Mass Email', 0)");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 1430 FROM `acl_role_access` as a WHERE a.rule_id = 1040;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id`=1430;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}