<?php

use Officio\Migration\AbstractMigration;

class AddRecentProspects extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('200', 'prospects', 'index', 'get-recent-prospects');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=200 AND `module_id`='prospects' AND `resource_id`='index' AND `resource_privilege`='get-recent-prospects';");
    }
}
