<?php

use Phinx\Migration\AbstractMigration;

class ChangeCaseCategoriesStructure extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $this->execute("ALTER TABLE `client_categories` ADD COLUMN `client_type_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `company_id`;");
        $this->execute("ALTER TABLE `client_categories` ADD COLUMN `client_status_list_id` INT(11) UNSIGNED NOT NULL DEFAULT '0' AFTER `client_type_id`;");
        $this->execute("ALTER TABLE `client_categories` ADD COLUMN `client_category_link_to_employer` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `client_category_abbreviation`;");
        $this->execute("ALTER TABLE `client_categories` ADD COLUMN `client_category_order` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0' AFTER `client_category_link_to_employer`;");

        $this->execute("UPDATE client_categories AS c JOIN client_categories_mapping AS m ON c.client_category_id = m.client_category_id SET c.client_type_id = m.client_type_id, c.client_category_order = m.client_category_mapping_order;");
        $this->execute("DELETE FROM `client_categories` WHERE  `client_type_id` = 0;");
        $this->execute("ALTER TABLE `client_categories` ADD CONSTRAINT `FK_client_categories_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->execute("DROP TABLE `client_categories_mapping`;");

        $this->execute("UPDATE client_categories AS c JOIN client_statuses_lists_mapping_to_categories AS m ON c.client_category_id = m.client_category_id SET c.client_status_list_id = m.client_status_list_id;");
        $this->execute("UPDATE client_categories AS c 
        JOIN client_statuses_lists AS l ON c.company_id = l.company_id AND client_status_list_name = 'Generic V1'
        SET c.client_status_list_id = l.client_status_list_id
        WHERE c.client_status_list_id = 0;");
        $this->execute("ALTER TABLE `client_categories` ADD CONSTRAINT `FK_client_categories_client_statuses_lists` FOREIGN KEY (`client_status_list_id`) REFERENCES `client_statuses_lists` (`client_status_list_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->execute("DROP TABLE `client_statuses_lists_mapping_to_categories`;");

        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'superadmin' AND resource_id = 'manage-company-case-categories'");
    }

    public function down()
    {
        // Add access to the new controller/action
        $statement = $this->getQueryBuilder()
            ->select('rule_id')
            ->from('acl_rules')
            ->where(['rule_check_id' => 'manage-groups-view'])
            ->execute();

        $parentRuleId = false;
        $row          = $statement->fetch();
        if (count($row)) {
            $parentRuleId = $row[array_key_first($row)];
        }

        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        $this->getQueryBuilder()
            ->insert(
                [
                    'rule_id',
                    'module_id',
                    'resource_id',
                    'resource_privilege',
                    'rule_allow',
                ]
            )
            ->into('acl_rule_details')
            ->values(
                [
                    'rule_id'            => $parentRuleId,
                    'module_id'          => 'superadmin',
                    'resource_id'        => 'manage-company-case-categories',
                    'resource_privilege' => '',
                    'rule_allow'         => 1,
                ]
            )
            ->execute();

        $this->execute("CREATE TABLE `client_statuses_lists_mapping_to_categories` (
            `client_status_list_id` INT(11) UNSIGNED NOT NULL,
            `client_category_id` INT(11) UNSIGNED NOT NULL,
            INDEX `FK_client_statuses_lists_mapping_to_categories_client_categories` (`client_category_id`) USING BTREE,
            INDEX `FK_client_statuses_lists_mapping_to_categories_lists` (`client_status_list_id`) USING BTREE,
            CONSTRAINT `FK_client_statuses_lists_mapping_to_categories_client_categories` FOREIGN KEY (`client_category_id`) REFERENCES `client_categories` (`client_category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_statuses_lists_mapping_to_categories_lists` FOREIGN KEY (`client_status_list_id`) REFERENCES `client_statuses_lists` (`client_status_list_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Case categories linkage to lists.'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;");
        $this->execute("INSERT INTO client_statuses_lists_mapping_to_categories (client_status_list_id, client_category_id) SELECT client_status_list_id, client_category_id FROM client_categories");

        $this->execute("CREATE TABLE `client_categories_mapping` (
           `client_type_id` INT(11) UNSIGNED NOT NULL,
           `client_category_id` INT(11) UNSIGNED NOT NULL,
           `client_category_mapping_order` TINYINT(3) UNSIGNED NULL DEFAULT '0',
           INDEX `FK_client_categories_mapping_client_categories` (`client_category_id`) USING BTREE,
           INDEX `FK_client_categories_mapping_client_types` (`client_type_id`) USING BTREE,
           CONSTRAINT `FK_client_categories_mapping_client_categories` FOREIGN KEY (`client_category_id`) REFERENCES `client_categories` (`client_category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
           CONSTRAINT `FK_client_categories_mapping_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Categories mapping to case types.'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;");
        $this->execute("INSERT INTO client_categories_mapping (client_type_id, client_category_id, client_category_mapping_order) SELECT client_type_id, client_category_id, client_category_order FROM client_categories");

        $this->execute("ALTER TABLE `client_categories` DROP FOREIGN KEY `FK_client_categories_client_statuses_lists`;");
        $this->execute("ALTER TABLE `client_categories` DROP FOREIGN KEY `FK_client_categories_client_types`;");
        $this->execute("ALTER TABLE `client_categories` DROP COLUMN `client_category_order`;");
        $this->execute("ALTER TABLE `client_categories` DROP COLUMN `client_category_link_to_employer`;");
        $this->execute("ALTER TABLE `client_categories` DROP COLUMN `client_status_list_id`;");
        $this->execute("ALTER TABLE `client_categories` DROP COLUMN `client_type_id`;");
    }
}