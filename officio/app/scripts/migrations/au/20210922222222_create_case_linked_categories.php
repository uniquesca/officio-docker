<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Migration\AbstractMigration;

class CreateCaseLinkedCategories extends AbstractMigration
{
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
                    'resource_id'        => 'manage-company-case-categories',
                    'resource_privilege' => '',
                    'rule_allow'         => 1,
                ]
            )
            ->execute();


        // Create new tables
        $this->execute("CREATE TABLE `client_categories` (
                  `client_category_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `company_id` BIGINT(20) NULL DEFAULT NULL,
                  `client_category_parent_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                  `client_category_name` CHAR(255) NULL DEFAULT NULL,
                  `client_category_abbreviation` CHAR(255) NULL DEFAULT NULL,
                PRIMARY KEY (`client_category_id`),
                CONSTRAINT `FK_client_categories_category_parent_id` FOREIGN KEY (`client_category_parent_id`) REFERENCES `client_categories` (`client_category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_client_categories_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COMMENT='Categories list for each company.' 
                ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        $this->execute("CREATE TABLE `client_categories_mapping` (
            `client_type_id` INT(11) UNSIGNED NOT NULL,
            `client_category_id` INT(11) UNSIGNED NOT NULL,
            `client_category_mapping_order` TINYINT(3) UNSIGNED NULL DEFAULT 0,
            CONSTRAINT `FK_client_categories_mapping_client_categories` FOREIGN KEY (`client_category_id`) REFERENCES `client_categories` (`client_category_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_categories_mapping_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Categories mapping to case types.'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;");

        // Insert all records at once, preserve the ids
        $this->execute("INSERT INTO `client_categories` (`client_category_id`, `company_id`, `client_category_name`, `client_category_abbreviation`) SELECT o.default_option_id, o.company_id, o.default_option_name, o.default_option_abbreviation FROM company_default_options AS o;");

        // Collect categories and case types for the default company
        $arrDefaultCompanyCategories = $this->fetchAll('SELECT * FROM company_default_options WHERE company_id = 0 ORDER BY default_option_order');
        $arrDefaultCompanyCaseTypes  = $this->fetchAll('SELECT * FROM client_types WHERE company_id = 0');

        // Prepare default categories, assign them to all default case types
        $arrMappingRows              = array();
        $arrCreatedDefaultCategories = array();
        $categoryOrder               = 0;
        foreach ($arrDefaultCompanyCategories as $arrDefaultCompanyCategory) {
            $arrCreatedDefaultCategories[] = array(
                'category_id'           => $arrDefaultCompanyCategory['default_option_id'],
                'category_name'         => $arrDefaultCompanyCategory['default_option_name'],
                'category_abbreviation' => $arrDefaultCompanyCategory['default_option_abbreviation']
            );

            foreach ($arrDefaultCompanyCaseTypes as $arrDefaultCompanyCaseTypeInfo) {
                $arrMappingRows[] = sprintf(
                    '(%d, %d, %d)',
                    $arrDefaultCompanyCaseTypeInfo['client_type_id'],
                    $arrDefaultCompanyCategory['default_option_id'],
                    $categoryOrder
                );
            }

            $categoryOrder++;
        }

        // Load all categories for all companies (except of the default one)
        $arrAllCompaniesCategoriesGrouped = array();
        $arrAllCompaniesCategories        = $this->fetchAll('SELECT * FROM company_default_options WHERE company_id != 0 ORDER BY default_option_order');
        foreach ($arrAllCompaniesCategories as $arrCompanyCategoryInfo) {
            $arrAllCompaniesCategoriesGrouped[$arrCompanyCategoryInfo['company_id']][] = $arrCompanyCategoryInfo;
        }

        // Load all case types for all companies (except of the default one)
        $arrCompaniesGroupedCaseTypes = array();
        $arrCompaniesCaseTypes        = $this->fetchAll('SELECT * FROM client_types WHERE company_id != 0');
        foreach ($arrCompaniesCaseTypes as $arrCompanyCaseTypeInfo) {
            $arrCompaniesGroupedCaseTypes[$arrCompanyCaseTypeInfo['company_id']][] = $arrCompanyCaseTypeInfo;
        }


        // Create categories (if some default categories are not created - create them),
        // assign them to all case types of the company,
        // map them to default categories
        foreach ($arrAllCompaniesCategoriesGrouped as $companyId => $arrCompanyCategories) {
            $categoryOrder = 0;

            foreach ($arrCreatedDefaultCategories as $arrDefaultCategoryInfo) {
                $booFoundDefault = false;
                foreach ($arrCompanyCategories as $key => $arrCompanyCategoryInfo) {
                    if (strtolower($arrCompanyCategoryInfo['default_option_name']) == strtolower($arrDefaultCategoryInfo['category_name'])) {
                        $booFoundDefault = true;

                        $arrCompanyCategories[$key]['client_category_parent_id']   = $arrDefaultCategoryInfo['category_id'];
                        $arrCompanyCategories[$key]['default_option_name']         = $arrDefaultCategoryInfo['category_name'];
                        $arrCompanyCategories[$key]['default_option_abbreviation'] = $arrDefaultCategoryInfo['category_abbreviation'];
                        break;
                    }
                }

                if (!$booFoundDefault) {
                    $arrCompanyCategories[] = array(
                        'default_option_id'           => 0,
                        'client_category_parent_id'   => $arrDefaultCategoryInfo['category_id'],
                        'default_option_name'         => $arrDefaultCategoryInfo['category_name'],
                        'default_option_abbreviation' => $arrDefaultCategoryInfo['category_abbreviation'],
                    );
                }
            }


            foreach ($arrCompanyCategories as $arrCompanyCategoryInfo) {
                $parentCategoryId     = null;
                $categoryName         = $arrCompanyCategoryInfo['default_option_name'];
                $categoryAbbreviation = $arrCompanyCategoryInfo['default_option_abbreviation'];
                if (isset($arrCompanyCategoryInfo['client_category_parent_id'])) {
                    $parentCategoryId = $arrCompanyCategoryInfo['client_category_parent_id'];
                } else {
                    foreach ($arrCreatedDefaultCategories as $arrDefaultCategoryInfo) {
                        if (strtolower($arrCompanyCategoryInfo['default_option_name']) == strtolower($arrDefaultCategoryInfo['category_name'])) {
                            $parentCategoryId     = $arrDefaultCategoryInfo['category_id'];
                            $categoryName         = $arrDefaultCategoryInfo['category_name'];
                            $categoryAbbreviation = $arrDefaultCategoryInfo['category_abbreviation'];
                            break;
                        }
                    }
                }

                if (empty($arrCompanyCategoryInfo['default_option_id'])) {
                    $statement = $this->getQueryBuilder()
                        ->insert(array('company_id', 'client_category_parent_id', 'client_category_name', 'client_category_abbreviation'))
                        ->into('client_categories')
                        ->values([
                            'company_id'                   => $companyId,
                            'client_category_parent_id'    => $parentCategoryId,
                            'client_category_name'         => $categoryName,
                            'client_category_abbreviation' => $categoryAbbreviation,
                        ])
                        ->execute();

                    $newId = $statement->lastInsertId('client_categories');
                } else {
                    $this->getQueryBuilder()
                        ->update('client_categories')
                        ->set([
                            'client_category_parent_id'    => $parentCategoryId,
                            'client_category_name'         => $categoryName,
                            'client_category_abbreviation' => $categoryAbbreviation,
                        ])
                        ->where([
                            'client_category_id' => (int)$arrCompanyCategoryInfo['default_option_id']
                        ])
                        ->execute();

                    $newId = $arrCompanyCategoryInfo['default_option_id'];
                }

                if (isset($arrCompaniesGroupedCaseTypes[$companyId])) {
                    foreach ($arrCompaniesGroupedCaseTypes[$companyId] as $arrCompanyCaseTypeInfo) {
                        $arrMappingRows[] = sprintf(
                            '(%d, %d, %d)',
                            $arrCompanyCaseTypeInfo['client_type_id'],
                            $newId,
                            $categoryOrder
                        );
                    }

                    $categoryOrder++;
                }
            }
        }


        if (!empty($arrMappingRows)) {
            $sql = sprintf("INSERT INTO client_categories_mapping (`client_type_id`, `client_category_id`, `client_category_mapping_order`) VALUES %s", implode(',', $arrMappingRows));
            $this->execute($sql);
        }

        // Remove the old table
        $this->execute('ALTER TABLE `company_prospects` DROP FOREIGN KEY `FK_company_prospects_company_default_options`;');
        $this->execute('ALTER TABLE `company_prospects` ADD CONSTRAINT `FK_company_prospects_client_categories` FOREIGN KEY (`visa`) REFERENCES `client_categories` (`client_category_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
        $this->execute('ALTER TABLE `company_default_options` DROP FOREIGN KEY `FK_company_default_options`;');
        $this->execute('DROP TABLE `company_default_options`;');

        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        /** @var StorageInterface $cache */
        $cache = $serviceManager->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'superadmin' AND resource_id = 'manage-company-case-categories'");

        $this->execute("CREATE TABLE `company_default_options` (
            `default_option_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `company_id` BIGINT(20) NULL DEFAULT NULL,
            `default_option_type` ENUM('categories') NULL DEFAULT 'categories' COLLATE 'utf8_general_ci',
            `default_option_name` CHAR(255) NULL DEFAULT '' COLLATE 'utf8_general_ci',
            `default_option_abbreviation` CHAR(255) NULL DEFAULT NULL COLLATE 'utf8_general_ci',
            `default_option_order` TINYINT(3) UNSIGNED NULL DEFAULT '0',
            INDEX `FK_company_default_options` (`company_id`) USING BTREE,
            CONSTRAINT `FK_company_default_options` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Each company can have own list options (for now Categories combo uses this table).'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        $this->execute('ALTER TABLE `company_prospects` DROP FOREIGN KEY `FK_company_prospects_client_categories`;');
        $this->execute('ALTER TABLE `company_prospects` ADD CONSTRAINT `FK_company_prospects_company_default_options` FOREIGN KEY (`visa`) REFERENCES `company_default_options` (`default_option_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
        $this->execute('DROP TABLE `client_categories_mapping`;');
        $this->execute('DROP TABLE `client_categories`;');

        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        /** @var StorageInterface $cache */
        $cache = $serviceManager->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}
