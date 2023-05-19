<?php

use Phinx\Migration\AbstractMigration;

class AddLastAccessClientKey extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM members_last_access WHERE view_member_id NOT IN (SELECT member_id FROM members)");
        $this->execute("ALTER TABLE `members_last_access` ADD CONSTRAINT `FK_members_last_access_members` FOREIGN KEY (`view_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_last_access` DROP FOREIGN KEY `FK_members_last_access_members`;");
    }
}