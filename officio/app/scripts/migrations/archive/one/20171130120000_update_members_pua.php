<?php

use Phinx\Migration\AbstractMigration;

class UpdateMembersPua extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members_pua`
        	ADD COLUMN `pua_business_contact_phone` VARCHAR(255) NULL DEFAULT NULL AFTER `pua_business_contact_or_service`,
        	ADD COLUMN `pua_business_contact_email` VARCHAR(255) NULL DEFAULT NULL AFTER `pua_business_contact_phone`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_pua` DROP COLUMN `pua_business_contact_phone`, DROP COLUMN `pua_business_contact_email`;");
    }
}