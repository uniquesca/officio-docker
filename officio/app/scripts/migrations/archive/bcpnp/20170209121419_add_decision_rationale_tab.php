<?php

use Phinx\Migration\AbstractMigration;

class AddDecisionRationaleTab extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details`
            ADD COLUMN `allow_decision_rationale_tab` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `allow_multiple_advanced_search_tabs`;");

        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `superadmin_only`, `crm_only`, `rule_visible`, `rule_order`) VALUES
            (40, 10, 'notes', 'Decision Rationale', 'clients-decision-rationale-view', 0, 'N', 1, 9);");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES
            (40, 'notes', 'index', 'get-note'),
            (40, 'notes', 'index', 'get-notes'),
            (40, 'notes', 'index', 'get-notes-list');");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
            (1, 40, 'Decision Rationale', 1);");

        $this->execute("CREATE TABLE `client_types_decision_rationale_fields` (
                            `client_type_id` INT(11) UNSIGNED NOT NULL,
                            `field_id` INT(11) UNSIGNED NOT NULL,
                            INDEX `FK_client_types_decision_rationale_fields_client_types` (`client_type_id`),
                            INDEX `FK_client_types_decision_rationale_fields_client_form_fields` (`field_id`),
                            CONSTRAINT `FK_client_types_decision_rationale_fields_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_client_types_decision_rationale_fields_client_form_fields` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;");

        $this->execute("ALTER TABLE `u_notes`
	        ADD COLUMN `field_id` INT(11) UNSIGNED DEFAULT NULL AFTER `author_id`;");

        $this->execute("ALTER TABLE `u_notes`
	        ADD CONSTRAINT `FK_u_notes_client_form_fields` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details`
	      DROP COLUMN `allow_decision_rationale_tab`;");

        $this->execute("DELETE FROM `acl_rules` WHERE  `rule_id` = 40;");

        $this->execute("DROP TABLE IF EXISTS `client_types_decision_rationale_fields`;");

        $this->execute("ALTER TABLE `u_notes` DROP FOREIGN KEY `FK_u_notes_client_form_fields`;");
        $this->execute("ALTER TABLE `u_notes` DROP COLUMN `field_id`;");
    }
}