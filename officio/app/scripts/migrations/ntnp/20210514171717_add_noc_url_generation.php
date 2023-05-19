<?php

use Officio\Migration\AbstractMigration;

class addNocUrlGeneration extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('1', 'qnr', 'index', 'get-noc-url-by-code')");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id`=1 AND `module_id`='qnr' AND `resource_id`='index' AND `resource_privilege`='get-noc-url-by-code'");
    }
}
