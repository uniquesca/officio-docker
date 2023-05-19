<?php

use Phinx\Migration\AbstractMigration;

class AddZendDateFormatVariable extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `u_variable` (`name`, `value`) VALUES ('dateZendFormatFull', 'MMM dd, yyyy');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `u_variable` WHERE  `name`='dateZendFormatFull';");
    }
}