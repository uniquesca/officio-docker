<?php

use Officio\Migration\AbstractMigration;

class AddUrlChecker extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $application = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder = $this->getQueryBuilder();

        $this->execute("CREATE TABLE `snapshots` (
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
        ENGINE=InnoDB;");

        $statement = $builder
            ->insert(
            array(
                'rule_parent_id',
                'module_id',
                'rule_description',
                'rule_check_id',
                'superadmin_only',
                'rule_visible',
                'rule_order'
            )
        )
            ->into('acl_rules')
            ->values(
                array(
            'rule_parent_id'   => 4,
            'module_id'        => 'superadmin',
            'rule_description' => 'PDF Version Checker',
            'rule_check_id'    => 'url-checker',
            'superadmin_only'  => 1,
            'rule_visible'     => 1,
            'rule_order'       => 1,
                )
            )
            ->execute();

        $id = $statement->lastInsertId('acl_rules');

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES
            ($id, 'superadmin', 'url-checker', '');"
        );

        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
            (1, $id, 'PDF Version Checker', 1);"
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `snapshots`;");

        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_check_id` = 'url-checker';");
    }
}
