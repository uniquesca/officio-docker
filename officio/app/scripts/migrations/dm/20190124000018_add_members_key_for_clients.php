<?php

use Phinx\Migration\AbstractMigration;

class AddMembersKeyForClients extends AbstractMigration
{
    public function up()
    {
        $this->execute('UPDATE clients SET modified_by = NULL WHERE modified_by NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `clients` CHANGE COLUMN `modified_by` `modified_by` BIGINT(20) NULL DEFAULT NULL AFTER `forms_locked`;');
        $this->execute('ALTER TABLE `clients` ADD CONSTRAINT `FK_clients_members` FOREIGN KEY (`modified_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `clients` DROP FOREIGN KEY `FK_clients_members`;');
        $this->execute('ALTER TABLE `clients` CHANGE COLUMN `modified_by` `modified_by` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `forms_locked`;');
    }
}