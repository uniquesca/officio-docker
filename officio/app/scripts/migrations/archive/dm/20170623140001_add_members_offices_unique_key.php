<?php

use Phinx\Migration\AbstractMigration;

class AddMembersOfficesUniqueKey extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members_divisions` ADD UNIQUE INDEX `member_id_division_id_type` (`member_id`, `division_id`, `type`);");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_divisions` DROP INDEX `member_id_division_id_type`;");
    }
}