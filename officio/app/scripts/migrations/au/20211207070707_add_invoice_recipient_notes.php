<?php

use Officio\Migration\AbstractMigration;

class AddInvoiceRecipientNotes extends AbstractMigration
{
    public function up()
    {
        $this->table('u_invoice')
            ->addColumn('invoice_recipient_notes', 'string', [
                'after' => 'notes',
                'null'  => true
            ])
            ->save();
    }

    public function down()
    {
        $this->table('u_invoice')
            ->removeColumn('invoice_recipient_notes')
            ->save();
    }
}