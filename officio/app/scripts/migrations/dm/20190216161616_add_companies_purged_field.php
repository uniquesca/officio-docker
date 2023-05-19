<?php

use Phinx\Migration\AbstractMigration;

class AddCompaniesPurgedField extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details`
        	ADD COLUMN `purged` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `use_annotations`,
        	ADD COLUMN `purged_details` TEXT NULL DEFAULT NULL AFTER `purged`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details`
        	DROP COLUMN `purged`,
        	DROP COLUMN `purged_details`;");
    }
}