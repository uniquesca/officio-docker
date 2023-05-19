<?php

use Clients\Service\Clients;
use Officio\Service\Company;
use Phinx\Migration\AbstractMigration;

class SetFieldsAccessToDefaultRoles extends AbstractMigration
{
    public function up()
    {
        // Took 0.3360s on local server...

        try {
            /** @var Zend_Db_Adapter_Abstract $db */
            $db               = Zend_Registry::get('serviceManager')->get('db');
            /** @var Company $company */
            $company = Zend_Registry::get('serviceManager')->get(Company::class);
            /** @var Clients $clients */
            $clients = Zend_Registry::get('serviceManager')->get(Clients::class);
            $defaultCompanyId = $company->getDefaultCompanyId();
            $contactTypeId    = $clients->getMemberTypeIdByName('contact');

            // Load all roles
            $select = $db->select()
                ->from('acl_roles')
                ->where('company_id = ?', $defaultCompanyId, 'INT')
                ->where('role_type NOT IN (?)', array('superadmin', 'guest', 'crmuser'));

            $arrDefaultRoles = $db->fetchAll($select);


            // Load all blocks
            $select = $db->select()
                ->from('applicant_form_blocks')
                ->where('company_id = ?', $defaultCompanyId, 'INT');

            $arrDefaultBlocks = $db->fetchAll($select);

            $totalCreatedUpdated = 0;
            foreach ($arrDefaultBlocks as $arrDefaultBlockInfo) {
                // Load groups for each block
                $select = $db->select()
                    ->from('applicant_form_groups')
                    ->where('applicant_block_id = ?', $arrDefaultBlockInfo['applicant_block_id'])
                    ->where('company_id = ?', $defaultCompanyId, 'INT');

                $arrDefaultGroups = $db->fetchAll($select);

                foreach ($arrDefaultGroups as $arrDefaultGroupInfo) {
                    // Load all fields for each group
                    $select = $db->select()
                        ->from(array('o' => 'applicant_form_order'))
                        ->joinLeft(array('f' => 'applicant_form_fields'), 'f.applicant_field_id = o.applicant_field_id', 'applicant_field_unique_id')
                        ->where('o.applicant_group_id = ?', $arrDefaultGroupInfo['applicant_group_id'], 'INT');

                    $arrDefaultFields = $db->fetchAll($select);

                    $arrRowsInsert = array();
                    foreach ($arrDefaultRoles as $arrDefaultRoleInfo) {
                        // Allow full access to all Contact fields if role has access to Contacts tab
                        if ($arrDefaultBlockInfo['member_type_id'] == $contactTypeId) {
                            $select = $db->select()
                                ->from(array('a' => 'acl_role_access'), 'rule_id')
                                ->where('a.role_id = ?', $arrDefaultRoleInfo['role_parent_id'])
                                ->where('a.rule_id = ?', 120);

                            $ruleId = $db->fetchOne($select);
                            if (empty($ruleId)) {
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
                                        '(%s, %s, %s, %s)',
                                        $db->quote($arrDefaultRoleInfo['role_id'], 'INT'),
                                        $db->quote($arrDefaultGroupInfo['applicant_group_id'], 'INT'),
                                        $db->quote($arrDefaultFieldInfo['applicant_field_id'], 'INT'),
                                        $db->quote('F')
                                    );
                                }
                                break;

                            case 'Agent':
                                // Full access to all fields except of Client Login group
                                if ($arrDefaultGroupInfo['title'] == 'Client Login') {
                                    continue 2;
                                }

                                foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
                                    $arrRowsInsert[] = sprintf(
                                        '(%s, %s, %s, %s)',
                                        $db->quote($arrDefaultRoleInfo['role_id'], 'INT'),
                                        $db->quote($arrDefaultGroupInfo['applicant_group_id'], 'INT'),
                                        $db->quote($arrDefaultFieldInfo['applicant_field_id'], 'INT'),
                                        $db->quote('F')
                                    );
                                }
                                break;

                            case 'Individual Client':
                            case 'Employer Client':
                            case 'Client':
                                // Read access to all fields except of Client Login group + several specific ones
                                if ($arrDefaultGroupInfo['title'] == 'Client Login') {
                                    continue 2;
                                }

                                foreach ($arrDefaultFields as $arrDefaultFieldInfo) {
                                    if (in_array($arrDefaultFieldInfo['applicant_field_unique_id'], array('salutation_in_native_lang', 'photo', 'name_in_native_lang'))) {
                                        continue;
                                    }

                                    $arrRowsInsert[] = sprintf(
                                        '(%s, %s, %s, %s)',
                                        $db->quote($arrDefaultRoleInfo['role_id'], 'INT'),
                                        $db->quote($arrDefaultGroupInfo['applicant_group_id'], 'INT'),
                                        $db->quote($arrDefaultFieldInfo['applicant_field_id'], 'INT'),
                                        $db->quote('R')
                                    );
                                }
                                break;

                            default:
                                break;
                        }
                    }

                    if (count($arrRowsInsert)) {
                        $query = sprintf(
                            "INSERT IGNORE INTO applicant_form_fields_access " .
                            "(`role_id`, `applicant_group_id`, `applicant_field_id`, `status`) VALUES %s;",
                            implode(',', $arrRowsInsert)
                        );
                        $this->execute($query);

                        $totalCreatedUpdated += count($arrRowsInsert);
                    }
                }
            }

            echo 'Done. Processed: ' . $totalCreatedUpdated . PHP_EOL;

        } catch (\Exception $e) {
            echo 'Fatal error' . print_r($e->getTraceAsString(), 1);
        }
    }

    public function down()
    {
    }
}