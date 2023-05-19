<?php

namespace Forms\Service;

use Clients\Service\Clients;
use Clients\Service\Clients\Fields;
use Exception;
use Forms\XfdfParseResult;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\TableIdentifier;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Settings;

class XfdfDbSync extends BaseService
{

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
    }

    /**
     * Check if there are records in a specific table
     *
     * @param string $strTable
     * @param array $arrWhere
     * @return bool
     */
    private function _checkRowExists($strTable, $arrWhere)
    {
        $select = (new Select())
            ->from($strTable)
            ->columns(['fields_count' => new Expression('COUNT(*)')])
            ->where($arrWhere);

        return $this->_db2->fetchOne($select) > 0;
    }

    /**
     * Check if field value means
     * that this row must be not used in DB
     *
     * @param string $fieldName
     * @param string $fieldVal
     *
     * @return bool true if row must be not used in DB
     */
    private function _isFieldValueNA($fieldName, $fieldVal)
    {
        $booIsNA = false;

        // Check only these 2 fields
        if (in_array($fieldName, array('fName', 'lName'))) {
            // Such field values mean that this row must be not used in DB
            // I.e. removed from db, but not from xfdf
            $arrNAValues = array(
                '',
                'sans objet',
                's.o.',
                'so',
                's/o',

                'not applicable',
                'n.a.',
                'na',
                'n/a',
                'n.o.',
                'no',
                'n/o',
            );

            $fieldVal = strtolower(trim($fieldVal));
            if (in_array($fieldVal, $arrNAValues)) {
                $booIsNA = true;
            }
        }

        return $booIsNA;
    }

    public function syncXfdfResultToDb(XfdfParseResult $parseResult)
    {
        $updateProfileFields = $parseResult->profileFieldsToUpdate;
        $arrParentsData      = $parseResult->parentsData;

        // Update fields in database
        if (is_array($updateProfileFields) && count($updateProfileFields)) {
            // Get column names list from updating tables
            $select = (new Select())
                ->from(new TableIdentifier('Columns', 'INFORMATION_SCHEMA'))
                ->columns(['COLUMN_NAME'])
                ->where(['TABLE_NAME' => 'members']);

            $arrMembersColumns = $this->_db2->fetchCol($select);

            // Clear one part so we can redefine it
            $select->reset(Select::WHERE);

            // And specify a different column
            $select->where(['TABLE_NAME' => 'client_form_dependents']);

            $arrDependentsColumns = $this->_db2->fetchCol($select);

            // Load saved dependents list and save their order for later usage
            $arrLines           = array();
            $maxLine            = 0;
            $arrSavedDependents = $this->_clients->getFields()->getDependents(array($parseResult->updateMemberId), false);
            foreach ($arrSavedDependents as $arrSavedDependentInfo) {
                $arrLines[$arrSavedDependentInfo['relationship']][] = $arrSavedDependentInfo['line'];
                $maxLine                                            = max($maxLine, $arrSavedDependentInfo['line']) + 1;
            }

            // Get parent for the case
            $arrParents = $this->_clients->getParentsForAssignedApplicants(array($parseResult->updateMemberId), false, false);

            if (!is_array($arrParents)) {
                $parseResult->code = Pdf::XFDF_INCORRECT_INCOMING_XFDF;
                return $parseResult;
            } else {
                $booIncorrectXfdf = false;
                foreach ($arrParents as $parent) {
                    if ($parent['child_member_id'] == $parseResult->updateMemberId) {
                        $booIncorrectXfdf = true;
                    }
                }
                if (!$booIncorrectXfdf) {
                    $parseResult->code = Pdf::XFDF_INCORRECT_INCOMING_XFDF;
                    return $parseResult;
                }
            }

            $arrCaseParentData = array();
            $arrCaseData       = array();
            foreach ($updateProfileFields as $familyMemberId => $arrUpdateInfo) {
                if (!is_array($arrUpdateInfo)) {
                    continue;
                }

                $arrToUpdate           = array();
                $arrDependentsToUpdate = array();
                $arrProfileSynFields   = $this->_clients->getFields()->getProfileSyncFields($familyMemberId);

                // Update client's profile info
                switch ($familyMemberId) {
                    case 'main_applicant':
                        foreach ($arrUpdateInfo as $updateFieldName => $updateFieldInfo) {
                            foreach ($updateFieldInfo as $parentMemberTypeId => $updateFieldVal) {
                                if (is_array($updateFieldVal) && array_key_exists('country', $updateFieldVal)) {
                                    $updateFieldVal = (string)$updateFieldVal['country'];
                                } else {
                                    $updateFieldVal = (string)$updateFieldVal;
                                }

                                if (in_array($updateFieldName, $arrMembersColumns)) {
                                    $memberId       = $parseResult->mainParentId;
                                    $memberTypeName = $this->_clients->getMemberTypeNameById($parentMemberTypeId);

                                    if ($memberTypeName != 'case') {
                                        foreach ($arrParentsData as $parent) {
                                            if ($parent['parent_member_type_id'] == $parentMemberTypeId) {
                                                $memberId = $parent['parent_member_id'];
                                                break;
                                            }
                                        }
                                    }

                                    $arrToUpdate[$memberId]['members'][$updateFieldName] = array(
                                        'val' => $updateFieldVal
                                    );
                                } else { // Dynamic field
                                    // Get id of dynamic field by company field id
                                    $booIsCaseField = true;
                                    $id             = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId($updateFieldName, $parseResult->updateCompanyId);

                                    $updateParentId = $arrParentsData[0]['parent_member_id'];

                                    if (empty($id)) {
                                        $booIsCaseField = false;

                                        // Identify field id by its unique id
                                        // and internal contact id we need update value for
                                        foreach ($arrParentsData as $parent) {
                                            foreach ($parent['fields']['blocks'] as $arrBlockInfo) {
                                                foreach ($arrBlockInfo['block_groups'] as $arrGroupInfo) {
                                                    foreach ($arrGroupInfo['group_fields'] as $arrFieldInfo) {
                                                        if ($arrFieldInfo['applicant_field_unique_id'] == $updateFieldName && $parentMemberTypeId == $parent['parent_member_type_id']) {
                                                            // Found field
                                                            $id = $arrFieldInfo['applicant_field_id'];
                                                            // Search for internal contact we need save data to
                                                            if ($arrBlockInfo['block_is_contact'] == 'Y') {
                                                                foreach ($parent['internal_contacts'] as $arrAssignedInternalContact) {
                                                                    if ($arrAssignedInternalContact['applicant_group_id'] == $arrGroupInfo['group_id']) {
                                                                        $updateParentId = $arrAssignedInternalContact['child_member_id'];
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                            break 3;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    if (!empty($id) && is_array($arrProfileSynFields)) {
                                        foreach ($arrProfileSynFields as $profileSyncField) {
                                            if (($profileSyncField['id'] == $updateFieldName) && (is_array($profileSyncField) && array_key_exists('type', $profileSyncField))) {
                                                switch ($profileSyncField['type']) {
                                                    case 'combo':
                                                        if (!$booIsCaseField) {
                                                            // Use option id instead of the value
                                                            foreach ($arrParentsData as $parent) {
                                                                foreach ($parent['field_options'] as $arrParentFieldOptionInfo) {
                                                                    if ($arrParentFieldOptionInfo['applicant_field_id'] == $id && $arrParentFieldOptionInfo['value'] == $updateFieldVal) {
                                                                        $updateFieldVal = $arrParentFieldOptionInfo['applicant_form_default_id'];
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        break;

                                                    case 'date':
                                                    case 'date_repeatable':
                                                        if (is_array($updateFieldVal)) {
                                                            // Collect date field from parts
                                                            if (isset($updateFieldVal['date_year']) &&
                                                                isset($updateFieldVal['date_month']) &&
                                                                isset($updateFieldVal['date_day']) &&
                                                                !empty($updateFieldVal['date_year']) &&
                                                                !empty($updateFieldVal['date_month']) &&
                                                                !empty($updateFieldVal['date_day'])
                                                            ) {
                                                                // Make additional check
                                                                if (checkdate((int)$updateFieldVal['date_month'], (int)$updateFieldVal['date_day'], (int)$updateFieldVal['date_year'])) {
                                                                    $updateFieldVal = $updateFieldVal['date_year'] . '-' . $updateFieldVal['date_month'] . '-' . $updateFieldVal['date_day'];
                                                                } else {
                                                                    $updateFieldVal = '';
                                                                }
                                                            }
                                                        } elseif (!empty($updateFieldVal) && Settings::isValidDateFormat((string)$updateFieldVal, Settings::DATE_XFDF)) {
                                                            $updateFieldVal = $this->_settings->reformatDate((string)$updateFieldVal, Settings::DATE_XFDF, Settings::DATE_UNIX);
                                                        }
                                                        break;
                                                }
                                            }
                                        }

                                        // Collect data we want update - for case or for parent client (maybe for his internal contact)
                                        if ($booIsCaseField) {
                                            $arrCaseData[$id] = $updateFieldVal;
                                        } else {
                                            $arrCaseParentData[$updateParentId][] = array(
                                                'field_id' => $id,
                                                'value'    => (string)$updateFieldVal,
                                                'row'      => 0,
                                                'row_id'   => 0
                                            );
                                        }
                                    }
                                }
                            }
                        }

                        break;

                    default:
                        // Update info not for 'main_applicant'
                        foreach ($arrUpdateInfo as $updateFieldName => $updateFieldInfo) {
                            foreach ($updateFieldInfo as $updateFieldVal) {
                                if (!in_array($updateFieldName, $arrDependentsColumns) || !preg_match('/^(spouse|child|parent|sibling|other)(\d*)$/', $familyMemberId, $regs)) {
                                    continue;
                                }

                                $parsedFamilyMemberId = $regs[1];
                                $id                   = empty($regs[2]) ? 0 : (int)$regs[2] - 1;
                                $id                   = max(0, $id);

                                if (is_array($arrProfileSynFields)) {
                                    foreach ($arrProfileSynFields as $profileSyncField) {
                                        if (($profileSyncField['id'] == $updateFieldName) && (is_array($profileSyncField) && array_key_exists('type', $profileSyncField))) {
                                            switch ($profileSyncField['type']) {
                                                case 'country':
                                                    $updateFieldVal = $updateFieldVal['country'];
                                                    break;

                                                case 'combo':
                                                    if (array_key_exists('field_options', $profileSyncField)) {
                                                        foreach ($profileSyncField['field_options'] as $arrSyncFieldOptionInfo) {
                                                            if ($arrSyncFieldOptionInfo['option_name'] == $updateFieldVal) {
                                                                $updateFieldVal = $arrSyncFieldOptionInfo['option_id'];
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    break;

                                                case 'date':
                                                case 'date_repeatable':
                                                    if (is_array($updateFieldVal)) {
                                                        // Collect date field from parts
                                                        if (isset($updateFieldVal['date_year']) &&
                                                            isset($updateFieldVal['date_month']) &&
                                                            isset($updateFieldVal['date_day']) &&
                                                            !empty($updateFieldVal['date_year']) &&
                                                            !empty($updateFieldVal['date_month']) &&
                                                            !empty($updateFieldVal['date_day'])
                                                        ) {
                                                            // Make additional check
                                                            if (checkdate((int)$updateFieldVal['date_month'], (int)$updateFieldVal['date_day'], (int)$updateFieldVal['date_year'])) {
                                                                $updateFieldVal = $updateFieldVal['date_year'] . '-' . $updateFieldVal['date_month'] . '-' . $updateFieldVal['date_day'];
                                                            } else {
                                                                $updateFieldVal = '';
                                                            }
                                                        }
                                                    } elseif (!empty($updateFieldVal) && Settings::isValidDateFormat((string)$updateFieldVal, Settings::DATE_XFDF)) {
                                                        $updateFieldVal = $this->_settings->reformatDate((string)$updateFieldVal, Settings::DATE_XFDF, Settings::DATE_UNIX);
                                                    }
                                                    break;
                                            }
                                        }
                                    }
                                } // if is array

                                $arrDependentsToUpdate[$parsedFamilyMemberId][$id][$updateFieldName] = $updateFieldVal;
                            }
                        }
                        break;
                } // switch

                // Update case's parent fields in DB
                if (is_array($arrCaseParentData) && count($arrCaseParentData)) {
                    foreach ($arrCaseParentData as $applicantId => $arrApplicantData) {
                        $arrUpdateResult = $this->_clients->updateApplicantData($applicantId, $arrApplicantData);
                        if (!$arrUpdateResult['success']) {
                            $parseResult->code = Pdf::XFDF_FILE_NOT_SAVED;
                            return $parseResult;
                        }
                    }
                }

                // Update client's fields in DB
                if (is_array($arrCaseData) && count($arrCaseData)) {
                    $this->_clients->saveClientData($parseResult->updateMemberId, $arrCaseData);
                }

                if (is_array($arrToUpdate)) {
                    foreach ($arrToUpdate as $parentId => $arrParentUpdate) {
                        foreach ($arrParentUpdate as $tableName => $arrToUpdate1) {
                            foreach ($arrToUpdate1 as $fieldName => $update) {
                                $booUpdateName = ($tableName == 'members' && in_array($fieldName, array('fName', 'lName')));

                                // Remove unnecessary symbols
                                $filteredVal = (string)$update['val'];
                                if ($booUpdateName) {
                                    $filteredVal = Fields::filterName($filteredVal);
                                }

                                // Update changes
                                if ($booUpdateName && (empty($filteredVal) || !Fields::validName($filteredVal))) {
                                    // Skip - fName and lName must be not empty and valid
                                } else {
                                    $arrWhere = [
                                        'member_id' => $parentId
                                    ];

                                    // Check if we need update or insert
                                    if (!$this->_checkRowExists($tableName, $arrWhere)) {
                                        throw new Exception('Member record must be created before.');
                                    } else {
                                        $this->_db2->update(
                                            $tableName,
                                            [$fieldName => $filteredVal],
                                            $arrWhere
                                        );
                                    }
                                }
                            }
                        }
                    }
                } // Update fields in 'members' and 'clients' tables in DB

                // Update dependent's info
                if (is_array($arrDependentsToUpdate) && count($arrDependentsToUpdate)) {
                    foreach ($arrDependentsToUpdate as $relationship => $arrDependentData) {
                        foreach ($arrDependentData as $dependentId => $arrToInsert) {
                            $booNeedInsert      = true;
                            $arrDependentInsert = array();

                            // Check if field must be updated
                            foreach ($arrToInsert as $insertFieldId => $insertFieldVal) {
                                $filteredVal = (string)$insertFieldVal;

                                // Collect info
                                $arrDependentInsert[$insertFieldId] = $filteredVal;

                                // Check if field val is N/A -
                                // in this case new row must be not created
                                if ($booNeedInsert && $this->_isFieldValueNA($insertFieldId, $filteredVal)) {
                                    $booNeedInsert = false;
                                }
                            }

                            // Make sure that value of the 'migrating' field is correct
                            if (array_key_exists('migrating', $arrDependentInsert)) {
                                $booCorrectValue    = false;
                                $arrDependantFields = $this->_clients->getFields()->getDependantFields();
                                foreach ($arrDependantFields as $arrDependantFieldInfo) {
                                    if ($arrDependantFieldInfo['field_id'] == 'migrating') {
                                        $arrOptions = $arrDependantFieldInfo['field_options'];
                                        foreach ($arrOptions as $arrOptionInfo) {
                                            if ($arrOptionInfo['option_id'] == strtolower($arrDependentInsert['migrating'] ?? '')) {
                                                $booCorrectValue = true;
                                                break;
                                            }
                                        }
                                        break;
                                    }
                                }

                                if (!$booCorrectValue) {
                                    unset($arrDependentInsert['migrating']);
                                }
                            }

                            if (array_key_exists('DOB', $arrDependentInsert) && Settings::isDateEmpty($arrDependentInsert['DOB'])) {
                                unset($arrDependentInsert['DOB']);
                            }

                            if (array_key_exists('passport_date', $arrDependentInsert) && Settings::isDateEmpty($arrDependentInsert['passport_date'])) {
                                unset($arrDependentInsert['passport_date']);
                            }

                            // If there is something that we need insert
                            if (!empty($arrDependentInsert)) {
                                $line = null;
                                if (array_key_exists($relationship, $arrLines) && array_key_exists($dependentId, $arrLines[$relationship])) {
                                    $line = $arrLines[$relationship][$dependentId];
                                }

                                // Check if we need insert or update
                                if (is_null($line)) {
                                    // Insert or skip
                                    if ($booNeedInsert) {
                                        $arrDependentInsert['member_id']    = $parseResult->updateMemberId;
                                        $arrDependentInsert['relationship'] = $relationship;
                                        $arrDependentInsert['line']         = $maxLine;

                                        $this->_db2->insert('client_form_dependents', $arrDependentInsert);
                                        $maxLine++;
                                    }
                                } else {
                                    // Update
                                    $this->_db2->update(
                                        'client_form_dependents',
                                        $arrDependentInsert,
                                        [
                                            'member_id'    => (int)$parseResult->updateMemberId,
                                            'relationship' => $relationship,
                                            'line'         => (int)$line
                                        ]
                                    );
                                }
                            }
                        }
                    }
                } // Update dependent's info

            } // foreach $arrProfileFieldsUpdate

            // Update some fields in the 'data' table (for internal contact records) if they were updated
            // for the parent client
            foreach ($arrParentsData as $parent) {
                $this->_clients->updateMainContactFromApplicant($parent['parent_member_id']);
            }

            // Update 'Updated On', 'Updated By' columns
            $strUpdatedOn = '';
            if (is_numeric($parseResult->pdfId) && is_numeric($parseResult->updateMemberId)) {
                $strUpdatedOn  = date('Y-m-d H:i:s');
                $arrUpdateData = array(
                    'updated_by'      => $parseResult->currentMemberId,
                    'last_update_date' => $strUpdatedOn
                );

                $this->_db2->update('form_assigned', $arrUpdateData, ['form_assigned_id' => $parseResult->pdfId]);

                $this->_db2->update(
                    'clients',
                    ['modified_by' => $parseResult->currentMemberId, 'modified_on' => date('c')],
                    ['member_id' => $parseResult->updateMemberId]
                );
            }

            $parseResult->updatedOn = $strUpdatedOn;
        }

        return $parseResult;
    }
}