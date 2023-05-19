<?php

use Phinx\Migration\AbstractMigration;

class AddWebSiteScripts extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_websites` ADD COLUMN `script_google_analytics` TEXT NULL DEFAULT NULL AFTER `options`;");
        $this->execute("ALTER TABLE `company_websites` ADD COLUMN `script_facebook_pixel` TEXT NULL DEFAULT NULL AFTER `script_google_analytics`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_websites` DROP COLUMN `script_facebook_pixel`;");
        $this->execute("ALTER TABLE `company_websites` DROP COLUMN `script_google_analytics`;");
    }
}