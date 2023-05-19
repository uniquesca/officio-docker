<?php

use Phinx\Migration\AbstractMigration;

class AddTaKey extends AbstractMigration
{
    public function up()
    {
        $this->execute('DELETE FROM u_trust_account WHERE company_ta_id NOT IN (SELECT c.company_ta_id FROM company_ta as c);');
        $this->execute('ALTER TABLE `u_trust_account` ADD CONSTRAINT `FK_u_trust_account_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `u_trust_account` DROP FOREIGN KEY `FK_u_trust_account_company_ta`');
    }
}
