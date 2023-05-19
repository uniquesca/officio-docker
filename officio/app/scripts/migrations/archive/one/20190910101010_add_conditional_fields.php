<?php

use Officio\Migration\AbstractMigration;

class addConditionalFields extends AbstractMigration
{
    protected $clearAclCache = true;

    public function up()
    {
        $builder = $this->getQueryBuilder();

        $statement = $builder
            ->select('rule_id')
            ->from(array('r' => 'acl_rules'),)
            ->where(['r.rule_check_id' => 'manage-groups-view'])
            ->execute();

        $parentId = false;
        $row      = $statement->fetch();
        if (count($row)) {
            $parentId = $row[array_key_first($row)];
        }

        if (empty($parentId)) {
            throw new Exception('There is no manage case fields/groups rule.');
        }

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentId, 'superadmin', 'conditional-fields', '');");

        $this->execute("CREATE TABLE `client_form_field_conditions` (
            `field_condition_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `client_type_id` INT(11) UNSIGNED NOT NULL,
            `field_id` INT(11) UNSIGNED NOT NULL,
            `field_option_id` INT(11) UNSIGNED NULL DEFAULT NULL,
            `field_option_value` VARCHAR(128) NULL DEFAULT '',
            PRIMARY KEY (`field_condition_id`),
            INDEX `FK_client_form_field_conditions_client_types` (`client_type_id`),
            INDEX `FK_client_form_field_conditions_client_form_fields` (`field_id`),
            INDEX `FK_client_form_field_conditions_client_form_default` (`field_option_id`),
            CONSTRAINT `FK_client_form_field_conditions_client_form_default` FOREIGN KEY (`field_option_id`) REFERENCES `client_form_default` (`form_default_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_form_field_conditions_client_form_fields` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_form_field_conditions_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='List of conditions that will be checked for the related field'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute("CREATE TABLE `client_form_field_condition_hidden_fields` (
            `field_condition_id` BIGINT(20) UNSIGNED NOT NULL,
            `field_id` INT(11) UNSIGNED NOT NULL,
            UNIQUE INDEX `field_condition_id_field_id` (`field_condition_id`, `field_id`),
            INDEX `FK_client_form_field_condition_hidden_fields_client_form_fields` (`field_id`),
            CONSTRAINT `FK_client_form_field_condition_hidden_fields_client_form_fields` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_form_field_condition_hidden_fields_conditions` FOREIGN KEY (`field_condition_id`) REFERENCES `client_form_field_conditions` (`field_condition_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='List of fields that will be hidden if related field condition will be due'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute("CREATE TABLE `client_form_field_condition_hidden_groups` (
            `field_condition_id` BIGINT(20) UNSIGNED NOT NULL,
            `group_id` INT(11) UNSIGNED NOT NULL,
            UNIQUE INDEX `field_condition_id_group_id` (`field_condition_id`, `group_id`),
            INDEX `FK_client_form_field_condition_hidden_groups_client_form_groups` (`group_id`),
            CONSTRAINT `FK_client_form_field_condition_hidden_groups_client_form_groups` FOREIGN KEY (`group_id`) REFERENCES `client_form_groups` (`group_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_form_field_condition_hidden_groups_conditions` FOREIGN KEY (`field_condition_id`) REFERENCES `client_form_field_conditions` (`field_condition_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='List of groups (and all inner fields) that will be hidden if related field condition will be due'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB"
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE `client_form_field_condition_hidden_groups`;");
        $this->execute("DROP TABLE `client_form_field_condition_hidden_fields`;");
        $this->execute("DROP TABLE `client_form_field_conditions`;");
        $this->execute("DELETE FROM `acl_rule_details` WHERE `resource_id` = 'conditional-fields' AND `module_id` = 'superadmin';");
    }
}