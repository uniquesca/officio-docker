<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddDocumentsChecklist extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description' => 'Upload Documents',
                'rule_check_id' => 'client-documents-checklist-upload',
                'module_id' => 'documents',
                'resource_id' => 'checklist',
                'resource_privilege' => array('upload')
            ),

            array(
                'rule_description'   => 'Delete Documents',
                'rule_check_id'      => 'client-documents-checklist-delete',
                'module_id'          => 'documents',
                'resource_id'        => 'checklist',
                'resource_privilege' => array('delete')
            ),

            array(
                'rule_description'   => 'Download Documents',
                'rule_check_id'      => 'client-documents-checklist-download',
                'module_id'          => 'documents',
                'resource_id'        => 'checklist',
                'resource_privilege' => array('download')
            ),

            array(
                'rule_description'   => 'Set Tags',
                'rule_check_id'      => 'client-documents-checklist-change-tags',
                'module_id'          => 'documents',
                'resource_id'        => 'checklist',
                'resource_privilege' => array('set-tags', 'get-all-tags')
            ),
        );

        return $arrRules;
    }

    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select(array('rule_id'))
                ->from(array('acl_rules'))
                ->where(
                    [
                        'rule_check_id' => 'clients-view'
                    ]
                )
                ->execute();

            $ruleParentId = false;
            $row = $statement->fetch();
            if (!empty($row)) {
                $ruleParentId =  $row[array_key_first($row)];
            }

            if (empty($ruleParentId)) {
                throw new Exception('Main parent rule not found.');
            }

            $statement = $this->getQueryBuilder()
                ->insert(
                [
                    'rule_parent_id',
                    'module_id',
                    'rule_description',
                    'rule_check_id',
                    'superadmin_only',
                    'crm_only',
                    'rule_visible',
                    'rule_order'
                ]
            )
                ->into('acl_rules')
                ->values(
                    array(
                        'rule_parent_id'   => $ruleParentId,
                        'module_id'        => 'documents',
                        'rule_description' => 'Client Documents Checklist',
                        'rule_check_id'    => 'client-documents-checklist-view',
                        'superadmin_only'  => 0,
                        'crm_only'         => 'N',
                        'rule_visible'     => 1,
                        'rule_order'       => 13,
                    )
                )
                ->execute();

            $mainRuleId = $statement->lastInsertId('acl_rules');

            $this->table('acl_rule_details')
                ->insert([
                    [
                        'rule_id'            => $mainRuleId,
                        'module_id'          => 'documents',
                        'resource_id'        => 'checklist',
                        'resource_privilege' => 'get-list',
                        'rule_allow'         => 1,
                    ]
                ])
                ->saveData();

            $this->table('packages_details')
                ->insert([
                    [
                        'package_id'                 => 1,
                        'rule_id'                    => $mainRuleId,
                        'package_detail_description' => 'Client Documents Checklist',
                        'visible'                    => 1,
                    ]
                ])
                ->saveData();

            $booDocumentsChecklistEnabled = !empty(self::getService('config')['site_version']['documents_checklist_enabled']);
            if ($booDocumentsChecklistEnabled) {
                $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                    SELECT a.role_id, $mainRuleId
                                    FROM acl_role_access AS a
                                    LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                    WHERE a.rule_id = $ruleParentId");
            }


            $arrRules = $this->getNewRules();

            $order = 0;
            foreach ($arrRules as $arrRuleInfo) {
                $statement = $this->getQueryBuilder()
                    ->insert(
                        [
                            'rule_parent_id',
                            'module_id',
                            'rule_description',
                            'rule_check_id',
                            'superadmin_only',
                            'crm_only',
                            'rule_visible',
                            'rule_order'
                        ]
                    )
                    ->into('acl_rules')
                    ->values(
                        array(
                            'rule_parent_id'   => $mainRuleId,
                            'module_id'        => $arrRuleInfo['module_id'],
                            'rule_description' => $arrRuleInfo['rule_description'],
                            'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                            'superadmin_only'  => 0,
                            'crm_only'         => 'N',
                            'rule_visible'     => 1,
                            'rule_order'       => $order++
                        )
                    )
                    ->execute();

                $ruleId = $statement->lastInsertId('acl_rules');

                foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                    $this->table('acl_rule_details')
                        ->insert([
                            [
                                'rule_id'            => $ruleId,
                                'module_id'          => $arrRuleInfo['module_id'],
                                'resource_id'        => $arrRuleInfo['resource_id'],
                                'resource_privilege' => $resourcePrivilege,
                                'rule_allow'         => 1,
                            ]
                        ])
                        ->saveData();
                }

                $this->table('packages_details')
                    ->insert([
                        [
                            'package_id'                 => 1,
                            'rule_id'                    => $ruleId,
                            'package_detail_description' => $arrRuleInfo['rule_description'],
                            'visible'                    => 1,
                        ]
                    ])
                    ->saveData();

                if ($booDocumentsChecklistEnabled) {
                    $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                        SELECT a.role_id, $ruleId
                                        FROM acl_role_access AS a
                                        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                        WHERE a.rule_id = $mainRuleId");
                }
            }

            $this->query("
                CREATE TABLE `client_form_dependents_required_files` (
                    `required_file_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `company_id` BIGINT(20) NOT NULL,
                    `required_file_description` CHAR(255) NOT NULL,
                    `main_applicant_show` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    `main_applicant_required` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    `adult_show` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    `adult_required` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    `minor_16_and_above_show` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    `minor_16_and_above_required` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    `minor_less_16_show` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    `minor_less_16_required` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    INDEX `required_file_id` (`required_file_id`),
                    INDEX `FK_client_form_dependents_required_files_company` (`company_id`),
                    CONSTRAINT `FK_client_form_dependents_required_files_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;
            ");

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Form 12', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id,'D1 - Application for Citizenship by Investment Disclosure', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'D2 - Fingerprint and Photograph Verification Form', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'D3 - Medical Questionnaire', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'D4 - Investment Agreement (FUND OPTION ONLY)', 'Y', 'N', 'N', 'N', 'N', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Sale and Purchase Agreement (REAL ESTATE OPTION)', 'Y', 'N', 'N', 'N', 'N', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Passport', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Driver\'s License', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Birth Certificate', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Identity Document, /Book or Card', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Marriage Certificate / Divorce Certificate', 'Y', 'N', 'Y', 'N', 'N', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Letter of Employment / Audited Financial Statements / Letter of Incorporation', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Letter to the Minister', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'CV / Resume', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Four (4) Passport Photographs', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Military Service and Discharge Documents', 'Y', 'N', 'Y', 'N', 'Y', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Police Record / Certification from country of origin and any country applicant has been a resident of for more than 6 months during the past 10 years (all countries)', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Professional Reference', 'Y', 'Y', 'Y', 'Y', 'N', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Enrollment letters/University letter', 'N', 'N', 'N', 'N', 'Y', 'Y', 'Y', 'Y' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Two Personal References', 'Y', 'N', 'Y', 'N', 'Y', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Recommendation from Banker', 'Y', 'N', 'N', 'N', 'N', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, '12 months Bank  Statement', 'Y', 'Y', 'N', 'N', 'N', 'N', 'N', 'N' FROM company;"
            );

            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'Proof of Residential Address', 'Y', 'Y', 'N', 'N', 'N', 'N', 'N', 'N' FROM company;"
            );
            $this->query(
                "INSERT INTO `client_form_dependents_required_files` (`company_id`, `required_file_description`, `main_applicant_show`, `main_applicant_required`, `adult_show`, `adult_required`, `minor_16_and_above_show`, `minor_16_and_above_required`, `minor_less_16_show`, `minor_less_16_required`) 
                SELECT company_id, 'An affidavit of consent from other parents is required, if both parents are not part of the application when applying for children', 'N', 'N', 'N', 'N', 'N', 'N', 'Y', 'N' FROM company;"
            );

            $this->query("
                CREATE TABLE `client_form_dependents_uploaded_files` (
                    `uploaded_file_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `required_file_id` BIGINT(20) UNSIGNED NOT NULL,
                    `member_id` BIGINT(20) NOT NULL,
                    `dependent_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                    `uploaded_file_name` CHAR(255) NOT NULL,
                    `uploaded_file_size` BIGINT(20) UNSIGNED NOT NULL,
                    INDEX `uploaded_file_id` (`uploaded_file_id`),
                    INDEX `FK_client_form_dependents_uploaded_files_client_form_dependents` (`dependent_id`),
                    INDEX `FK_client_form_dependents_uploaded_files_client_required_files` (`required_file_id`),
                    INDEX `FK_client_form_dependents_uploaded_files_members` (`member_id`),
                    CONSTRAINT `FK_client_form_dependents_uploaded_files_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT `FK_client_form_dependents_uploaded_files_client_form_dependents` FOREIGN KEY (`dependent_id`) REFERENCES `client_form_dependents` (`dependent_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT `FK_client_form_dependents_uploaded_files_client_required_files` FOREIGN KEY (`required_file_id`) REFERENCES `client_form_dependents_required_files` (`required_file_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB
            ");

            $this->query("
                CREATE TABLE `client_form_dependents_uploaded_file_tags` (
                    `uploaded_file_id` BIGINT(20) UNSIGNED NOT NULL,
                    `company_id` BIGINT(20) NOT NULL,
                    `tag` CHAR(255) NOT NULL,
                    INDEX `FK_client_form_dependents_uploaded_file_tags` (`uploaded_file_id`),
                    INDEX `FK_client_form_dependents_uploaded_file_tags_company` (`company_id`),
                    CONSTRAINT `FK_client_form_dependents_uploaded_file_tags_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT `FK_client_form_dependents_uploaded_file_tags` FOREIGN KEY (`uploaded_file_id`) REFERENCES `client_form_dependents_uploaded_files` (`uploaded_file_id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB
            ");
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $this->getQueryBuilder()
                ->delete('acl_rules')
                ->where(
                    [
                        'rule_check_id' => 'client-documents-checklist-view'
                    ]
                )
                ->execute();

            $this->query('DROP TABLE IF EXISTS `client_form_dependents_uploaded_file_tags`;');
            $this->query('DROP TABLE IF EXISTS `client_form_dependents_uploaded_files`;');
            $this->query('DROP TABLE IF EXISTS `client_form_dependents_required_files`;');
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }
}