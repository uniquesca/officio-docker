<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddUrlChecker extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `snapshots` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `url` CHAR(255) NULL DEFAULT NULL,
            `assigned_form_id` INT(10) UNSIGNED NULL DEFAULT NULL,
            `url_description` TEXT NULL,
            `created` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` TIMESTAMP NULL DEFAULT NULL,
            `hash` CHAR(255) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `FK_snapshots_FormUpload` (`assigned_form_id`),
            CONSTRAINT `FK_snapshots_FormUpload` FOREIGN KEY (`assigned_form_id`) REFERENCES `FormUpload` (`FormId`) ON UPDATE CASCADE ON DELETE SET NULL
        )
        COMMENT='Contains URLs for PDF Version Checker in SuperAdmin'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;"
        );

        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->insert(
            'acl_rules',
            array(
                'rule_parent_id'   => 4,
                'module_id'        => 'superadmin',
                'rule_description' => 'PDF Version Checker',
                'rule_check_id'    => 'url-checker',
                'superadmin_only'  => 1,
                'rule_visible'     => 1,
                'rule_order'       => 1,
            )
        );

        $id = $db->lastInsertId('acl_rules');

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES
            ($id, 'superadmin', 'url-checker', '');"
        );

        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
            (1, $id, 'PDF Version Checker', 1);"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `snapshots`;");

        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_check_id` = 'url-checker';");
    }
}
