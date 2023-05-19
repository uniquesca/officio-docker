<?php

use Phinx\Migration\AbstractMigration;

class UpdateTemplates extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `templates`
	        ADD COLUMN `attachments_pdf` TINYINT(1) NULL DEFAULT '0' AFTER `default`"
        );

        $this->execute(
            "CREATE TABLE `template_attachments` (
                            `email_template_id` INT(11) UNSIGNED NOT NULL,
                            `letter_template_id` INT(11) UNSIGNED NOT NULL,
                            INDEX `FK_template_attachments_email_templates` (`email_template_id`),
                            INDEX `FK_template_attachments_letter_templates` (`letter_template_id`),
                            CONSTRAINT `FK_template_attachments_email_templates` FOREIGN KEY (`email_template_id`) REFERENCES `templates` (`template_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_template_attachments_letter_templates` FOREIGN KEY (`letter_template_id`) REFERENCES `templates` (`template_id`) ON UPDATE CASCADE ON DELETE CASCADE
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;"
        );
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `templates`
	        DROP COLUMN `attachments_pdf`;"
        );

        $this->execute("DROP TABLE IF EXISTS `template_attachments`;");
    }
}
