<?php

use Phinx\Migration\AbstractMigration;

class UpdateAccounting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_invoice`
            ADD COLUMN `fee` DOUBLE(12,2) NOT NULL AFTER `amount`,
            ADD COLUMN `tax` DOUBLE(12,2) NOT NULL AFTER `fee`,
            ADD COLUMN `description` TINYTEXT NOT NULL AFTER `tax`,
	        ADD COLUMN `received` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `notes`;"
        );

        $this->execute("ALTER TABLE `u_assigned_withdrawals`
	        ADD COLUMN `member_id` BIGINT(20) NULL DEFAULT NULL AFTER `author_id`;"
        );

        $this->execute("ALTER TABLE `u_assigned_withdrawals`
	        ADD CONSTRAINT `FK_u_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;"
        );

        $this->execute("ALTER TABLE `u_payment`
	        ADD COLUMN `invoice_id` INT(11) NULL DEFAULT NULL AFTER `invoice_number`;"
        );

        $this->execute("ALTER TABLE `u_payment`
	        ADD CONSTRAINT `FK_u_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `u_invoice` (`invoice_id`) ON UPDATE CASCADE ON DELETE SET NULL;"
        );

        $this->execute("ALTER TABLE `u_payment_schedule`
	        ADD COLUMN `template_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `gst_tax_label`;"
        );

        $this->execute("ALTER TABLE `u_payment_schedule`
	        ADD CONSTRAINT `FK_u_template_id` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`) ON UPDATE CASCADE ON DELETE SET NULL;"
        );
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_invoice`
	        DROP COLUMN `fee`,
	        DROP COLUMN `tax`,
	        DROP COLUMN `description`,
	        DROP COLUMN `received`;"
        );

        $this->execute("ALTER TABLE `u_assigned_withdrawals`
	        DROP FOREIGN KEY `FK_u_member_id`;"
        );

        $this->execute("ALTER TABLE `u_assigned_withdrawals`
	        DROP COLUMN `member_id`;"
        );

        $this->execute("ALTER TABLE `u_payment`
	        DROP FOREIGN KEY `FK_u_invoice_id`;"
        );

        $this->execute("ALTER TABLE `u_payment`
	        DROP COLUMN `invoice_id`;"
        );

        $this->execute("ALTER TABLE `u_payment_schedule`
	        DROP FOREIGN KEY `FK_u_template_id`;"
        );

        $this->execute("ALTER TABLE `u_payment_schedule`
	        DROP COLUMN `template_id`;"
        );
    }
}
