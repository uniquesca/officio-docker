<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddMarketplaceRules extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES 
            (2231, 5, 'prospects', 'Marketplace', 'marketplace-view', 0, 'N', 1, 25),
            (2232, 2231, 'prospects', 'Convert to Client', 'marketplace-convert-to-client', 0, 'N', 1, 0),
            (2233, 2231, 'prospects', 'Advanced search', 'marketplace-advanced-search-run', 0, 'N', 1, 1),
            (2234, 2233, 'prospects', 'Export', 'marketplace-advanced-search-export', 0, 'N', 1, 0),
            (2235, 2233, 'prospects', 'Print', 'marketplace-advanced-search-print', 0, 'N', 1, 1),
            (2236, 2231, 'prospects', 'Prospect Notes', 'marketplace-notes-view', 0, 'N', 1, 2),
            (2237, 2236, 'prospects', 'Add Notes', 'marketplace-notes-add', 0, 'N', 1, 0),
            (2238, 2236, 'prospects', 'Edit Notes', 'marketplace-notes-edit', 0, 'N', 1, 1),
            (2239, 2236, 'prospects', 'Delete Notes', 'marketplace-notes-delete', 0, 'N', 1, 2),
            (2240, 2231, 'prospects', 'Prospect Documents', 'marketplace-documents', 0, 'N', 1, 3)
            ;
        ");

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES 
            (2231, 'prospects', 'index', 'index', 1),
            (2231, 'superadmin', 'manage-company-prospects', 'get-templates-list', 1),
            (2231, 'prospects', 'index', 'get-prospects-list', 1),
            (2231, 'prospects', 'index', 'get-prospects-page', 1),
            (2231, 'prospects', 'index', 'get-prospect-title', 1),
            (2231, 'prospects', 'index', 'mark', 1),
            (2231, 'prospects', 'index', 'get-all-prospects-list', 1),
            (2231, 'prospects', 'index', 'get-done-tasks-list', 1),
            (2231, 'prospects', 'index', 'export-to-pdf', 1),
            
            (2231, 'prospects', 'index', 'download-resume', 1),
            
            (2232, 'prospects', 'index', 'convert-to-client', 1),
            
            (2233, 'prospects', 'index', 'get-adv-search-fields', 1),
            (2234, 'prospects', 'index', 'export-to-excel', 1),
            (2235, 'prospects', 'index', 'index', 1),
            
            (2236, 'prospects', 'index', 'get-note', 1),
            (2236, 'prospects', 'index', 'get-notes', 1),
            (2237, 'prospects', 'index', 'notes-add', 1),
            (2238, 'prospects', 'index', 'notes-edit', 1),
            (2239, 'prospects', 'index', 'notes-delete', 1),
            
            (2240, 'prospects', 'index', 'add-folder', 1),
            (2240, 'prospects', 'index', 'copy-files', 1),
            (2240, 'prospects', 'index', 'create-letter', 1),
            (2240, 'prospects', 'index', 'delete', 1),
            (2240, 'prospects', 'index', 'download-email', 1),
            (2240, 'prospects', 'index', 'drag-and-drop', 1),
            (2240, 'prospects', 'index', 'files-upload', 1),
            (2240, 'prospects', 'index', 'get-documents-tree', 1),
            (2240, 'prospects', 'index', 'get-image', 1),
            (2240, 'prospects', 'index', 'get-pdf', 1),
            (2240, 'prospects', 'index', 'open-pdf', 1),
            (2240, 'prospects', 'index', 'move-files', 1),
            (2240, 'prospects', 'index', 'new-file', 1),
            (2240, 'prospects', 'index', 'preview', 1),
            (2240, 'prospects', 'index', 'save-file', 1),
            (2240, 'prospects', 'index', 'rename-file', 1),
            (2240, 'prospects', 'index', 'rename-folder', 1),
            (2240, 'prospects', 'index', 'save-to-inbox', 1)
            ;"
        );

        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES 
            (3, 2231, 'Marketplace', 1),
            (3, 2232, 'Convert to client', 1),
            (3, 2233, 'Advanced search', 1),
            (3, 2234, 'Export', 1),
            (3, 2235, 'Print', 1),
            (3, 2236, 'Prospect Notes', 1),
            (3, 2237, 'Add Notes', 1),
            (3, 2238, 'Edit Notes', 1),
            (3, 2239, 'Delete Notes', 1),
            (3, 2240, 'Prospect Documents', 1)
            ;"
        );

        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2231 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2232 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2233 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2234 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2235 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2236 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2237 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2238 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2239 FROM acl_role_access WHERE `rule_id` = '200';");
        $this->execute("INSERT IGNORE INTO acl_role_access (`role_id`, `rule_id`) SELECT `role_id`, 2240 FROM acl_role_access WHERE `rule_id` = '200';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_id` >= 2231 AND `rule_id` <= 2240;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}