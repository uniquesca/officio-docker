<?php

use Phinx\Migration\AbstractMigration;

class ChangePasswordHashLength extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `users` CHANGE COLUMN `vevo_password` `vevo_password` TEXT NULL DEFAULT NULL AFTER `vevo_login`;");
        $this->execute("ALTER TABLE `user_smtp` CHANGE COLUMN `smtp_password` `smtp_password` TEXT NULL DEFAULT NULL AFTER `smtp_username`;");
        $this->execute("ALTER TABLE `superadmin_smtp` CHANGE COLUMN `smtp_password` `smtp_password` TEXT NULL DEFAULT NULL AFTER `smtp_username`;");
        $this->execute(
            "ALTER TABLE `eml_accounts`
        	CHANGE COLUMN `inc_password` `inc_password` TEXT NULL DEFAULT NULL AFTER `inc_login`,
        	CHANGE COLUMN `out_password` `out_password` TEXT NULL DEFAULT NULL AFTER `out_login`;"
        );
    }

    public function down()
    {
        $this->execute("ALTER TABLE `users` CHANGE COLUMN `vevo_password` `vevo_password` VARCHAR(255) NULL AFTER `vevo_login`;");
        $this->execute("ALTER TABLE `user_smtp` CHANGE COLUMN `smtp_password` `smtp_password` CHAR(255) NULL AFTER `smtp_username`;");
        $this->execute("ALTER TABLE `superadmin_smtp` CHANGE COLUMN `smtp_password` `smtp_password` CHAR(255) NULL AFTER `smtp_username`;");
        $this->execute(
            "ALTER TABLE `eml_accounts`
        	CHANGE COLUMN `inc_password` `inc_password` VARCHAR(128) NULL AFTER `inc_login`,
        	CHANGE COLUMN `out_password` `out_password` VARCHAR(128) NULL AFTER `out_login`;"
        );
    }
}