<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddProspectNotesAttachments extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `company_prospect_notes_attachments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `note_id` BIGINT(20) UNSIGNED NOT NULL,
            `prospect_id` BIGINT(20) NULL DEFAULT NULL,
            `name` VARCHAR(255) NULL DEFAULT NULL,
            `size` INT(11) NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `FK_company_prospect_notes_attachments_company_prospects_notes` (`note_id`),
            INDEX `FK_company_prospect_notes_attachments_company_prospects` (`prospect_id`),
            CONSTRAINT `FK_client_notes_attachments_company_prospects_notes` FOREIGN KEY (`note_id`) REFERENCES `company_prospects_notes` (`note_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_notes_attachments_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Contains info about attachments in Prospect File Notes'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;");

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'prospects-notes-add');

        $addRuleId = $db->fetchOne($select);

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'prospects-notes-edit');

        $editRuleId = $db->fetchOne($select);

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES
            ($addRuleId, 'prospects', 'index', 'upload-attachments', 1),
            ($addRuleId, 'prospects', 'index', 'download-attachment', 1),
            ($editRuleId, 'prospects', 'index', 'upload-attachments', 1),
            ($editRuleId, 'prospects', 'index', 'download-attachment', 1);");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `company_prospect_notes_attachments`;");

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'prospects-notes-add');

        $addRuleId = $db->fetchOne($select);

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'prospects-notes-edit');

        $editRuleId = $db->fetchOne($select);

        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id` IN ($addRuleId, $editRuleId) AND `resource_privilege`='upload-attachments';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id` IN ($addRuleId, $editRuleId) AND `resource_privilege`='download-attachment';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}
