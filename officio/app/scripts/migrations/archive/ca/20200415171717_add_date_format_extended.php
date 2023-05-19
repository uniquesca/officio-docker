<?php

use Officio\Migration\AbstractMigration;

class AddDateFormatExtended extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `u_variable` WHERE `name`='dateFormatFullExtended'");
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) SELECT 'dateFormatFullExtended', `value` FROM `u_variable` WHERE `name` = 'dateFormatFull'");
    }

    public function down()
    {
        $this->execute("DELETE FROM `u_variable` WHERE  `name`='dateFormatFullExtended';");
    }
}
