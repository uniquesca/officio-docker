<?php

use Officio\Migration\AbstractMigration;

class DeleteGetImageAclRuleDetails extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `resource_privilege`='get-image';");
    }

    public function down()
    {
        
    }
}
