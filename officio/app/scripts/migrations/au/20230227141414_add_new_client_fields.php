<?php

use Officio\Migration\AbstractMigration;

class AddNewClientFields extends AbstractMigration
{
    public function up()
    {
        $this->getAdapter()->commitTransaction();

        $arrFields = [
            [
                'id'             => 'linkedin',
                'label'          => 'Linkedin ID',
                'ia_order'       => 13,
                'employer_order' => 14,
                'contact_order'  => 11,
            ],
            [
                'id'             => 'skype',
                'label'          => 'Skype ID',
                'ia_order'       => 14,
                'employer_order' => 15,
                'contact_order'  => 12,
            ],
            [
                'id'             => 'whatsapp',
                'label'          => 'Whatsapp ID',
                'ia_order'       => 15,
                'employer_order' => 16,
                'contact_order'  => 13,
            ],
            [
                'id'             => 'wechat',
                'label'          => 'Wechat ID',
                'ia_order'       => 16,
                'employer_order' => 17,
                'contact_order'  => 14,
            ],
        ];


        $arrIAGroups        = $this->fetchAll("SELECT * FROM applicant_form_groups WHERE title = 'Primary Applicant Contact Info' ORDER BY company_id ASC");
        $arrIAGroupsGrouped = [];
        foreach ($arrIAGroups as $arrIAGroupInfo) {
            $arrIAGroupsGrouped[$arrIAGroupInfo['company_id']] = $arrIAGroupInfo['applicant_group_id'];
        }

        $arrEmployerGroups        = $this->fetchAll("SELECT * FROM applicant_form_groups WHERE title = 'Authorised Contacts' ORDER BY company_id ASC");
        $arrEmployerGroupsGrouped = [];
        foreach ($arrEmployerGroups as $arrEmployerGroupInfo) {
            $arrEmployerGroupsGrouped[$arrEmployerGroupInfo['company_id']] = $arrEmployerGroupInfo['applicant_group_id'];
        }

        $arrContactGroups        = $this->fetchAll("SELECT * FROM applicant_form_groups WHERE title = 'Address & Contact Details' ORDER BY company_id ASC");
        $arrContactGroupsGrouped = [];
        foreach ($arrContactGroups as $arrContactGroupInfo) {
            $arrContactGroupsGrouped[$arrContactGroupInfo['company_id']][] = $arrContactGroupInfo['applicant_group_id'];
        }


        $arrPhoneFieldsAccess        = $this->fetchAll("SELECT * FROM applicant_form_fields_access WHERE applicant_field_id IN (SELECT applicant_field_id FROM `applicant_form_fields` AS f WHERE f.applicant_field_unique_id = 'phone_main')");
        $arrPhoneFieldsAccessGrouped = [];
        foreach ($arrPhoneFieldsAccess as $arrPhoneFieldAccess) {
            $arrPhoneFieldsAccessGrouped[$arrPhoneFieldAccess['applicant_group_id']][] = [
                'role_id' => $arrPhoneFieldAccess['role_id'],
                'status'  => $arrPhoneFieldAccess['status'],
            ];
        }

        $arrCompanies = $this->fetchAll('SELECT * FROM company ORDER BY company_id ASC');

        $arrFieldsOrder  = [];
        $arrFieldsAccess = [];
        foreach ($arrCompanies as $arrCompanyInfo) {
            $companyId = $arrCompanyInfo['company_id'];

            $arrCompanyAllGroupsIds = [
                $arrEmployerGroupsGrouped[$companyId],
                $arrIAGroupsGrouped[$companyId]
            ];

            foreach ($arrContactGroupsGrouped[$companyId] as $contactGroupId) {
                $arrCompanyAllGroupsIds[] = $contactGroupId;
            }

            foreach ($arrFields as $arrFieldInfo) {
                $arrInsert = [
                    'member_type_id'            => 9,
                    'company_id'                => $companyId,
                    'applicant_field_unique_id' => $arrFieldInfo['id'],
                    'type'                      => 'text',
                    'label'                     => $arrFieldInfo['label'],
                ];

                $statement = $this->getQueryBuilder()->insert(array_keys($arrInsert))
                    ->into('applicant_form_fields')
                    ->values($arrInsert)
                    ->execute();

                $fieldId = $statement->lastInsertId('applicant_form_fields');


                // For Employer group - Authorised Contacts
                $arrFieldsOrder[] = [
                    'applicant_group_id' => $arrEmployerGroupsGrouped[$companyId],
                    'applicant_field_id' => $fieldId,
                    'field_order'        => $arrFieldInfo['employer_order'],
                ];

                // For IA group - Primary Applicant Contact Info
                $arrFieldsOrder[] = [
                    'applicant_group_id' => $arrIAGroupsGrouped[$companyId],
                    'applicant_field_id' => $fieldId,
                    'field_order'        => $arrFieldInfo['ia_order'],
                ];

                // For Contacts group - Contact Information
                foreach ($arrContactGroupsGrouped[$companyId] as $contactGroupId) {
                    $arrFieldsOrder[] = [
                        'applicant_group_id' => $contactGroupId,
                        'applicant_field_id' => $fieldId,
                        'field_order'        => $arrFieldInfo['contact_order'],
                    ];
                }

                foreach ($arrCompanyAllGroupsIds as $checkGroupId) {
                    if (isset($arrPhoneFieldsAccessGrouped[$checkGroupId])) {
                        foreach ($arrPhoneFieldsAccessGrouped[$checkGroupId] as $arrNewAccess) {
                            $arrFieldsAccess[] = [
                                'role_id'            => $arrNewAccess['role_id'],
                                'applicant_group_id' => $checkGroupId,
                                'applicant_field_id' => $fieldId,
                                'status'             => $arrNewAccess['status'],
                            ];
                        }
                    }
                }
            }
        }

        if (!empty($arrFieldsOrder)) {
            $this->table('applicant_form_order')
                ->insert($arrFieldsOrder)
                ->save();
        }

        if (!empty($arrFieldsAccess)) {
            $this->table('applicant_form_fields_access')
                ->insert($arrFieldsAccess)
                ->save();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `applicant_form_fields` WHERE applicant_field_unique_id IN ('linkedin', 'skype', 'whatsapp', 'wechat');");
    }
}