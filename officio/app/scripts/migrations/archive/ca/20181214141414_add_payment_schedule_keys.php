<?php

use Phinx\Migration\AbstractMigration;

class AddPaymentScheduleKeys extends AbstractMigration
{
    public function up()
    {
        $this->execute('DELETE FROM `u_payment_schedule` WHERE member_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `u_payment_schedule` ADD CONSTRAINT `FK_u_payment_schedule_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute('DELETE FROM `members_ta` WHERE member_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `members_ta` ADD CONSTRAINT `FK_members_ta_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `members_ta` DROP FOREIGN KEY `FK_members_ta_members`');
        $this->execute('ALTER TABLE `u_payment_schedule` DROP FOREIGN KEY `FK_u_payment_schedule_members`');
    }
}