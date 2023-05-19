<?php

use Officio\Migration\AbstractMigration;

class AddActionGetReferenceFieldView extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (11, 'applicants', 'profile', 'get-reference-field-view');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=11 AND `module_id`='applicants' AND `resource_id`='profile' AND `resource_privilege`='get-reference-field-view';");
    }
}
