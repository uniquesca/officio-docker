<?php

use Phinx\Migration\AbstractMigration;

class ChangeNewsCreatedOn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `news` CHANGE COLUMN `create_date` `create_date` DATETIME NULL DEFAULT NULL AFTER `content`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `news` CHANGE COLUMN `create_date` `create_date` DATE NULL DEFAULT NULL AFTER `content`;");
    }
}