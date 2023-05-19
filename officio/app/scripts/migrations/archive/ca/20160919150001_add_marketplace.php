<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddMarketplace extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `company_marketplace_profiles` (
        	`company_id` BIGINT(20) NOT NULL,
        	`marketplace_profile_id` BIGINT(20) NOT NULL,
        	`marketplace_profile_key` VARCHAR(255) DEFAULT NULL,
        	`marketplace_profile_name` VARCHAR(255) DEFAULT NULL,
        	`marketplace_profile_status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'inactive',
        	`marketplace_profile_old_status` ENUM('active', 'inactive', 'suspended') NULL DEFAULT NULL,
        	`marketplace_profile_created_on` DATETIME NOT NULL,
        	UNIQUE INDEX `company_id_marketplace_profile_id` (`company_id`, `marketplace_profile_id`),
        	INDEX `FK_company_marketplace_profiles_company` (`company_id`),
        	CONSTRAINT `FK_company_marketplace_profiles_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB"
        );

        $this->execute("ALTER TABLE `company_details` ADD COLUMN `marketplace_module_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle for the Marketplace module' AFTER `time_tracker_enabled`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1, 'api', 'marketplace', '', 1);");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_marketplace_prospect_convert', '100.00')");

        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->insert(
            'acl_rules',
            array(
                'rule_parent_id'   => '4',
                'module_id'        => 'superadmin',
                'rule_description' => 'Marketplace Profiles',
                'rule_check_id'    => 'manage-marketplace',
                'rule_visible'     => '1',
            )
        );
        $id = $db->lastInsertId('acl_rules');
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`) VALUES ($id, 'superadmin', 'marketplace');");
        $this->execute(
            "INSERT INTO `admin_navigation` (`navigation_id`, `section_id`, `resource_id`, `action`, `rule_check_id`, `title`, `order_id`, `status`) VALUES (NULL, 4, 'marketplace', NULL, 'manage-marketplace', 'Marketplace Profiles', 23, 1);"
        );
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, $id, 'Marketplace Profiles', 1);");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
        $cache->removeItem('superadmin_as_admin_menu');
        $cache->removeItem('admin_menu');
    }

    public function down()
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $db     = Zend_Registry::get('serviceManager')->get('db');
        $select = $db->select()
            ->from('acl_rules', 'rule_id')
            ->where('rule_check_id = ?', 'manage-marketplace');
        $ruleId = $db->fetchOne($select);

        $this->execute("DELETE FROM acl_role_access WHERE rule_id = $ruleId");
        $this->execute("DELETE FROM packages_details WHERE package_detail_description = 'Marketplace Profiles'");
        $this->execute("DELETE FROM admin_navigation WHERE resource_id = 'manage-marketplace'");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'superadmin' AND resource_id = 'marketplace'");
        $this->execute("DELETE FROM acl_rules WHERE rule_check_id = 'manage-marketplace'");
        $this->execute("DELETE FROM `u_variable` WHERE  `name`='price_marketplace_prospect_convert'");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1 AND `module_id`='api' AND `resource_id`='marketplace' AND `resource_privilege`='';");
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `marketplace_module_enabled`");
        $this->execute("DROP TABLE `company_marketplace_profiles`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
        $cache->removeItem('superadmin_as_admin_menu');
        $cache->removeItem('admin_menu');
    }
}