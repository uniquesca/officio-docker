<?php

use Officio\Migration\AbstractMigration;

class AddMoreAuthorKeys extends AbstractMigration
{
    public function up()
    {
        $this->execute('UPDATE u_tasks SET author_id = NULL WHERE author_id NOT IN (SELECT member_id FROM members);');
        $this->execute('ALTER TABLE `u_tasks` ADD CONSTRAINT `FK_u_tasks_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute('UPDATE u_withdrawal_types SET author_id = NULL WHERE author_id NOT IN (SELECT member_id FROM members);');
        $this->execute('ALTER TABLE `u_withdrawal_types` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_id`;');
        $this->execute('ALTER TABLE `u_withdrawal_types` ADD CONSTRAINT `FK_u_withdrawal_types_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `u_tasks` DROP FOREIGN KEY `FK_u_tasks_members`;');

        $this->execute('ALTER TABLE `u_withdrawal_types` DROP FOREIGN KEY `FK_u_withdrawal_types_members`;');
        $this->execute('ALTER TABLE `u_withdrawal_types` CHANGE COLUMN `author_id` `author_id` INT(11) NULL DEFAULT NULL AFTER `company_id`;');
    }
}