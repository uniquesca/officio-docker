<?php

use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class SetFieldsAccessToDefaultRoles extends AbstractMigration
{
    public function up()
    {
        // Took 19s on local server

        try {
            // Add employer role to all companies
            $this->execute("UPDATE `acl_roles` SET `role_name`='Individual Client', `role_type`='individual_client' WHERE  `role_type`='client';");
            $this->execute("INSERT INTO `acl_roles` (`role_id`, `company_id`, `role_name`, `role_type`, `role_parent_id`, `role_child_id`, `role_visible`, `role_regTime`) 
                SELECT NULL, c.company_id, 'Employer Client', 'employer_client', CONCAT('employer_client_company_', c.company_id), 'guest', 1, UNIX_TIMESTAMP()
                FROM `company` as c;");

            $defaultCompanyId = 0;
            $contactTypeId    = 10;

            // Load all roles
            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('acl_roles')
                ->where(['company_id' => $defaultCompanyId])
                ->whereNotInList('role_type', ['superadmin', 'guest', 'crmuser'])
                ->execute();

            $arrDefaultRoles = $statement->fetchAll('assoc');


            // Load all blocks
            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('applicant_form_blocks')
                ->where(['company_id' => $defaultCompanyId])
                ->execute();

            $arrDefaultBlocks = $statement->fetchAll('assoc');

            foreach ($arrDefaultBlocks as $arrDefaultBlockInfo) {
                // Load groups for each block
                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from('applicant_form_groups')
                    ->where([
                        'applicant_block_id' => $arrDefaultBlockInfo['applicant_block_id'],
                        'company_id'         => $defaultCompanyId
                    ])
                    ->execute();

                $arrDefaultGroups = $statement->fetchAll('assoc');

                foreach ($arrDefaultGroups as $arrDefaultGroupInfo) {
                    // Load all fields for each group
                    $statement = $this->getQueryBuilder()
                        ->select(['o.*', 'f.applicant_field_unique_id'])
                        ->from(['o' => 'applicant_form_order'])
                        ->leftJoin(['f' => 'applicant_form_fields'], ['f.applicant_field_id = o.applicant_field_id'])
                        ->where(['o.applicant_group_id' => (int)$arrDefaultGroupInfo['applicant_group_id']])
                        ->execute();

                    $arrDefaultFields = $statement->fetchAll('assoc');

                    $arrRowsInsert = [];
                    foreach ($arrDefaultRoles as $arrDefaultRoleInfo) {
                        // Allow full access to all Contact fields if role has access to Contacts tab
                        if ($arrDefaultBlockInfo['member_type_id'] == $contactTypeId) {
                            $statement = $this->getQueryBuilder()
                                ->select(['rule_id'])
                                ->from('acl_role_access')
                                ->where([
                                    'role_id' => $arrDefaultRoleInfo['role_parent_id'],
                                    'rule_id' => 120
                                ])
                                ->execute();

                            $row = $statement->fetch();

                            if (empty($row)) {
                                continue;
                            }
                        }

                        switch ($arrDefaultRoleInfo['role_name']) {
                            case 'Admin':
                            case 'Processing':
                            case 'Accounting':
                                // Full access to all fields
                                foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
                                    $arrRowsInsert[] = sprintf(
                                        "(%d, %d, %d, '%s')",
                                        $arrDefaultRoleInfo['role_id'],
                                        $arrDefaultGroupInfo['applicant_group_id'],
                                        $arrDefaultFieldInfo['applicant_field_id'],
                                        'F'
                                    );
                                }
                                break;

                            case 'Agent':
                                // Full access to all fields except of Client Login group
                                if (in_array($arrDefaultGroupInfo['title'], ['Client Login Information', 'Employer Login'])) {
                                    continue 2;
                                }

                                foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
                                    $arrRowsInsert[] = sprintf(
                                        "(%d, %d, %d, '%s')",
                                        $arrDefaultRoleInfo['role_id'],
                                        $arrDefaultGroupInfo['applicant_group_id'],
                                        $arrDefaultFieldInfo['applicant_field_id'],
                                        'F'
                                    );
                                }
                                break;

                            case 'Individual Client':
                            case 'Employer Client':
                            case 'Client':
                                // Read-only access to all fields except of Client Login group + several specific ones
                                if (in_array($arrDefaultGroupInfo['title'], ['Client Login Information', 'Employer Login'])) {
                                    continue 2;
                                }

                                // Don't allow access to these specific fields for each role
                                $arrExceptions = [];
                                if ($arrDefaultRoleInfo['role_name'] == 'Employer Client') {
                                    $arrExceptions = ['DOB', 'city_of_birth', 'country_of_birth', 'country_of_residence', 'country_of_citizenship', 'passport_issue_date', 'passport_expiry_date', 'UCI'];
                                } elseif ($arrDefaultRoleInfo['role_name'] == 'Individual Client') {
                                    $arrExceptions = ['photo', 'special_instruction'];
                                }

                                foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
                                    if (in_array($arrDefaultFieldInfo['applicant_field_unique_id'], $arrExceptions)) {
                                        continue;
                                    }

                                    $arrRowsInsert[] = sprintf(
                                        "(%d, %d, %d, '%s')",
                                        $arrDefaultRoleInfo['role_id'],
                                        $arrDefaultGroupInfo['applicant_group_id'],
                                        $arrDefaultFieldInfo['applicant_field_id'],
                                        'R'
                                    );
                                }
                                break;

                            default:
                                break;
                        }
                    }

                    if (count($arrRowsInsert)) {
                        $query = sprintf(
                            "INSERT IGNORE INTO applicant_form_fields_access (`role_id`, `applicant_group_id`, `applicant_field_id`, `status`) VALUES %s;",
                            implode(',', $arrRowsInsert)
                        );

                        $this->execute($query);
                    }
                }
            }
        } catch (Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}
