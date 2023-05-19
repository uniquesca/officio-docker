<?php

use Phinx\Migration\AbstractMigration;

class ConvertOtherTaToInvoices extends AbstractMigration
{
    public function up()
    {
        $arrMaxInvoiceId = $this->fetchRow("SELECT MAX(invoice_id) as max_invoice_id FROM u_invoice");

        $this->execute("INSERT INTO u_invoice (member_id, company_ta_id, author_id, invoice_num, amount, fee, description, date_of_invoice, date_of_creation, notes, received)
        SELECT member_id, company_ta_id, author_id, invoice_number, deposit, deposit, description, CAST(date_of_event AS DATE), date_of_event, notes, 'N'
        FROM u_payment WHERE invoice_number IS NOT NULL");

        if (!empty($arrMaxInvoiceId['max_invoice_id'])) {
            $this->execute("INSERT INTO u_invoice_payments (invoice_id, company_ta_other, invoice_payment_amount, invoice_payment_date)
            SELECT invoice_id, 'Operating account', amount, date_of_invoice
            FROM u_invoice WHERE invoice_id > " . $arrMaxInvoiceId['max_invoice_id']);
        }

        $this->execute("DELETE FROM u_payment WHERE invoice_number IS NOT NULL");
    }

    public function down()
    {
    }
}
