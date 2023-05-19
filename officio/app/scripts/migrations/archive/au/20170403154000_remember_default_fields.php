<?php

use Phinx\Migration\AbstractMigration;

class RememberDefaultFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `remember_default_fields` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `use_annotations`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `remember_default_fields`;");
    }
}
