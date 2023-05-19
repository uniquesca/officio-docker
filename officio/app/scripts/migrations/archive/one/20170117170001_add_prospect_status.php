<?php

use Phinx\Migration\AbstractMigration;

class AddProspectStatus extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active' AFTER `email_sent`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `status`;");
    }
}