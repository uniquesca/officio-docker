<?php

use Phinx\Migration\AbstractMigration;

class SaveEmailToSentFolder extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `eml_accounts`
	        ADD COLUMN `out_save_sent` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `out_ssl`;"
        );
    }

    public function down()
    {
        $this->execute("ALTER TABLE `eml_accounts` DROP COLUMN `out_save_sent`;");
    }
}