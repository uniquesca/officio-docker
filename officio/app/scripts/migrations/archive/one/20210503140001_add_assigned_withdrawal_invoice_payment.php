<?php

use Phinx\Migration\AbstractMigration;

class AddAssignedWithdrawalInvoicePayment extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_assigned_withdrawals` ADD COLUMN `invoice_payment_id` INT(11) NULL DEFAULT NULL AFTER `invoice_id`;");
        $this->execute("ALTER TABLE `u_assigned_withdrawals` ADD CONSTRAINT `FK_u_assigned_withdrawals_u_invoice_payments` FOREIGN KEY (`invoice_payment_id`) REFERENCES `u_invoice_payments` (`invoice_payment_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $arrInvoicePayments = $this->fetchAll('SELECT * FROM u_invoice_payments');
        foreach ($arrInvoicePayments as $arrInvoicePaymentInfo) {
            $this->getQueryBuilder()
                ->update('u_assigned_withdrawals')
                ->set('invoice_payment_id', $arrInvoicePaymentInfo['invoice_payment_id'])
                ->where(['invoice_id' => $arrInvoicePaymentInfo['invoice_id']])
                ->execute();
        }

        $this->execute("ALTER TABLE `u_assigned_withdrawals` DROP FOREIGN KEY `FK_invoice_id`, DROP COLUMN `invoice_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_assigned_withdrawals` ADD COLUMN `invoice_id` INT(11) NULL DEFAULT NULL AFTER `withdrawal`;");
        $this->execute("ALTER TABLE `u_assigned_withdrawals` ADD CONSTRAINT `FK_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `u_invoice` (`invoice_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $arrInvoicePayments = $this->fetchAll('SELECT * FROM u_invoice_payments');
        foreach ($arrInvoicePayments as $arrInvoicePaymentInfo) {
            $this->getQueryBuilder()
                ->update('u_assigned_withdrawals')
                ->set('invoice_id', $arrInvoicePaymentInfo['invoice_id'])
                ->where(['invoice_payment_id' => $arrInvoicePaymentInfo['invoice_payment_id']])
                ->execute();
        }

        $this->execute("ALTER TABLE `u_assigned_withdrawals` DROP FOREIGN KEY `FK_u_assigned_withdrawals_u_invoice_payments`, DROP COLUMN `invoice_payment_id`;");
    }
}
