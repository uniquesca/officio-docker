<?php

use Phinx\Migration\AbstractMigration;

class FixSessionsStorage extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `sessions` CHANGE COLUMN `data` `data` MEDIUMTEXT NULL AFTER `lifetime`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `sessions` CHANGE COLUMN `data` `data` TEXT NULL AFTER `lifetime`;");
    }
}