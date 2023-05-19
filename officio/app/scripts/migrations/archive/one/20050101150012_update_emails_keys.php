<?php

use Phinx\Migration\AbstractMigration;

class UpdateEmailsKeys extends AbstractMigration
{
    public function up()
    {
        // Took ??? on local server
        // ACHTUNG!!!!
//        $this->execute('SET FOREIGN_KEY_CHECKS=0;');
//        $this->execute('ALTER TABLE `eml_messages` DROP FOREIGN KEY `FK_eml_messages_eml_folders`;');
//        $this->execute('ALTER TABLE `eml_messages` ADD CONSTRAINT `FK_eml_messages_eml_folders` FOREIGN KEY (`id_folder`) REFERENCES `eml_folders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;');
//        $this->execute('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down()
    {
    }
}