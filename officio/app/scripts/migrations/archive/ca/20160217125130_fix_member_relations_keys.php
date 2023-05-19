<?php

use Officio\Migration\AbstractMigration;

class FixMemberRelationsKeys extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members_relations` DROP INDEX `FK_members_relations`, ADD INDEX `FK_members_relations` (`parent_member_id`);");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_relations` DROP INDEX `FK_members_relations`, ADD INDEX `FK_members_relations` (`parent_member_id`,`child_member_id`);");
    }
}
