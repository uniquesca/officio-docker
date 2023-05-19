<?php

use Officio\Migration\AbstractMigration;

class UpdateMembersDivisions extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `members_divisions` DROP COLUMN `responsible_for`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (98, 'applicants', 'queue', 'pull-from-queue');");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members_divisions` ADD COLUMN `responsible_for` ENUM('Y','N') NULL DEFAULT 'N' AFTER `division_id`;");
        $this->execute("UPDATE `members_divisions` SET `responsible_for`='Y' WHERE `type`='responsible_for';");

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=98 AND `module_id`='applicants' AND `resource_id`='queue' AND `resource_privilege`='pull-from-queue';");
    }
}
