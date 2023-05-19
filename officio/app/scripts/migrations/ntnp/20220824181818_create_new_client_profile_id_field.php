<?php

use Officio\Migration\AbstractMigration;

class CreateNewClientProfileIdField extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        // Create a new field type
        $arrCaseTypeFieldTypeInfo = [
            'field_type_text_id'               => 'client_profile_id',
            'field_type_label'                 => 'Client Profile ID',
            'field_type_can_be_used_in_search' => 'Y',
            'field_type_can_be_encrypted'      => 'Y',
            'field_type_with_max_length'       => 'N',
            'field_type_with_options'          => 'N',
            'field_type_with_default_value'    => 'N',
            'field_type_with_custom_height'    => 'N',
            'field_type_use_for'               => 'all',
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrCaseTypeFieldTypeInfo))
            ->into('field_types')
            ->values($arrCaseTypeFieldTypeInfo)
            ->execute();

        $this->execute("ALTER TABLE `applicant_form_fields` CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo','reference','authorized_agents','hyperlink','client_referrals','client_profile_id') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");


        // Get the list of all companies
        $statement = $this->getQueryBuilder()
            ->select(['company_id'])
            ->from('company')
            ->order('company_id')
            ->execute();

        $arrCompanies = array_column($statement->fetchAll('assoc'), 'company_id');


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


        // Get the first block id for Individuals
        $statement = $this->getQueryBuilder()
            ->select(['applicant_block_id'])
            ->from('applicant_form_blocks')
            ->where([
                'member_type_id' => 8,
                'contact_block'  => 'Y',
            ])
            ->execute();

        $arrIndividualGroupedBlocks = array_column($statement->fetchAll('assoc'), 'applicant_block_id');


        // Get the first group for Individuals
        $statement = $this->getQueryBuilder()
            ->select(['company_id', 'applicant_group_id'])
            ->from(array('g' => 'applicant_form_groups'))
            ->where([
                'g.applicant_block_id IN ' => $arrIndividualGroupedBlocks,
                'g.order'                  => 0,
            ])
            ->execute();

        $arrSavedIndividualGroups = $statement->fetchAll('assoc');

        $arrIndividualGroups = [];
        foreach ($arrSavedIndividualGroups as $arrSavedIndividualGroupInfo) {
            $arrIndividualGroups[$arrSavedIndividualGroupInfo['company_id']] = $arrSavedIndividualGroupInfo['applicant_group_id'];
        }


        // Get the first block id for Employers
        $statement = $this->getQueryBuilder()
            ->select(['applicant_block_id'])
            ->from('applicant_form_blocks')
            ->where([
                'member_type_id' => 7,
                'contact_block'  => 'Y',
            ])
            ->execute();

        $arrEmployerGroupedBlocks = array_column($statement->fetchAll('assoc'), 'applicant_block_id');

        // Get the first group for Employers
        $statement = $this->getQueryBuilder()
            ->select(['company_id', 'applicant_group_id'])
            ->from(array('g' => 'applicant_form_groups'))
            ->where([
                'g.applicant_block_id IN ' => $arrEmployerGroupedBlocks,
                'g.order'                  => 0,
            ])
            ->execute();

        $arrSavedEmployerGroups = $statement->fetchAll('assoc');

        $arrEmployerGroups = [];
        foreach ($arrSavedEmployerGroups as $arrSavedEmployerGroupInfo) {
            $arrEmployerGroups[$arrSavedEmployerGroupInfo['company_id']] = $arrSavedEmployerGroupInfo['applicant_group_id'];
        }

        foreach ($arrCompanies as $companyId) {
            // Create the field for each company (Internal Contact)
            $arrClientFieldInsert = [
                'member_type_id'            => 9,
                'company_id'                => $companyId,
                'applicant_field_unique_id' => 'client_profile_id',
                'type'                      => 'client_profile_id',
                'label'                     => 'Client Profile ID'
            ];

            $statement = $this->getQueryBuilder()
                ->insert(array_keys($arrClientFieldInsert))
                ->into('applicant_form_fields')
                ->values($arrClientFieldInsert)
                ->execute();

            $fieldId = $statement->lastInsertId('applicant_form_fields');


            // For internal contact
            $arrInternalClientGroupInsert = [
                'applicant_group_id' => $arrInternalContactGroups[$companyId],
                'applicant_field_id' => $fieldId,
                'use_full_row'       => 'N',
                'field_order'        => 66,
            ];

            $this->getQueryBuilder()
                ->insert(array_keys($arrInternalClientGroupInsert))
                ->into('applicant_form_order')
                ->values($arrInternalClientGroupInsert)
                ->execute();


            // For IA
            $arrNewGroupInsert = [
                'applicant_group_id' => $arrIndividualGroups[$companyId],
                'applicant_field_id' => $fieldId,
                'use_full_row'       => 'N',
                'field_order'        => 24,
            ];

            $this->getQueryBuilder()
                ->insert(array_keys($arrNewGroupInsert))
                ->into('applicant_form_order')
                ->values($arrNewGroupInsert)
                ->execute();


            // For Employer
            $arrNewGroupInsert = [
                'applicant_group_id' => $arrEmployerGroups[$companyId],
                'applicant_field_id' => $fieldId,
                'use_full_row'       => 'N',
                'field_order'        => 7,
            ];

            $this->getQueryBuilder()
                ->insert(array_keys($arrNewGroupInsert))
                ->into('applicant_form_order')
                ->values($arrNewGroupInsert)
                ->execute();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM applicant_form_fields WHERE type = 'client_profile_id'");
        $this->execute("DELETE FROM field_types WHERE field_type_text_id = 'client_profile_id'");
        $this->execute("ALTER TABLE `applicant_form_fields` CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo','reference','authorized_agents','hyperlink','client_referrals') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");
    }
}
