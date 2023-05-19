<?php

use Officio\Migration\AbstractMigration;

class AddCompaniesExportMainInfoRule extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $this->execute("INSERT IGNORE INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`) SELECT r.rule_id, 'superadmin', 'manage-company', 'export-companies-main-info', 1 FROM `acl_rules` as r WHERE r.rule_check_id = 'manage-company-delete';");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `resource_privilege` = 'export-companies-main-info'");
    }
}