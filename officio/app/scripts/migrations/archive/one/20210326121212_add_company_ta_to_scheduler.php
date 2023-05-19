<?php

use Phinx\Migration\AbstractMigration;

class AddCompanyTaToScheduler extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_payment_schedule`
        	ADD COLUMN `company_ta_id` INT(11) NULL DEFAULT NULL AFTER `member_id`,
        	ADD CONSTRAINT `FK_u_payment_schedule_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `u_payment_schedule`
                DROP FOREIGN KEY `FK_u_payment_schedule_company_ta`,
            	DROP COLUMN `company_ta_id`;"
        );
    }
}
