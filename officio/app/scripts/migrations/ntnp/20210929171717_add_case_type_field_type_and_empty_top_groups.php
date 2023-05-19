<?php

use Officio\Migration\AbstractMigration;

class AddCaseTypeFieldTypeAndEmptyTopGroups extends AbstractMigration
{
    protected $clearCache = true;

    private function createCaseTypeField($companyId, $parentFieldId, $caseTypeFieldTypeId)
    {
        $arrFieldInfo = [
            'parent_field_id'         => $parentFieldId,
            'company_id'              => $companyId,
            'type'                    => $caseTypeFieldTypeId,
            'company_field_id'        => 'case_type',
            'label'                   => 'Immigration Program',
            'required'                => 'Y',
            'required_for_submission' => 'Y',
        ];

        $builder   = $this->getQueryBuilder();
        $statement = $builder
            ->insert(array_keys($arrFieldInfo))
            ->into('client_form_fields')
            ->values($arrFieldInfo)
            ->execute();

        return $statement->lastInsertId('client_form_fields');
    }

    private function createCaseFieldsGroup($companyId, $caseTypeId, $parentGroupId)
    {
        $arrGroupInfo = [
            'company_id'      => $companyId,
            'client_type_id'  => $caseTypeId,
            'parent_group_id' => $parentGroupId,
            'title'           => 'Top group',
            'order'           => 0,
            'cols_count'      => 3,
            'regTime'         => time(),
            'assigned'        => 'A',
            'show_title'      => 'N',
            'collapsed'       => 'N',
        ];

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrGroupInfo))
            ->into('client_form_groups')
            ->values($arrGroupInfo)
            ->execute();

        return $statement->lastInsertId('client_form_groups');
    }

    public function getGroupedOrphanFields($fieldName)
    {
        $arrAllFields = $this->fetchAll(
            sprintf(
                "SELECT t.client_type_id, f.field_id, o.order_id
                FROM client_types AS t
                INNER JOIN client_form_groups AS g ON g.client_type_id = t.client_type_id
                LEFT JOIN client_form_fields AS f ON t.company_id = f.company_id AND f.company_field_id = '%s'
                LEFT JOIN client_form_order AS o ON g.group_id = o.group_id AND o.field_id = f.field_id",
                $fieldName
            )
        );

        $arrGroupedByCaseType = [];
        $arrIgnoreFields      = [];
        foreach ($arrAllFields as $arrFieldOrder) {
            if (isset($arrIgnoreFields[$arrFieldOrder['client_type_id'] . '_' . $arrFieldOrder['field_id']])) {
                continue;
            }

            if (empty($arrFieldOrder['order_id'])) {
                $arrGroupedByCaseType[$arrFieldOrder['client_type_id']] = $arrFieldOrder['field_id'];
            } else {
                // Add to the "ignore list", so if found later - skip
                $arrIgnoreFields[$arrFieldOrder['client_type_id'] . '_' . $arrFieldOrder['field_id']] = 1;
                unset($arrGroupedByCaseType[$arrFieldOrder['client_type_id']]);
            }
        }

        return $arrGroupedByCaseType;
    }

    public function up()
    {
        // Create a new field type
        $arrCaseTypeFieldTypeInfo = [
            'field_type_text_id'               => 'case_type',
            'field_type_label'                 => 'Case Type / Immigration Program',
            'field_type_can_be_used_in_search' => 'N',
            'field_type_can_be_encrypted'      => 'N',
            'field_type_with_max_length'       => 'N',
            'field_type_with_options'          => 'N',
            'field_type_with_default_value'    => 'N',
            'field_type_with_custom_height'    => 'N',
            'field_type_use_for'               => 'case',
        ];

        $statement = $this->getQueryBuilder()
            ->insert(array_keys($arrCaseTypeFieldTypeInfo))
            ->into('field_types')
            ->values($arrCaseTypeFieldTypeInfo)
            ->execute();

        $caseTypeFieldTypeId = $statement->lastInsertId('field_types');

        $arrFieldMapping = [];

        // Create a field for the default company
        $parentCaseTypeFieldId = $this->createCaseTypeField(0, null, $caseTypeFieldTypeId);
        $arrFieldMapping[0] = $parentCaseTypeFieldId;

        // Create a field for other companies
        $arrAllCompanies = $this->fetchAll('SELECT * FROM company WHERE company_id != 0');
        foreach ($arrAllCompanies as $arrCompanyInfo) {
            $createdFieldId = $this->createCaseTypeField($arrCompanyInfo['company_id'], $parentCaseTypeFieldId, $caseTypeFieldTypeId);

            $arrFieldMapping[$arrCompanyInfo['company_id']] = $createdFieldId;
        }


        // Add a possibility to hide the title of the group
        $this->execute("ALTER TABLE `client_form_groups` ADD COLUMN `show_title` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `collapsed`;");


        // Move all groups, so we can add a new group to the top
        $this->execute("UPDATE client_form_groups SET `order` = `order` + 1 WHERE assigned = 'A'");


        // Prepare data to move the "categories" field
        $arrGroupedCategoriesOrder            = [];
        $arrAllCompaniesCategoriesFieldsOrder = $this->fetchAll(
            sprintf(
                "SELECT *
                FROM client_form_order AS o
                LEFT JOIN client_form_groups AS g ON g.group_id = o.group_id
                WHERE o.field_id IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`= '%s') AND g.assigned = 'A'",
                'categories'
            )
        );

        $arrOrdersToDelete = [];
        foreach ($arrAllCompaniesCategoriesFieldsOrder as $arrCompanyCategoryFieldOrder) {
            $arrOrdersToDelete[] = $arrCompanyCategoryFieldOrder['order_id'];

            $arrGroupedCategoriesOrder[$arrCompanyCategoryFieldOrder['client_type_id']] = $arrCompanyCategoryFieldOrder['field_id'];
        }

        $arrOrpanFields = $this->getGroupedOrphanFields('categories');
        foreach ($arrOrpanFields as $caseTypeId => $fieldId) {
            $arrGroupedCategoriesOrder[$caseTypeId] = $fieldId;
        }

        // Prepare data to move the "case status" field
        $arrAllCompaniesFileStatusFieldsOrder = $this->fetchAll(
            sprintf(
                "SELECT *
                FROM client_form_order AS o
                LEFT JOIN client_form_groups AS g ON g.group_id = o.group_id
                WHERE o.field_id IN (SELECT field_id FROM `client_form_fields` WHERE `company_field_id`= '%s') AND g.assigned = 'A'",
                'file_status'
            )
        );

        $arrGroupedFileStatusOrder = [];
        foreach ($arrAllCompaniesFileStatusFieldsOrder as $arrCompanyFileStatusFieldOrder) {
            $arrOrdersToDelete[] = $arrCompanyFileStatusFieldOrder['order_id'];

            $arrGroupedFileStatusOrder[$arrCompanyFileStatusFieldOrder['client_type_id']] = $arrCompanyFileStatusFieldOrder['field_id'];
        }

        $arrOrpanFields = $this->getGroupedOrphanFields('file_status');
        foreach ($arrOrpanFields as $caseTypeId => $fieldId) {
            $arrGroupedFileStatusOrder[$caseTypeId] = $fieldId;
        }

        if (!empty($arrOrdersToDelete)) {
            $this->execute(sprintf("DELETE FROM client_form_order WHERE order_id IN (%s)", implode(',', $arrOrdersToDelete)));
        }


        // Create a new Top Group for the default company, remember created ids
        $arrDefaultCompanyCaseTypes = $this->fetchAll('SELECT * FROM client_types WHERE company_id = 0');

        $arrGroupsMapping           = [];
        $arrGroupsMappingByCaseType = [];
        foreach ($arrDefaultCompanyCaseTypes as $arrDefaultCompanyCaseTypeInfo) {
            $defaultCompanyGroupId = $this->createCaseFieldsGroup(0, $arrDefaultCompanyCaseTypeInfo['client_type_id'], null);

            $arrGroupsMapping[0][] = $defaultCompanyGroupId;

            $arrGroupsMappingByCaseType[$arrDefaultCompanyCaseTypeInfo['client_type_id']] = $defaultCompanyGroupId;
        }

        $arrAllCompaniesCaseTypes = $this->fetchAll('SELECT * FROM client_types');
        foreach ($arrAllCompaniesCaseTypes as $arrCompanyCaseTypeInfo) {
            if (empty($arrCompanyCaseTypeInfo['company_id'])) {
                $createdGroupId = $arrGroupsMappingByCaseType[$arrCompanyCaseTypeInfo['client_type_id']];
            } else {
                // Create a new Top Group for all other companies, use the parent group id
                $defaultGroupId = isset($arrGroupsMappingByCaseType[$arrCompanyCaseTypeInfo['parent_client_type_id']]) ? $arrGroupsMappingByCaseType[$arrCompanyCaseTypeInfo['parent_client_type_id']] : null;
                $createdGroupId = $this->createCaseFieldsGroup($arrCompanyCaseTypeInfo['company_id'], $arrCompanyCaseTypeInfo['client_type_id'], $defaultGroupId);

                $arrGroupsMapping[$arrCompanyCaseTypeInfo['company_id']][] = $createdGroupId;
            }

            // Place a new "case type" field to the just created group
            $order = 0;
            if (isset($arrFieldMapping[$arrCompanyCaseTypeInfo['company_id']])) {
                $arrCaseTypeFieldTypeInfo = [
                    'group_id'    => $createdGroupId,
                    'field_id'    => $arrFieldMapping[$arrCompanyCaseTypeInfo['company_id']],
                    'field_order' => $order++,
                ];

                $this->getQueryBuilder()
                    ->insert(array_keys($arrCaseTypeFieldTypeInfo))
                    ->into('client_form_order')
                    ->values($arrCaseTypeFieldTypeInfo)
                    ->execute();
            }

            // Move the "categories" field to this group
            if (isset($arrGroupedCategoriesOrder[$arrCompanyCaseTypeInfo['client_type_id']])) {
                $arrCaseTypeFieldTypeInfo = [
                    'group_id'    => $createdGroupId,
                    'field_id'    => $arrGroupedCategoriesOrder[$arrCompanyCaseTypeInfo['client_type_id']],
                    'field_order' => $order++,
                ];

                $this->getQueryBuilder()
                    ->insert(array_keys($arrCaseTypeFieldTypeInfo))
                    ->into('client_form_order')
                    ->values($arrCaseTypeFieldTypeInfo)
                    ->execute();
            }

            // Move the "case status" field to this group
            if (isset($arrGroupedFileStatusOrder[$arrCompanyCaseTypeInfo['client_type_id']])) {
                $arrCaseTypeFieldTypeInfo = [
                    'group_id'    => $createdGroupId,
                    'field_id'    => $arrGroupedFileStatusOrder[$arrCompanyCaseTypeInfo['client_type_id']],
                    'field_order' => $order,
                ];

                $this->getQueryBuilder()
                    ->insert(array_keys($arrCaseTypeFieldTypeInfo))
                    ->into('client_form_order')
                    ->values($arrCaseTypeFieldTypeInfo)
                    ->execute();
            }
        }


        // Set groups and fields access
        $arrGroupsAccess = [];
        $arrFieldsAccess = [];

        $arrAllCompaniesRoles = $this->fetchAll("SELECT * FROM acl_roles WHERE role_type IN ('individual_client', 'employer_client', 'user', 'admin')");
        foreach ($arrAllCompaniesRoles as $arrCompanyRole) {
            if (isset($arrGroupsMapping[$arrCompanyRole['company_id']])) {
                foreach ($arrGroupsMapping[$arrCompanyRole['company_id']] as $groupId) {
                    $arrGroupsAccess[] = sprintf(
                        "(%d, %d, 'F')",
                        $arrCompanyRole['role_id'],
                        $groupId
                    );
                }
            }

            if (isset($arrFieldMapping[$arrCompanyRole['company_id']])) {
                $arrFieldsAccess[] = sprintf(
                    "(%d, %d, 'F')",
                    $arrCompanyRole['role_id'],
                    $arrFieldMapping[$arrCompanyRole['company_id']]
                );
            }
        }

        if (!empty($arrGroupsAccess)) {
            $this->execute(sprintf('INSERT IGNORE INTO client_form_group_access (role_id, group_id, `status`) VALUES %s', implode(',', $arrGroupsAccess)));
        }

        if (!empty($arrFieldsAccess)) {
            $this->execute(sprintf('INSERT IGNORE INTO client_form_field_access (role_id, field_id, `status`) VALUES %s', implode(',', $arrFieldsAccess)));
        }
    }

    public function down()
    {
        $arrFieldType = $this->fetchRow("SELECT field_type_id FROM field_types WHERE field_type_text_id = 'case_type';");
        if (!$arrFieldType || !isset($arrFieldType['field_type_id'])) {
            throw new Exception('Case Type field type not found.');
        }

        $fieldTypeId = $arrFieldType['field_type_id'];

        $this->execute("DELETE FROM client_form_groups WHERE `title` = 'Top group' AND `order` = 0");
        $this->execute("DELETE FROM client_form_fields WHERE `type` = $fieldTypeId");
        $this->execute("DELETE FROM field_types WHERE field_type_id = $fieldTypeId");
        $this->execute('ALTER TABLE `client_form_groups` DROP COLUMN `show_title`');
        $this->execute("UPDATE client_form_groups SET `order` = `order` - 1 WHERE assigned = 'A'");
    }
}
