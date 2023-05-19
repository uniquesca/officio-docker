<?php

use Officio\Migration\AbstractMigration;

class AddProspectsDropboxAction extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $builder = $this->getQueryBuilder();

        $statement = $builder
            ->select('rule_id')
            ->from(array('r' => 'acl_rules'))
            ->where(
                [
                    'r.rule_check_id' => 'prospects-documents'
                ]
            )
            ->execute();

        $aclRulesRow = $statement->fetch();

        if (empty($aclRulesRow)) {
            throw new Exception('There is no access to default rule.');
        }

        $parentId = $aclRulesRow[0];

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentId, 'prospects', 'index', 'files-upload-from-dropbox');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentId, 'prospects', 'index', 'get-file-download-url');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'prospects' AND `resource_id` = 'index' AND `resource_privilege` = 'get-file-download-url';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'prospects' AND `resource_id` = 'index' AND `resource_privilege` = 'files-upload-from-dropbox';");
    }
}
