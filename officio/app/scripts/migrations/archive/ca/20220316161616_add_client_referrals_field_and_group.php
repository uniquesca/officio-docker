<?php

use Officio\Migration\AbstractMigration;

class AddClientReferralsFieldAndGroup extends AbstractMigration
{
    public function up()
    {
        // Get the list of all companies
        $statement = $this->getQueryBuilder()
            ->select(array('company_id'))
            ->from('company')
            ->order('company_id')
            ->execute();

        $arrCompanies = array_column($statement->fetchAll('assoc'), 'company_id');

        // Get the first block id for Individuals
        $statement = $this->getQueryBuilder()
            ->select(['company_id', 'applicant_block_id'])
            ->from('applicant_form_blocks')
            ->where([
                'member_type_id' => 8,
                'contact_block'  => 'Y',
            ])
            ->execute();

        $arrSavedBlocks = $statement->fetchAll('assoc');

        $arrIndividualGroupedBlocks = [];
        foreach ($arrSavedBlocks as $arrSavedBlockInfo) {
            $arrIndividualGroupedBlocks[$arrSavedBlockInfo['company_id']] = $arrSavedBlockInfo['applicant_block_id'];
        }


        // Get group id for the Internal contact's main group
        $statement = $this->getQueryBuilder()
            ->select(['g.company_id', 'g.applicant_group_id'])
            ->from(array('b' => 'applicant_form_blocks'))
            ->innerJoin(array('g' => 'applicant_form_groups'), 'b.applicant_block_id = g.applicant_block_id')
            ->where([
                'b.member_type_id' => 9,
            ])
            ->execute();

        $arrSavedInternalContactGroups = $statement->fetchAll('assoc');

        $arrInternalContactGroups = [];
        foreach ($arrSavedInternalContactGroups as $arrSavedInternalContactGroup) {
            $arrInternalContactGroups[$arrSavedInternalContactGroup['company_id']] = $arrSavedInternalContactGroup['applicant_group_id'];
        }


        // Get the first group for the Individual
        $statement = $this->getQueryBuilder()
            ->select(['applicant_group_id'])
            ->from(array('g' => 'applicant_form_groups'))
            ->where([
                'g.applicant_block_id IN ' => array_values($arrIndividualGroupedBlocks),
                'g.order'                  => 0,
            ])
            ->execute();

        $arrIndividualGroupsForAccess = array_column($statement->fetchAll('assoc'), 'applicant_group_id');

        // Find access rights for the first individual group + last name field
        $statement = $this->getQueryBuilder()
            ->select(['g.company_id', 'fa.role_id', 'fa.status'])
            ->from(array('fa' => 'applicant_form_fields_access'))
            ->innerJoin(array('g' => 'applicant_form_groups'), 'fa.applicant_group_id = g.applicant_group_id')
            ->innerJoin(array('f' => 'applicant_form_fields'), 'fa.applicant_field_id = f.applicant_field_id')
            ->innerJoin(array('r' => 'acl_roles'), 'r.role_id = fa.role_id')
            ->where([
                'r.role_type IN '             => ['user', 'admin'],
                'fa.applicant_group_id IN '   => $arrIndividualGroupsForAccess,
                'f.member_type_id'            => 9,
                'f.applicant_field_unique_id' => 'last_name',
            ])
            ->execute();

        $arrSavedDefaultIndividualFieldAccess = $statement->fetchAll('assoc');

        $arrGroupedDefaultIndividualFieldAccess = [];
        foreach ($arrSavedDefaultIndividualFieldAccess as $arrSavedDefaultIndividualFieldAccessRow) {
            $arrGroupedDefaultIndividualFieldAccess[$arrSavedDefaultIndividualFieldAccessRow['company_id']][] = [
                'role_id' => $arrSavedDefaultIndividualFieldAccessRow['role_id'],
                'status'  => $arrSavedDefaultIndividualFieldAccessRow['status'],
            ];
        }


        // Default saved role access rights for the last name field
        $statement = $this->getQueryBuilder()
            ->select(['f.company_id', 'fa.role_id', 'fa.access'])
            ->from(array('fa' => 'applicant_form_fields_access_default'))
            ->innerJoin(array('f' => 'applicant_form_fields'), 'f.applicant_field_id = fa.applicant_field_id')
            ->innerJoin(array('r' => 'acl_roles'), 'r.role_id = fa.role_id')
            ->where([
                'r.role_type IN '             => ['user', 'admin'],
                'f.member_type_id'            => 9,
                'f.applicant_field_unique_id' => 'last_name',
            ])
            ->execute();

        $arrSavedDefaultFieldAccess = $statement->fetchAll('assoc');

        $arrGroupeddDefaultFieldAccess = [];
        foreach ($arrSavedDefaultFieldAccess as $arrDefaultFieldAccess) {
            $arrGroupeddDefaultFieldAccess[$arrDefaultFieldAccess['company_id']][] = [
                'role_id' => $arrDefaultFieldAccess['role_id'],
                'access'  => $arrDefaultFieldAccess['access'],
            ];
        }


        // For each company:
        //  - create a new field + group, place this field in the group
        //  - assign correct access rights to them
        foreach ($arrCompanies as $companyId) {
            $arrClientFieldInsert = [
                'member_type_id'            => 9,
                'company_id'                => $companyId,
                'applicant_field_unique_id' => 'client_referrals',
                'type'                      => 'client_referrals',
                'label'                     => 'Client Referrals'
            ];

            $statement = $this->getQueryBuilder()
                ->insert(array_keys($arrClientFieldInsert))
                ->into('applicant_form_fields')
                ->values($arrClientFieldInsert)
                ->execute();

            $fieldId = $statement->lastInsertId('applicant_form_fields');


            $arrClientGroupInsert = [
                'applicant_block_id' => $arrIndividualGroupedBlocks[$companyId],
                'company_id'         => $companyId,
                'title'              => 'Referrals by this Client',
                'cols_count'         => 1,
                'collapsed'          => 'Y',
                'order'              => 2
            ];

            $statement = $this->getQueryBuilder()
                ->insert(array_keys($arrClientGroupInsert))
                ->into('applicant_form_groups')
                ->values($arrClientGroupInsert)
                ->execute();

            $groupId = $statement->lastInsertId('applicant_form_groups');


            // For internal contact
            $arrInternalClientGroupInsert = [
                'applicant_group_id' => $arrInternalContactGroups[$companyId],
                'applicant_field_id' => $fieldId,
                'use_full_row'       => 'N',
                'field_order'        => 65,
            ];

            $this->getQueryBuilder()
                ->insert(array_keys($arrInternalClientGroupInsert))
                ->into('applicant_form_order')
                ->values($arrInternalClientGroupInsert)
                ->execute();


            // For IA
            $arrNewGroupInsert = [
                'applicant_group_id' => $groupId,
                'applicant_field_id' => $fieldId,
                'use_full_row'       => 'N',
                'field_order'        => 0,
            ];

            $this->getQueryBuilder()
                ->insert(array_keys($arrNewGroupInsert))
                ->into('applicant_form_order')
                ->values($arrNewGroupInsert)
                ->execute();

            if (isset($arrGroupedDefaultIndividualFieldAccess[$companyId])) {
                foreach ($arrGroupedDefaultIndividualFieldAccess[$companyId] as $arrDefaultIndividualAccessRightsRow) {
                    $arrFieldAccessInsert = [
                        'role_id'            => $arrDefaultIndividualAccessRightsRow['role_id'],
                        'applicant_group_id' => $groupId, // For IA
                        'applicant_field_id' => $fieldId,
                        'status'             => $arrDefaultIndividualAccessRightsRow['status'],
                    ];

                    $this->getQueryBuilder()
                        ->insert(array_keys($arrFieldAccessInsert))
                        ->into('applicant_form_fields_access')
                        ->values($arrFieldAccessInsert)
                        ->execute();
                }
            }

            if (isset($arrGroupeddDefaultFieldAccess[$companyId])) {
                foreach ($arrGroupeddDefaultFieldAccess[$companyId] as $arrDefaultAccessRightsRow) {
                    $arrFieldAccessDefaultInsert = [
                        'applicant_field_id' => $fieldId,
                        'role_id'            => $arrDefaultAccessRightsRow['role_id'],
                        'access'             => $arrDefaultAccessRightsRow['access'],
                        'updated_on'         => date('Y-m-d H:i:s'),
                    ];

                    $this->getQueryBuilder()
                        ->insert(array_keys($arrFieldAccessDefaultInsert))
                        ->into('applicant_form_fields_access_default')
                        ->values($arrFieldAccessDefaultInsert)
                        ->execute();
                }
            }
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `applicant_form_fields` WHERE applicant_field_unique_id = 'client_referrals'");
        $this->execute("DELETE FROM `applicant_form_groups` WHERE title = 'Referrals by this Client'");
    }
}
