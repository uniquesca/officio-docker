<?php

use Officio\Migration\AbstractMigration;

class AddColumnsToClientFormDependantsTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_dependents`
            ADD COLUMN `address` VARCHAR(255) NULL DEFAULT NULL AFTER `photo`,
            ADD COLUMN `city` VARCHAR(255) NULL DEFAULT NULL AFTER `address`,
            ADD COLUMN `country` VARCHAR(255) NULL DEFAULT NULL AFTER `city`,
            ADD COLUMN `region` VARCHAR(255) NULL DEFAULT NULL AFTER `country`,
            ADD COLUMN `postal_code` VARCHAR(255) NULL DEFAULT NULL AFTER `region`,
            ADD COLUMN `profession` VARCHAR(255) NULL DEFAULT NULL AFTER `postal_code`,
            ADD COLUMN `marital_status` ENUM('single','married','divorced','widowed') NULL DEFAULT NULL AFTER `profession`,
            ADD COLUMN `passport_issuing_country` VARCHAR(255) NULL DEFAULT NULL AFTER `marital_status`,
            ADD COLUMN `third_country_visa` ENUM('Y','N') NULL DEFAULT NULL AFTER `passport_issuing_country`;"
        );
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_dependents`
            DROP COLUMN `address`,
            DROP COLUMN `city`,
            DROP COLUMN `country`,
            DROP COLUMN `region`,
            DROP COLUMN `postal_code`,
            DROP COLUMN `profession`,
            DROP COLUMN `marital_status`,
            DROP COLUMN `passport_issuing_country`,
            DROP COLUMN `third_country_visa`;"
        );
    }
}