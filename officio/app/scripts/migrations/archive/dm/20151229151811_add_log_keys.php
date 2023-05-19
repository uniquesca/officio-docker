<?php

use Phinx\Migration\AbstractMigration;

class AddLogKeys extends AbstractMigration
{
    public function up()
    {
        $this->execute('DELETE FROM u_log WHERE trust_account_id NOT IN (SELECT trust_account_id FROM u_trust_account)');
        $this->execute('ALTER TABLE `u_log` ADD CONSTRAINT `FK_u_log_u_trust_account` FOREIGN KEY (`trust_account_id`) REFERENCES `u_trust_account` (`trust_account_id`) ON UPDATE CASCADE ON DELETE CASCADE');

        $this->execute('DELETE FROM u_log WHERE author_id NOT IN (SELECT member_id FROM members)');
        $this->execute('ALTER TABLE `u_log` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `action_id`');
        $this->execute('ALTER TABLE `u_log` ADD CONSTRAINT `FK_u_log_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `u_log` DROP FOREIGN KEY `FK_u_log_u_trust_account`');
        $this->execute('ALTER TABLE `u_log` DROP FOREIGN KEY `FK_u_log_members`');
        $this->execute('ALTER TABLE `u_log` CHANGE COLUMN `author_id` `author_id` INT(10) DEFAULT NULL AFTER `action_id`');
    }
}