<?php

use Phinx\Migration\AbstractMigration;

class AddProspectMpColumns extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `mp_prospect_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `email_sent`;");
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `mp_prospect_expiration_date` DATE DEFAULT NULL AFTER `mp_prospect_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `mp_prospect_id`;");
        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `mp_prospect_expiration_date`;");
    }
}