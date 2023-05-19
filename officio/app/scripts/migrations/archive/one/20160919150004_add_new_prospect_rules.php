<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddNewProspectRules extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='index' WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='';");

        $this->execute(
            "INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES 
            (2219, 200, 'prospects', 'New Prospect', 'prospects-add', 0, 'N', 1, 0),
            (2220, 200, 'prospects', 'Change/Save Profile', 'prospects-edit', 0, 'N', 1, 1),
            (2221, 200, 'prospects', 'Delete Prospect', 'prospects-delete', 0, 'N', 1, 2),
            (2222, 200, 'prospects', 'Convert to Client', 'prospects-convert-to-client', 0, 'N', 1, 3),
            (2223, 200, 'prospects', 'Advanced search', 'prospects-advanced-search-run', 0, 'N', 1, 4),
            (2224, 2223, 'prospects', 'Export', 'prospects-advanced-search-export', 0, 'N', 1, 0),
            (2225, 2223, 'prospects', 'Print', 'prospects-advanced-search-print', 0, 'N', 1, 1),
            (2226, 200, 'prospects', 'Prospect Notes', 'prospects-notes-view', 0, 'N', 1, 5),
            (2227, 2226, 'prospects', 'Add Notes', 'prospects-notes-add', 0, 'N', 1, 0),
            (2228, 2226, 'prospects', 'Edit Notes', 'prospects-notes-edit', 0, 'N', 1, 1),
            (2229, 2226, 'prospects', 'Delete Notes', 'prospects-notes-delete', 0, 'N', 1, 2),
            (2230, 200, 'prospects', 'Prospect Documents', 'prospects-documents', 0, 'N', 1, 6)
            ;
        ");

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES 
            (200, 'prospects', 'index', 'get-prospects-list', 1),
            (200, 'prospects', 'index', 'get-prospects-page', 1),
            (200, 'prospects', 'index', 'get-prospect-title', 1),
            (200, 'prospects', 'index', 'mark', 1),
            (200, 'prospects', 'index', 'get-all-prospects-list', 1),
            (200, 'prospects', 'index', 'get-done-tasks-list', 1),
            (200, 'prospects', 'index', 'export-to-pdf', 1),
            
            (2219, 'prospects', 'index', 'save', 1),
            
            (2220, 'prospects', 'index', 'save', 1),
            (2220, 'prospects', 'index', 'save-assessment', 1),
            (2220, 'prospects', 'index', 'save-business', 1),
            (2220, 'prospects', 'index', 'save-occupations', 1),
            (2220, 'prospects', 'index', 'delete-resume', 1),
            (2220, 'prospects', 'index', 'download-resume', 1),
            
            (2221, 'prospects', 'index', 'delete-prospect', 1),
            
            (2222, 'prospects', 'index', 'convert-to-client', 1),
            
            (2223, 'prospects', 'index', 'get-adv-search-fields', 1),
            (2224, 'prospects', 'index', 'export-to-excel', 1),
            (2225, 'prospects', 'index', 'index', 1),
            
            (2226, 'prospects', 'index', 'get-note', 1),
            (2226, 'prospects', 'index', 'get-notes', 1),
            (2227, 'prospects', 'index', 'notes-add', 1),
            (2228, 'prospects', 'index', 'notes-edit', 1),
            (2229, 'prospects', 'index', 'notes-delete', 1),
            
            (2230, 'prospects', 'index', 'add-folder', 1),
            (2230, 'prospects', 'index', 'copy-files', 1),
            (2230, 'prospects', 'index', 'create-letter', 1),
            (2230, 'prospects', 'index', 'delete', 1),
            (2230, 'prospects', 'index', 'download-email', 1),
            (2230, 'prospects', 'index', 'drag-and-drop', 1),
            (2230, 'prospects', 'index', 'files-upload', 1),
            (2230, 'prospects', 'index', 'get-documents-tree', 1),
            (2230, 'prospects', 'index', 'get-image', 1),
            (2230, 'prospects', 'index', 'get-pdf', 1),
            (2230, 'prospects', 'index', 'open-pdf', 1),
            (2230, 'prospects', 'index', 'move-files', 1),
            (2230, 'prospects', 'index', 'new-file', 1),
            (2230, 'prospects', 'index', 'preview', 1),
            (2230, 'prospects', 'index', 'save-file', 1),
            (2230, 'prospects', 'index', 'rename-file', 1),
            (2230, 'prospects', 'index', 'rename-folder', 1),
            (2230, 'prospects', 'index', 'save-to-inbox', 1)
            ;"
        );

        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES 
            (1, 2219, 'New Prospect', 1),
            (1, 2220, 'Change/Save Profile', 1),
            (1, 2221, 'Delete Prospect', 1),
            (1, 2222, 'Convert to client', 1),
            (1, 2223, 'Advanced search', 1),
            (1, 2224, 'Export', 1),
            (1, 2225, 'Print', 1),
            (1, 2226, 'Prospect Notes', 1),
            (1, 2227, 'Add Notes', 1),
            (1, 2228, 'Edit Notes', 1),
            (1, 2229, 'Delete Notes', 1),
            (1, 2230, 'Prospect Documents', 1)
            ;"
        );

        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2219 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2220 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2221 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2222 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2223 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2224 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2225 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2226 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2227 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2228 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2229 FROM acl_role_access as a WHERE a.rule_id = 200;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, 2230 FROM acl_role_access as a WHERE a.rule_id = 200;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_id` IN (2219, 2220, 2221, 2222, 2223, 2224, 2225, 2226, 2227, 2228, 2229, 2230);");
        $this->execute(
            "DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='export-to-pdf';
            DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='get-all-prospects-list';
            DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='get-done-tasks-list';
            DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='get-prospect-title';
            DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='get-prospects-list';
            DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='get-prospects-page';
            DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='mark';"
        );
        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='' WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='index';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}