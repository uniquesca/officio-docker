<?php

use Phinx\Migration\AbstractMigration;

class AddClientsImportKey extends AbstractMigration
{
    public function up()
    {
        $this->execute('UPDATE clients_import SET creator_id = NULL WHERE creator_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `clients_import` ALTER `creator_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `clients_import` CHANGE COLUMN `creator_id` `creator_id` BIGINT(20) NULL AFTER `company_id`;');
        $this->execute('ALTER TABLE `clients_import` DROP FOREIGN KEY `FK1_company_id_to_clients_import`, DROP FOREIGN KEY `FK2_creator_id_to_members`;');
        $this->execute('ALTER TABLE `clients_import`
        	ADD CONSTRAINT `FK1_company_id_to_clients_import` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	ADD CONSTRAINT `FK2_creator_id_to_members` FOREIGN KEY (`creator_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `clients_import` DROP FOREIGN KEY `FK1_company_id_to_clients_import`, DROP FOREIGN KEY `FK2_creator_id_to_members`;');
        $this->execute('ALTER TABLE `clients_import`
                	ADD CONSTRAINT `FK1_company_id_to_clients_import` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE NO ACTION ON DELETE CASCADE,
                	ADD CONSTRAINT `FK2_creator_id_to_members` FOREIGN KEY (`creator_id`) REFERENCES `members` (`member_id`) ON UPDATE RESTRICT ON DELETE CASCADE;');

        $this->execute('ALTER TABLE `clients_import` ALTER `creator_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `clients_import` CHANGE COLUMN `creator_id` `creator_id` BIGINT(20) NOT NULL AFTER `company_id`;');
    }
}