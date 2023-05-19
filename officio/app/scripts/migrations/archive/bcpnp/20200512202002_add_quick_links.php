<?php

use Phinx\Migration\AbstractMigration;

class AddQuickLinks extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `users` ADD COLUMN `quick_links` TEXT NULL DEFAULT NULL AFTER `vevo_password`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `users` DROP COLUMN `quick_links`;");
    }
}