<?php

use Officio\Migration\AbstractMigration;

class IntroduceTemplateHiddenForTheCompany extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_types` ADD COLUMN `client_type_hidden_for_company` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `client_type_hidden`;");
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `client_types` DROP COLUMN `client_type_hidden_for_company`;"
        );
    }
}
