<?php

use Phinx\Migration\AbstractMigration;

class AddColumnToMembersDivisions extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `members_divisions`
            ADD COLUMN `responsible_for` ENUM('Y','N') NULL DEFAULT 'N' AFTER `division_id`;
        "
        );
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `members_divisions`
	        DROP COLUMN `responsible_for`;"
        );
    }
}