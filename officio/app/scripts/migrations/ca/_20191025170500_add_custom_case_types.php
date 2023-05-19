<?php

use Officio\Migration\AbstractMigration;

class AddCustomCaseTypes extends AbstractMigration
{
    public function up()
    {
        exit('No!');

        $arrToCreate = array(
            'employer' => array(
                'New Employer Case Type' => array(
                    'Employer Group Name 1' => array(
                        'file_number', 'file_status', 'Client_file_status', 'LMO_number'
                    ),

                    'Employer Group Name 2' => array(
                        'sales_and_marketing', 'processing', 'accounting', 'categories'
                    )
                ),
            ),

            'individual' => array(
                'New IA Case Type' => array(
                    'Group Name 1' => array(
                        'file_number', 'file_status', 'Client_file_status',
                    ),

                    'Group Name 2' => array(
                        'date_client_signed', 'interview_date', 'sales_and_marketing', 'processing', 'accounting'
                    )
                ),

                'New IA Case Type 2' => array(
                    'Another Group Name 1' => array(
                        'file_number', 'file_status', 'Client_file_status', 'categories'
                    ),

                    'Another Group Name 2' => array(
                        'sales_and_marketing', 'processing', 'accounting', 'LMO_number', 'medical_issued_date', 'additional_info_file_details'
                    )
                ),
            ),
        );

        $statement = $this->getQueryBuilder()
            ->select(array('company_id'))
            ->from('company')
            ->execute();

        $arrCompanies = array_column($statement->fetchAll('assoc'), 'company_id');

        foreach ($arrCompanies as $companyId) {
            $statement = $this->getQueryBuilder()
                ->select(array('field_id', 'company_field_id'))
                ->from('client_form_fields')
                ->where(
                    [
                        'company_id' => (int)$companyId
                    ]
                )
                ->execute();

            $arrCompanyFields = $statement->fetchAll('assoc');

            $statement = $this->getQueryBuilder()
                ->select(array('role_id', 'role_type'))
                ->from('acl_roles')
                ->whereInList('role_type', array('user', 'admin', 'individual_client', 'employer_client'))
                ->andWhere(
                    [
                        'company_id' => (int)$companyId
                    ]
                )
                ->execute();

            $arrCompanyRoles = $statement->fetchAll('assoc');

            $arrCompanyFieldIdsGrouped = array();
            foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                $arrCompanyFieldIdsGrouped[$arrCompanyFieldInfo['company_field_id']] = $arrCompanyFieldInfo['field_id'];
            }

            foreach ($arrToCreate as $memberType => $arrGroupsAndFields) {

                foreach ($arrGroupsAndFields as $caseTypeLabel => $arrGroups) {
                    $statement = $this->getQueryBuilder()
                        ->insert(
                            array(
                                'company_id',
                                'client_type_name',
                                'client_type_needs_ia',
                                'client_type_employer_sponsorship'
                            )
                        )
                        ->into('client_types')
                        ->values(
                            array(
                                'company_id'                       => $companyId,
                                'client_type_name'                 => $caseTypeLabel,
                                'client_type_needs_ia'             => $memberType == 'individual' ? 'Y' : 'N',
                                'client_type_employer_sponsorship' => $memberType == 'employer' ? 'Y' : 'N'
                            )
                        )
                        ->execute();

                    $newCaseTypeId = $statement->lastInsertId('client_types');

                    $this->insert(
                        'client_types_kinds',
                        array(
                            'client_type_id' => $newCaseTypeId,
                            'member_type_id' => $memberType == 'employer' ? 7 : 8,
                        )
                    );

                    $groupOrder = 0;
                    foreach ($arrGroups as $groupLabel => $arrFields) {
                        $statement = $this->getQueryBuilder()
                            ->insert(
                                array(
                                    'company_id',
                                    'client_type_id',
                                    'title',
                                    'order',
                                    'cols_count',
                                    'collapsed',
                                    'regTime',
                                    'assigned'
                                )
                            )
                            ->into('client_form_groups')
                            ->values(
                                array(
                                    'company_id'     => $companyId,
                                    'client_type_id' => $newCaseTypeId,
                                    'title'          => $groupLabel,
                                    'order'          => $groupOrder++,
                                    'cols_count'     => 3,
                                    'collapsed'      => 'N',
                                    'regTime'        => time(),
                                    'assigned'       => 'A'
                                )
                            )
                            ->execute();

                        $newGroupId = $statement->lastInsertId('client_form_groups');


                        $fieldOrder         = 0;
                        $countFieldsInGroup = 0;
                        foreach ($arrFields as $fieldId) {
                            if (!isset($arrCompanyFieldIdsGrouped[$fieldId])) {
                                // cannot be here
                                continue;
                            }

                            $this->insert(
                                'client_form_order',
                                array(
                                    'group_id'     => $newGroupId,
                                    'field_id'     => $arrCompanyFieldIdsGrouped[$fieldId],
                                    'use_full_row' => 'N',
                                    'field_order'  => $fieldOrder++,
                                )
                            );

                            $countFieldsInGroup++;
                        }

                        if (!empty($countFieldsInGroup)) {
                            foreach ($arrCompanyRoles as $arrCompanyRoleInfo) {
                                $this->insert(
                                    'client_form_group_access',
                                    array(
                                        'role_id'  => $arrCompanyRoleInfo['role_id'],
                                        'group_id' => $newGroupId,
                                        'status'   => 'F'
                                    )
                                );
                            }
                        }
                    }
                }
            }

        }

        exit('STOP!');
    }

    public function down()
    {
    }
}