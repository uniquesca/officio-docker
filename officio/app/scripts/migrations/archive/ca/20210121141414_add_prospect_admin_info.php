<?php

use Officio\Migration\AbstractMigration;

class addProspectAdminInfo extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `prospects` ADD COLUMN `admin_first_name` VARCHAR(255) NULL DEFAULT NULL AFTER `pricing_category_id`;");
        $this->execute("ALTER TABLE `prospects` ADD COLUMN `admin_last_name` VARCHAR(255) NULL DEFAULT NULL AFTER `admin_first_name`;");
        $this->execute("ALTER TABLE `prospects` ADD COLUMN `admin_email` VARCHAR(255) NULL DEFAULT NULL AFTER `admin_last_name`;");
        $this->execute("ALTER TABLE `prospects` ADD COLUMN `admin_username` VARCHAR(50) NULL DEFAULT NULL AFTER `admin_email`;");
        $this->execute("ALTER TABLE `prospects` ADD COLUMN `admin_password` TEXT NULL AFTER `admin_username`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `prospects` DROP COLUMN `admin_first_name`, DROP COLUMN `admin_last_name`, DROP COLUMN `admin_email`, DROP COLUMN `admin_username`, DROP COLUMN `admin_password`;");
    }
}
