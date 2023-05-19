<?php

use Officio\Migration\AbstractMigration;

class ChangeJrccDependantColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` CHANGE COLUMN `jrcc_result` `jrcc_result` ENUM('nothing_derogatory','unable_to_verify_identity','cleared','financial_irregularities','security_threat','us_visa_denial','uk_visa_denial','visa_revoked','rejected_by_other_cip','third_country_visa_refused','passport_reported_lost_stolen') NULL DEFAULT NULL COLLATE 'utf8_general_ci' AFTER `third_country_visa`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_dependents` CHANGE COLUMN `jrcc_result` `jrcc_result` ENUM('nothing_derogatory','unable_to_verify_identity','cleared','financial_irregularities','security_threat','us_visa_denial','uk_visa_denial','visa_revoked','rejected_by_other_cip','third_country_visa_refused') NULL DEFAULT NULL COLLATE 'utf8_general_ci' AFTER `third_country_visa`;");
    }
}