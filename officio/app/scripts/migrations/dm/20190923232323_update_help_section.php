<?php

use Officio\Migration\AbstractMigration;

class UpdateHelpSection extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $application = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_subtitle` VARCHAR(255) NULL DEFAULT NULL AFTER `section_name`;");
        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_description` TEXT NULL AFTER `section_subtitle`;");
        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_color` VARCHAR(255) NULL DEFAULT NULL AFTER `section_subtitle`;");
        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_class` VARCHAR(255) NULL DEFAULT NULL AFTER `section_color`;");
        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_external_link` VARCHAR(255) NULL DEFAULT NULL AFTER `section_class`;");
        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_show_as_heading` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `section_external_link`");
        $this->execute("ALTER TABLE `faq_sections` ADD COLUMN `section_type` ENUM('help','ilearn') NOT NULL DEFAULT 'help' AFTER `parent_section_id`");
        $this->execute("ALTER TABLE `faq` ADD COLUMN `featured` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `answer`");
        $this->execute("ALTER TABLE `faq` ADD COLUMN `content_type` ENUM('text','video') NOT NULL DEFAULT 'text' AFTER `featured`");
        $this->execute("ALTER TABLE `faq` ADD COLUMN `meta_tags`  LONGTEXT NULL AFTER `answer`");

        $this->execute("CREATE TABLE `faq_context_ids` (
        	`faq_context_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        	`faq_context_id_text` VARCHAR(255) NOT NULL,
        	`faq_context_id_description` VARCHAR(255) NULL DEFAULT '',
        	PRIMARY KEY (`faq_context_id`)
        )
        COMMENT='Help context ids - are like groups of tags that will be used to filter articles list'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute("CREATE TABLE `faq_tags` (
        	`faq_tag_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        	`faq_tag_text` VARCHAR(255) NOT NULL DEFAULT '',
        	PRIMARY KEY (`faq_tag_id`)
        )
        COMMENT='Help tags - used for help articles and context ids'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute("CREATE TABLE `faq_context_ids_tags` (
        	`faq_context_id` INT(11) UNSIGNED NOT NULL,
        	`faq_tag_id` INT(11) UNSIGNED NOT NULL,
        	UNIQUE INDEX `faq_context_id_faq_tag_id` (`faq_context_id`, `faq_tag_id`),
        	INDEX `FK_faq_context_ids_tags_faq_tags` (`faq_tag_id`),
        	CONSTRAINT `FK_faq_context_ids_tags_faq_context_ids` FOREIGN KEY (`faq_context_id`) REFERENCES `faq_context_ids` (`faq_context_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_faq_context_ids_tags_faq_tags` FOREIGN KEY (`faq_tag_id`) REFERENCES `faq_tags` (`faq_tag_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Tags assigned to the help context id'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute("CREATE TABLE `faq_assigned_tags` (
        	`faq_id` INT(11) UNSIGNED NOT NULL,
        	`faq_tag_id` INT(11) UNSIGNED NOT NULL,
        	UNIQUE INDEX `faq_id_faq_tag_id` (`faq_id`, `faq_tag_id`),
        	INDEX `FK_faq_assigned_tags_faq_tags` (`faq_tag_id`),
        	CONSTRAINT `FK_faq_assigned_tags_faq` FOREIGN KEY (`faq_id`) REFERENCES `faq` (`faq_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_faq_assigned_tags_faq_tags` FOREIGN KEY (`faq_tag_id`) REFERENCES `faq_tags` (`faq_tag_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Tags assigned to the help article'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB"
        );

        // Fix for superadmin access: if can manage - can preview too
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`) VALUES ('1140', 'help', 'index');");
        // Fix for superadmin access: if has access to the superadmin tab - can open the "Admin" tab (view navigation links)
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('1390', 'superadmin', 'manage-company', 'index');");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `faq` DROP COLUMN `meta_tags`;");
        $this->execute("ALTER TABLE `faq` DROP COLUMN `content_type`;");
        $this->execute("ALTER TABLE `faq` DROP COLUMN `featured`;");
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_type`;");
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_show_as_heading`;");
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_external_link`;");
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_color`;");
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_class`;");
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_description`;");
        $this->execute("ALTER TABLE `faq_sections` DROP COLUMN `section_subtitle`;");

        $this->execute("DROP TABLE `faq_assigned_tags`;");
        $this->execute("DROP TABLE `faq_context_ids_tags`;");
        $this->execute("DROP TABLE `faq_tags`;");
        $this->execute("DROP TABLE `faq_context_ids`;");
    }
}