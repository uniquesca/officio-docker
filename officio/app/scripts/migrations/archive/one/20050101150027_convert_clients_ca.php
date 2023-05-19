<?php

use Clients\Service\Clients;
use Clients\Service\Members;
use Files\Service\Files;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Company;
use Officio\Service\Country;
use Officio\Encryption;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ConvertClientsCa extends AbstractMigration
{
    private $_cachedOptions = array();

    private function _getConvertedValue($fieldId, $fieldVal, $arrParentClientFields)
    {
        /** @var Zend_Db_Adapter_Abstract $db */
        $db             = Zend_Registry::get('serviceManager')->get('db');

        /** @var Clients $clients */
        $clients = Zend_Registry::get('serviceManager')->get(Clients::class);
        /** @var Country $oCountry */
        $oCountry         = Zend_Registry::get('serviceManager')->get(Country::class);

        $convertedValue = trim($fieldVal);

        if ($convertedValue == '') {
            return $convertedValue;
        }

        $arrClientFieldInfo = array();
        $fieldType          = '';
        $booEncrypted       = false;
        foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
            if ($arrParentClientFieldInfo['applicant_field_unique_id'] == $fieldId) {
                $arrClientFieldInfo = $arrParentClientFieldInfo;

                $fieldType    = $arrParentClientFieldInfo['type'];
                $booEncrypted = $arrParentClientFieldInfo['encrypted'] == 'Y';
                break;
            }
        }

        switch ($fieldType) {
            case 'combo':
                $optionOrder    = -1;
                $booFoundOption = false;

                if (!isset($this->_cachedOptions[$arrClientFieldInfo['applicant_field_id']])) {
                    $this->_cachedOptions[$arrClientFieldInfo['applicant_field_id']] = $clients->getApplicantFields()->getFieldsOptions(array($arrClientFieldInfo['applicant_field_id']));
                }

                $arrOptions = $this->_cachedOptions[$arrClientFieldInfo['applicant_field_id']];
                foreach ($arrOptions as $arrOption) {
                    $optionOrder = max($optionOrder, $arrOption['order']);

                    if ($arrOption['value'] == $fieldVal) {
                        $booFoundOption = true;
                        $convertedValue = $arrOption['applicant_form_default_id'];
                        break;
                    }
                }

                if (!$booFoundOption) {
                    $arrNewOptionInfo = array(
                        'applicant_field_id' => $arrClientFieldInfo['applicant_field_id'],
                        'value'              => $convertedValue,
                        'order'              => $optionOrder + 1
                    );

                    $db->insert('applicant_form_default', $arrNewOptionInfo);
                    $newId = $db->lastInsertId('applicant_form_default');

                    // Save to cache this new option
                    $arrNewOptionInfo['applicant_form_default_id'] = $newId;
                    $this->_cachedOptions[$arrClientFieldInfo['applicant_field_id']][] = $arrNewOptionInfo;

                    $convertedValue = $newId;
                }
                break;

            case 'country':
                if (is_numeric($fieldVal)) {
                    if (!isset($this->_cachedOptions['arrCountries'])) {
                        $this->_cachedOptions['arrCountries'] = $oCountry->getCountries(true);
                    }

                    $convertedValue = null;
                    if (isset($this->_cachedOptions['arrCountries'][$fieldVal])) {
                        $convertedValue = $this->_cachedOptions['arrCountries'][$fieldVal];
                    }
                }
                break;

            // 'agents','office','office_multi','assigned_to'
            // 'photo','file','office_change_date_time'

            // 'text','password','number','email','phone','memo',
            // 'date','date_repeatable','radio','checkbox',
            default:
                break;
        }

        return $booEncrypted ? Encryption::encode($convertedValue) : $convertedValue;
    }

    public function up()
    {
        // Took 660s for 10 000 clients (total 292 000) on local server...
        $msg = '';

        try {
            echo 'Start: ' . date('c') . PHP_EOL;

            /** @var \Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            // TODO: Enable when ready
            $booTest = false;

            $processMembersAtOnce = 15000;

            /** @var Members $members */
            $members = Zend_Registry::get('serviceManager')->get(Members::class);
            $select = $db->select()
                ->from(array('m' => 'members'), new Zend_Db_Expr('SQL_CALC_FOUND_ROWS m.*'))
                ->joinLeft(array('mr' => 'members_relations'), 'm.member_id = mr.child_member_id', '')
//                 ->where('m.company_id = ?', 1) // TODO: remove
                ->where('m.userType IN (?)', $members->getMemberType('case'))
                ->where('mr.parent_member_id IS NULL')
                ->order(array('m.company_id ASC', 'm.member_id ASC'))
            ->limit($processMembersAtOnce);

            $arrSavedCases = $db->fetchAll($select);
            $casesCount    = $db->fetchOne($db->select()->from(null, new Zend_Db_Expr('FOUND_ROWS()')));

            /** @var Clients $oClients */
            $oClients          = Zend_Registry::get('serviceManager')->get(Clients::class);
            /** @var Company $oCompany */
            $oCompany = Zend_Registry::get('serviceManager')->get(Company::class);
            $oFieldTypes      = $oClients->getFieldTypes();
            /** @var Files $oFiles */
            $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);

            $applicantTypeId       = 0;
            $individualTypeId      = $oClients->getMemberTypeIdByName('individual');
            $internalContactTypeId = $oClients->getMemberTypeIdByName('internal_contact');

            $booAustralia          = Zend_Registry::get('serviceManager')->get('config')['site_version']['version'] == 'australia';
            $strClientLastNameKey  = $booAustralia ? 'family_name' : 'last_name';
            $strClientFirstNameKey = $booAustralia ? 'given_names' : 'first_name';

            $arrCachedInfo = array();

            $processCasesAtOnce = 100;

            $i          = 0;
            $totalCount = count($arrSavedCases);
            $casesBlock = 0;
            $totalBlocksCount = round($totalCount / $processCasesAtOnce);

            foreach (array_chunk($arrSavedCases, $processCasesAtOnce) as $arrCasesSet) {
                echo str_repeat('*', 80) . PHP_EOL . "Cases block: " . '(' . ++$casesBlock . '/' . $totalBlocksCount . ') ' . PHP_EOL . str_repeat('*', 80) . PHP_EOL;

                // For all cases load info at once
                $arrCaseIds = array();
                foreach ($arrCasesSet as $arrCaseInfo) {
                    $arrCaseIds[] = $arrCaseInfo['member_id'];
                }

                $arrAllCasesSavedData = $oClients->getClientSavedData($arrCaseIds);


                foreach ($arrCasesSet as $arrCaseInfo) {
                    // For each case: create IA + Internal Contact, assign Case to IA
                    $companyId = $arrCaseInfo['company_id'];
                    $caseId    = $arrCaseInfo['member_id'];
                    echo "Company #$companyId,  Case id: #$caseId " . '(' . ++$i . '/' . $totalCount . ' ' . round($i * 100 / $totalCount, 2) . '%) ';

                    if (isset($arrCachedInfo[$companyId]) && count($arrCachedInfo[$companyId])) {
                        $adminId                 = $arrCachedInfo[$companyId]['admin_id'];
                        $arrParentClientFields   = $arrCachedInfo[$companyId]['fields'];
                        $arrDisabledLoginOptions = $arrCachedInfo[$companyId]['disable_login_options'];
                        $booLocal                = $arrCachedInfo[$companyId]['is_storage_local'];
                    } else {
                        $booLocal = $arrCachedInfo[$companyId]['is_storage_local'] = $oCompany->isCompanyStorageLocationLocal($companyId);

                        $arrParentClientFields = $arrCachedInfo[$companyId]['fields'] = $oClients->getApplicantFields()->getCompanyFields(
                            $companyId,
                            $individualTypeId,
                            $applicantTypeId
                        );

                        $arrDisabledLoginOptions = array();
                        foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                            switch ($arrParentClientFieldInfo['applicant_field_unique_id']) {
                                case 'disable_login':
                                    $arrDisabledLoginOptions = $arrCachedInfo[$companyId]['disable_login_options'] = $oClients->getApplicantFields()->getFieldsOptions($arrParentClientFieldInfo['applicant_field_id']);
                                    break;

                                default:
                                    break;
                            }
                        }

                        // Get the real admin of the company
                        $adminId = $arrCachedInfo[$companyId]['admin_id'] = $oCompany->getCompanyAdminId($companyId, 0);
                        if (empty($adminId)) {
                            /** @var Log $log */
                            $log = Zend_Registry::get('serviceManager')->get('log');
                            $log->debugErrorToFile('Admin does not exists for company', $companyId, 'cases_converted_ca');
                            echo "!!!! Admin does not exists !!!!" . PHP_EOL;
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
                    if (isset($arrCaseInfo['username']) && !empty($arrCaseInfo['username'])) {
                        $arrFieldsMapping['username'] = $arrCaseInfo['username'];
                        $arrFieldsMapping['password'] = Encryption::decode($arrCaseInfo['password']);
                    }

                    foreach ($arrDisabledLoginOptions as $arrDisabledLoginOptionInfo) {
                        if ($arrDisabledLoginOptionInfo['value'] == 'Enabled') {
                            $arrFieldsMapping['disable_login'] = $arrDisabledLoginOptionInfo['applicant_form_default_id'];
                            break;
                        }
                    }

                    // Load saved info for this Case (and use fields that must be moved to IA)
                    $arrCaseSavedPhotoData = array();
                    foreach ($arrAllCasesSavedData as $arrCaseSavedData) {
                        if ($arrCaseSavedData['member_id'] == $caseId) {
                            if ($arrCaseSavedData['type'] == $oFieldTypes->getFieldTypeId('photo')) {
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
                                            $convertedValue = $this->_getConvertedValue(
                                                $arrCaseSavedData['company_field_id'],
                                                $arrCaseSavedData['value'],
                                                $arrParentClientFields
                                            );
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
                    foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                        foreach ($arrFieldsMapping as $uniqueFieldId => $fieldVal) {
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

                                    if (!in_array($arrParentClientFieldInfo['group_id'], $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'])) {
                                        $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'][] = $arrParentClientFieldInfo['group_id'];
                                    }

                                    $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['data'][] = array(
                                        'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                        'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
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

                    $arrNewClientInfo = array(
                        'createdBy'  => $adminId,
                        'createdOn'  => $arrCaseInfo['regTime'],
                        'arrParents' => array(
                            array(
                                // Parent client info
                                'arrParentClientInfo' => array(
                                    'emailAddress'     => $arrCaseInfo['emailAddress'],
                                    'fName'            => $arrCaseInfo['fName'],
                                    'lName'            => $arrCaseInfo['lName'],
                                    'createdBy'        => $adminId,
                                    'createdOn'        => $arrCaseInfo['regTime'],
                                    'memberTypeId'     => $individualTypeId,
                                    'applicantTypeId'  => $applicantTypeId,
                                    'arrApplicantData' => $arrClientData,
                                    'arrOffices'       => $arrOffices,
                                ),

                                // Internal contact(s) info
                                'arrInternalContacts' => $arrInternalContacts,
                            )
                        )
                    );

                    if ($booTest) {
                        $arrCreationResult = array(
                            'strError'            => 'TEST',
                            'arrCreatedClientIds' => array()
                        );
                    } else {
                        $arrCreationResult = $oClients->createClient($arrNewClientInfo, $companyId, 0);
                    }
                    $strError = $arrCreationResult['strError'];

                    // Assign case to the parent applicant(s)
                    if (empty($strError) && count($arrCreationResult['arrCreatedClientIds'])) {
                        foreach ($arrCreationResult['arrCreatedClientIds'] as $parentClientId) {
                            $arrAssignData = array(
                                'applicant_id' => $parentClientId,
                                'case_id'      => $caseId,
                            );

                            if (!$oClients->assignCaseToApplicant($arrAssignData)) {
                                $strError = 'Internal error.';
                                break;
                            }
                        }
                    }

                    if (!empty($strError)) {
                        /** @var Log $log */
                        $log = Zend_Registry::get('serviceManager')->get('log');
                        $log->debugErrorToFile('Error', $strError, 'cases_converted_ca');
                        echo sprintf("!!! Error: %s !!!", $strError) . PHP_EOL;
                    } else {
                        // Copy photos from Case to IA/internal
                        $arrCasePhotoFields = array();
                        foreach ($arrCaseSavedPhotoData as $arrCaseSavedPhotoDataInfo) {
                            $arrCasePhotoFields[] = $arrCaseSavedPhotoDataInfo['company_field_id'];
                        }

                        if (count($arrCasePhotoFields)) {
                            $select = $db->select()
                                ->from(array('d' => 'applicant_form_data'))
                                ->joinLeft(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', 'applicant_field_unique_id')
                                ->where('d.applicant_id IN (?)', $arrCreationResult['arrAllCreatedClientIds'], 'INT')
                                ->where('f.applicant_field_unique_id IN (?)', $arrCasePhotoFields)
                                ->where('f.type = ?', 'photo');

                            $arrApplicantSavedPhotoData = $db->fetchAll($select);

                            foreach ($arrApplicantSavedPhotoData as $arrApplicantSavedPhotoDataInfo) {
                                foreach ($arrCaseSavedPhotoData as $arrCaseSavedPhotoDataInfo) {
                                    if ($arrApplicantSavedPhotoDataInfo['applicant_field_unique_id'] == $arrCaseSavedPhotoDataInfo['company_field_id']) {
                                        $pathToFile = $oFiles->getPathToClientImages($companyId, $caseId, $booLocal) . '/' . 'field-' . $arrCaseSavedPhotoDataInfo['field_id'];
                                        $newFolder     = $oFiles->getPathToClientImages($companyId, $arrApplicantSavedPhotoDataInfo['applicant_id'], $booLocal);
                                        $pathToNewFile = $newFolder . '/' . 'field-' . $arrApplicantSavedPhotoDataInfo['applicant_field_id'];

                                        if ($booLocal) {
                                            $oFiles->createFTPDirectory($newFolder);
                                        } else {
                                            $oFiles->createCloudDirectory($newFolder);
                                        }

                                        $oFiles->copyFile($pathToFile, $pathToNewFile, $booLocal);
                                        break;
                                    }
                                }
                            }
                        }


                        // Reset specific fields for the Case
                        $db->update(
                            'members',
                            array(
                                'fName'    => new Zend_Db_Expr('NULL'),
                                'lName'    => '',
                                'username' => new Zend_Db_Expr('NULL'),
                                'password' => new Zend_Db_Expr('NULL')
                            ),
                            $db->quoteInto('member_id = ?', $caseId, 'INT')
                        );

                        echo 'Converted.' . PHP_EOL;
                    }
                }
            }

            /** @var StorageInterface $cache */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
            echo 'Done.' . PHP_EOL;

            echo 'End: ' . date('c') . PHP_EOL;

            $msg = sprintf('Run again to process %d clients.', $casesCount - $processMembersAtOnce);
            if ($casesCount > $processMembersAtOnce) {
                throw new Exception($msg);
            }
        } catch (\Exception $e) {
            if ($e->getMessage() != $msg) {
                echo 'Fatal error' . $e->getTraceAsString();
                /** @var Log $log */
                $log = Zend_Registry::get('serviceManager')->get('log');
                $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'cases_converted_ca');
            }
            throw $e;
        }
    }

    public function down()
    {
    }
}