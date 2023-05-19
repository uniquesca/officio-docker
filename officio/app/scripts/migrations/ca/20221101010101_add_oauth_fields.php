<?php

use Officio\Migration\AbstractMigration;

class AddOauthFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members`
            ADD COLUMN `oauth_idir` VARCHAR(255) NULL DEFAULT NULL AFTER `password`,
            ADD COLUMN `oauth_guid` VARCHAR(255) NULL DEFAULT NULL AFTER `oauth_idir`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members` DROP COLUMN `oauth_idir`, DROP COLUMN `oauth_guid`;");
    }
}
