<?php

use Phinx\Migration\AbstractMigration;

class UpdateProspectDataValueToText extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_prospects_data` ALTER `q_value` DROP DEFAULT;");
        $this->execute("ALTER TABLE `company_prospects_data` CHANGE COLUMN `q_value` `q_value` TEXT NOT NULL AFTER `q_field_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects_data` ALTER `q_value` DROP DEFAULT;");
        $this->execute("ALTER TABLE `company_prospects_data` CHANGE COLUMN `q_value` `q_value` VARCHAR(255) NOT NULL AFTER `q_field_id`;");
    }
}