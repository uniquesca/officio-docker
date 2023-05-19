<?php

use Cake\Database\Expression\QueryExpression;
use Clients\Service\Clients;
use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class ConvertFieldsCa extends AbstractMigration
{
    // Temp method as "fields types" table wasn't generated yet
    private function _getTextFieldType($intFieldId)
    {
        switch ($intFieldId) {
            case 1:
                $strType = 'text';
                break;

            case 2:
                $strType = 'password';
                break;

            case 3:
                $strType = 'combo';
                break;

            case 4:
                $strType = 'country';
                break;

            case 5:
                $strType = 'number';
                break;

            case 6:
                $strType = 'radio';
                break;

            case 7:
                $strType = 'checkbox';
                break;

            case 8:
                $strType = 'date';
                break;

            case 9:
                $strType = 'email';
                break;

            case 10:
                $strType = 'phone';
                break;

            case 11:
                $strType = 'memo';
                break;

            case 12:
                $strType = 'agents';
                break;

            case 13:
                $strType = 'office';
                break;

            case 14:
                $strType = 'assigned_to';
                break;

            case 15:
                $strType = 'date_repeatable';
                break;

            case 16:
                $strType = 'photo';
                break;

            case 30:
                $strType = 'categories';
                break;

            default:
                $strType = '';
                break;
        }

        return $strType;
    }


    public function up()
    {
        // 560s to create copies from the default one
        // Took 2077s on local server...
        try {
            $this->getAdapter()->commitTransaction();

            /** @var Clients $clients */
            $clients = self::getService(Clients::class);

            $defaultCompanyId = 0;

            $statement = $this->getQueryBuilder()
                ->select(array('company_id'))
                ->from('company')
                ->orderAsc('company_id')
                ->execute();

            $arrCompanyIds = array_column($statement->fetchAll('assoc'), 'company_id');


            // Rename duplicated field for the test company
            $this->execute("UPDATE `client_form_fields` SET `company_field_id`='Client_file_status1' WHERE `field_id`=142;");
            // Make sure that a specific field will have a correct text field id
            $this->execute("UPDATE `client_form_fields` SET `company_field_id`='archive_box_number' WHERE `company_field_id`='Archive_Box_Number' AND company_id = 1757;");

            // Change Client_file_status combo to closed_case_status
            $this->execute("UPDATE `client_form_fields` SET `label`='Closed Case Status', `company_field_id` = 'closed_case_status' WHERE `company_field_id`='Client_file_status';");

            // Collect grouped ids for this field for all companies
            $statement = $this->getQueryBuilder()
                ->select(['f.company_id', 'f.field_id'])
                ->from(['f' => 'client_form_fields'])
                ->where([
                    'f.company_field_id' => 'closed_case_status'
                ])
                ->execute();

            $arrSavedCaseStatusFields = $statement->fetchAll('assoc');

            $arrSavedCaseStatusFieldsGrouped = [];
            foreach ($arrSavedCaseStatusFields as $arrSavedCaseStatusFieldInfo) {
                $arrSavedCaseStatusFieldsGrouped[$arrSavedCaseStatusFieldInfo['company_id']] = $arrSavedCaseStatusFieldInfo['field_id'];
            }

            $arrNewFieldsValuesInsert      = [];
            $arrNewFieldsOrderInsert       = [];
            $arrNewFieldsAccessInsert      = [];
            $arrNewClosedCaseStatusOptions = [];
            foreach ($arrCompanyIds as $companyId) {
                // Change Client_file_status to 'Closed Case Status' field
                // Add a new Client_file_status field as checkbox
                // Place new checkbox to the same group as the combo is placed (also set access rights the same)
                // Set value to 'Active' for the checkbox for all cases that have 'Active' in the combo
                // Remove 'Active' from the combo
                // Add new options to the combo, reorder them

                if (!isset($arrSavedCaseStatusFieldsGrouped[$companyId])) {
                    throw new Exception('Closed Case Status field not found for the company #' . $companyId);
                }

                if ($arrSavedCaseStatusFieldsGrouped[$companyId] == 149199) {
                    // Exceptions - these 2 options already are created for this company
                    $this->getQueryBuilder()
                        ->update('client_form_default')
                        ->set(['value' => 'File Destroyed'])
                        ->where([
                            'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                            'value'    => 'Destroyed'
                        ])
                        ->execute();

                    $this->getQueryBuilder()
                        ->update('client_form_default')
                        ->set(['order' => 2])
                        ->where([
                            'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                            'value'    => 'Transferred'
                        ])
                        ->execute();
                } else {
                    // Archived
                    if ($arrSavedCaseStatusFieldsGrouped[$companyId] == 113) {
                        // Add new options for this field
                        $arrNewClosedCaseStatusOptions[] = [
                            'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                            'value'    => 'Archived',
                            'order'    => 1,
                        ];
                    }

                    // Add new options for this field
                    $arrNewClosedCaseStatusOptions[] = [
                        'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                        'value'    => 'Transferred',
                        'order'    => 2,
                    ];

                    $arrNewClosedCaseStatusOptions[] = [
                        'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                        'value'    => 'File Destroyed',
                        'order'    => 3,
                    ];

                    // Exceptions
                    if ($arrSavedCaseStatusFieldsGrouped[$companyId] == 65240) {
                        $this->getQueryBuilder()
                            ->update('client_form_default')
                            ->set(['order' => 4])
                            ->where([
                                'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                                'value'    => 'Complete (to be closed)'
                            ])
                            ->execute();

                        $this->getQueryBuilder()
                            ->update('client_form_default')
                            ->set(['order' => 5])
                            ->where([
                                'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                                'value'    => 'Cancelled (To Be Closed)'
                            ])
                            ->execute();

                        $this->getQueryBuilder()
                            ->update('client_form_default')
                            ->set(['order' => 6])
                            ->where([
                                'field_id' => $arrSavedCaseStatusFieldsGrouped[$companyId],
                                'value'    => 'Closed (Due to Cancellation)'
                            ])
                            ->execute();
                    }
                }

                // Create a new Client_file_status checkbox, set value to Active for all cases that have closed_case_status Active
                $arrCaseStatusInsert = [
                    'company_id'       => $companyId,
                    'company_field_id' => 'Client_file_status',
                    'type'             => 7,
                    'label'            => 'Active Case',
                ];

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrCaseStatusInsert))
                    ->into('client_form_fields')
                    ->values($arrCaseStatusInsert)
                    ->execute();

                $newFieldId = $statement->lastInsertId('client_form_fields');

                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from('client_form_data')
                    ->where([
                        'field_id' => (int)$arrSavedCaseStatusFieldsGrouped[$companyId],
                        'value'    => 'Active'
                    ])
                    ->execute();

                $arrActiveValues = $statement->fetchAll('assoc');

                foreach ($arrActiveValues as $arrActiveValue) {
                    $arrNewFieldsValuesInsert[] = [
                        'member_id' => $arrActiveValue['member_id'],
                        'field_id'  => $newFieldId,
                        'value'     => 'Active'
                    ];
                }


                // Place the new field into the same group as the old one
                $arrOldFieldOrder = $this->fetchRow("SELECT * FROM client_form_order WHERE field_id = " . $arrSavedCaseStatusFieldsGrouped[$companyId]);
                if (!empty($arrOldFieldOrder)) {
                    $arrNewFieldsOrderInsert[] = [
                        'group_id'    => $arrOldFieldOrder['group_id'],
                        'field_id'    => $newFieldId,
                        'field_order' => max($arrOldFieldOrder['field_order'] - 1, 0),
                    ];
                }

                // Set the same access rights to the new field as they were for the old one
                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from('client_form_field_access')
                    ->where(['field_id' => (int)$arrSavedCaseStatusFieldsGrouped[$companyId]])
                    ->execute();

                $arrOldFieldAccess = $statement->fetchAll('assoc');
                foreach ($arrOldFieldAccess as $arrOldFieldAccessInfo) {
                    $arrNewFieldsAccessInsert = [
                        'role_id'  => $arrOldFieldAccessInfo['role_id'],
                        'field_id' => $newFieldId,
                        'status'   => $arrOldFieldAccessInfo['status'],
                    ];
                }
            }


            // Delete 'Active' saved values + option form the 'closed_case_status' combo
            $this->execute("DELETE FROM `client_form_data` WHERE `value` = 'Active' AND `field_id` IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`='closed_case_status');");
            $this->execute("DELETE FROM `client_form_default` WHERE `value` = 'Active' AND `field_id` IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`='closed_case_status');");

            // Set all values at once
            if (!empty($arrNewFieldsValuesInsert)) {
                $this->table('client_form_data')
                    ->insert($arrNewFieldsValuesInsert)
                    ->save();
            }

            // Place new fields
            if (!empty($arrNewFieldsOrderInsert)) {
                $this->table('client_form_order')
                    ->insert($arrNewFieldsOrderInsert)
                    ->save();
            }

            // Set access to new fields
            if (!empty($arrNewFieldsAccessInsert)) {
                $this->table('client_form_field_access')
                    ->insert($arrNewFieldsAccessInsert)
                    ->save();
            }

            // Add new options to the closed_case_status combo
            if (!empty($arrNewClosedCaseStatusOptions)) {
                $this->table('client_form_default')
                    ->insert($arrNewClosedCaseStatusOptions)
                    ->save();
            }

            // Change order for specific options of the closed_case_status combo
            $this->execute("UPDATE `client_form_default` SET `order` = 0 WHERE `value` = 'Closed' AND `field_id` IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`='closed_case_status')");
            $this->execute("UPDATE `client_form_default` SET `order` = 1 WHERE `value` = 'Archived' AND `field_id` IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`='closed_case_status')");

            echo "Processed all case status fields" . PHP_EOL;


            $companiesSkippedCount   = 0;
            $companiesProcessedCount = 0;
            $companiesFailedCount    = 0;

            // Move such fields manually (exceptions)
            $arrExceptionsToMove = array(
                41   => array('country_of_residence', 'country_of_residence2', 'name_in_native_language', 'associations', 'contact_type', 'agreed_fee'),
                323  => array('o_c_phone'),
                743  => array('date_married', 'dependants_yn', 'marital_status'),
                786  => array('add_address2', 'add_address2_type', 'add_address3', 'add_address3_type', 'add_address4', 'add_address4_type', 'add_addresses', 'country_of_birth_selectbox', 'country_of_citizenship_selectbox', 'country_of_residence_selectbox', 'passport2_exp', 'passport2_issue', 'passport2_issue_selectbox', 'passport3_exp', 'passport3_issue', 'passport3_issue_selectbox', 'passport_2', 'passport_3', 'preferred_language_selectbox'),
                808  => array('dual_citizenship', 'expiration_of_status_in_canada', 'immigration_program', 'status_in_canada', 'status_in_country_of_residence'),
                837  => array('contact_label'),
                1757 => array('2nd_Phone_Office', 'Address_Office', 'Address_Residence', 'Always_Contact_Assistant', 'Assistant_2nd_Phone_Office', 'Assistant_Email', 'Assistant_Fax', 'Assistant_Mobile_Phone', 'Assistant_Name', 'Assistant_Phone_Office', 'Assistant_Phone_Residence', 'Assistant_Salutation', 'City_Office', 'City_Residence', 'Country_Office', 'Country_Residence', 'Phone_Office', 'Phone_Residence', 'Postal_Code_Office', 'Postal_Code_Residence', 'Preferred_Mailing_Address', 'Special_Contact_Instructions'),
            );

            foreach ($arrCompanyIds as $companyId) {
                if (empty($companyId)) {
                    continue;
                }

                echo PHP_EOL . str_repeat('*', 80) . PHP_EOL . "Company id: #$companyId" . PHP_EOL;

                if ($clients->getApplicantFields()->hasCompanyBlocks($companyId)) {
                    // Skip, if company has created blocks already -
                    // a simple check to be sure that no duplicates will be created
                    echo "Skipped" . PHP_EOL;
                    $companiesSkippedCount++;
                } else {
                    // Copy ALL applicant blocks/groups/fields + DON'T set access to them
                    $arrMappingClientGroupsAndFields = $clients->getApplicantFields()->createDefaultCompanyFieldsAndGroups(
                        $defaultCompanyId,
                        $companyId,
                        array()
                    );

                    if (!$arrMappingClientGroupsAndFields['success']) {
                        throw new Exception('Fields/groups were not created');
                    }

                    // Move such fields manually (exceptions)
                    $arrApplicantFieldsAccessSet = [];
                    foreach ($arrExceptionsToMove as $exceptionCompanyId => $arrExceptionFields) {
                        if ($exceptionCompanyId != $companyId) {
                            continue;
                        }

                        // Get the list of field we want to move
                        $statement = $this->getQueryBuilder()
                            ->select('*')
                            ->from('client_form_fields')
                            ->where([
                                'company_id'           => (int)$companyId,
                                'company_field_id IN ' => $arrExceptionFields
                            ])
                            ->execute();

                        $arrFieldsToMove = $statement->fetchAll('assoc');


                        // Get the group id we want to place all these fields to
                        $statement = $this->getQueryBuilder()
                            ->select(['applicant_group_id'])
                            ->from('applicant_form_groups')
                            ->where([
                                'company_id' => (int)$companyId,
                                'title'      => 'Personal Information'
                            ])
                            ->execute();

                        $applicantGroupId = false;

                        $row = $statement->fetch();
                        if (!empty($row)) {
                            $applicantGroupId = $row[array_key_first($row)];
                        }

                        // Get the max order, so placed fields will be at the bottom of the list
                        $statement = $this->getQueryBuilder()
                            ->select([new QueryExpression('MAX(field_order)')])
                            ->from('applicant_form_order')
                            ->where(['applicant_group_id' => (int)$applicantGroupId])
                            ->execute();

                        $maxOrder = 0;
                        $row      = $statement->fetch();
                        if (!empty($row)) {
                            $maxOrder = (int)$row[array_key_first($row)];
                        }

                        foreach ($arrFieldsToMove as $arrFieldToMoveInfo) {
                            $arrInsert = [
                                'member_type_id'            => 9, // Internal contact
                                'company_id'                => $companyId,
                                'applicant_field_unique_id' => $arrFieldToMoveInfo['company_field_id'],
                                'type'                      => $this->_getTextFieldType($arrFieldToMoveInfo['type']),
                                'label'                     => $arrFieldToMoveInfo['label'],
                                'maxlength'                 => $arrFieldToMoveInfo['maxlength'],
                                'encrypted'                 => $arrFieldToMoveInfo['encrypted'],
                                'required'                  => $arrFieldToMoveInfo['required'],
                                'disabled'                  => $arrFieldToMoveInfo['disabled'],
                                'blocked'                   => $arrFieldToMoveInfo['blocked'],
                            ];

                            $statement = $this->getQueryBuilder()
                                ->insert(array_keys($arrInsert))
                                ->into('applicant_form_fields')
                                ->values($arrInsert)
                                ->execute();

                            $newApplicantFieldId = $statement->lastInsertId('applicant_form_fields');

                            // Copy options if this is a combo/radio
                            if (in_array($this->_getTextFieldType($arrFieldToMoveInfo['type']), array('combo', 'radio'))) {
                                $statement = $this->getQueryBuilder()
                                    ->select('*')
                                    ->from('client_form_default')
                                    ->where(['field_id' => $arrFieldToMoveInfo['field_id']])
                                    ->execute();

                                $arrDefaultValues = $statement->fetchAll('assoc');

                                if (!empty($arrDefaultValues)) {
                                    foreach ($arrDefaultValues as $defaultOption) {
                                        $this->getQueryBuilder()
                                            ->insert([
                                                'applicant_field_id',
                                                'value',
                                                'order'
                                            ])
                                            ->into('applicant_form_default')
                                            ->values(
                                                [
                                                    'applicant_field_id' => $newApplicantFieldId,
                                                    'value'              => $defaultOption['value'],
                                                    'order'              => $defaultOption['order']
                                                ]
                                            )
                                            ->execute();
                                    }
                                }
                            }

                            // Place this field to the correct group
                            $this->getQueryBuilder()
                                ->insert([
                                    'applicant_group_id',
                                    'applicant_field_id',
                                    'use_full_row',
                                    'field_order'
                                ])
                                ->into('applicant_form_order')
                                ->values(
                                    [
                                        'applicant_group_id' => $applicantGroupId,
                                        'applicant_field_id' => $newApplicantFieldId,
                                        'use_full_row'       => 'N',
                                        'field_order'        => ++$maxOrder
                                    ]
                                )
                                ->execute();

                            // Set the same access rights
                            $statement = $this->getQueryBuilder()
                                ->select('*')
                                ->from('client_form_field_access')
                                ->where(['field_id' => $arrFieldToMoveInfo['field_id']])
                                ->execute();

                            $arrDefaultAccessRights = $statement->fetchAll('assoc');

                            foreach ($arrDefaultAccessRights as $arrDefaultAccessRightsInfo) {
                                if (!isset($arrApplicantFieldsAccessSet[$arrDefaultAccessRightsInfo['role_id'] . '_' . $applicantGroupId . '_' . $newApplicantFieldId])) {
                                    $this->getQueryBuilder()
                                        ->insert(
                                            [
                                                'role_id',
                                                'applicant_group_id',
                                                'applicant_field_id',
                                                'status',
                                            ]
                                        )
                                        ->into('applicant_form_fields_access')
                                        ->values(
                                            [
                                                'role_id'            => $arrDefaultAccessRightsInfo['role_id'],
                                                'applicant_group_id' => $applicantGroupId,
                                                'applicant_field_id' => $newApplicantFieldId,
                                                'status'             => $arrDefaultAccessRightsInfo['status'],
                                            ]
                                        )
                                        ->execute();

                                    $arrApplicantFieldsAccessSet[$arrDefaultAccessRightsInfo['role_id'] . '_' . $applicantGroupId . '_' . $newApplicantFieldId] = 1;
                                }
                            }
                        }
                    }

                    // Rename back to the company's used field id
                    $arrToFix = array(
                        'address_3'           => array('Address_3', 'address3'),
                        'passport_issue_date' => array('passport_date_of_issue'),
                        'photo'               => array('picture', 'photo1_field', 'Photo'),
                        'UCI'                 => array('uci_main_applicant', 'uci_number', 'uci_file_number', 'uci', 'file_number_uci', 'client_id'),
                    );

                    foreach ($arrToFix as $newFieldName => $arrOldFieldNames) {
                        $statement = $this->getQueryBuilder()
                            ->select('*')
                            ->from(['f' => 'client_form_fields'])
                            ->where([
                                'company_id'                     => (int)$companyId,
                                'BINARY(f.company_field_id) IN ' => $arrOldFieldNames
                            ])
                            ->execute();

                        $arrFieldsToFix = $statement->fetchAll('assoc');

                        foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                            $this->getQueryBuilder()
                                ->update('applicant_form_fields')
                                ->set(['applicant_field_unique_id' => $arrFieldInfoToFix['company_field_id']])
                                ->where([
                                    'company_id'                => $arrFieldInfoToFix['company_id'],
                                    'applicant_field_unique_id' => $newFieldName
                                ])
                                ->execute();
                        }
                    }

                    $statement = $this->getQueryBuilder()
                        ->select('fg.applicant_group_id')
                        ->from(['fb' => 'applicant_form_blocks'])
                        ->innerJoin(['fg' => 'applicant_form_groups'], ['fg.applicant_block_id = fb.applicant_block_id'])
                        ->where([
                            'fb.member_type_id' => 8,
                            'fb.company_id'     => (int)$companyId
                        ])
                        ->execute();

                    $arrCompanyIAGroups = array_column($statement->fetchAll('assoc'), 'applicant_group_id');

                    if (!empty($arrCompanyIAGroups)) {
                        // Such fields must be removed from the IA GUI, if they are not in the V1
                        $arrFieldsToRemove = array('address_3', 'email_3', 'fax_w', 'fax_o');
                        foreach ($arrFieldsToRemove as $fieldIdToRemove) {
                            $caseFieldIdToCheck = $fieldIdToRemove;
                            if ($companyId == 670 && $caseFieldIdToCheck == 'address_3') {
                                $caseFieldIdToCheck = 'address3';
                            }

                            $statement = $this->getQueryBuilder()
                                ->select(['field_id'])
                                ->from('client_form_fields')
                                ->where([
                                    'company_field_id' => $caseFieldIdToCheck,
                                    'company_id'       => (int)$companyId
                                ])
                                ->execute();

                            $caseFieldId = 0;

                            $row = $statement->fetch();
                            if (!empty($row)) {
                                $caseFieldId = $row[array_key_first($row)];
                            }

                            $statement = $this->getQueryBuilder()
                                ->select(['applicant_field_id'])
                                ->from('applicant_form_fields')
                                ->where([
                                    'applicant_field_unique_id' => $fieldIdToRemove,
                                    'company_id'                => (int)$companyId
                                ])
                                ->execute();

                            $applicantFieldIdToDelete = 0;

                            $row = $statement->fetch();
                            if (!empty($row)) {
                                $applicantFieldIdToDelete = $row[array_key_first($row)];
                            }

                            if (empty($caseFieldId) && !empty($applicantFieldIdToDelete)) {
                                $this->getQueryBuilder()
                                    ->delete('applicant_form_order')
                                    ->where([
                                        'applicant_group_id IN' => $arrCompanyIAGroups,
                                        'applicant_field_id'    => (int)$applicantFieldIdToDelete
                                    ])
                                    ->execute();

                                $this->getQueryBuilder()
                                    ->delete('applicant_form_fields_access')
                                    ->where([
                                        'applicant_group_id IN' => $arrCompanyIAGroups,
                                        'applicant_field_id'    => (int)$applicantFieldIdToDelete
                                    ])
                                    ->execute();
                            }
                        }

                        // Such fields must be removed from the IA GUI for specific companies
                        $arrFieldsToRemove = array(
                            41 => array('country_of_residence', 'name_in_native_lang')
                        );

                        foreach ($arrFieldsToRemove as $companyIdToCheck => $arrCompanyFieldsToRemove) {
                            if ($companyIdToCheck != $companyId) {
                                continue;
                            }

                            $statement = $this->getQueryBuilder()
                                ->select(['applicant_field_id'])
                                ->from('applicant_form_fields')
                                ->where([
                                    'applicant_field_unique_id IN' => $arrCompanyFieldsToRemove,
                                    'company_id'                   => (int)$companyId
                                ])
                                ->execute();

                            $applicantFieldIdToDelete = $statement->fetchAll('assoc');

                            if (!empty($applicantFieldIdToDelete)) {
                                $this->getQueryBuilder()
                                    ->delete('applicant_form_order')
                                    ->where([
                                        'applicant_group_id IN' => $arrCompanyIAGroups,
                                        'applicant_field_id'    => (int)$applicantFieldIdToDelete[0]['applicant_field_id']
                                    ])
                                    ->execute();

                                $this->getQueryBuilder()
                                    ->delete('applicant_form_fields_access')
                                    ->where([
                                        'applicant_group_id IN' => $arrCompanyIAGroups,
                                        'applicant_field_id'    => (int)$applicantFieldIdToDelete[0]['applicant_field_id']
                                    ])
                                    ->execute();
                            }
                        }
                    }

                    // Set access to the same fields as they are in the DB
                    $statement = $this->getQueryBuilder()
                        ->select(['role_id', 'role_name'])
                        ->from('acl_roles')
                        ->where(['company_id' => (int)$companyId])
                        ->execute();

                    $arrThisCompanyRoles = $statement->fetchAll('assoc');

                    $arrRolesIds = array_column($arrThisCompanyRoles, 'role_id');

                    $statement = $this->getQueryBuilder()
                        ->select(['fa.*', 'f.company_field_id'])
                        ->from(['fa' => 'client_form_field_access'])
                        ->leftJoin(['f' => 'client_form_fields'], ['f.field_id = fa.field_id'])
                        ->leftJoin(['r' => 'acl_roles'], ['r.role_id = fa.role_id'])
                        ->where([
                            'r.role_type NOT IN ' => ['individual_client', 'employer_client'],
                            'fa.role_id IN '      => $arrRolesIds
                        ])
                        ->execute();

                    $arrCompanyFieldsAccess = $statement->fetchAll('assoc');

                    $arrApplicantFieldsAccess = [];
                    if (!empty($arrCompanyIAGroups)) {
                        foreach ($arrCompanyFieldsAccess as $arrCompanyFieldAccessInfo) {
                            $statement = $this->getQueryBuilder()
                                ->select(['fo.applicant_group_id', 'fo.applicant_field_id'])
                                ->from(['fo' => 'applicant_form_order'])
                                ->leftJoin(['f' => 'applicant_form_fields'], ['f.applicant_field_id = fo.applicant_field_id'])
                                ->where([
                                    'f.company_id'                => (int)$companyId,
                                    'fo.applicant_group_id IN'    => $arrCompanyIAGroups,
                                    'f.applicant_field_unique_id' => $arrCompanyFieldAccessInfo['company_field_id']
                                ])
                                ->execute();

                            $arrFieldGroups = $statement->fetchAll('assoc');

                            foreach ($arrFieldGroups as $arrFieldGroupInfo) {
                                if (!isset($arrApplicantFieldsAccessSet[$arrCompanyFieldAccessInfo['role_id'] . '_' . $arrFieldGroupInfo['applicant_group_id'] . '_' . $arrFieldGroupInfo['applicant_field_id']])) {
                                    $arrApplicantFieldsAccess[] = sprintf(
                                        "(%d, %d, %d, '%s')",
                                        $arrCompanyFieldAccessInfo['role_id'],
                                        $arrFieldGroupInfo['applicant_group_id'],
                                        $arrFieldGroupInfo['applicant_field_id'],
                                        $arrCompanyFieldAccessInfo['status']
                                    );

                                    $arrApplicantFieldsAccessSet[$arrCompanyFieldAccessInfo['role_id'] . '_' . $arrFieldGroupInfo['applicant_group_id'] . '_' . $arrFieldGroupInfo['applicant_field_id']] = 1;
                                }
                            }
                        }

                        // Insert all at once
                        if (!empty($arrApplicantFieldsAccess)) {
                            $this->query(sprintf('INSERT IGNORE INTO applicant_form_fields_access (`role_id`, `applicant_group_id`, `applicant_field_id`, `status`) VALUES %s', implode(',', $arrApplicantFieldsAccess)));
                            $arrApplicantFieldsAccess = array();
                        }


                        $statement = $this->getQueryBuilder()
                            ->select(['fo.applicant_group_id', 'fo.applicant_field_id', 'f.applicant_field_unique_id'])
                            ->from(['fo' => 'applicant_form_order'])
                            ->leftJoin(['f' => 'applicant_form_fields'], ['f.applicant_field_id = fo.applicant_field_id'])
                            ->where([
                                'f.company_id'                   => (int)$companyId,
                                'fo.applicant_group_id IN'       => $arrCompanyIAGroups,
                                'f.applicant_field_unique_id IN' => ['UCI', 'passport_date_of_issue', 'passport_country_of_issue', 'city_of_birth', 'linkedin', 'skype', 'whatsapp', 'wechat', 'disable_login']
                            ])
                            ->execute();

                        $arrFieldGroups = $statement->fetchAll('assoc');

                        foreach ($arrFieldGroups as $arrFieldGroupInfo) {
                            switch ($arrFieldGroupInfo['applicant_field_unique_id']) {
                                case 'UCI':
                                    // Don't do this for these companies,
                                    // because these companies have other named UCI fields (so we'll rename them below)
                                    if (in_array($companyId, array(190, 1362, 481, 727, 1588, 628, 1410, 1252, 1337))) {
                                        $mainField = '';
                                    } else {
                                        $mainField = 'passport_number';
                                    }
                                    break;

                                case 'passport_date_of_issue':
                                case 'passport_country_of_issue':
                                    $mainField = 'passport_number';
                                    break;

                                case 'city_of_birth':
                                    $mainField = 'DOB';
                                    break;

                                case 'linkedin':
                                case 'skype':
                                case 'whatsapp':
                                case'wechat':
                                    $mainField = 'email';
                                    break;

                                case'disable_login':
                                    $mainField = 'password';
                                    break;

                                default:
                                    $mainField = '';
                                    break;
                            }

                            if (!empty($mainField)) {
                                $statement = $this->getQueryBuilder()
                                    ->select('*')
                                    ->from(['fa' => 'applicant_form_fields_access'])
                                    ->leftJoin(['f' => 'applicant_form_fields'], ['f.applicant_field_id = fa.applicant_field_id'])
                                    ->leftJoin(['r' => 'acl_roles'], ['r.role_id = fa.role_id'])
                                    ->where([
                                        'r.role_type NOT IN '         => ['individual_client', 'employer_client'],
                                        'f.company_id'                => (int)$companyId,
                                        'f.applicant_field_unique_id' => $mainField,
                                        'fa.applicant_group_id'       => (int)$arrFieldGroupInfo['applicant_group_id']
                                    ])
                                    ->execute();

                                $arrFieldCorrectAccess = $statement->fetchAll('assoc');

                                foreach ($arrFieldCorrectAccess as $arrFieldCorrectAccessInfo) {
                                    if (!isset($arrApplicantFieldsAccessSet[$arrFieldCorrectAccessInfo['role_id'] . '_' . $arrFieldGroupInfo['applicant_group_id'] . '_' . $arrFieldGroupInfo['applicant_field_id']])) {
                                        $arrApplicantFieldsAccess[] = sprintf(
                                            "(%d, %d, %d, '%s')",
                                            $arrFieldCorrectAccessInfo['role_id'],
                                            $arrFieldGroupInfo['applicant_group_id'],
                                            $arrFieldGroupInfo['applicant_field_id'],
                                            $arrFieldCorrectAccessInfo['status']
                                        );

                                        $arrApplicantFieldsAccessSet[$arrFieldCorrectAccessInfo['role_id'] . '_' . $arrFieldGroupInfo['applicant_group_id'] . '_' . $arrFieldGroupInfo['applicant_field_id']] = 1;
                                    }
                                }
                            }
                        }
                    }

                    // Insert all at once
                    if (!empty($arrApplicantFieldsAccess)) {
                        $this->query(sprintf('INSERT IGNORE INTO applicant_form_fields_access (`role_id`, `applicant_group_id`, `applicant_field_id`, `status`) VALUES %s', implode(',', $arrApplicantFieldsAccess)));
                        $arrApplicantFieldsAccess = [];
                    }


                    // For IA and Employer fields:
                    // * if this is a client role
                    // * recognized role
                    // * not recognized role - apply the same access rights as it is set for the IA "last name" field
                    $statement = $this->getQueryBuilder()
                        ->select('fg.applicant_group_id')
                        ->from(['fb' => 'applicant_form_blocks'])
                        ->innerJoin(['fg' => 'applicant_form_groups'], ['fg.applicant_block_id = fb.applicant_block_id'])
                        ->where([
                            'fb.member_type_id IN ' => [7, 8],
                            'fb.company_id'         => (int)$companyId
                        ])
                        ->execute();

                    $arrCompanyEmployerGroups = array_column($statement->fetchAll('assoc'), 'applicant_group_id');

                    if (!empty($arrCompanyEmployerGroups)) {
                        $statement = $this->getQueryBuilder()
                            ->select(['fo.applicant_group_id', 'fo.applicant_field_id', 'f.applicant_field_unique_id', 'g.title'])
                            ->from(['fo' => 'applicant_form_order'])
                            ->innerJoin(['g' => 'applicant_form_groups'], ['g.applicant_group_id = fo.applicant_group_id'])
                            ->innerJoin(['f' => 'applicant_form_fields'], ['f.applicant_field_id = fo.applicant_field_id'])
                            ->where(['fo.applicant_group_id IN' => $arrCompanyEmployerGroups])
                            ->execute();

                        $arrEmployerFieldGroups = $statement->fetchAll('assoc');

                        $statement = $this->getQueryBuilder()
                            ->select(['role_id', 'status'])
                            ->from(['fa' => 'client_form_field_access'])
                            ->innerJoin(['f' => 'client_form_fields'], ['f.field_id = fa.field_id'])
                            ->where([
                                'f.company_id'       => (int)$companyId,
                                'f.company_field_id' => 'last_name'
                            ])
                            ->execute();

                        $arrFieldCorrectAccess = $statement->fetchAll('assoc');

                        $arrFieldCorrectAccessGrouped = [];
                        foreach ($arrFieldCorrectAccess as $arrFieldCorrectAccessRow) {
                            $arrFieldCorrectAccessGrouped[$arrFieldCorrectAccessRow['role_id']] = $arrFieldCorrectAccessRow['status'];
                        }

                        foreach ($arrEmployerFieldGroups as $arrEmployerFieldGroupInfo) {
                            foreach ($arrThisCompanyRoles as $arrThisCompanyRoleInfo) {
                                $roleId   = $arrThisCompanyRoleInfo['role_id'];
                                $roleName = $arrThisCompanyRoleInfo['role_name'];

                                $access = '';
                                switch ($roleName) {
                                    case 'Admin':
                                    case 'Processing':
                                    case 'Accounting':
                                        $access = 'F';
                                        break;

                                    case 'Agent':
                                        if (!in_array($arrEmployerFieldGroupInfo['title'], ['Client Login Information', 'Employer Login'])) {
                                            $access = 'F';
                                        }
                                        break;

                                    case 'Individual Client':
                                    case 'Employer Client':
                                    case 'Client':
                                        if (!in_array($arrEmployerFieldGroupInfo['title'], ['Client Login Information', 'Employer Login'])) {
                                            $arrExceptions = [];
                                            if ($roleName == 'Employer Client') {
                                                $arrExceptions = ['DOB', 'city_of_birth', 'country_of_birth', 'country_of_residence', 'country_of_citizenship', 'passport_issue_date', 'passport_date_of_issue', 'passport_expiry_date', 'UCI', 'uci_main_applicant', 'uci_number', 'uci_file_number', 'uci', 'file_number_uci', 'client_id'];
                                            } elseif ($roleName == 'Individual Client') {
                                                $arrExceptions = ['photo', 'picture', 'photo1_field', 'Photo', 'special_instruction'];
                                            }

                                            if (!in_array($arrEmployerFieldGroupInfo['applicant_field_unique_id'], $arrExceptions)) {
                                                $access = 'R';
                                            }
                                        }
                                        break;

                                    default:
                                        // Use the same access as it is for the last name field
                                        if (isset($arrFieldCorrectAccessGrouped[$roleId])) {
                                            $access = $arrFieldCorrectAccessGrouped[$roleId];
                                        }
                                        break;
                                }

                                if (!empty($access) && !isset($arrApplicantFieldsAccessSet[$roleId . '_' . $arrEmployerFieldGroupInfo['applicant_group_id'] . '_' . $arrEmployerFieldGroupInfo['applicant_field_id']])) {
                                    $arrApplicantFieldsAccess[] = sprintf(
                                        "(%d, %d, %d, '%s')",
                                        $roleId,
                                        $arrEmployerFieldGroupInfo['applicant_group_id'],
                                        $arrEmployerFieldGroupInfo['applicant_field_id'],
                                        $access
                                    );

                                    $arrApplicantFieldsAccessSet[$roleId . '_' . $arrEmployerFieldGroupInfo['applicant_group_id'] . '_' . $arrEmployerFieldGroupInfo['applicant_field_id']] = 1;
                                }
                            }
                        }
                    }

                    // Insert all at once
                    if (!empty($arrApplicantFieldsAccess)) {
                        $this->query(sprintf('INSERT IGNORE INTO applicant_form_fields_access (`role_id`, `applicant_group_id`, `applicant_field_id`, `status`) VALUES %s', implode(',', $arrApplicantFieldsAccess)));
                        $arrApplicantFieldsAccess = [];
                    }


                    // For Contact fields - set full access to ALL roles that have access to the "Agents" tab
                    $statement = $this->getQueryBuilder()
                        ->select(['fg.applicant_group_id'])
                        ->from(['fb' => 'applicant_form_blocks'])
                        ->innerJoin(['fg' => 'applicant_form_groups'], ['fg.applicant_block_id = fb.applicant_block_id'])
                        ->where([
                            'fb.member_type_id' => 10,
                            'fb.company_id'     => (int)$companyId
                        ])
                        ->execute();

                    $arrCompanyContactGroups = array_column($statement->fetchAll('assoc'), 'applicant_group_id');

                    if (!empty($arrCompanyContactGroups)) {
                        $statement = $this->getQueryBuilder()
                            ->select(['applicant_group_id', 'applicant_field_id'])
                            ->from('applicant_form_order')
                            ->where(['applicant_group_id IN' => $arrCompanyContactGroups])
                            ->execute();

                        $arrContactFieldsGroups = $statement->fetchAll('assoc');

                        $statement = $this->getQueryBuilder()
                            ->select(['r.role_id'])
                            ->from(['a' => 'acl_role_access'])
                            ->innerJoin(['r' => 'acl_roles'], ['a.role_id = r.role_parent_id'])
                            ->where([
                                'r.company_id' => (int)$companyId,
                                'a.rule_id'    => 400 // agents-view now contacts-view (Contacts tab)
                            ])
                            ->group('r.role_id')
                            ->execute();

                        $arrCompanyRoles = array_column($statement->fetchAll('assoc'), 'role_id');

                        if (!empty($arrContactFieldsGroups) && !empty($arrCompanyRoles)) {
                            foreach ($arrCompanyRoles as $roleId) {
                                foreach ($arrContactFieldsGroups as $arrContactFieldsGroupsInfo) {
                                    if (!isset($arrApplicantFieldsAccessSet[$roleId . '_' . $arrContactFieldsGroupsInfo['applicant_group_id'] . '_' . $arrContactFieldsGroupsInfo['applicant_field_id']])) {
                                        $arrApplicantFieldsAccess[] = sprintf(
                                            "(%d, %d, %d, '%s')",
                                            $roleId,
                                            $arrContactFieldsGroupsInfo['applicant_group_id'],
                                            $arrContactFieldsGroupsInfo['applicant_field_id'],
                                            'F'
                                        );

                                        $arrApplicantFieldsAccessSet[$roleId . '_' . $arrContactFieldsGroupsInfo['applicant_group_id'] . '_' . $arrContactFieldsGroupsInfo['applicant_field_id']] = 1;
                                    }
                                }
                            }
                        }

                        // Insert all at once
                        if (!empty($arrApplicantFieldsAccess)) {
                            $this->query(sprintf('INSERT IGNORE INTO applicant_form_fields_access (`role_id`, `applicant_group_id`, `applicant_field_id`, `status`) VALUES %s', implode(',', $arrApplicantFieldsAccess)));
                        }
                    }

                    echo "Fields/groups were successfully created" . PHP_EOL;
                    $companiesProcessedCount++;
                }

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');

                echo str_repeat('*', 80) . PHP_EOL;
            }

            echo sprintf(
                str_repeat('*', 80) . PHP_EOL .
                '%d successfully, ' . PHP_EOL .
                '%d skipped, ' . PHP_EOL .
                '%d failed' . PHP_EOL .
                str_repeat('*', 80) . PHP_EOL,
                $companiesProcessedCount,
                $companiesSkippedCount,
                $companiesFailedCount
            );


            // ***************************************************************************
            // Fix specific fields for specific companies
            // Rename the field if it was already saved in DB
            // Also change the type of the field if it is different from what we created
            // ***************************************************************************


            // Load options for ALL case/client fields
            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('client_form_default')
                ->execute();

            $arrFieldsDefaultOptions = $statement->fetchAll('assoc');

            $arrCaseDefaultOptions = array();
            foreach ($arrFieldsDefaultOptions as $arrFieldsDefaultOptionInfo) {
                $arrCaseDefaultOptions[$arrFieldsDefaultOptionInfo['field_id']][] = $arrFieldsDefaultOptionInfo['value'];
            }

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('applicant_form_default')
                ->execute();

            $arrApplicantFieldsDefaultOptions = $statement->fetchAll('assoc');

            $arrApplicantDefaultOptions = array();
            foreach ($arrApplicantFieldsDefaultOptions as $arrApplicantFieldsDefaultOptionInfo) {
                $arrApplicantDefaultOptions[$arrApplicantFieldsDefaultOptionInfo['applicant_field_id']][] = $arrApplicantFieldsDefaultOptionInfo['value'];
            }

            $oFieldTypes = $clients->getFieldTypes();
            $arrChanges  = array();
            foreach ($arrCompanyIds as $companyId) {
                if (empty($companyId)) {
                    continue;
                }

                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from('applicant_form_fields')
                    ->where(['company_id' => (int)$companyId])
                    ->execute();

                $arrCompanyApplicantFields = $statement->fetchAll('assoc');

                $arrCompanyApplicantFieldIds      = array();
                $arrCompanyApplicantFieldsGrouped = array();
                foreach ($arrCompanyApplicantFields as $arrCompanyApplicantFieldInfo) {
                    $arrCompanyApplicantFieldIds[] = $arrCompanyApplicantFieldInfo['applicant_field_unique_id'];

                    $arrCompanyApplicantFieldsGrouped[$arrCompanyApplicantFieldInfo['applicant_field_unique_id']] = $arrCompanyApplicantFieldInfo;
                }

                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from('client_form_fields')
                    ->where([
                        'company_id'          => (int)$companyId,
                        'company_field_id IN' => $arrCompanyApplicantFieldIds
                    ])
                    ->execute();

                $arrCompanyCaseFields = $statement->fetchAll('assoc');


                foreach ($arrCompanyCaseFields as $arrCompanyCaseFieldInfo) {
                    if (isset($arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']])) {
                        $arrFieldChanges = array();

                        $booUpdateType = false;
                        if ($oFieldTypes->getFieldTypeId($arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['type']) != $arrCompanyCaseFieldInfo['type'] && $arrCompanyCaseFieldInfo['company_field_id'] != 'office') {
                            $booUpdateType = true;
                        }

                        if (!$booUpdateType && $arrCompanyCaseFieldInfo['type'] == '3') {
                            if (!isset($arrCaseDefaultOptions[$arrCompanyCaseFieldInfo['field_id']]) || !is_array($arrCaseDefaultOptions[$arrCompanyCaseFieldInfo['field_id']])) {
                                $arrCaseDefaultOptions[$arrCompanyCaseFieldInfo['field_id']] = array();
                            }

                            $arrIntersect = array_intersect($arrCaseDefaultOptions[$arrCompanyCaseFieldInfo['field_id']], $arrApplicantDefaultOptions[$arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['applicant_field_id']]);
                            if ($arrIntersect != $arrCaseDefaultOptions[$arrCompanyCaseFieldInfo['field_id']] || empty($arrCaseDefaultOptions[$arrCompanyCaseFieldInfo['field_id']])) {
                                $booUpdateType = true;
                            }
                        }

                        if ($booUpdateType) {
                            $arrFieldChanges['type']['case']   = $this->_getTextFieldType($arrCompanyCaseFieldInfo['type']);
                            $arrFieldChanges['type']['client'] = $arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['type'];
                        }

                        if ($arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['label'] != $arrCompanyCaseFieldInfo['label']) {
                            $arrFieldChanges['label']['case']   = $arrCompanyCaseFieldInfo['label'];
                            $arrFieldChanges['label']['client'] = $arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['label'];
                        }

                        if ($arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['required'] != $arrCompanyCaseFieldInfo['required']) {
                            $arrFieldChanges['required']['case']   = $arrCompanyCaseFieldInfo['required'];
                            $arrFieldChanges['required']['client'] = $arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['required'];
                        }

                        if ($arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['disabled'] != $arrCompanyCaseFieldInfo['disabled']) {
                            $arrFieldChanges['disabled']['case']   = $arrCompanyCaseFieldInfo['disabled'];
                            $arrFieldChanges['disabled']['client'] = $arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['disabled'];
                        }

                        // if ($arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['blocked'] != $arrCompanyCaseFieldInfo['blocked']) {
                        //     $arrFieldChanges['blocked']['case']   = $arrCompanyCaseFieldInfo['blocked'];
                        //     $arrFieldChanges['blocked']['client'] = $arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['blocked'];
                        // }

                        if (!empty($arrFieldChanges)) {
                            $arrFieldChanges['text_id']       = $arrCompanyCaseFieldInfo['company_field_id'];
                            $arrFieldChanges['case_field_id'] = $arrCompanyCaseFieldInfo['field_id'];

                            $arrChanges[$companyId][$arrCompanyApplicantFieldsGrouped[$arrCompanyCaseFieldInfo['company_field_id']]['applicant_field_id']] = $arrFieldChanges;
                        }
                    }
                }
            }

            $arrApplicantDefaultOptions   = [];
            $arrApplicantFieldsIdsToClear = [];
            foreach ($arrChanges as $arrFieldsToChange) {
                foreach ($arrFieldsToChange as $applicantFieldId => $arrChanges) {
                    $arrRealChanges = array();

                    if (isset($arrChanges['type'])) {
                        $arrRealChanges['type'] = $arrChanges['type']['case'];

                        switch ($arrChanges['type']['case']) {
                            case 'text':
                                $arrApplicantFieldsIdsToClear[] = (int)$applicantFieldId;
                                break;

                            case 'combo':
                                $statement = $this->getQueryBuilder()
                                    ->select('*')
                                    ->from('client_form_default')
                                    ->where(['field_id' => $arrChanges['case_field_id']])
                                    ->execute();

                                $arrDefaultValues = $statement->fetchAll('assoc');

                                $arrApplicantFieldsIdsToClear[] = (int)$applicantFieldId;

                                if (!empty($arrDefaultValues)) {
                                    foreach ($arrDefaultValues as $defaultOption) {
                                        $arrApplicantDefaultOptions[] = [
                                            'applicant_field_id' => $applicantFieldId,
                                            'value'              => $defaultOption['value'],
                                            'order'              => $defaultOption['order']
                                        ];
                                    }
                                }
                                break;

                            default:
                                break;
                        }
                    }

                    if (isset($arrChanges['label'])) {
                        $arrRealChanges['label'] = $arrChanges['label']['case'];
                    }

                    if (isset($arrChanges['required'])) {
                        $arrRealChanges['required'] = $arrChanges['required']['case'];
                    }

                    if (isset($arrChanges['disabled'])) {
                        $arrRealChanges['disabled'] = $arrChanges['disabled']['case'];
                    }

                    if (isset($arrChanges['blocked'])) {
                        $arrRealChanges['blocked'] = $arrChanges['blocked']['case'];
                    }

                    if (!empty($arrRealChanges)) {
                        $this->getQueryBuilder()
                            ->update('applicant_form_fields')
                            ->set($arrRealChanges)
                            ->where(['applicant_field_id' => $applicantFieldId])
                            ->execute();
                    }
                }
            }

            if (!empty($arrApplicantFieldsIdsToClear)) {
                $this->getQueryBuilder()
                    ->delete('applicant_form_default')
                    ->where(['applicant_field_id IN ' => $arrApplicantFieldsIdsToClear])
                    ->execute();
            }


            if (!empty($arrApplicantDefaultOptions)) {
                $this->table('applicant_form_default')
                    ->insert($arrApplicantDefaultOptions)
                    ->save();
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
