<?php

use Officio\Migration\AbstractMigration;

class AddMembersOfficesUniqueKey extends AbstractMigration
{
    public function up()
    {
        // Took 218s on the local server
        $this->execute("CREATE TABLE members_divisions_temp LIKE members_divisions;");
        $this->execute("ALTER TABLE `members_divisions_temp` ADD UNIQUE (`member_id`, `division_id`, `type`);");
        $this->execute("INSERT IGNORE INTO members_divisions_temp SELECT * FROM members_divisions;");
        $this->execute("DELETE FROM members_divisions;");
        $this->execute("ALTER TABLE `members_divisions` ADD UNIQUE INDEX `member_id_division_id_type` (`member_id`, `division_id`, `type`);");
        $this->execute("ALTER TABLE `members_divisions` ROW_FORMAT=DEFAULT;");
        $this->execute("INSERT INTO members_divisions SELECT * FROM members_divisions_temp;");
        $this->execute("DROP TABLE members_divisions_temp;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_divisions` DROP INDEX `member_id_division_id_type`;");
    }
}