<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddMembersPua extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'list');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'manage');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'delete');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'upload-designation-form');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'download-designation-form');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'delete-designation-form');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'get-designation-form');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (1030, 'superadmin', 'manage-members-pua', 'export');");

        $this->execute(
            "CREATE TABLE `members_pua` (
             `pua_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
             `member_id` BIGINT(20) NOT NULL,
             `pua_type` ENUM('designated_person', 'business_contact') NOT NULL,
             
             `pua_designated_person_type` ENUM('responsible_person', 'authorized_representative') DEFAULT NULL,
             `pua_designated_person_form` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_full_name` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_given_name` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_family_name` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_address` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_secondary_address` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_phone` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_email` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_rcic_full_name` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_rcic_given_name` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_rcic_family_name` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_rcic_primary_address` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_rcic_secondary_address` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_rcic_phone` VARCHAR(255) NULL DEFAULT NULL,
             `pua_designated_person_primary_rcic_email` VARCHAR(255) NULL DEFAULT NULL,
             
             `pua_business_contact_name` VARCHAR(255) NULL DEFAULT NULL,
             `pua_business_contact_or_service` VARCHAR(255) NULL DEFAULT NULL,
             `pua_business_contact_username` VARCHAR(255) NULL DEFAULT NULL,
             `pua_business_contact_password` VARCHAR(255) NULL DEFAULT NULL,
             `pua_business_contact_instructions` TEXT NULL DEFAULT NULL,
             
             `pua_created_by` BIGINT(20) DEFAULT NULL,
             `pua_created_on` DATETIME NOT NULL,
             `pua_updated_by` BIGINT(20) DEFAULT NULL,
             `pua_updated_on` DATETIME NULL DEFAULT NULL,
             PRIMARY KEY (`pua_id`),
             CONSTRAINT `FK_members_pua_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
             CONSTRAINT `FK_members_pua_members_2` FOREIGN KEY (`pua_created_by`) REFERENCES `members` (`member_id`) ON UPDATE SET NULL ON DELETE SET NULL,
             CONSTRAINT `FK_members_pua_members_3` FOREIGN KEY (`pua_updated_by`) REFERENCES `members` (`member_id`) ON UPDATE SET NULL ON DELETE SET NULL
            )
        COMMENT='Members Planned or Unplanned Absence (i.e. death)'
        COLLATE='utf8_unicode_ci'
        ENGINE=InnoDB;"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1030 AND `module_id`='superadmin' AND `resource_id`='manage-members-pua';");
        $this->execute("DROP TABLE `members_pua`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}