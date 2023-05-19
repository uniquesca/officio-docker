<?php

use Phinx\Migration\AbstractMigration;

class AddTaInvoiceKeys extends AbstractMigration
{
    public function up()
    {
        $this->execute('DELETE FROM u_invoice WHERE company_ta_id NOT IN (SELECT company_ta_id FROM company_ta)');
        $this->execute('ALTER TABLE `u_invoice` ADD CONSTRAINT `FK_u_invoice_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('UPDATE u_invoice SET author_id = NULL WHERE author_id NOT IN (SELECT member_id FROM members);');
        $this->execute('ALTER TABLE `u_invoice` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL DEFAULT NULL AFTER `company_ta_id`;');
        $this->execute('ALTER TABLE `u_invoice` ADD CONSTRAINT `FK_u_invoice_members1` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `u_invoice` DROP FOREIGN KEY `FK_u_invoice_members1`, DROP FOREIGN KEY `FK_u_invoice_company_ta`;');
        $this->execute('ALTER TABLE `u_invoice` CHANGE COLUMN `author_id` `author_id` INT(11) NULL DEFAULT NULL AFTER `company_ta_id`;');
    }
}