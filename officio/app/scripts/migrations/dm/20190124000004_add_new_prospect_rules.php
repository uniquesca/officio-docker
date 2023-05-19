<?php

use Officio\Migration\AbstractMigration;

class AddNewProspectRules extends AbstractMigration
{

    protected $clearAclCache = true;

    private function _getMainRuleSubRules()
    {
        return array(
            'get-prospects-list',
            'get-prospects-page',
            'get-prospect-title',
            'mark',
            'get-all-prospects-list',
            'get-done-tasks-list',
            'export-to-pdf',
        );
    }

    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

        $statement = $builder
            ->select('rule_id')
            ->from(array('r' => 'acl_rules'))
            ->where(
                [
                    'r.rule_check_id' => 'prospects-view'
                ]
            )
            ->execute();

        $parentId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $parentId =  $row[array_key_first($row)];
        }

        if (empty($parentId)) {
            throw new Exception('There is no prospects rule.');
        }

        $statement = $builder
            ->select('MAX(rule_id)')
            ->from(array('r' => 'acl_rules'))
            ->execute();

        $maxId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $maxId =  $row[array_key_first($row)];
        }

        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='index' WHERE  `rule_id`=$parentId AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='';");

        $this->execute(
            "INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES 
                    (" . ($maxId + 1) . ", $parentId, 'prospects', 'New Prospect', 'prospects-add', 0, 'N', 1, 0),
                    (" . ($maxId + 2) . ", $parentId, 'prospects', 'Change/Save Profile', 'prospects-edit', 0, 'N', 1, 1),
                    (" . ($maxId + 3) . ", $parentId, 'prospects', 'Delete Prospect', 'prospects-delete', 0, 'N', 1, 2),
                    (" . ($maxId + 4) . ", $parentId, 'prospects', 'Convert to Client', 'prospects-convert-to-client', 0, 'N', 1, 3),
                    (" . ($maxId + 5) . ", $parentId, 'prospects', 'Advanced search', 'prospects-advanced-search-run', 0, 'N', 1, 4),
                    (" . ($maxId + 6) . ", " . ($maxId + 5) . ", 'prospects', 'Export', 'prospects-advanced-search-export', 0, 'N', 1, 0),
                    (" . ($maxId + 7) . ", " . ($maxId + 5) . ", 'prospects', 'Print', 'prospects-advanced-search-print', 0, 'N', 1, 1),
                    (" . ($maxId + 8) . ", $parentId, 'prospects', 'Prospect Notes', 'prospects-notes-view', 0, 'N', 1, 5),
                    (" . ($maxId + 9) . ", " . ($maxId + 8) . ", 'prospects', 'Add Notes', 'prospects-notes-add', 0, 'N', 1, 0),
                    (" . ($maxId + 10) . ", " . ($maxId + 8) . ", 'prospects', 'Edit Notes', 'prospects-notes-edit', 0, 'N', 1, 1),
                    (" . ($maxId + 11) . ", " . ($maxId + 8) . ", 'prospects', 'Delete Notes', 'prospects-notes-delete', 0, 'N', 1, 2),
                    (" . ($maxId + 12) . ", $parentId, 'prospects', 'Prospect Documents', 'prospects-documents', 0, 'N', 1, 6)
                    ;
                ");

        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) VALUES 
                    ($parentId, 'prospects', 'index', 'get-prospects-list', 1),
                    ($parentId, 'prospects', 'index', 'get-prospects-page', 1),
                    ($parentId, 'prospects', 'index', 'get-prospect-title', 1),
                    ($parentId, 'prospects', 'index', 'mark', 1),
                    ($parentId, 'prospects', 'index', 'get-all-prospects-list', 1),
                    ($parentId, 'prospects', 'index', 'get-done-tasks-list', 1),
                    ($parentId, 'prospects', 'index', 'export-to-pdf', 1),
                    
                    (" . ($maxId + 1) . ", 'prospects', 'index', 'save', 1),
                    
                    (" . ($maxId + 2) . ", 'prospects', 'index', 'save', 1),
                    (" . ($maxId + 2) . ", 'prospects', 'index', 'save-assessment', 1),
                    (" . ($maxId + 2) . ", 'prospects', 'index', 'save-business', 1),
                    (" . ($maxId + 2) . ", 'prospects', 'index', 'save-occupations', 1),
                    (" . ($maxId + 2) . ", 'prospects', 'index', 'delete-resume', 1),
                    (" . ($maxId + 2) . ", 'prospects', 'index', 'download-resume', 1),
                    
                    (" . ($maxId + 3) . ", 'prospects', 'index', 'delete-prospect', 1),
                    
                    (" . ($maxId + 4) . ", 'prospects', 'index', 'convert-to-client', 1),
                    
                    (" . ($maxId + 5) . ", 'prospects', 'index', 'get-adv-search-fields', 1),
                    (" . ($maxId + 6) . ", 'prospects', 'index', 'export-to-excel', 1),
                    (" . ($maxId + 7) . ", 'prospects', 'index', 'index', 1),
                    
                    (" . ($maxId + 8) . ", 'prospects', 'index', 'get-note', 1),
                    (" . ($maxId + 8) . ", 'prospects', 'index', 'get-notes', 1),
                    (" . ($maxId + 9) . ", 'prospects', 'index', 'notes-add', 1),
                    (" . ($maxId + 10) . ", 'prospects', 'index', 'notes-edit', 1),
                    (" . ($maxId + 11) . ", 'prospects', 'index', 'notes-delete', 1),
                    
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'add-folder', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'copy-files', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'create-letter', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'delete', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'download-email', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'drag-and-drop', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'files-upload', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'get-documents-tree', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'get-image', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'get-pdf', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'open-pdf', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'move-files', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'new-file', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'preview', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'save-file', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'rename-file', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'rename-folder', 1),
                    (" . ($maxId + 12) . ", 'prospects', 'index', 'save-to-inbox', 1)
                    ;"
        );

        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES 
                    (1, " . ($maxId + 1) . ", 'New Prospect', 1),
                    (1, " . ($maxId + 2) . ", 'Change/Save Profile', 1),
                    (1, " . ($maxId + 3) . ", 'Delete Prospect', 1),
                    (1, " . ($maxId + 4) . ", 'Convert to client', 1),
                    (1, " . ($maxId + 5) . ", 'Advanced search', 1),
                    (1, " . ($maxId + 6) . ", 'Export', 1),
                    (1, " . ($maxId + 7) . ", 'Print', 1),
                    (1, " . ($maxId + 8) . ", 'Prospect Notes', 1),
                    (1, " . ($maxId + 9) . ", 'Add Notes', 1),
                    (1, " . ($maxId + 10) . ", 'Edit Notes', 1),
                    (1, " . ($maxId + 11) . ", 'Delete Notes', 1),
                    (1, " . ($maxId + 12) . ", 'Prospect Documents', 1)
                    ;"
        );

        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 1) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 2) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 3) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 4) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 5) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 6) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 7) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 8) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 9) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 10) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 11) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
        $this->execute("INSERT INTO `acl_role_access` (`role_id`, `rule_id`) SELECT a.role_id, " . ($maxId + 12) . " FROM acl_role_access as a WHERE a.rule_id = $parentId;");
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

        $statement   = $builder
            ->select('rule_id')
            ->from(array('r' => 'acl_rules'))
            ->where(
                [
                    'r.rule_check_id' => 'prospects-view'
                ]
            )
            ->execute();

        $parentId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $parentId =  $row[array_key_first($row)];
        }

        if (empty($parentId)) {
            throw new Exception('There is no prospects rule.');
        }

        $arrNewRules = array(
            'prospects-add',
            'prospects-edit',
            'prospects-delete',
            'prospects-convert-to-client',
            'prospects-advanced-search-run',
            'prospects-advanced-search-export',
            'prospects-advanced-search-print',
            'prospects-notes-view',
            'prospects-notes-add',
            'prospects-notes-edit',
            'prospects-notes-delete',
            'prospects-documents'
        );
        $builder
            ->delete('acl_rules')
        ->whereInList('rule_check_id', $arrNewRules)
        ->execute();
        $builder
            ->delete('acl_rule_details')
            ->whereInList('resource_privilege', $this->_getMainRuleSubRules())
            ->andWhere(
                [
                    'rule_id' => $parentId
                ]
            )
            ->execute();

        $this->execute("UPDATE `acl_rule_details` SET `resource_privilege`='' WHERE  `rule_id`=$parentId AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='index';");
    }
}