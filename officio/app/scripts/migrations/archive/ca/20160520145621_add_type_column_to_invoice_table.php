<?php

use Officio\Migration\AbstractMigration;

class AddTypeColumnToInvoiceTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_invoice` ADD COLUMN `type` VARCHAR(255) NOT NULL DEFAULT 'invoice' AFTER `received`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_invoice` DROP COLUMN `type`;");

    }
}
