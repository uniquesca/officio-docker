<?php

use Cake\Database\Expression\QueryExpression;
use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Service\Company;
use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class ConvertClientsCa extends AbstractMigration
{
    private $_cachedOptions = array();

    public function up()
    {
        // Took 750s for 15 000 clients (total 323 500) on local server...
        // Took 18 000 s (5 hours) for 100 000 clients (total 368 000) on local server...
        $msg = '';

        try {
            $this->getAdapter()->commitTransaction();

            $start = microtime(true);
            echo 'Start: ' . date('c') . PHP_EOL;

            $processMembersAtOnce = 1000000;

            /** @var Clients $oClients */
            $oClients = self::getService(Clients::class);

            /** @var Company $oCompany */
            $oCompany = self::getService(Company::class);

            /** @var Files $oFiles */
            $oFiles = self::getService(Files::class);

            $statement = $this->getQueryBuilder()
                ->select('m.*')
                ->from(['m' => 'members'])
                ->leftJoin(['mr' => 'members_relations'], ['m.member_id = mr.child_member_id'])
                ->where(function (QueryExpression $exp) {
                    return $exp
                        ->in('m.userType', array(3))
                        ->isNull('mr.parent_member_id');
                })
                ->order(['m.company_id ASC', 'm.member_id ASC'])
                ->limit($processMembersAtOnce)
                ->execute();

            $arrSavedCases = $statement->fetchAll('assoc');

            $statement = $this->getQueryBuilder()
                ->select('COUNT(*)')
                ->from(['m' => 'members'])
                ->leftJoin(['mr' => 'members_relations'], ['m.member_id = mr.child_member_id'])
                ->where(function (QueryExpression $exp) {
                    return $exp
                        ->in('m.userType', array(3))
                        ->isNull('mr.parent_member_id');
                })
                ->execute();

            $casesCount = 0;

            $row = $statement->fetch();
            if (!empty($row)) {
                $casesCount = $row[array_key_first($row)];
            }


            $individualTypeId      = $oClients->getMemberTypeIdByName('individual');
            $internalContactTypeId = $oClients->getMemberTypeIdByName('internal_contact');

            $strClientLastNameKey  = 'last_name';
            $strClientFirstNameKey = 'first_name';

            $arrCachedInfo = array();

            $processCasesAtOnce = 1000;

            $i                = 0;
            $totalCount       = count($arrSavedCases);
            $casesBlock       = 0;
            $totalBlocksCount = round($totalCount / $processCasesAtOnce);

            foreach (array_chunk($arrSavedCases, $processCasesAtOnce) as $arrCasesSet) {
                echo str_repeat('*', 80) . PHP_EOL . "Cases block: " . '(' . ++$casesBlock . '/' . $totalBlocksCount . ') ' . PHP_EOL . str_repeat('*', 80) . PHP_EOL;

                // For all cases load info at once
                $arrCaseIds = array();
                foreach ($arrCasesSet as $arrCaseInfo) {
                    $arrCaseIds[] = $arrCaseInfo['member_id'];
                }

                $statement = $this->getQueryBuilder()
                    ->select(['d.*', 'f.company_field_id', 'f.type'])
                    ->from(['d' => 'client_form_data'])
                    ->leftJoin(['f' => 'client_form_fields'], ['d.field_id = f.field_id'])
                    ->where(function (QueryExpression $exp) use ($arrCaseIds) {
                        return $exp
                            ->in('d.member_id', $arrCaseIds);
                    })
                    ->execute();

                $arrAllCasesSavedData = $statement->fetchAll('assoc');

                // Group info for each case
                $arrCasesSavedDataGrouped = [];
                foreach ($arrAllCasesSavedData as $arrCaseSavedDataRow) {
                    $arrCasesSavedDataGrouped[$arrCaseSavedDataRow['member_id']][] = $arrCaseSavedDataRow;
                }


                $arrCaseIdsToReset    = [];
                $arrAssignToOffices   = [];
                $arrApplicantFormData = [];
                $arrMembersRelations  = [];
                $arrMembersRoles      = [];
                foreach ($arrCasesSet as $arrCaseInfo) {
                    // For each case: create IA + Internal Contact, assign Case to IA
                    $companyId = $arrCaseInfo['company_id'];
                    $caseId    = $arrCaseInfo['member_id'];
                    echo "Company #$companyId,  Case id: #$caseId " . '(' . ++$i . '/' . $totalCount . ' ' . number_format(round($i * 100 / $totalCount, 2), 2) . '%) ';

                    $booLocal = false; // All companies use S3
                    if (isset($arrCachedInfo[$companyId]) && count($arrCachedInfo[$companyId])) {
                        $adminId                 = $arrCachedInfo[$companyId]['admin_id'];
                        $arrParentClientFields   = $arrCachedInfo[$companyId]['fields'];
                        $arrDisabledLoginOptions = $arrCachedInfo[$companyId]['disable_login_options'];
                        $divisionGroupId         = $arrCachedInfo[$companyId]['division_group_id'];
                        $individualRoleId        = $arrCachedInfo[$companyId]['individual_client_role_id'];
                    } else {
                        $statement = $this->getQueryBuilder()
                            ->select(['f.*', 'g.applicant_group_id', 'b.applicant_block_id'])
                            ->from(['f' => 'applicant_form_fields'])
                            ->innerJoin(['o' => 'applicant_form_order'], ['o.applicant_field_id = f.applicant_field_id'])
                            ->innerJoin(['g' => 'applicant_form_groups'], ['g.applicant_group_id = o.applicant_group_id'])
                            ->innerJoin(['b' => 'applicant_form_blocks'], ['g.applicant_block_id = b.applicant_block_id'])
                            ->where([
                                'f.company_id'     => (int)$companyId,
                                'b.member_type_id' => (int)$individualTypeId
                            ])
                            ->execute();

                        $arrParentClientFields = $arrCachedInfo[$companyId]['fields'] = $statement->fetchAll('assoc');

                        $arrDisabledLoginOptions = array();
                        foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                            switch ($arrParentClientFieldInfo['applicant_field_unique_id']) {
                                case 'disable_login':
                                    $arrDisabledLoginOptions = $arrCachedInfo[$companyId]['disable_login_options'] = $oClients->getApplicantFields()->getFieldsOptions($arrParentClientFieldInfo['applicant_field_id']);
                                    break 2;

                                default:
                                    break;
                            }
                        }

                        // Get the real admin of the company
                        $adminId = $arrCachedInfo[$companyId]['admin_id'] = $oCompany->getCompanyAdminId($companyId, 0);
                        if (empty($adminId)) {
                            /** @var Log $log */
                            $log = self::getService('log');
                            $log->debugErrorToFile('Admin does not exists for company', $companyId, 'cases_converted_ca');
                            echo "!!!! Admin does not exists !!!!" . PHP_EOL;
                            continue;
                        }

                        $divisionGroupId  = $arrCachedInfo[$companyId]['division_group_id'] = $oCompany->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId);
                        $individualRoleId = $arrCachedInfo[$companyId]['individual_client_role_id'] = $oClients->getRoleIdByRoleType('individual_client', $companyId);

                        if (empty($individualRoleId)) {
                            /** @var Log $log */
                            $log = self::getService('log');
                            $log->debugErrorToFile('Individual role does not exists for company', $companyId, 'cases_converted_ca');
                            echo "!!!! Individual role does not exists !!!!" . PHP_EOL;
                            continue;
                        }
                    }

                    $arrOffices = $oClients->getMemberDivisions($caseId);

                    // Main info of the saved Case
                    $arrFieldsMapping = array(
                        $strClientLastNameKey  => $arrCaseInfo['lName'],
                        $strClientFirstNameKey => $arrCaseInfo['fName'],
                        'email'                => $arrCaseInfo['emailAddress'],
                        'office'               => implode(',', $arrOffices),
                    );

                    // Set username/pass and 'disable login' fields for login block
                    $username = null;
                    if (isset($arrCaseInfo['username']) && !empty($arrCaseInfo['username'])) {
                        $arrFieldsMapping['username'] = $username = $arrCaseInfo['username'];
                        $arrFieldsMapping['password'] = '*******';
                    }

                    $password = null;
                    if (isset($arrCaseInfo['password']) && !empty($arrCaseInfo['password'])) {
                        $password = $arrCaseInfo['password'];
                    }

                    foreach ($arrDisabledLoginOptions as $arrDisabledLoginOptionInfo) {
                        if ($arrDisabledLoginOptionInfo['value'] == 'Enabled') {
                            $arrFieldsMapping['disable_login'] = $arrDisabledLoginOptionInfo['applicant_form_default_id'];
                            break;
                        }
                    }

                    // Load saved info for this Case (and use fields that must be moved to IA)
                    $arrCaseSavedPhotoData = array();
                    if (isset($arrCasesSavedDataGrouped[$caseId])) {
                        foreach ($arrCasesSavedDataGrouped[$caseId] as $arrCaseSavedData) {
                            if ($arrCaseSavedData['type'] == 16) { // Photo
                                $arrCaseSavedPhotoData[] = $arrCaseSavedData;
                            }

                            // Check if we need move field's value
                            foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                                if ($arrParentClientFieldInfo['applicant_field_unique_id'] == $arrCaseSavedData['company_field_id']) {
                                    switch ($arrCaseSavedData['company_field_id']) {
                                        case 'last_name':
                                            $convertedValue = $arrCaseInfo['lName'];
                                            break;

                                        case 'first_name':
                                            $convertedValue = $arrCaseInfo['fName'];
                                            break;

                                        case 'email':
                                            $convertedValue = $arrCaseInfo['emailAddress'];
                                            break;

                                        default:
                                            $convertedValue = trim($arrCaseSavedData['value']);

                                            if ($convertedValue != '' && $arrParentClientFieldInfo['type'] == 'combo') {
                                                $optionOrder    = -1;
                                                $booFoundOption = false;

                                                if (!isset($this->_cachedOptions[$arrParentClientFieldInfo['applicant_field_id']])) {
                                                    $this->_cachedOptions[$arrParentClientFieldInfo['applicant_field_id']] = $oClients->getApplicantFields()->getFieldsOptions(array($arrParentClientFieldInfo['applicant_field_id']));
                                                }

                                                $arrOptions = $this->_cachedOptions[$arrParentClientFieldInfo['applicant_field_id']];
                                                foreach ($arrOptions as $arrOption) {
                                                    $optionOrder = max($optionOrder, $arrOption['order']);

                                                    if ($arrOption['value'] == $convertedValue) {
                                                        $booFoundOption = true;
                                                        $convertedValue = $arrOption['applicant_form_default_id'];
                                                        break;
                                                    }
                                                }

                                                if (!$booFoundOption) {
                                                    $arrNewOptionInfo = array(
                                                        'applicant_field_id' => $arrParentClientFieldInfo['applicant_field_id'],
                                                        'value'              => $convertedValue,
                                                        'order'              => $optionOrder + 1
                                                    );

                                                    $statement = $this->getQueryBuilder()
                                                        ->insert(array_keys($arrNewOptionInfo))
                                                        ->into('applicant_form_default')
                                                        ->values($arrNewOptionInfo)
                                                        ->execute();

                                                    $newId = $statement->lastInsertId('applicant_form_default');

                                                    // Save to cache this new option
                                                    $arrNewOptionInfo['applicant_form_default_id']                           = $newId;
                                                    $this->_cachedOptions[$arrParentClientFieldInfo['applicant_field_id']][] = $arrNewOptionInfo;

                                                    $convertedValue = $newId;
                                                }
                                            }
                                            break;
                                    }

                                    $arrFieldsMapping[$arrCaseSavedData['company_field_id']] = $convertedValue;
                                    break;
                                }
                            }
                        }
                    }

                    // Prepare IA fields data
                    $arrInternalContacts = array();
                    $arrClientData       = array();
                    foreach ($arrFieldsMapping as $uniqueFieldId => $fieldVal) {
                        foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                            if ($arrParentClientFieldInfo['applicant_field_unique_id'] == $uniqueFieldId) {
                                // Group fields by parent client type
                                // i.e. internal contact info and main client info
                                if ($arrParentClientFieldInfo['member_type_id'] == $internalContactTypeId) {
                                    if (!array_key_exists($arrParentClientFieldInfo['applicant_block_id'], $arrInternalContacts)) {
                                        $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']] = array(
                                            'parent_group_id' => array(),
                                            'data'            => array()
                                        );
                                    }

                                    if (!in_array($arrParentClientFieldInfo['applicant_group_id'], $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'])) {
                                        $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'][] = $arrParentClientFieldInfo['applicant_group_id'];
                                    }

                                    $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['data'][] = array(
                                        'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                        'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                        'type'            => $arrParentClientFieldInfo['type'],
                                        'value'           => $fieldVal,
                                        'row'             => 0,
                                        'row_id'          => 0
                                    );
                                } else {
                                    $arrClientData[] = array(
                                        'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                        'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                        'value'           => $fieldVal,
                                        'row'             => 0,
                                        'row_id'          => 0
                                    );
                                }
                            }
                        }
                    }

                    // 1. Create IA record
                    $arrMemberInsertInfo = array(
                        'company_id'        => $companyId,
                        'division_group_id' => $divisionGroupId,
                        'userType'          => $individualTypeId,
                        'fName'             => $arrCaseInfo['fName'],
                        'lName'             => $arrCaseInfo['lName'],
                        'emailAddress'      => $arrCaseInfo['emailAddress'],
                        'username'          => $username,
                        'password'          => $password,
                        'regTime'           => $arrCaseInfo['regTime'],
                        'status'            => 1,
                        'login_enabled'     => 'Y'
                    );

                    // Update/use login, password and login enabled fields only when we've received them
                    if (is_null($username)) {
                        unset($arrMemberInsertInfo['username']);
                    }

                    if (is_null($password)) {
                        unset($arrMemberInsertInfo['password']);
                    }

                    $statement = $this->getQueryBuilder()
                        ->insert(array_keys($arrMemberInsertInfo))
                        ->into('members')
                        ->values($arrMemberInsertInfo)
                        ->execute();

                    $applicantId = $statement->lastInsertId('members');

                    $arrClientInfo = array(
                        'member_id'          => $applicantId,
                        'added_by_member_id' => $adminId,
                    );

                    $this->getQueryBuilder()
                        ->insert(array_keys($arrClientInfo))
                        ->into('clients')
                        ->values($arrClientInfo)
                        ->execute();

                    foreach ($arrOffices as $officeId) {
                        $arrAssignToOffices[] = [
                            'member_id'   => $applicantId,
                            'division_id' => $officeId,
                            'type'        => 'access_to'
                        ];
                    }

                    $arrMembersRoles[] = [
                        'member_id' => $applicantId,
                        'role_id'   => $individualRoleId
                    ];

                    // Assign case to the parent applicant(s)
                    $arrMembersRelations[] = [
                        'parent_member_id' => $applicantId,
                        'child_member_id'  => $caseId,
                    ];

                    foreach ($arrClientData as $arrClientDataRow) {
                        $arrApplicantFormData[] = [
                            'applicant_id'       => $applicantId,
                            'applicant_field_id' => $arrClientDataRow['field_id'],
                            'value'              => $arrClientDataRow['value'],
                            'row'                => 0,
                        ];
                    }

                    // 2. Create an Internal Contact record
                    $arrMemberInsertInfo = array(
                        'company_id'        => $companyId,
                        'division_group_id' => $divisionGroupId,
                        'userType'          => $internalContactTypeId,
                        'fName'             => $arrCaseInfo['fName'],
                        'lName'             => $arrCaseInfo['lName'],
                        'emailAddress'      => $arrCaseInfo['emailAddress'],
                        'regTime'           => $arrCaseInfo['regTime'],
                        'status'            => 1,
                    );

                    $statement = $this->getQueryBuilder()
                        ->insert(array_keys($arrMemberInsertInfo))
                        ->into('members')
                        ->values($arrMemberInsertInfo)
                        ->execute();

                    $internalContactId = $statement->lastInsertId('members');

                    $arrClientInfo = array(
                        'member_id'          => $internalContactId,
                        'added_by_member_id' => $adminId,
                    );

                    $this->getQueryBuilder()
                        ->insert(array_keys($arrClientInfo))
                        ->into('clients')
                        ->values($arrClientInfo)
                        ->execute();

                    foreach ($arrOffices as $officeId) {
                        $arrAssignToOffices[] = [
                            'member_id'   => $internalContactId,
                            'division_id' => $officeId,
                            'type'        => 'access_to'
                        ];
                    }

                    $arrCasePhotoFields = array();
                    foreach ($arrCaseSavedPhotoData as $arrCaseSavedPhotoDataInfo) {
                        $arrCasePhotoFields[] = $arrCaseSavedPhotoDataInfo['company_field_id'];
                    }

                    $arrApplicantSavedPhotoData = [];
                    foreach ($arrInternalContacts as $arrBlockInfo) {
                        foreach ($arrBlockInfo['data'] as $arrClientDataRow) {
                            $arrApplicantFormData[] = [
                                'applicant_id'       => $internalContactId,
                                'applicant_field_id' => $arrClientDataRow['field_id'],
                                'value'              => $arrClientDataRow['value'],
                                'row'                => 0,
                            ];

                            if ($arrClientDataRow['type'] == 'photo' && in_array($arrClientDataRow['field_unique_id'], $arrCasePhotoFields)) {
                                $arrApplicantSavedPhotoData[] = $arrClientDataRow;
                            }
                        }

                        foreach ($arrBlockInfo['parent_group_id'] as $parentGroupId) {
                            $arrMembersRelations[] = [
                                'parent_member_id'   => $applicantId,
                                'child_member_id'    => $internalContactId,
                                'applicant_group_id' => $parentGroupId,
                                'row'                => 0,
                            ];
                        }
                    }

                    // Copy photos from Case to IA/internal
                    if (count($arrCasePhotoFields) && !empty($arrApplicantSavedPhotoData)) {
                        foreach ($arrApplicantSavedPhotoData as $arrApplicantSavedPhotoDataInfo) {
                            foreach ($arrCaseSavedPhotoData as $arrCaseSavedPhotoDataInfo) {
                                if ($arrApplicantSavedPhotoDataInfo['field_unique_id'] == $arrCaseSavedPhotoDataInfo['company_field_id']) {
                                    $pathToFile = $oFiles->getPathToClientImages($companyId, $caseId, $booLocal) . '/' . 'field-' . $arrCaseSavedPhotoDataInfo['field_id'];

                                    $booExists = $booLocal
                                        ? file_exists($pathToFile)
                                        : $oFiles->getCloud()->checkObjectExists($pathToFile);

                                    if ($booExists) {
                                        $newFolder     = $oFiles->getPathToClientImages($companyId, $internalContactId, $booLocal);
                                        $pathToNewFile = $newFolder . '/' . 'field-' . $arrApplicantSavedPhotoDataInfo['field_id'];

                                        if ($booLocal) {
                                            $oFiles->createFTPDirectory($newFolder);
                                        } else {
                                            $oFiles->createCloudDirectory($newFolder);
                                        }

                                        $oFiles->copyFile($pathToFile, $pathToNewFile, $booLocal);
                                    } else {
                                        echo 'Case file does not exists: ' . $pathToFile . PHP_EOL;
                                    }
                                    break;
                                }
                            }
                        }
                    }

                    $arrCaseIdsToReset[] = (int)$caseId;

                    echo 'Converted.' . PHP_EOL;
                }

                // Reset specific fields for processed cases
                $this->getQueryBuilder()
                    ->update('members')
                    ->set(
                        [
                            'fName'    => new QueryExpression('NULL'),
                            'lName'    => '',
                            'username' => new QueryExpression('NULL'),
                            'password' => new QueryExpression('NULL')
                        ]
                    )
                    ->where(['member_id IN ' => $arrCaseIdsToReset])
                    ->execute();

                // Insert all at once
                $this->table('members_divisions')
                    ->insert($arrAssignToOffices)
                    ->save();

                $this->table('applicant_form_data')
                    ->insert($arrApplicantFormData)
                    ->save();

                $this->table('members_relations')
                    ->insert($arrMembersRelations)
                    ->save();

                $this->table('members_roles')
                    ->insert($arrMembersRoles)
                    ->save();
            }

            echo 'Done.' . PHP_EOL;

            echo 'End: ' . date('c') . PHP_EOL;
            echo 'Worked: ' . round(microtime(true) - $start, 2) . 's' . PHP_EOL;

            $msg = sprintf('Run again to process %d clients.', $casesCount - $processMembersAtOnce);
            if ($casesCount > $processMembersAtOnce) {
                throw new Exception($msg);
            }
        } catch (Exception $e) {
            if ($e->getMessage() != $msg) {
                /** @var Log $log */
                $log = self::getService('log');
                $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
            throw $e;
        }
    }

    public function down()
    {
    }
}
