<?php

use Phinx\Migration\AbstractMigration;

class AddZoho extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `zoho_keys` (
          `zoho_key` varchar(255) NOT NULL default '',
          `zoho_key_status` ENUM('enabled', 'disabled') NOT NULL DEFAULT 'enabled',
          PRIMARY KEY  (`zoho_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("INSERT INTO `zoho_keys` (`zoho_key`, `zoho_key_status`) VALUES ('ba52082120340f887665ed87a2637f3c', 'enabled');");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `zoho_keys`;");
    }
}