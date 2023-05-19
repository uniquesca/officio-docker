<?php

use Phinx\Migration\AbstractMigration;

class AddCountryKeyForUsers extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `users` CHANGE COLUMN `country` `country` INT(11) NULL DEFAULT NULL AFTER `state`;');
        $this->execute('UPDATE users SET country = NULL WHERE country NOT IN (SELECT c.countries_id FROM country_master as c);');
        $this->execute('ALTER TABLE `users` ADD CONSTRAINT `FK_users_country_master` FOREIGN KEY (`country`) REFERENCES `country_master` (`countries_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `users` DROP FOREIGN KEY `FK_users_country_master`;');
        $this->execute("ALTER TABLE `users` CHANGE COLUMN `country` `country` INT(6) NOT NULL DEFAULT '0' AFTER `state`");
    }
}