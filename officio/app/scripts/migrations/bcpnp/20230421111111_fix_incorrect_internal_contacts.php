<?php

use Officio\Migration\AbstractMigration;

class FixIncorrectInternalContacts extends AbstractMigration
{
    public function up()
    {
        // 1. Remove orphan internal contacts
        // They were previously linked to IAs but are not anymore
        $arrOrphanClientIds = $this->fetchAll("SELECT m.member_id FROM members AS m LEFT JOIN members_relations AS r ON m.member_id = r.child_member_id WHERE m.userType = 9 AND r.parent_member_id IS NULL");
        $arrOrphanClientIds = empty($arrOrphanClientIds) ? [] : array_column($arrOrphanClientIds, 'member_id');

        if (!empty($arrOrphanClientIds)) {
            $this->getQueryBuilder()
                ->delete('members')
                ->whereInList('member_id', $arrOrphanClientIds)
                ->execute();
        }


        // 2. Move data to the correct internal contact and remove extra internal contact for the same IA
        $arrExtraContactsToDelete = [];

        $arrIAsWithExtraInternalContacts = $this->fetchAll("SELECT r.parent_member_id FROM members_relations AS r LEFT JOIN members AS m ON m.member_id = r.child_member_id WHERE m.userType = 9 AND `row` > 0 GROUP BY r.parent_member_id");
        $arrIAsWithExtraInternalContacts = empty($arrIAsWithExtraInternalContacts) ? [] : array_column($arrIAsWithExtraInternalContacts, 'parent_member_id');

        $arrSkipIAFields = $this->fetchAll("SELECT applicant_field_id FROM `applicant_form_order` WHERE applicant_group_id IN (SELECT applicant_group_id FROM applicant_form_groups WHERE applicant_block_id IN (SELECT applicant_block_id FROM applicant_form_blocks WHERE company_id = 1 AND member_type_id = 8 AND contact_block = 'N'))");
        $arrSkipIAFields = empty($arrSkipIAFields) ? [] : array_column($arrSkipIAFields, 'applicant_field_id');
        foreach ($arrIAsWithExtraInternalContacts as $IAClientId) {
            // Load internal contacts for the IA
            $arrIAInternalContacts = $this->fetchAll(sprintf('SELECT r.* FROM members_relations AS r LEFT JOIN members AS m ON m.member_id = r.child_member_id WHERE r.parent_member_id = %d AND m.userType = 9 GROUP BY r.child_member_id', $IAClientId));

            if (count($arrIAInternalContacts) != 2) {
                // IA must have 2 internal contacts that we want to fix
                throw new Exception('There are no 2 internal contacts for ' . $IAClientId);
            }

            $mainInternalContact  = 0;
            $extraInternalContact = 0;
            foreach ($arrIAInternalContacts as $arrIAInternalContactInfo) {
                if (empty($arrIAInternalContactInfo['row'])) {
                    // This is the main internal contact, leave it
                    $mainInternalContact = $arrIAInternalContactInfo['child_member_id'];
                } else {
                    // This internal contact we will remove
                    $extraInternalContact = $arrIAInternalContactInfo['child_member_id'];

                    $arrExtraContactsToDelete[] = $extraInternalContact;
                }
            }

            if (empty($mainInternalContact)) {
                // IA must have 1 main internal contact
                throw new Exception('Main internal contact not found for ' . $IAClientId);
            }

            if (empty($extraInternalContact)) {
                // IA must have 1 extra/incorrect internal contact
                throw new Exception('Extra internal contact not found for ' . $IAClientId);
            }


            // Load + group saved data for IA and internal contacts
            $arrSavedData = $this->fetchAll(sprintf('SELECT * FROM applicant_form_data AS d WHERE d.applicant_id IN (%s)', implode(',', [$IAClientId, $mainInternalContact, $extraInternalContact])));

            $arrGroupedSavedData = [];
            foreach ($arrSavedData as $arrSavedDataRow) {
                // Skip the fields that relate to the IA
                if ($arrSavedDataRow['applicant_id'] == $IAClientId && in_array($arrSavedDataRow['applicant_field_id'], $arrSkipIAFields)) {
                    continue;
                }

                $arrGroupedSavedData[$arrSavedDataRow['applicant_id']][$arrSavedDataRow['applicant_field_id']] = $arrSavedDataRow['value'];
            }


            // Move data from IA to the main/correct internal contact
            if (isset($arrGroupedSavedData[$IAClientId])) {
                foreach ($arrGroupedSavedData[$IAClientId] as $fieldId => $fieldVal) {
                    if (isset($arrGroupedSavedData[$mainInternalContact][$fieldId])) {
                        // Update only if the value isn't the same
                        if ($arrGroupedSavedData[$mainInternalContact][$fieldId] != $fieldVal) {
                            $this->getQueryBuilder()
                                ->update('applicant_form_data')
                                ->set('value', $fieldVal)
                                ->where([
                                    'applicant_id'       => $mainInternalContact,
                                    'applicant_field_id' => $fieldId,
                                ])
                                ->execute();

                            $arrGroupedSavedData[$mainInternalContact][$fieldId] = $fieldVal;
                        }
                    } else {
                        // There is no this field's value saved for the main/correct internal contact
                        $arrDataInsert = [
                            'applicant_id'       => $mainInternalContact,
                            'applicant_field_id' => $fieldId,
                            'value'              => $fieldVal,
                            'row'                => 0,
                        ];

                        $this->getQueryBuilder()
                            ->insert(array_keys($arrDataInsert))
                            ->into('applicant_form_data')
                            ->values($arrDataInsert)
                            ->execute();

                        $arrGroupedSavedData[$mainInternalContact][$fieldId] = $fieldVal;
                    }

                    // Remove value for the IA - it was moved to the internal contact
                    $this->getQueryBuilder()
                        ->delete('applicant_form_data')
                        ->where([
                            'applicant_id'       => $IAClientId,
                            'applicant_field_id' => $fieldId,
                        ])
                        ->execute();
                }
            }


            // Let's do this again for the extra internal contact - has a higher priority
            if (isset($arrGroupedSavedData[$extraInternalContact])) {
                foreach ($arrGroupedSavedData[$extraInternalContact] as $fieldId => $fieldVal) {
                    if (isset($arrGroupedSavedData[$mainInternalContact][$fieldId])) {
                        // Update only if the value isn't the same
                        if ($arrGroupedSavedData[$mainInternalContact][$fieldId] != $fieldVal) {
                            $this->getQueryBuilder()
                                ->update('applicant_form_data')
                                ->set('value', $fieldVal)
                                ->where([
                                    'applicant_id'       => $mainInternalContact,
                                    'applicant_field_id' => $fieldId,
                                ])
                                ->execute();

                            $arrGroupedSavedData[$mainInternalContact][$fieldId] = $fieldVal;
                        }
                    } else {
                        // There is no field's value saved for the main/correct internal contact
                        $arrDataInsert = [
                            'applicant_id'       => $mainInternalContact,
                            'applicant_field_id' => $fieldId,
                            'value'              => $fieldVal,
                            'row'                => 0,
                        ];

                        $this->getQueryBuilder()
                            ->insert(array_keys($arrDataInsert))
                            ->into('applicant_form_data')
                            ->values($arrDataInsert)
                            ->execute();

                        $arrGroupedSavedData[$mainInternalContact][$fieldId] = $fieldVal;
                    }
                }
            }
        }

        if (!empty($arrExtraContactsToDelete)) {
            $this->getQueryBuilder()
                ->delete('members')
                ->whereInList('member_id', $arrExtraContactsToDelete)
                ->execute();
        }


        // 3. And finally fix incorrectly placed IA's values
        // that were not moved in the step #2 (if client's profile was not saved from the GUI)
        $arrIncorrectData = $this->fetchAll("SELECT * FROM applicant_form_data AS d WHERE d.applicant_id IN (SELECT member_id FROM members WHERE userType = 8) AND d.applicant_field_id IN (SELECT applicant_field_id FROM `applicant_form_order` WHERE applicant_group_id IN (SELECT applicant_group_id FROM applicant_form_groups WHERE applicant_block_id IN (SELECT applicant_block_id FROM applicant_form_blocks WHERE company_id = 1 AND member_type_id = 8 AND contact_block = 'Y')))");
        if (!empty($arrIncorrectData)) {
            // Group saved data by IA
            $arrIncorrectDataGrouped = [];
            foreach ($arrIncorrectData as $arrIncorrectDataRow) {
                $arrIncorrectDataGrouped[$arrIncorrectDataRow['applicant_id']][$arrIncorrectDataRow['applicant_field_id']] = $arrIncorrectDataRow['value'];
            }

            foreach ($arrIncorrectDataGrouped as $IAClientId => $arrIncorrectData) {
                // Find the internal contact for the IA
                $arrIAInternalContacts = $this->fetchAll(sprintf('SELECT r.child_member_id FROM members_relations AS r LEFT JOIN members AS m ON m.member_id = r.child_member_id WHERE r.parent_member_id = %d AND m.userType = 9 GROUP BY r.child_member_id', $IAClientId));

                if (count($arrIAInternalContacts) != 1) {
                    throw new Exception('Main internal contact not found for ' . $IAClientId);
                }

                $mainInternalContact = $arrIAInternalContacts[0]['child_member_id'];

                $arrInternalContactSavedData        = $this->fetchAll(sprintf('SELECT * FROM applicant_form_data AS d WHERE d.applicant_id IN (%s)', $mainInternalContact));
                $arrInternalContactGroupedSavedData = [];
                foreach ($arrInternalContactSavedData as $arrSavedDataRow) {
                    $arrInternalContactGroupedSavedData[$arrSavedDataRow['applicant_field_id']] = $arrSavedDataRow['value'];
                }


                // Move the data to the internal contact
                foreach ($arrIncorrectData as $fieldId => $fieldVal) {
                    if (isset($arrInternalContactGroupedSavedData[$fieldId])) {
                        // Update only if the value isn't the same
                        if ($arrInternalContactGroupedSavedData[$fieldId] != $fieldVal) {
                            $this->getQueryBuilder()
                                ->update('applicant_form_data')
                                ->set('value', $fieldVal)
                                ->where([
                                    'applicant_id'       => $mainInternalContact,
                                    'applicant_field_id' => $fieldId,
                                ])
                                ->execute();
                        }
                    } else {
                        // There is no field's value saved for the internal contact
                        $arrDataInsert = [
                            'applicant_id'       => $mainInternalContact,
                            'applicant_field_id' => $fieldId,
                            'value'              => $fieldVal,
                            'row'                => 0,
                        ];

                        $this->getQueryBuilder()
                            ->insert(array_keys($arrDataInsert))
                            ->into('applicant_form_data')
                            ->values($arrDataInsert)
                            ->execute();

                        $arrGroupedSavedData[$mainInternalContact][$fieldId] = $fieldVal;
                    }

                    // Remove value for the IA - it was moved to the internal contact
                    $this->getQueryBuilder()
                        ->delete('applicant_form_data')
                        ->where([
                            'applicant_id'       => $IAClientId,
                            'applicant_field_id' => $fieldId,
                        ])
                        ->execute();
                }
            }
        }
    }

    public function down()
    {
    }
}
