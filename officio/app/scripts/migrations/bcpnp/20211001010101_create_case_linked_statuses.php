<?php

use Officio\Migration\AbstractMigration;

class CreateCaseLinkedStatuses extends AbstractMigration
{
    protected $clearCache = true;

    private function createCaseStatus($statusId, $companyId, $statusName, $parentStatusId)
    {
        $arrListInfo = [
            'client_status_id'        => $statusId,
            'company_id'              => $companyId,
            'client_status_name'      => $statusName,
            'client_status_parent_id' => $parentStatusId,
        ];

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrListInfo))
            ->into('client_statuses')
            ->values($arrListInfo)
            ->execute();

        return $statement->lastInsertId('client_statuses');
    }

    private function createCaseStatusList($companyId, $listName, $parentListId)
    {
        $arrListInfo = [
            'company_id'                   => $companyId,
            'client_status_list_name'      => $listName,
            'client_status_list_parent_id' => $parentListId,
        ];

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrListInfo))
            ->into('client_statuses_lists')
            ->values($arrListInfo)
            ->execute();

        $listId = $statement->lastInsertId('client_statuses_lists');

        // Automatically make this list as a default one for the case type of the company
        $this->execute("UPDATE client_types SET client_status_list_id = $listId WHERE company_id = $companyId;");

        return $listId;
    }

    private function assignCaseStatusListToCategories($listId, $arrCategories)
    {
        foreach ($arrCategories as $categoryId) {
            $arrListInfo = [
                'client_status_list_id' => $listId,
                'client_category_id'    => $categoryId,
            ];

            $statement = $this->getQueryBuilder()
                ->insert(array_keys($arrListInfo))
                ->into('client_statuses_lists_mapping_to_categories')
                ->values($arrListInfo)
                ->execute();

            $statement->lastInsertId('client_statuses_lists_mapping_to_categories');
        }
    }

    private function assignCaseStatusListToStatuses($listId, $arrStatuses)
    {
        $order = 0;
        foreach ($arrStatuses as $statusId) {
            $arrMapping = [
                'client_status_list_id'    => $listId,
                'client_status_id'         => $statusId,
                'client_status_list_order' => $order++,
            ];

            $statement = $this->getQueryBuilder()
                ->insert(array_keys($arrMapping))
                ->into('client_statuses_lists_mapping_to_statuses')
                ->values($arrMapping)
                ->execute();

            $statement->lastInsertId('client_statuses_lists_mapping_to_statuses');
        }
    }

    public function up()
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
                    'resource_id'        => 'manage-company-case-statuses',
                    'resource_privilege' => '',
                    'rule_allow'         => 1,
                ]
            )
            ->execute();


        // Create a new field type
        $arrCaseTypeFieldTypeInfo = [
            'field_type_text_id'               => 'case_status',
            'field_type_label'                 => 'Case Status',
            'field_type_can_be_used_in_search' => 'Y',
            'field_type_can_be_encrypted'      => 'N',
            'field_type_with_max_length'       => 'N',
            'field_type_with_options'          => 'N',
            'field_type_with_default_value'    => 'N',
            'field_type_with_custom_height'    => 'N',
            'field_type_use_for'               => 'case',
        ];

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrCaseTypeFieldTypeInfo))
            ->into('field_types')
            ->values($arrCaseTypeFieldTypeInfo)
            ->execute();

        $caseStatusFieldTypeId = $statement->lastInsertId('field_types');

        $this->execute("UPDATE client_form_fields SET type = $caseStatusFieldTypeId WHERE company_field_id = 'file_status';");


        // Create new tables
        $this->execute("CREATE TABLE `client_statuses` (
                  `client_status_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `company_id` BIGINT(20) NULL DEFAULT NULL,
                  `client_status_parent_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                  `client_status_name` CHAR(255) NULL DEFAULT NULL,
                PRIMARY KEY (`client_status_id`),
                CONSTRAINT `FK_client_statuses_status_parent_id` FOREIGN KEY (`client_status_parent_id`) REFERENCES `client_statuses` (`client_status_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_client_statuses_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COMMENT='Case Statuses for each company.' 
                ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `client_statuses_lists` (
            `client_status_list_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `company_id` BIGINT(20) NULL DEFAULT NULL,
            `client_status_list_parent_id` INT(11) UNSIGNED NULL DEFAULT NULL,
            `client_status_list_name` CHAR(255) NULL DEFAULT NULL,
            PRIMARY KEY (`client_status_list_id`) USING BTREE,
            INDEX `FK_client_statuses_lists_company` (`company_id`) USING BTREE,
            INDEX `FK_client_statuses_lists_client_statuses_lists` (`client_status_list_parent_id`) USING BTREE,
            CONSTRAINT `FK_client_statuses_lists_client_statuses_lists` FOREIGN KEY (`client_status_list_parent_id`) REFERENCES `client_statuses_lists` (`client_status_list_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_statuses_lists_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Case Statuses Lists for each company.'
        ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `client_statuses_lists_mapping_to_statuses` (
            `client_status_list_id` INT(11) UNSIGNED NOT NULL,
            `client_status_id` INT(11) UNSIGNED NOT NULL,
            `client_status_list_order` TINYINT(3) UNSIGNED NULL DEFAULT '0',
            CONSTRAINT `FK_client_statuses_lists_mapping_to_statuses` FOREIGN KEY (`client_status_id`) REFERENCES `client_statuses` (`client_status_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_statuses_lists_mapping_to_lists` FOREIGN KEY (`client_status_list_id`) REFERENCES `client_statuses_lists` (`client_status_list_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Case statuses linkage to lists.'
        ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `client_statuses_lists_mapping_to_categories` (
            `client_status_list_id` INT(11) UNSIGNED NOT NULL,
            `client_category_id` INT(11) UNSIGNED NOT NULL,
            CONSTRAINT `FK_client_statuses_lists_mapping_to_categories_client_categories` FOREIGN KEY (`client_category_id`) REFERENCES `client_categories` (`client_category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_statuses_lists_mapping_to_categories_lists` FOREIGN KEY (`client_status_list_id`) REFERENCES `client_statuses_lists` (`client_status_list_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Case categories linkage to lists.'
        ENGINE=InnoDB DEFAULT CHARSET=utf8;");


        // A new setting for the case type
        $this->execute("ALTER TABLE `client_types` ADD COLUMN `client_status_list_id` INT(11) UNSIGNED NULL AFTER `company_id`;");
        $this->execute("ALTER TABLE `client_types` ADD CONSTRAINT `FK_client_types_client_statuses_lists` FOREIGN KEY (`client_status_list_id`) REFERENCES `client_statuses_lists` (`client_status_list_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        // Load the list of all companies statuses
        $arrFieldsOptions = $this->fetchAll("SELECT d.form_default_id, f.company_id, d.field_id, d.value FROM client_form_default AS d LEFT JOIN client_form_fields AS f ON f.field_id = d.field_id WHERE f.company_field_id = 'file_status' ORDER BY `d`.`order`");

        // Create default statuses first
        $arrFieldsIds                   = [];
        $arrDefaultOptions              = [];
        $arrAllCompaniesStatusesGrouped = [];

        $arrFieldsOptionsGrouped = [];
        foreach ($arrFieldsOptions as $key => $arrFieldOptionInfo) {
            // Remove extra slashes (if any)
            $string = implode("", explode("\\", $arrFieldOptionInfo['value']));
            $option = stripslashes(trim($string));

            $arrFieldsOptions[$key]['value'] = $option;

            $companyId = $arrFieldOptionInfo['company_id'];
            if (empty($companyId)) {
                $statusId = $this->createCaseStatus(
                    $arrFieldOptionInfo['form_default_id'],
                    $companyId,
                    $option,
                    null
                );

                $arrDefaultOptions[$option]                   = $statusId;
                $arrAllCompaniesStatusesGrouped[$companyId][] = $statusId;
            } else {
                $arrFieldsOptionsGrouped[$companyId][] = $arrFieldOptionInfo;
            }


            $arrFieldsIds[] = $arrFieldOptionInfo['field_id'];
        }

        if (!empty($arrFieldsIds)) {
            $arrFieldsIds = array_unique($arrFieldsIds);
            $this->execute(
                sprintf(
                    "DELETE FROM client_form_default WHERE field_id IN (%s)",
                    implode(',', $arrFieldsIds)
                )
            );
        }

        // Create statuses for all companies
        foreach ($arrFieldsOptionsGrouped as $companyId => $arrFieldOptions) {
            foreach ($arrFieldOptions as $arrFieldOptionInfo) {
                $arrAllCompaniesStatusesGrouped[$companyId][] = $this->createCaseStatus(
                    $arrFieldOptionInfo['form_default_id'],
                    $companyId,
                    $arrFieldOptionInfo['value'],
                    isset($arrDefaultOptions[$arrFieldOptionInfo['value']]) ? $arrDefaultOptions[$arrFieldOptionInfo['value']] : null
                );
            }
        }

        // Prepare the list of categories
        $arrAllCompaniesCategories        = $this->fetchAll('SELECT * FROM client_categories');
        $arrAllCompaniesCategoriesGrouped = [];
        foreach ($arrAllCompaniesCategories as $arrCategoryInfo) {
            $arrAllCompaniesCategoriesGrouped[$arrCategoryInfo['company_id']][] = $arrCategoryInfo['client_category_id'];
        }


        // Create a default "case status list" + mappings for this list
        $defaultListName = 'Generic V1';
        $defaultListId   = $this->createCaseStatusList(0, $defaultListName, null);
        if (isset($arrAllCompaniesCategoriesGrouped[0])) {
            $this->assignCaseStatusListToCategories($defaultListId, $arrAllCompaniesCategoriesGrouped[0]);
        }
        if (isset($arrAllCompaniesStatusesGrouped[0])) {
            $this->assignCaseStatusListToStatuses($defaultListId, $arrAllCompaniesStatusesGrouped[0]);
        }

        $arrAllCompanies = $this->fetchAll('SELECT * FROM company WHERE company_id != 0');
        foreach ($arrAllCompanies as $arrCompanyInfo) {
            $companyId = $arrCompanyInfo['company_id'];

            // Create the list for all companies
            $companyListId = $this->createCaseStatusList($companyId, $defaultListName, $defaultListId);

            // Assign all company categories to this new list
            if (isset($arrAllCompaniesCategoriesGrouped[$companyId])) {
                $this->assignCaseStatusListToCategories($companyListId, $arrAllCompaniesCategoriesGrouped[$companyId]);
            }

            // Assign all company statuses to this new list
            if (isset($arrAllCompaniesStatusesGrouped[$companyId])) {
                $this->assignCaseStatusListToStatuses($companyListId, $arrAllCompaniesStatusesGrouped[$companyId]);
            }
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_types` DROP FOREIGN KEY `FK_client_types_client_statuses_lists`, DROP COLUMN `client_status_list_id`;");
        $this->execute("UPDATE client_form_fields SET type = 3 WHERE company_field_id = 'file_status';");
        $this->execute("DELETE FROM field_types WHERE field_type_text_id = 'case_status'");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'superadmin' AND resource_id = 'manage-company-case-statuses'");
        $this->execute('DROP TABLE `client_statuses_lists_mapping_to_categories`;');
        $this->execute('DROP TABLE `client_statuses_lists_mapping_to_statuses`;');
        $this->execute('DROP TABLE `client_statuses_lists`;');
        $this->execute('DROP TABLE `client_statuses`;');
    }
}
