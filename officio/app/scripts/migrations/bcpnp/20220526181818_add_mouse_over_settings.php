<?php

use Phinx\Migration\AbstractMigration;

class AddMouseOverSettings extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `users` CHANGE COLUMN `quick_links` `quick_menu_settings` TEXT NULL DEFAULT NULL AFTER `vevo_password`;");
        $this->execute("UPDATE `users` SET `quick_menu_settings`=NULL");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `users` CHANGE COLUMN `quick_menu_settings` `quick_links` TEXT NULL DEFAULT NULL AFTER `vevo_password`;");
        $this->execute("UPDATE `users` SET `quick_links`=NULL");
    }
}