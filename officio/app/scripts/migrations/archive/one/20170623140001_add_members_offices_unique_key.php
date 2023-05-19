<?php

use Phinx\Migration\AbstractMigration;

class AddMembersOfficesUniqueKey extends AbstractMigration
{
    public function up()
    {
        $this->execute("set session old_alter_table=1;");
        // SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use near 'IGNORE TABLE `members_divisions` ADD UNIQUE INDEX `member_id_division_id_type` (' at line 1
        // ALTER IGNORE ADD INDEX removed in mysql 5.7.4
        // @see http://www.tocker.ca/2013/11/06/the-future-of-alter-ignore-table-syntax.html
        try {
            $this->execute("ALTER TABLE `members_divisions` ADD UNIQUE INDEX `member_id_division_id_type` (`member_id`, `division_id`, `type`);");
        } catch (\Exception $e) {
        }

        $this->execute("set session old_alter_table=0;");
    }

    public function down()
    {
        try {
            $this->execute("ALTER TABLE `members_divisions` DROP INDEX `member_id_division_id_type`;");
        } catch (\Exception $e) {
        }
    }
}