<?php

use Phinx\Migration\AbstractMigration;

class AddRulesForChangeMyPasswordDialog extends AbstractMigration
{
    public function up()
    {
        $this->getAdapter()->beginTransaction();

        $this->execute(
            "
            INSERT IGNORE INTO `acl_role_access`
            (`role_id`, `rule_id`)
            SELECT r.role_parent_id, 500
            FROM `acl_roles` as r
            WHERE r.role_type IN ('superadmin');
        "
        );

        $this->getAdapter()->commitTransaction();
    }

    public function down()
    {
        $this->getAdapter()->beginTransaction();

        $this->execute("DELETE FROM acl_role_access WHERE rule_id = 500 AND role_id IN (SELECT role_parent_id FROM acl_roles WHERE role_type = 'superadmin')");

        $this->getAdapter()->commitTransaction();
    }
}