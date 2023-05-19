<?php

use Officio\Migration\AbstractMigration;

class AddGenerateInvoiceRules extends AbstractMigration
{

    protected $clearAclCache;

    public function up()
    {
        $application = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("INSERT INTO `acl_rule_details` VALUES (1230, 'superadmin', 'manage-company', 'run-charge', 1);");
        $this->execute("INSERT INTO `acl_rule_details` VALUES (1230, 'superadmin', 'manage-company', 'generate-invoice-template', 1);");
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1230 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='generate-invoice-template';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1230 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='run-charge';");
    }
}