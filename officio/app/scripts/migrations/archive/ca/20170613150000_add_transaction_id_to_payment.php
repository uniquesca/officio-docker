<?php

use Officio\Migration\AbstractMigration;

class addTransactionIdToPayment extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_payment` ADD COLUMN `transaction_id` TINYTEXT NULL AFTER `company_ta_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_payment` DROP COLUMN `transaction_id`;");
    }
}
