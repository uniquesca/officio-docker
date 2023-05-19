<?php

use Phinx\Migration\AbstractMigration;

class AddEmailTemplateAttachments extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `template_file_attachments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `template_id` INT(11) UNSIGNED NOT NULL,
            `member_id` BIGINT(20) NOT NULL,
            `name` VARCHAR(255) NULL DEFAULT NULL,
            `size` INT(11) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `FK_template_file_attachments_templates` (`template_id`),
            INDEX `FK_template_file_attachments_members` (`member_id`),
            CONSTRAINT `FK_template_file_attachments_templates` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_template_file_attachments_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Contains info about attachments in email templates'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;"
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `template_file_attachments`;");
    }
}
