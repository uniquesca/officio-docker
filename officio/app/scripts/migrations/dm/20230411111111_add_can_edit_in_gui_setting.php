<?php

use Officio\Migration\AbstractMigration;

class AddCanEditInGuiSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `can_edit_in_gui` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `multiple_values`;");
        $this->execute("ALTER TABLE `applicant_form_fields` ADD COLUMN `can_edit_in_gui` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `multiple_values`;");

        $this->query("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`)
                            SELECT `rule_id`, 'superadmin', 'manage-applicant-fields-groups', 'manage-options', 1
                            FROM acl_rules AS r
                            WHERE r.rule_check_id = 'manage-individuals-fields'");

        $this->query("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`)
                            SELECT `rule_id`, 'superadmin', 'manage-applicant-fields-groups', 'manage-options', 1
                            FROM acl_rules AS r
                            WHERE r.rule_check_id = 'manage-employers-fields'");

        $this->query("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`)
                            SELECT `rule_id`, 'superadmin', 'manage-applicant-fields-groups', 'manage-options', 1
                            FROM acl_rules AS r
                            WHERE r.rule_check_id = 'manage-contacts-fields'");

        $this->query("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`, `rule_allow`)
                            SELECT `rule_id`, 'superadmin', 'manage-applicant-fields-groups', 'manage-options', 1
                            FROM acl_rules AS r
                            WHERE r.rule_check_id = 'manage-internals-fields'");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `can_edit_in_gui`;");
        $this->execute("ALTER TABLE `applicant_form_fields` DROP COLUMN `can_edit_in_gui`;");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id`='superadmin' AND `resource_id`='manage-applicant-fields-groups' AND `resource_privilege`='manage-options';");
    }
}