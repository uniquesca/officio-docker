<?php

use Officio\Migration\AbstractMigration;

class AddCaseFieldsSyncingSwitch extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `sync_with_default` ENUM('Yes','No','Label') NOT NULL DEFAULT 'Yes' AFTER `skip_access_requirements`;");
        $this->execute("UPDATE `client_form_fields` SET sync_with_default='No'");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `sync_with_default`;");
    }
}