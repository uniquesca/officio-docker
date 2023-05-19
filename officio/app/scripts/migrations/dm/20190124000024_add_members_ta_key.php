<?php

use Phinx\Migration\AbstractMigration;

class AddMembersTaKey extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `members_ta` ADD CONSTRAINT `FK_members_ta_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `members_ta` DROP FOREIGN KEY `FK_members_ta_company_ta`;');
    }
}