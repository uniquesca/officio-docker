<?php

use Phinx\Migration\AbstractMigration;

class AddTaskCc extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `u_tasks` ADD COLUMN `cc` CHAR(255) NULL DEFAULT NULL AFTER `from`');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `u_tasks` DROP COLUMN `cc`');
    }
}
