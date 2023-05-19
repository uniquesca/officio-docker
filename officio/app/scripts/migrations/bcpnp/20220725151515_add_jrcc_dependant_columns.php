<?php

use Officio\Migration\AbstractMigration;

class AddJrccDependantColumns extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `jrcc_result` ENUM('nothing_derogatory','unable_to_verify_identity','cleared','financial_irregularities','security_threat','us_visa_denial','uk_visa_denial','visa_revoked','rejected_by_other_cip','third_country_visa_refused') NULL DEFAULT NULL AFTER `third_country_visa`;");
        $this->execute("ALTER TABLE `client_form_dependents` ADD COLUMN `include_in_minute_checkbox` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `jrcc_result`;");
    }

    public function down()
    {
        $this->execute('ALTER TABLE `client_form_dependents` DROP COLUMN `jrcc_result`, DROP COLUMN `include_in_minute_checkbox`;');
    }
}