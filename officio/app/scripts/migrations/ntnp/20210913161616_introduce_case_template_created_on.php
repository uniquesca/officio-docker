<?php

use Phinx\Migration\AbstractMigration;

class IntroduceCaseTemplateCreatedOn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_types` ADD COLUMN `client_type_created_on` DATETIME NOT NULL AFTER `client_type_hidden_for_company`;");
        $this->execute("UPDATE `client_types` SET `client_type_created_on` = NOW();");
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `client_types` DROP COLUMN `client_type_created_on`;"
        );
    }
}
