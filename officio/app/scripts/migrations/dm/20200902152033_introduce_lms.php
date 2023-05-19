<?php

use Phinx\Migration\AbstractMigration;

class IntroduceLms extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `users` ADD COLUMN `lms_user_id` BIGINT(20) NULL DEFAULT NULL AFTER `user_migration_number`;');
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1030, 'superadmin', 'manage-members', 'enable-lms-user', 1)");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `rule_id`=1030 AND `module_id`='superadmin' AND `resource_id`='manage-members' AND `resource_privilege`='enable-lms-user';");
        $this->execute("ALTER TABLE `users` DROP COLUMN `lms_user_id`;");
    }
}