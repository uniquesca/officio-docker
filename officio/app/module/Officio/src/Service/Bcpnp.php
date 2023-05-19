<?php

namespace Officio\Service;


use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * Class Bcpnp
 *
 * This class contains a set of functions used for BC PNP migrations.
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Bcpnp extends BaseService
{

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    /** @var Forms */
    protected $_forms;

    public function initAdditionalServices(array $services)
    {
        $this->_authHelper = $services[AuthHelper::class];
        $this->_clients    = $services[Clients::class];
        $this->_files      = $services[Files::class];
        $this->_forms      = $services[Forms::class];
    }

    public function authenticateAsCompanyAdmin($companyName = 'BC PNP')
    {
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $select = (new Select())
            ->from(['m' => 'members'])
            ->columns(['m.username', 'm.password'])
            ->join(array('mt' => 'members_types'), 'mt.member_type_id = m.userType')
            ->join(array('c' => 'company'), 'c.company_id = m.company_id')
            ->where([
                'mt.member_type_name' => 'admin',
                'c.companyName' => $companyName
            ])
            ->limit(1);

        if (!$admin = $this->_db2->fetchRow($select)) {
            return false;
        }

        return $this->_authHelper->login($admin['username'], $admin['password'], false, false, true);
    }

    public function createCaseGroup($title, $cols, $caseTypeName, $execute = true) {
        $sql = "CALL `createCaseGroup` ('$title', $cols, '$caseTypeName');";
        if ($execute) {
            $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return $sql;
    }

    public function createCaseField($fieldName, $fieldType, $label, $maxLength, $required, $disabled, $groupName, $caseTypeName, $mapTo = '', $execute = true) {
        $sql = "CALL `createCaseField` ('$fieldName', $fieldType, '$label', $maxLength, '$required', '$disabled', '$groupName', '$caseTypeName', '$mapTo');";
        if ($execute) {
            $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return $sql;
    }

    public function putCaseFieldIntoGroup($fieldName, $groupName, $caseTypeName, $mapTo = '', $execute = true) {
        $sql = "CALL `putCaseFieldIntoGroup` ('$fieldName', '$groupName', '$caseTypeName', '$mapTo');";
        if ($execute) {
            $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return $sql;
    }

    public function createIAGroup($groupName, $colsCount = 3, $collapsed = 'N', $execute = true) {
        $sql = "CALL `createIAGroup` ('$groupName', $colsCount, $collapsed);";
        if ($execute) {
            $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return $sql;
    }

    public function createIAField($fieldName, $fieldType, $label, $required = 'N', $disabled = 'N', $groupName = '', $mapTo = '', $execute = true) {
        $sql = "CALL `createIAField` ('$fieldName', '$fieldType', '$label', '$required', '$disabled', '$groupName', '$mapTo');";
        if ($execute) {
            $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return $sql;
    }

    public function putIAFieldIntoGroup($fieldName, $groupName, $mapTo = '', $execute = true) {
        $sql = "CALL `putIAFieldIntoGroup` ('$fieldName', '$groupName', '$mapTo');";
        if ($execute) {
            $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }
        return $sql;
    }

    public function changeCaseFields($client, $caseId, $data)
    {
        $strError = '';
        if (!isset($client['client_type_id'])) {
            $strError = 'No client type id passed.';
        }

        if (empty($strError) && !isset($client['company_id'])) {
            $strError = 'No company id passed.';
        }

        if (empty($strError) && !$this->authenticateAsCompanyAdmin()) {
            $strError = 'Unable to authenticate.';
        }


        // Load sync fields
        $arrMappedParams = array();

        // Load Officio and sync fields - only they will be used
        // during IA/Case creation/update, other fields will be used in xfdf files only
        $strOfficioFieldPrefix = 'Officio_';
        foreach ($data as $fieldId => $fieldVal) {
            $fieldId = trim($fieldId);

            // Check which field is related to:
            // - Officio field, that is not in the mapping table, but must be provided
            if (preg_match("/^$strOfficioFieldPrefix(.*)/", $fieldId, $regs)) {
                $arrMappedParams[$regs[1]] = $fieldVal;
            }
        }

        $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

        // Create/update case for just created/updated IA
        if (empty($strError)) {
            $arrCaseParams = array();

            // Load grouped fields
            $arrGroupedCaseFields = $this->_clients->getFields()->getGroupedCompanyFields($client['client_type_id']);

            // Load all company fields for specific Immigration Program,
            // which are available for the current user
            $arrCaseFields = $this->_clients->getFields()->getCaseTemplateFields($client['company_id'], $client['client_type_id']);

            $currentRowId            = '';
            $previousBlockContact    = '';
            $previousBlockRepeatable = '';
            foreach ($arrGroupedCaseFields as $arrGroupInfo) {
                if (!isset($arrGroupInfo['fields'])) {
                    continue;
                }

                if ($previousBlockContact != $arrGroupInfo['group_contact_block'] || $previousBlockRepeatable != $arrGroupInfo['group_repeatable']) {
                    $currentRowId = $this->_clients->generateRowId();
                }
                $previousBlockContact    = $arrGroupInfo['group_contact_block'];
                $previousBlockRepeatable = $arrGroupInfo['group_repeatable'];
                $groupId                 = 'case_group_row_' . $arrGroupInfo['group_id'];

                foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                    $fieldValToSave = '';
                    if (empty($caseId) && !array_key_exists($groupId, $arrCaseParams)) {
                        $arrCaseParams[$groupId] = array($currentRowId);
                    }

                    if (array_key_exists($arrFieldInfo['field_unique_id'], $arrMappedParams)) {
                        // Convert fields data from readable format to the correct one (e.g. use ids for office field)
                        $arrFieldValResult = $this->_clients->getFields()->getFieldValue(
                            $arrCaseFields,
                            $arrFieldInfo['field_unique_id'],
                            trim($arrMappedParams[$arrFieldInfo['field_unique_id']]),
                            null,
                            $client['company_id']
                        );

                        if ($arrFieldValResult['error']) {
                            $strError .= $arrFieldValResult['error-msg'];
                        } else {
                            $fieldValToSave = $arrFieldValResult['result'];

                            // Date must be in the same format as it is passed from the client side
                            if (!empty($fieldValToSave) && in_array($arrFieldInfo['field_type'],
                                    array(
                                        'date',
                                        'date_repeatable'
                                    ))
                            ) {
                                $fieldValToSave = date($dateFormatFull, strtotime($fieldValToSave));
                            }
                        }

                        $arrCaseParams[$arrFieldInfo['field_id']] = $fieldValToSave;
                    }
                }
            }

            if (empty($strError)) {
                $arrUpdateResult = $this->_clients->saveClientData($caseId, $arrCaseParams);
                if (!$arrUpdateResult['success']) {
                    $strError = 'Internal error.';
                }
            }
        }

        return $strError;
    }

    public function changeCaseJsonFields($client, $changes) {
        if (!isset($client['form_assigned_id'])) return array(false, 'No form_assigned_id passed.');

        $strError = '';
        try {
            $arrData = array();

            // Get assigned form info by id
            $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($client['form_assigned_id']);
            if (!$assignedFormInfo) {
                $strError = 'There is no form with this assigned id.';
            }

            if (empty($strError)) {
                // Return xfdf for specific member id
                $caseId = $assignedFormInfo['client_member_id'];
                $familyMemberId = $assignedFormInfo['family_member_id'];

                // Check if we need load data from json or xfdf file
                $jsonFilePath = $this->_files->getClientJsonFilePath($caseId, $familyMemberId, $client['form_assigned_id']);
                if (file_exists($jsonFilePath)) {
                    $savedJson = file_get_contents($jsonFilePath);
                    $arrData = (array) json_decode($savedJson);
                }
                else {
                    $strError = 'Json file does not exist.';
                }

                if (empty($strError)) {
                    $arrDataToWrite = $arrData;
                    $caseData = $applicantData = array();
                    foreach ($arrData as $fieldName => $fieldValue) {
                        if (isset($changes[$fieldName])) {
                            $change = $changes[$fieldName];

                            if (isset($change['newValue'])) {
                                if (is_string($change['newValue'])) {
                                    $fieldValue = $change['newValue'];
                                }
                                elseif (is_array($change['newValue'])) {
                                    if (!is_string($fieldValue)) {
                                        $strError = 'Cannot convert value for field ' . $fieldName . ' which is of an array type. Value in JSON format is: ' . json_encode($fieldValue);
                                        break;
                                    }
                                    elseif (!isset($change['newValue'][$fieldValue])) {
                                        $strError = 'Conversion value not found for field ' . $fieldName . ': ' . $fieldValue;
                                        break;
                                    }
                                    else {
                                        $fieldValue = $change['newValue'][$fieldValue];
                                    }
                                }
                            }

                            if (isset($change['newFieldName'])) {
                                // Renaming field
                                $arrDataToWrite[$change[$fieldName]] = $fieldValue;
                                unset($arrDataToWrite[$fieldName]);
                            }
                            else {
                                $arrDataToWrite[$fieldName] = $fieldValue;
                            }

                            if (isset($change['caseFieldName'])) {
                                // Write this field value into case
                                $caseData[$change['caseFieldName']] = $fieldValue;
                            }

                            if (isset($change['applicantFieldName'])) {
                                // Write this field value into case
                                $applicantData[$change['applicantFieldName']] = $fieldValue;
                            }
                        }
                    }

                    if (empty($strError)) {
                        // Writing new JSON
                        if (!file_put_contents($jsonFilePath, json_encode($arrDataToWrite))) {
                            return array(false, 'Could not update json file.');
                        }

                        // Updating case fields
                        $strError = $this->changeCaseFields($client, $caseId, $caseData);
                    }
                }
            }
        }
        catch (Exception $e) {
            $strError = $e->getMessage();
        }

        if (!empty($strError)) {
            return array(false, $strError);
        }
        else {
            return array(true, null);
        }
    }

    /**
     * List of changes to be applied to the forms on the fly.
     * Each change is an array containing following values:
     * - changeIsNotNeededSince: this change will be applied to forms submitted (created) before this date only
     * - formName: this change will be applied to form with this name only
     * - changes: associative array where key is old field name and value is new field name to use
     * @return array
     */
    public static function jsonChangesOnTheFly() {
        $changes = array();

        $changes[] = array(
            'changeIsNotNeededSince' => strtotime('2017-03-29 11:00:00'), // Phase 3 Round 2
            'formName' => 'SI Application Form',
            'changes' => array(
                'BCPNP_App_EduBC_City' => 'syncA_App_EduBC_City',
                'BCPNP_App_EduBC_Field' => 'syncA_App_EduBC_Field',
                'BCPNP_App_EduBC_Institution' => 'syncA_App_EduBC_Institution',
                'BCPNP_App_Emp_IncorpNo' => 'syncA_App_Emp_IncorpNo',
                'BCPNP_App_Spouse_BirthPlace' => 'syncA_App_Spouse_BirthPlace',
                'BCPNP_App_Spouse_Citizenship' => 'syncA_App_Spouse_Citizenship',
                'BCPNP_App_Spouse_DOB' => 'syncA_App_Spouse_DOB',
                'BCPNP_App_Spouse_Fname' => 'syncA_App_Spouse_Fname',
                'BCPNP_App_Spouse_Lname' => 'syncA_App_Spouse_Lname',
                'BCPNP_App_Spouse_Sex' => 'syncA_App_Spouse_Sex',
            ),
        );

        $changes[] = array(
            'changeIsNotNeededSince' => strtotime('2017-08-30 14:00:00'), // Phase 4 Round 1
            'formName' => 'EI Registration Form v2',
            'changes' => array(
                'BCPNP_Reg_EnglishLevel' => 'syncA_Reg_EnglishLevel',
                'BCPNP_Reg_Education' => 'syncA_Reg_Education',
                'BCPNP_Reg_RegistrationAge' => 'syncA_Reg_RegistrationAge',
                'BCPNP_Reg_CANWorkExp' => 'syncA_Reg_CANWorkExp',
                'BCPNP_Reg_CANBusExp' => 'syncA_Reg_CANBusExp',
                'BCPNP_Reg_CANStudies' => 'syncA_Reg_CANStudies',
                'BCPNP_Reg_CityVisitedEntry' => 'syncA_Reg_CityVisitedEntry',
                'BCPNP_Reg_CityVisitedExit' => 'syncA_Reg_CityVisitedExit',
            ),
        );

        $changes[] = array(
            'changeIsNotNeededSince' => strtotime('2017-08-30 14:00:00'), // Phase 4 Round 1
            'formName' => 'SI Application Form',
            'changes' => array(
                'BCPNP_App_ResAddrLine' => 'syncA_App_ResAddrLine',
                'BCPNP_App_ResCity' => 'syncA_App_ResCity',
                'BCPNP_App_ResProvince' => 'syncA_App_ResProvince',
                'BCPNP_App_ResCountry' => 'syncA_App_ResCountry',
                'BCPNP_App_ResPostal' => 'syncA_App_ResPostal',
            ),
        );

        return $changes;
    }

}
