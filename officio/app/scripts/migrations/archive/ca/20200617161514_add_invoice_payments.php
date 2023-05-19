<?php

use Officio\Migration\AbstractMigration;

class AddInvoicePayments extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `u_invoice_payments` (
            `invoice_payment_id` INT(11) NOT NULL AUTO_INCREMENT,
            `invoice_id` INT(11) NOT NULL,
            `company_ta_id` INT(11) NULL DEFAULT NULL,
            `company_ta_other` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `invoice_payment_amount` DOUBLE(12,2) NOT NULL,
            `invoice_payment_date` DATE NOT NULL,
            `invoice_payment_cheque_num` VARCHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            PRIMARY KEY (`invoice_payment_id`) USING BTREE,
            INDEX `FK_u_invoice_payments_u_invoice` (`invoice_id`) USING BTREE,
            INDEX `FK_u_invoice_payments_company_ta` (`company_ta_id`) USING BTREE,
            CONSTRAINT `FK_u_invoice_payments_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_u_invoice_payments_u_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `u_invoice` (`invoice_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Each invoice can be paid in several transactions'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute("INSERT INTO u_invoice_payments (invoice_id, company_ta_id, invoice_payment_amount, invoice_payment_date, invoice_payment_cheque_num)
        SELECT invoice_id, company_ta_id, amount, date_of_invoice, cheque_num
        FROM u_invoice");

        $this->execute("ALTER TABLE `u_invoice` DROP COLUMN `cheque_num`");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_invoice` ADD COLUMN `cheque_num` VARCHAR(255) NULL DEFAULT NULL AFTER `invoice_num`;");

        // Try to fill the cheque number
        $this->execute("UPDATE u_invoice a INNER JOIN u_invoice_payments b ON a.invoice_id = b.invoice_id SET a.cheque_num = b.invoice_payment_cheque_num");

        $this->execute("DROP TABLE `u_invoice_payments`");
    }
}
