<?php

use Phinx\Migration\AbstractMigration;

class UpdateManageSuperadminRoles extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rules` SET `superadmin_only`=1 WHERE  `rule_id`=2211;");
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rules` SET `superadmin_only`=0 WHERE  `rule_id`=2211;");
    }
}
