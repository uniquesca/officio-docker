<?php

use Phinx\Migration\AbstractMigration;

class AddInvoiceTransferFromOther extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_invoice_payments` ADD COLUMN `transfer_from_company_ta_id` INT(11) NULL DEFAULT NULL AFTER `invoice_payment_cheque_num`;");
        $this->execute("ALTER TABLE `u_invoice_payments` ADD CONSTRAINT `FK_u_invoice_payments_company_ta_2` FOREIGN KEY (`transfer_from_company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE SET NULL;");
        $this->execute("ALTER TABLE `u_invoice_payments` ADD COLUMN `transfer_from_amount` DOUBLE(12,2) NULL AFTER `transfer_from_company_ta_id`;");
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `u_invoice_payments`
                DROP FOREIGN KEY `FK_u_invoice_payments_company_ta_2`,
            	DROP COLUMN `transfer_from_company_ta_id`,
            	DROP COLUMN `transfer_from_amount`"
        );
    }
}
