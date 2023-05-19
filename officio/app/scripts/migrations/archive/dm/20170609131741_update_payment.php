<?php

use Phinx\Migration\AbstractMigration;

class UpdatePayment extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `u_payment`
	      ADD COLUMN `company_agent` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `notes`;"
        );
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `u_payment`
	      DROP COLUMN `company_agent`;"
        );
    }
}
