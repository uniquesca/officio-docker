<?php

use Officio\Migration\AbstractMigration;

class AddVevoFunctionality extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `members_vevo_mapping` (
                            `from_member_id` BIGINT(20) NOT NULL,
                            `to_member_id` BIGINT(20) NOT NULL,
                            INDEX `FK_members_vevo_mapping_members` (`from_member_id`),
                            INDEX `FK_members_vevo_mapping_members_2` (`to_member_id`),
                            CONSTRAINT `FK_members_vevo_mapping_members` FOREIGN KEY (`from_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_members_vevo_mapping_members_2` FOREIGN KEY (`to_member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB
                        ;");
        $this->execute("ALTER TABLE `users` ADD `vevo_login` VARCHAR(255) NULL DEFAULT NULL AFTER `time_tracker_round_up`;");
        $this->execute("ALTER TABLE `users` ADD `vevo_password` TEXT NULL DEFAULT NULL AFTER `vevo_login`;");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES
                        (1032, 'superadmin', 'manage-members', 'check-vevo-account'),
                        (1032, 'superadmin', 'manage-members', 'change-vevo-credentials'),
                        (11, 'applicants', 'profile', 'get-vevo-info'),
                        (13, 'applicants', 'profile', 'get-vevo-info'),
                        (401, 'applicants', 'profile', 'get-vevo-info'),
                        (403, 'applicants', 'profile', 'get-vevo-info'),
                        (11, 'applicants', 'profile', 'update-vevo-info'),
                        (13, 'applicants', 'profile', 'update-vevo-info'),
                        (401, 'applicants', 'profile', 'update-vevo-info'),
                        (403, 'applicants', 'profile', 'update-vevo-info');");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `members_vevo_mapping`;");
        $this->execute("ALTER TABLE `users` DROP COLUMN `vevo_login`;");
        $this->execute("ALTER TABLE `users` DROP COLUMN `vevo_password`;");
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 1032 AND `resource_privilege` = 'check-vevo-account';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 1032 AND `resource_privilege` = 'change-vevo-credentials';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'applicants' AND `resource_id` = 'profile' AND (`resource_privilege` = 'get-vevo-info' OR `resource_privilege` = 'update-vevo-info');");
    }
}
