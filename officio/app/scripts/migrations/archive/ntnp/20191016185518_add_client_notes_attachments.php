<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddClientNotesAttachments extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `client_notes_attachments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `note_id` INT(11) UNSIGNED NOT NULL,
            `member_id` BIGINT(20) NOT NULL,
            `name` VARCHAR(255) NULL DEFAULT NULL,
            `size` INT(11) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `FK_client_notes_attachments_u_notes` (`note_id`),
            INDEX `FK_client_notes_attachments_members` (`member_id`),
            CONSTRAINT `FK_client_notes_attachments_u_notes` FOREIGN KEY (`note_id`) REFERENCES `u_notes` (`note_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_notes_attachments_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Contains info about attachments in Client File Notes'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;"
        );

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES
            (31, 'notes', 'index', 'upload-attachments', 1),
            (31, 'notes', 'index', 'download-attachment', 1),
            (32, 'notes', 'index', 'upload-attachments', 1),
            (32, 'notes', 'index', 'download-attachment', 1);"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `client_notes_attachments`;");

        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id` IN (31, 32) AND `resource_privilege`='upload-attachments';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id` IN (31, 32) AND `resource_privilege`='download-attachment';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}
