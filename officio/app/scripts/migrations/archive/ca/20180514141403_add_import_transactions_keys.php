<?php

use Officio\Migration\AbstractMigration;

class AddImportTransactionsKeys extends AbstractMigration
{
    public function up()
    {
        $this->execute('DELETE FROM u_import_transactions WHERE company_ta_id NOT IN (SELECT company_ta_id FROM company_ta)');
        $this->execute('ALTER TABLE `u_import_transactions` ADD CONSTRAINT `FK_u_import_transactions_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE u_import_transactions SET author_id = NULL WHERE author_id NOT IN (SELECT member_id FROM members);');
        $this->execute('ALTER TABLE `u_import_transactions` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_ta_id`;');
        $this->execute('ALTER TABLE `u_import_transactions` ADD CONSTRAINT `FK_u_import_transactions_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `u_import_transactions` DROP FOREIGN KEY `FK_u_import_transactions_members`, DROP FOREIGN KEY `FK_u_import_transactions_company_ta`;');
        $this->execute('ALTER TABLE `u_import_transactions` CHANGE COLUMN `author_id` `author_id` INT(11) NULL DEFAULT NULL AFTER `company_ta_id`;');
    }
}