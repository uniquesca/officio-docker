<?php

use Officio\Migration\AbstractMigration;

class AddCaseFieldsSyncingSwitch extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` ADD COLUMN `sync_with_default` ENUM('Yes','No','Label') NOT NULL DEFAULT 'Yes' AFTER `skip_access_requirements`;");
        $this->execute("UPDATE `client_form_fields` SET sync_with_default='No' WHERE company_id = 0;");

        // Change the type for the specific field/company
        $this->execute("UPDATE `client_form_default` SET `value`='Hanoi, Vietnam' WHERE `value`='Hanoi, Vietnam '");
        $this->execute("UPDATE `client_form_default` SET `value`='Port Vila, Vanuatu' WHERE `value`='Port Vila, Vanuatu '");
        $this->execute("UPDATE `client_form_fields` SET `type` = 11 WHERE company_field_id = 'miss_docs_description' AND company_id = 4;");

        // Rename 2 specific fields (field text ids)
        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='this_case_is_dependent_of' WHERE company_field_id = 'related_case' AND company_id = 145;");
        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='nomination_subclass' WHERE company_field_id = 'sponsorship_subclass' AND company_id = 145;");

        // Add a missing field, place it to the Unassigned groups
        $arrInsert = [
            'company_id'       => 145,
            'company_field_id' => 'sponsorship_subclass',
            'type'             => 30,
            'label'            => 'Subclass',
            'maxlength'        => 0,
        ];

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrInsert))
            ->into('client_form_fields')
            ->values($arrInsert)
            ->execute();

        $fieldId = $statement->lastInsertId('client_form_fields');

        $arrUnassignedGroups = $this->fetchAll("SELECT * FROM client_form_groups WHERE company_id = 145 AND assigned = 'U'");
        foreach ($arrUnassignedGroups as $arrUnassignedGroupInfo) {
            $arrInsert = [
                'group_id'    => $arrUnassignedGroupInfo['group_id'],
                'field_id'    => $fieldId,
                'field_order' => 0
            ];

            $this->getQueryBuilder()
                ->insert(array_keys($arrInsert))
                ->into('client_form_order')
                ->values($arrInsert)
                ->execute();
        }


        $arrAllCompaniesCaseFields                 = $this->fetchAll('SELECT * FROM client_form_fields');
        $arrAllCompaniesCaseFieldsGroupedByCompany = array();
        foreach ($arrAllCompaniesCaseFields as $arrCompanyCaseFieldInfo) {
            $arrAllCompaniesCaseFieldsGroupedByCompany[$arrCompanyCaseFieldInfo['company_id']][] = $arrCompanyCaseFieldInfo;
        }


        $arrAllCompaniesCaseFieldsOptions                 = $this->fetchAll('SELECT * FROM client_form_default');
        $arrAllCompaniesCaseFieldsOptionsGroupedByCompany = array();
        foreach ($arrAllCompaniesCaseFieldsOptions as $arrCompanyCaseFieldInfo) {
            $arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrCompanyCaseFieldInfo['field_id']][] = strtolower($arrCompanyCaseFieldInfo['value']);
        }

        $arrDefaultCaseFields = $arrAllCompaniesCaseFieldsGroupedByCompany[0];
        unset($arrAllCompaniesCaseFieldsGroupedByCompany[0]);

        $arrSetToNo     = array();
        $arrSetToLabel  = array();
        $arrKeysToCheck = array('type', 'maxlength', 'encrypted', 'required', 'required_for_submission', 'disabled', 'multiple_values', 'skip_access_requirements', 'custom_height', 'min_value', 'max_value');
        foreach ($arrAllCompaniesCaseFieldsGroupedByCompany as $arrCompanyCaseFields) {
            foreach ($arrCompanyCaseFields as $arrCompanyCaseFieldInfo) {
                if ($arrCompanyCaseFieldInfo['company_field_id'] == 'file_status') {
                    continue;
                }

                $booFoundField             = false;
                $booLabelIsDifferent       = false;
                $booAtLeastOneKeyDifferent = false;
                foreach ($arrDefaultCaseFields as $arrDefaultCaseFieldInfo) {
                    if ($arrCompanyCaseFieldInfo['company_field_id'] == $arrDefaultCaseFieldInfo['company_field_id']) {
                        $booFoundField = true;
                        foreach ($arrKeysToCheck as $keyToCheck) {
                            if ($arrDefaultCaseFieldInfo[$keyToCheck] != $arrCompanyCaseFieldInfo[$keyToCheck]) {
                                if ($keyToCheck == 'maxlength' && empty($arrDefaultCaseFieldInfo[$keyToCheck]) && empty($arrCompanyCaseFieldInfo[$keyToCheck])) {
                                    // null or 0 is set, ignore
                                } else {
                                    $booAtLeastOneKeyDifferent = true;
                                }
                            }
                        }

                        if (!$booAtLeastOneKeyDifferent && in_array($arrDefaultCaseFieldInfo['type'], array(3, 6))) {
                            // Check options for combo/radio fields
                            $arrThisFieldOptions    = isset($arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrCompanyCaseFieldInfo['field_id']]) ? $arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrCompanyCaseFieldInfo['field_id']] : array();
                            $arrDefaultFieldOptions = $arrAllCompaniesCaseFieldsOptionsGroupedByCompany[$arrDefaultCaseFieldInfo['field_id']];
                            $arrTheSame             = array_intersect($arrThisFieldOptions, $arrDefaultFieldOptions);

                            if (count($arrTheSame) == count($arrThisFieldOptions) && count($arrTheSame) == count($arrDefaultFieldOptions)) {
                                // options are the same
                            } else {
                                $arrMissingDefaultOptions = array_diff($arrThisFieldOptions, $arrTheSame);
                                if (!empty($arrMissingDefaultOptions)) {
                                    // there is a difference
                                    $booAtLeastOneKeyDifferent = true;
                                }
                            }
                        }

                        if (!$booAtLeastOneKeyDifferent) {
                            $keyToCheck = 'label';
                            if ($arrDefaultCaseFieldInfo[$keyToCheck] != $arrCompanyCaseFieldInfo[$keyToCheck]) {
                                $booLabelIsDifferent = true;
                            }
                        }
                        break;
                    }
                }

                if (!$booFoundField || $booAtLeastOneKeyDifferent) {
                    $arrSetToNo[] = $arrCompanyCaseFieldInfo['field_id'];
                } elseif ($booLabelIsDifferent) {
                    $arrSetToLabel[] = $arrCompanyCaseFieldInfo['field_id'];
                }
            }
        }


        if (!empty($arrSetToNo)) {
            $query = sprintf(
                "UPDATE `client_form_fields` SET `sync_with_default`='No' WHERE `field_id` IN (%s);",
                implode(',', $arrSetToNo)
            );

            $this->execute($query);
        }

        if (!empty($arrSetToLabel)) {
            $query = sprintf(
                "UPDATE `client_form_fields` SET `sync_with_default`='Label' WHERE `field_id` IN (%s);",
                implode(',', $arrSetToLabel)
            );

            $this->execute($query);
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields` DROP COLUMN `sync_with_default`;");
    }
}