<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Migration\AbstractMigration;

class AddMarketplace extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $application = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder = $this->getQueryBuilder();

        $this->execute("CREATE TABLE `company_marketplace_profiles` (
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
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute("ALTER TABLE `company_details` ADD COLUMN `marketplace_module_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle for the Marketplace module' AFTER `time_tracker_enabled`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES (1, 'api', 'marketplace', '', 1);");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('price_marketplace_prospect_convert', '100.00')");

        $statement = $builder
            ->insert(
                array(
                    'rule_parent_id',
                    'module_id',
                    'rule_description',
                    'rule_check_id',
                    'rule_visible'
                )
            )
            ->into('acl_rules')
            ->values(
                array(
                    'rule_parent_id'   => '4',
                    'module_id'        => 'superadmin',
                    'rule_description' => 'Marketplace Profiles',
                    'rule_check_id'    => 'manage-marketplace',
                    'rule_visible'     => '1',
                )
            )
            ->execute();
        $id = $statement->lastInsertId('acl_rules');
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`) VALUES ($id, 'superadmin', 'marketplace');");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, $id, 'Marketplace Profiles', 1);");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        $cache->removeItem('superadmin_as_admin_menu');
        $cache->removeItem('admin_menu');
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

        $statement = $builder
            ->select('rule_id')
            ->from('acl_rules', )
            ->where(
                [
                    'rule_check_id' => 'manage-marketplace'
                ]
            )
            ->execute();

        $ruleId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $ruleId =  $row[array_key_first($row)];
        }

        $this->execute("DELETE FROM acl_role_access WHERE rule_id = $ruleId");
        $this->execute("DELETE FROM packages_details WHERE package_detail_description = 'Marketplace Profiles'");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'superadmin' AND resource_id = 'marketplace'");
        $this->execute("DELETE FROM acl_rules WHERE rule_check_id = 'manage-marketplace'");
        $this->execute("DELETE FROM `u_variable` WHERE  `name`='price_marketplace_prospect_convert'");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1 AND `module_id`='api' AND `resource_id`='marketplace' AND `resource_privilege`='';");
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `marketplace_module_enabled`");
        $this->execute("DROP TABLE `company_marketplace_profiles`;");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        $cache->removeItem('superadmin_as_admin_menu');
        $cache->removeItem('admin_menu');
    }
}