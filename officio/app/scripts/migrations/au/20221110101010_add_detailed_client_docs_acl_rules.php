<?php

use Officio\Migration\AbstractMigration;

class AddDetailedClientDocsAclRules extends AbstractMigration
{
    private function _getParentRuleId()
    {
        $statement = $this->getQueryBuilder()
            ->select('rule_id')
            ->from('acl_rules')
            ->where(['rule_check_id' => 'client-documents-view'])
            ->execute();

        $parentRuleId = false;

        $row = $statement->fetch();
        if (count($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        return $parentRuleId;
    }

    public function up()
    {
        $parentRuleId = $this->_getParentRuleId();
        $this->execute(sprintf("DELETE FROM acl_rule_details WHERE rule_id = %d", $parentRuleId));

        $arrNewRulesActions = [
            'add-default-folders',
            'add-folder',
            'convert-to-pdf',
            'copy-files',
            'create-letter',
            'create-letter-on-letterhead',
            'create-zip',
            'download-doc-file',
            'download-email',
            'drag-and-drop',
            'files-upload',
            'files-upload-from-dropbox',
            'files-upload-from-google-drive',
            'get-file',
            'get-file-download-url',
            'get-letterheads-list',
            'get-pdf',
            'get-tree',
            'index',
            'move-files',
            'new-file',
            'open-pdf',
            'preview',
            'print-email',
            'rename-file',
            'rename-folder',
            'save-doc-file',
            'save-file',
            'save-file-to-client-documents',
            'save-file-to-google-drive',
            'save-to-inbox',
            'upload-file',
        ];

        foreach ($arrNewRulesActions as $action) {
            $this->table('acl_rule_details')
                ->insert([
                    'rule_id'            => $parentRuleId,
                    'module_id'          => 'documents',
                    'resource_id'        => 'index',
                    'resource_privilege' => $action,
                    'rule_allow'         => 1,
                ])
                ->save();
        }

        // Create a new rule for delete only
        $arrRuleDetails = array(
            'rule_parent_id'   => $parentRuleId,
            'module_id'        => 'documents',
            'rule_description' => 'Delete Documents',
            'rule_check_id'    => 'client-documents-delete',
            'superadmin_only'  => 0,
            'crm_only'         => 'N',
            'rule_visible'     => 1,
            'rule_order'       => 1,
        );

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrRuleDetails))
            ->into('acl_rules')
            ->values($arrRuleDetails)
            ->execute();

        $ruleId = $statement->lastInsertId('acl_rules');

        $arrRuleDetails = [
            'rule_id'            => $ruleId,
            'module_id'          => 'documents',
            'resource_id'        => 'index',
            'resource_privilege' => 'delete',
            'rule_allow'         => 1,
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrRuleDetails))
            ->into('acl_rule_details')
            ->values($arrRuleDetails)
            ->execute();

        // Package details record
        $arrPackageDetails = [
            'package_id'                 => 1,
            'rule_id'                    => $ruleId,
            'package_detail_description' => 'Client Documents Delete',
            'visible'                    => 1,
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrPackageDetails))
            ->into('packages_details')
            ->values($arrPackageDetails)
            ->execute();

        $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
            SELECT a.role_id, $ruleId
            FROM acl_role_access AS a
            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
            WHERE a.rule_id = $parentRuleId AND r.role_type NOT IN ('employer_client', 'individual_client')"
        );
    }

    public function down()
    {
        $parentRuleId = $this->_getParentRuleId();
        $this->execute(sprintf("DELETE FROM acl_rule_details WHERE rule_id = %d", $parentRuleId));
        $this->execute(sprintf("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`) VALUES (%d, 'documents', 'index');", $parentRuleId));
    }
}
