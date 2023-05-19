<?php

use Officio\Migration\AbstractMigration;

class IntroduceParentClientTypeIdAndHiddenFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_types`
            ADD COLUMN `parent_client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `client_type_id`,
            ADD CONSTRAINT `FK_client_types_client_types` FOREIGN KEY (`parent_client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `client_types` ADD COLUMN `client_type_hidden` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `client_type_employer_sponsorship`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_types` DROP FOREIGN KEY `FK_client_types_client_types`;");

        $this->execute("ALTER TABLE `client_types` DROP COLUMN `parent_client_type_id`;");

        $this->execute("ALTER TABLE `client_types` DROP COLUMN `client_type_hidden`;");
    }
}