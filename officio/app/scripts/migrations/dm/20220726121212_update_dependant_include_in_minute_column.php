<?php

use Officio\Migration\AbstractMigration;

class UpdateDependantIncludeInMinuteColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents` CHANGE COLUMN `include_in_minute_checkbox` `include_in_minute_checkbox` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `jrcc_result`;");
        $this->execute("UPDATE `client_form_dependents` SET `include_in_minute_checkbox`='Y';");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_dependents` CHANGE COLUMN `include_in_minute_checkbox` `include_in_minute_checkbox` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `jrcc_result`;");
    }
}