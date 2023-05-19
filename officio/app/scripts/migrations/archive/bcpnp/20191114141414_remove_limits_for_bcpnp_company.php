<?php

use Phinx\Migration\AbstractMigration;

class RemoveLimitsForBcpnpCompany extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_details` SET `next_billing_date` = '2099-01-01', `free_users` = '10000', `free_storage` = '10000' WHERE `company_id` != 0;");
    }

    public function down()
    {
    }
}
