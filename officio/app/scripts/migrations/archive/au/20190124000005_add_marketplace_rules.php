<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddMarketplaceRules extends AbstractMigration
{
    public function up()
    {
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), 'rule_id')
            ->where('r.rule_check_id = ?', 'prospects-view');

        $parentId = $db->fetchOne($select);

        if (empty($parentId)) {
            throw new Exception('There is no prospects rule.');
        }

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), new Zend_Db_Expr('MAX(rule_id)'));
        $maxId  = (int)$db->fetchOne($select);

        $this->execute(
            "INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES 
            (" . ($maxId + 1) . ", 5, 'prospects', 'Marketplace', 'marketplace-view', 0, 'N', 1, 25),
            (" . ($maxId + 2) . ", " . ($maxId + 1) . ", 'prospects', 'Convert to Client', 'marketplace-convert-to-client', 0, 'N', 1, 0),
            (" . ($maxId + 3) . ", " . ($maxId + 1) . ", 'prospects', 'Advanced search', 'marketplace-advanced-search-run', 0, 'N', 1, 1),
            (" . ($maxId + 4) . ", " . ($maxId + 3) . ", 'prospects', 'Export', 'marketplace-advanced-search-export', 0, 'N', 1, 0),
            (" . ($maxId + 5) . ", " . ($maxId + 3) . ", 'prospects', 'Print', 'marketplace-advanced-search-print', 0, 'N', 1, 1),
            (" . ($maxId + 6) . ", " . ($maxId + 1) . ", 'prospects', 'Prospect Notes', 'marketplace-notes-view', 0, 'N', 1, 2),
            (" . ($maxId + 7) . ", " . ($maxId + 6) . ", 'prospects', 'Add Notes', 'marketplace-notes-add', 0, 'N', 1, 0),
            (" . ($maxId + 8) . ", " . ($maxId + 6) . ", 'prospects', 'Edit Notes', 'marketplace-notes-edit', 0, 'N', 1, 1),
            (" . ($maxId + 9) . ", " . ($maxId + 6) . ", 'prospects', 'Delete Notes', 'marketplace-notes-delete', 0, 'N', 1, 2),
            (" . ($maxId + 10) . ", " . ($maxId + 1) . ", 'prospects', 'Prospect Documents', 'marketplace-documents', 0, 'N', 1, 3)
            ;
        "
        );

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES 
            (" . ($maxId + 1) . ", 'prospects', 'index', 'index', 1),
            (" . ($maxId + 1) . ", 'superadmin', 'manage-company-prospects', 'get-templates-list', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'get-prospects-list', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'get-prospects-page', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'get-prospect-title', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'mark', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'get-all-prospects-list', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'get-done-tasks-list', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'export-to-pdf', 1),
            (" . ($maxId + 1) . ", 'prospects', 'index', 'download-resume', 1),
            
            (" . ($maxId + 2) . ", 'prospects', 'index', 'convert-to-client', 1),
            
            (" . ($maxId + 3) . ", 'prospects', 'index', 'get-adv-search-fields', 1),
            (" . ($maxId + 4) . ", 'prospects', 'index', 'export-to-excel', 1),
            (" . ($maxId + 5) . ", 'prospects', 'index', 'index', 1),
            
            (" . ($maxId + 6) . ", 'prospects', 'index', 'get-note', 1),
            (" . ($maxId + 6) . ", 'prospects', 'index', 'get-notes', 1),
            (" . ($maxId + 7) . ", 'prospects', 'index', 'notes-add', 1),
            (" . ($maxId + 8) . ", 'prospects', 'index', 'notes-edit', 1),
            (" . ($maxId + 9) . ", 'prospects', 'index', 'notes-delete', 1),
            
            (" . ($maxId + 10) . ", 'prospects', 'index', 'add-folder', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'copy-files', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'create-letter', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'delete', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'download-email', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'drag-and-drop', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'files-upload', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'get-documents-tree', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'get-image', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'get-pdf', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'open-pdf', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'move-files', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'new-file', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'preview', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'save-file', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'rename-file', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'rename-folder', 1),
            (" . ($maxId + 10) . ", 'prospects', 'index', 'save-to-inbox', 1)
            ;"
        );

        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES 
            (3, " . ($maxId + 1) . ", 'Marketplace', 1),
            (3, " . ($maxId + 2) . ", 'Convert to client', 1),
            (3, " . ($maxId + 3) . ", 'Advanced search', 1),
            (3, " . ($maxId + 4) . ", 'Export', 1),
            (3, " . ($maxId + 5) . ", 'Print', 1),
            (3, " . ($maxId + 6) . ", 'Prospect Notes', 1),
            (3, " . ($maxId + 7) . ", 'Add Notes', 1),
            (3, " . ($maxId + 8) . ", 'Edit Notes', 1),
            (3, " . ($maxId + 9) . ", 'Delete Notes', 1),
            (3, " . ($maxId + 10) . ", 'Prospect Documents', 1)
            ;"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_check_id` LIKE 'marketplace%';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}