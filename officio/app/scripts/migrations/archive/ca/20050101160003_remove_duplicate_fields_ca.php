<?php

use Clients\Service\Clients;
use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class RemoveDuplicateFieldsCa extends AbstractMigration
{
    public function up()
    {
        // Took 950s on local server...
        try {
            $statement = $this->getQueryBuilder()
                ->select(['company_id', 'storage_location'])
                ->from('company')
                ->order(['company_id' => 'ASC'])
                ->execute();

            $arrCompanies = $statement->fetchAll('assoc');

            /** @var Clients $oClients */
            $oClients = self::getService(Clients::class);

            $applicantTypeId  = 0;
            $individualTypeId = $oClients->getMemberTypeIdByName('individual');

            foreach ($arrCompanies as $arrCompanyInfo) {
                $companyId = $arrCompanyInfo['company_id'];

                echo "Company id: #$companyId";

                $arrParentClientFields = $oClients->getApplicantFields()->getCompanyFields(
                    $companyId,
                    $individualTypeId,
                    $applicantTypeId
                );

                $arrCaseFields = $oClients->getFields()->getCompanyFields($companyId);


                // Delete fields that were moved to IA
                $arrFieldIdsToDelete = array();
                foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                    foreach ($arrCaseFields as $arrCaseFieldInfo) {
                        if ($arrCaseFieldInfo['company_field_id'] == $arrParentClientFieldInfo['applicant_field_unique_id']) {
                            $arrFieldIdsToDelete[$arrParentClientFieldInfo['applicant_field_id']] = $arrCaseFieldInfo['field_id'];
                            break;
                        }
                    }
                }

                if (count($arrFieldIdsToDelete)) {
                    $statement = $this->getQueryBuilder()
                        ->select(['*'])
                        ->from('automatic_reminders')
                        ->where(
                            [
                                'company_id' => (int)$companyId,
                                'type'       => 'PROFILE',
                                'prof'       => (int)$arrFieldIdsToDelete
                            ]
                        )
                        ->execute();

                    $arrReminders = $statement->fetchAll('assoc');

                    foreach ($arrFieldIdsToDelete as $applicantId => $caseId) {
                        foreach ($arrReminders as $arrReminderInfo) {
                            if ($arrReminderInfo['prof'] === $caseId) {
                                $this->getQueryBuilder()
                                    ->update('automatic_reminders')
                                    ->set(['prof' => $applicantId])
                                    ->where(['prof' => (int)$caseId])
                                    ->execute();
                            }
                        }
                    }

                    // Delete all moved fields data
                    $this->getQueryBuilder()
                        ->delete('client_form_fields')
                        ->where([
                            'company_id'  => (int)$companyId,
                            'field_id IN' => $arrFieldIdsToDelete
                        ])
                        ->execute();
                }

                echo sprintf("Deleted %d fields (%s)", count($arrFieldIdsToDelete), implode(', ', $arrFieldIdsToDelete)) . PHP_EOL;

                // Delete empty groups (e.g. when we moved all fields from the group)
                $this->query(sprintf("DELETE FROM client_form_groups WHERE company_id = %d AND group_id NOT IN (SELECT group_id FROM client_form_order GROUP BY group_id) AND title <> 'Dependants'", $companyId));
            }

            // Fix the order for Case Details tab for all groups (for the default company)
            $this->execute("UPDATE `client_form_groups` SET `order`=1 WHERE  `title`='For Office Use' AND company_id = 0");
            $this->execute("UPDATE `client_form_groups` SET `order`=2 WHERE  `title`='Staff Responsible for this Case' AND company_id = 0");
            $this->execute("UPDATE `client_form_groups` SET `order`=3 WHERE  `title`='Immigration File Details' AND company_id = 0");
            $this->execute("UPDATE `client_form_groups` SET `order`=4 WHERE  `title`='Dependants' AND company_id = 0");


            /*
             We are deleting these groups from Case Details:
             - Main Details
             - Contact Information
             - Personal Information
             - Personal Info
             - Client Info
             - Contact Info
             - Client Address Info
             Most of their fields go to the new Profile tab & the rest are moved to one common group "Case Info"
             */
            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('client_form_groups')
                ->where(
                    [
                        'title IN'      => ['Main Details', 'Contact Information', 'Personal Information', 'Personal Info', 'Client Info', 'Contact Info', 'Client Address Info'],
                        'company_id !=' => 0,
                        'assigned'      => 'A'
                    ]
                )
                ->execute();

            $arrGroups = $statement->fetchAll('assoc');

            $arrGroupedGroups = array();
            foreach ($arrGroups as $arrGroupInfo) {
                $arrGroupedGroups[$arrGroupInfo['company_id']][] = $arrGroupInfo;
            }

            $arrGroupsToDelete = array();
            foreach ($arrGroupedGroups as $companyId => $arrCompanyGroups) {
                $statement = $this->getQueryBuilder()
                    ->insert(
                        [
                            'company_id',
                            'client_type_id',
                            'title',
                            'order',
                            'cols_count',
                            'regTime',
                            'assigned',
                        ]
                    )
                    ->into('client_form_groups')
                    ->values(
                        [
                            'company_id'     => $companyId,
                            'client_type_id' => $arrCompanyGroups[0]['client_type_id'],
                            'title'          => 'Case Info',
                            'order'          => 0,
                            'cols_count'     => 3,
                            'regTime'        => $arrCompanyGroups[0]['regTime'],
                            'assigned'       => 'A',
                        ]
                    )
                    ->execute();

                $newGroupId = $statement->lastInsertId('client_form_groups');

                $fieldOrder = 0;
                foreach ($arrCompanyGroups as $i => $arrCompanyGroupInfo) {
                    // Allow access for this new group - the same as for the first found group (that will be deleted)
                    if (empty($i)) {
                        $statement = $this->getQueryBuilder()
                            ->select('*')
                            ->from('client_form_group_access')
                            ->where(['group_id' => (int)$arrCompanyGroupInfo['group_id']])
                            ->execute();

                        $arrThisGroupAccess = $statement->fetchAll('assoc');
                        foreach ($arrThisGroupAccess as $thisGroupAccess) {
                            $this->getQueryBuilder()
                                ->insert(
                                    [
                                        'role_id',
                                        'group_id',
                                        'status',
                                    ]
                                )
                                ->into('client_form_group_access')
                                ->values(
                                    [
                                        'role_id'  => $thisGroupAccess['role_id'],
                                        'group_id' => $newGroupId,
                                        'status'   => $thisGroupAccess['status'],
                                    ]
                                )
                                ->execute();
                        }
                    }

                    // Move fields to the new group + change fields order
                    $statement = $this->getQueryBuilder()
                        ->select('*')
                        ->from('client_form_order')
                        ->where(['group_id' => (int)$arrCompanyGroupInfo['group_id']])
                        ->execute();

                    $arrThisGroupFieldsOrder = $statement->fetchAll('assoc');

                    foreach ($arrThisGroupFieldsOrder as $arrThisGroupFieldsOrderInfo) {
                        $this->getQueryBuilder()
                            ->update('client_form_order')
                            ->set([
                                'group_id'    => $newGroupId,
                                'field_order' => $fieldOrder++
                            ])
                            ->where(['order_id' => (int)$arrThisGroupFieldsOrderInfo['order_id']])
                            ->execute();
                    }
                }

                $arrGroupsToDelete[] = $companyId;
            }

            // Delete empty groups for the touched companies
            if (!empty($arrGroupsToDelete)) {
                foreach ($arrGroupsToDelete as $companyId) {
                    $this->query(sprintf("DELETE FROM client_form_groups WHERE company_id = %d AND group_id NOT IN (SELECT group_id FROM client_form_order GROUP BY group_id) AND title <> 'Dependants'", $companyId));
                }
            }


            // Make the "missing docs' description" field as a full line if:
            // - There are no other fields in the same group
            // - This is a memo field
            $arrRes = $this->fetchAll("SELECT *
             FROM client_form_order AS fo
             LEFT JOIN client_form_groups AS g ON fo.group_id = g.group_id
             WHERE fo.group_id IN (
             SELECT o.group_id
             FROM client_form_order AS o
             WHERE o.field_id IN (
             SELECT f.field_id
             FROM client_form_fields AS f
             WHERE company_field_id = 'miss_docs_description' AND f.`type` = 11))
             AND g.assigned = 'A'");


            $arrGrouped = array();
            foreach ($arrRes as $arrOrderRow) {
                $arrGrouped[$arrOrderRow['group_id']][] = $arrOrderRow;
            }

            foreach ($arrGrouped as $arrFieldOrderInfo) {
                if (count($arrFieldOrderInfo) == 1) {
                    $this->getQueryBuilder()
                        ->update('client_form_order')
                        ->set(['use_full_row' => 'Y'])
                        ->where(['order_id' => (int)$arrFieldOrderInfo[0]['order_id']])
                        ->execute();
                }
            }

            echo 'Done.' . PHP_EOL;
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
