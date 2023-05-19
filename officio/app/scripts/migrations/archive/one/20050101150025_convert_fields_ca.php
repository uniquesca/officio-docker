<?php

use Clients\Service\Clients;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Company;
use Officio\Service\Roles;
use Phinx\Migration\AbstractMigration;

class ConvertFieldsCa extends AbstractMigration
{
    public function up()
    {
        // Took 1327s on local server...
        try {
            /** @var \Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');
            /** @var Company $oCompany */
            $oCompany = Zend_Registry::get('serviceManager')->get(Company::class);
            $defaultCompanyId = $oCompany->getDefaultCompanyId();
            /** @var Clients $oCompany */
            $oClients = Zend_Registry::get('serviceManager')->get(Clients::class);
            /** @var Roles $oRoles */
            $oRoles = Zend_Registry::get('serviceManager')->get(Roles::class);

            $select = $db->select()
                ->from(array('c' => 'company'), 'company_id')
                ->where('company_id != ?', $defaultCompanyId, 'INT')
                ->order('company_id ASC');

            $arrCompanies = $db->fetchCol($select);

            $arrRoleTypes           = array('admin', 'user', 'individual_client', 'employer_client');
            $arrDefaultCompanyRoles = $oRoles->getCompanyRoles($defaultCompanyId, null, false, $arrRoleTypes);

            $companiesSkippedCount   = 0;
            $companiesProcessedCount = 0;
            $companiesFailedCount    = 0;

            foreach ($arrCompanies as $companyId) {
                echo PHP_EOL . str_repeat('*', 80) . PHP_EOL . "Company id: #$companyId" . PHP_EOL;

                // Try to create mapping between default company and this company roles in such priority:
                // 1. The same name and type
                // 2. The same type
                $arrMappingRoles = array();
                $arrCompanyRoles = $oRoles->getCompanyRoles($companyId, null, false, $arrRoleTypes);
                foreach ($arrDefaultCompanyRoles as $arrDefaultCompanyRoleInfo) {
                    $sameNameRoleId = 0;
                    $sameTypeRoleId = 0;

                    foreach ($arrCompanyRoles as $arrCompanyRoleInfo) {
                        if ($arrDefaultCompanyRoleInfo['role_name'] == $arrCompanyRoleInfo['role_name'] && $arrDefaultCompanyRoleInfo['role_type'] == $arrCompanyRoleInfo['role_type']) {
                            $sameNameRoleId = $arrCompanyRoleInfo['role_id'];
                        }

                        if ($arrDefaultCompanyRoleInfo['role_type'] == $arrCompanyRoleInfo['role_type']) {
                            $sameTypeRoleId = $arrCompanyRoleInfo['role_id'];
                        }
                    }

                    if (!empty($sameNameRoleId)) {
                        $arrMappingRoles[$arrDefaultCompanyRoleInfo['role_id']] = $sameNameRoleId;
                    } elseif (!empty($sameTypeRoleId)) {
                        $arrMappingRoles[$arrDefaultCompanyRoleInfo['role_id']] = $sameTypeRoleId;
                    }
                }

                if ($oClients->getApplicantFields()->hasCompanyBlocks($companyId)) {
                    // Skip, if company has created blocks already -
                    // a simple check to be sure that no duplicates will be created
                    echo "Skipped" . PHP_EOL;
                    $companiesSkippedCount++;
                } else {
                    // Copy ALL applicant blocks/groups/fields + set access to them
                    $arrMappingClientGroupsAndFields = $oClients->getApplicantFields()->createDefaultCompanyFieldsAndGroups(
                        $defaultCompanyId,
                        $companyId,
                        $arrMappingRoles
                    );

                    if (!$arrMappingClientGroupsAndFields['success']) {
                        echo "!!!!!! Fields/groups were not created !!!!!!" . PHP_EOL;
                        $companiesFailedCount++;
                    } else {
                        echo "Fields/groups were successfully created" . PHP_EOL;
                        $companiesProcessedCount++;
                    }
                }

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');

                echo str_repeat('*', 80) . PHP_EOL;
            }

            /** @var StorageInterface $cache */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }


            echo sprintf(
                str_repeat('*', 80) . PHP_EOL .
                '%d successfully, ' . PHP_EOL .
                '%d skipped, ' . PHP_EOL .
                '%d failed' . PHP_EOL .
                str_repeat('*', 80) . PHP_EOL,
                $companiesProcessedCount,
                $companiesSkippedCount,
                $companiesFailedCount
            );



            // ***************************************************************************
            // Fix specific fields for specific companies
            // Rename the field if it was already saved in DB
            // Also change the type of the field if it is different from what we created
            // ***************************************************************************

            // Fix "marital_status" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('marital_status', 'pclaw_Martial_Status'));

            $arrFieldsToFix = $db->fetchAll($select);

            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                $select = $db->select()
                    ->from(array('f' => 'applicant_form_fields'), 'applicant_field_id')
                    ->where('f.company_id = ?', $arrFieldInfoToFix['company_id'])
                    ->where('f.applicant_field_unique_id = ?', 'relationship_status');

                $applicantFieldId = $db->fetchOne($select);

                if (empty($applicantFieldId)) {
                    continue;
                }

                if ($arrFieldInfoToFix['type'] == '3') {
                    $select = $db->select()
                        ->from(array('d' => 'client_form_default'))
                        ->where('d.field_id = ?', $arrFieldInfoToFix['field_id']);

                    $arrDefaultValues = $db->fetchAll($select);

                    $db->delete('applicant_form_default', $db->quoteInto('applicant_field_id = ?', $applicantFieldId, 'INT'));

                    if (!empty($arrDefaultValues)) {
                        foreach ($arrDefaultValues as $defaultOption) {
                            $db->insert(
                                'applicant_form_default',
                                array(
                                    'applicant_field_id' => $applicantFieldId,
                                    'value'              => $defaultOption['value'],
                                    'order'              => $defaultOption['order']
                                )
                            );
                        }
                    }
                } else {
                    $this->execute(sprintf("UPDATE applicant_form_fields SET type = 'text' WHERE applicant_field_id = %d;", $applicantFieldId));
                    $this->execute(sprintf('DELETE FROM applicant_form_default WHERE applicant_field_id = %d;', $applicantFieldId));
                }

                $this->execute(
                    sprintf(
                        "UPDATE applicant_form_fields SET `applicant_field_unique_id` = '%s', `label` = '%s' WHERE applicant_field_id = %d;",
                        $arrFieldInfoToFix['company_field_id'],
                        $arrFieldInfoToFix['label'],
                        $applicantFieldId
                    )
                );
            }


            // Fix "address" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('address_1', 'address_2', 'address_3'))
                ->where('type != ?', 1);

            $arrFieldsToFix = $db->fetchAll($select);

            $oFieldTypes = $oClients->getFieldTypes();
            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                $select = $db->select()
                    ->from(array('f' => 'applicant_form_fields'))
                    ->where('f.company_id = ?', $arrFieldInfoToFix['company_id'])
                    ->where('f.applicant_field_unique_id = ?', $arrFieldInfoToFix['company_field_id']);

                $applicantFieldInfo = $db->fetchRow($select);

                if (!empty($applicantFieldInfo) && $arrFieldInfoToFix['type'] != $oFieldTypes->getFieldTypeId($applicantFieldInfo['type'])) {
                    $this->execute(
                        sprintf(
                            "UPDATE applicant_form_fields SET type = 'memo' WHERE applicant_field_id = %d;",
                            $applicantFieldInfo['applicant_field_id']
                        )
                    );
                }
            }


            // Fix "pref_contact_method" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('pref_contact_method'));

            $arrFieldsToFix = $db->fetchAll($select);

            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                $select = $db->select()
                    ->from(array('f' => 'applicant_form_fields'), 'applicant_field_id')
                    ->where('f.company_id = ?', $arrFieldInfoToFix['company_id'])
                    ->where('f.applicant_field_unique_id = ?', 'pref_contact_method');

                $applicantFieldId = $db->fetchOne($select);


                if (empty($applicantFieldId)) {
                    continue;
                }

                if ($arrFieldInfoToFix['type'] == '3') {
                    $select = $db->select()
                        ->from(array('d' => 'client_form_default'), 'value')
                        ->where('d.field_id = ?', $arrFieldInfoToFix['field_id']);

                    $arrDefaultValues = $db->fetchCol($select);

                    $select = $db->select()
                        ->from(array('d' => 'applicant_form_default'), 'value')
                        ->where('d.applicant_field_id = ?', $applicantFieldId);

                    $arrApplicantDefaultValues = $db->fetchCol($select);

                    $arrMissingOptions = array_diff($arrDefaultValues, $arrApplicantDefaultValues);

                    if (!empty($arrMissingOptions)) {
                        foreach ($arrMissingOptions as $missingOption) {
                            $db->insert(
                                'applicant_form_default',
                                array(
                                    'applicant_field_id' => $applicantFieldId,
                                    'value'              => $missingOption,
                                    'order'              => 0
                                )
                            );
                        }
                    }
                } else {
                    $this->execute(sprintf("UPDATE applicant_form_fields SET type = 'text' WHERE applicant_field_id = %d;", $applicantFieldId));
                    $this->execute(sprintf('DELETE FROM applicant_form_default WHERE applicant_field_id = %d;', $applicantFieldId));
                }
            }



            // Fix "gender" + "sex" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('gender', 'sex'));

            $arrFieldsToFix = $db->fetchAll($select);

            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                $select = $db->select()
                    ->from(array('f' => 'applicant_form_fields'), 'applicant_field_id')
                    ->where('f.company_id = ?', $arrFieldInfoToFix['company_id'])
                    ->where('f.applicant_field_unique_id = ?', 'sex');

                $applicantFieldId = $db->fetchOne($select);

                if (empty($applicantFieldId)) {
                    continue;
                }

                if ($arrFieldInfoToFix['type'] == '3') {
                    $select = $db->select()
                        ->from(array('d' => 'client_form_default'), 'value')
                        ->where('d.field_id = ?', $arrFieldInfoToFix['field_id']);

                    $arrDefaultValues = $db->fetchCol($select);

                    $select = $db->select()
                        ->from(array('d' => 'applicant_form_default'), 'value')
                        ->where('d.applicant_field_id = ?', $applicantFieldId);

                    $arrApplicantDefaultValues = $db->fetchCol($select);

                    $arrMissingOptions = array_diff($arrDefaultValues, $arrApplicantDefaultValues);

                    if (!empty($arrMissingOptions)) {
                        foreach ($arrMissingOptions as $missingOption) {
                            $db->insert(
                                'applicant_form_default',
                                array(
                                    'applicant_field_id' => $applicantFieldId,
                                    'value'              => $missingOption,
                                    'order'              => 0
                                )
                            );
                        }
                    }
                } else {
                    $this->execute(sprintf("UPDATE applicant_form_fields SET type = 'text' WHERE applicant_field_id = %d;", $applicantFieldId));
                    $this->execute(sprintf('DELETE FROM applicant_form_default WHERE applicant_field_id = %d;', $applicantFieldId));
                }

                $this->execute(
                    sprintf(
                        "UPDATE applicant_form_fields SET `applicant_field_unique_id` = '%s', `label` = '%s' WHERE applicant_field_id = %d;",
                        $arrFieldInfoToFix['company_field_id'],
                        $arrFieldInfoToFix['label'],
                        $applicantFieldId
                    )
                );
            }



            // Fix "title" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('title'));

            $arrFieldsToFix = $db->fetchAll($select);

            $select = $db->select()
                ->from(array('d' => 'client_form_default'))
                ->where("d.field_id IN (SELECT field_id FROM client_form_fields WHERE company_field_id = 'title')");

            $arrFieldsDefaultOptions = $db->fetchAll($select);

            $arrGroupedDefaultOptions = array();
            foreach ($arrFieldsDefaultOptions as $arrFieldsDefaultOptionInfo) {
                $arrGroupedDefaultOptions[$arrFieldsDefaultOptionInfo['field_id']][] = $arrFieldsDefaultOptionInfo['value'];
            }


            $select = $db->select()
                ->from(array('f' => 'applicant_form_fields'), array('company_id', '*'))
                ->where('f.applicant_field_unique_id = ?', 'title');

            $arrApplicantFields = $db->fetchAssoc($select);

            $select = $db->select()
                ->from(array('d' => 'applicant_form_default'))
                ->where("d.applicant_field_id IN (SELECT applicant_field_id FROM applicant_form_fields WHERE applicant_field_unique_id = 'title')");

            $arrApplicantFieldsDefaultOptions = $db->fetchAll($select);

            $arrApplicantGroupedDefaultOptions = array();
            foreach ($arrApplicantFieldsDefaultOptions as $arrApplicantFieldsDefaultOptionInfo) {
                $arrApplicantGroupedDefaultOptions[$arrApplicantFieldsDefaultOptionInfo['applicant_field_id']][] = $arrApplicantFieldsDefaultOptionInfo['value'];
            }

            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                if (!isset($arrApplicantFields[$arrFieldInfoToFix['company_id']])) {
                    continue;
                }

                $applicantFieldId = $arrApplicantFields[$arrFieldInfoToFix['company_id']]['applicant_field_id'];

                $arrDefaultValues = $arrGroupedDefaultOptions[$arrFieldInfoToFix['field_id']];

                $arrApplicantDefaultValues = $arrApplicantGroupedDefaultOptions[$applicantFieldId];

                $arrMissingOptions = array_diff($arrDefaultValues, $arrApplicantDefaultValues);

                if (!empty($arrMissingOptions)) {
                    foreach ($arrMissingOptions as $missingOption) {
                        $db->insert(
                            'applicant_form_default',
                            array(
                                'applicant_field_id' => $applicantFieldId,
                                'value'              => $missingOption,
                                'order'              => 0
                            )
                        );
                    }
                }

                // Rename label
                if ($arrFieldInfoToFix['label'] != $arrApplicantFields[$arrFieldInfoToFix['company_id']]['label']) {
                    $this->execute(
                        sprintf(
                            "UPDATE applicant_form_fields SET `label` = '%s' WHERE applicant_field_id = %d;",
                            $arrFieldInfoToFix['label'],
                            $applicantFieldId
                        )
                    );
                }
            }




            // Fix "country" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('country_of_birth', 'country_of_residence', 'country_of_citizenship', 'country'))
                ->where('f.type != 1');

            $arrFieldsToFix = $db->fetchAll($select);

            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                $select = $db->select()
                    ->from(array('f' => 'applicant_form_fields'))
                    ->where('f.company_id = ?', $arrFieldInfoToFix['company_id'])
                    ->where('f.applicant_field_unique_id = ?', $arrFieldInfoToFix['company_field_id']);

                $applicantFieldInfo = $db->fetchRow($select);

                if (empty($applicantFieldInfo)) {
                    continue;
                }

                if ($arrFieldInfoToFix['type'] != $oFieldTypes->getFieldTypeId($applicantFieldInfo['type'])) {
                    $this->execute(
                        sprintf(
                            "UPDATE applicant_form_fields SET type = 'country' WHERE applicant_field_id = %d;",
                            $applicantFieldInfo['applicant_field_id']
                        )
                    );
                }
            }


            // Fix "passport_date_of_issue" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('passport_date_of_issue'));

            $arrFieldsToFix = $db->fetchAll($select);

            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                $select = $db->select()
                    ->from(array('f' => 'applicant_form_fields'), 'applicant_field_id')
                    ->where('f.company_id = ?', $arrFieldInfoToFix['company_id'])
                    ->where('f.applicant_field_unique_id = ?', 'passport_issue_date');

                $applicantFieldId = $db->fetchOne($select);

                if (empty($applicantFieldId)) {
                    continue;
                }

                $this->execute(
                    sprintf(
                        "UPDATE applicant_form_fields SET `applicant_field_unique_id` = '%s', `label` = '%s' WHERE applicant_field_id = %d;",
                        $arrFieldInfoToFix['company_field_id'],
                        $arrFieldInfoToFix['label'],
                        $applicantFieldId
                    )
                );
            }


            // Fix "visa_expiry_date" fields
            $select = $db->select()
                ->from(array('f' => 'client_form_fields'))
                ->where('f.company_field_id IN (?)', array('appdet_visa_exp', 'exp_date', 'Visa_Expiry', 'VISA_Expiry_Date', 'PR_visa_expiry'));

            $arrFieldsToFix = $db->fetchAll($select);

            foreach ($arrFieldsToFix as $arrFieldInfoToFix) {
                $select = $db->select()
                    ->from(array('f' => 'applicant_form_fields'))
                    ->where('f.company_id = ?', $arrFieldInfoToFix['company_id'])
                    ->where('f.applicant_field_unique_id = ?', 'visa_expiry_date');

                $applicantFieldInfo = $db->fetchRow($select);

                if (empty($applicantFieldInfo)) {
                    continue;
                }

                if ($arrFieldInfoToFix['type'] != $oFieldTypes->getFieldTypeId($applicantFieldInfo['type'])) {
                    $this->execute(
                        sprintf(
                            "UPDATE applicant_form_fields SET type = '%s' WHERE applicant_field_id = %d;",
                            $oFieldTypes->getStringFieldTypeById($arrFieldInfoToFix['type']),
                            $applicantFieldInfo['applicant_field_id']
                        )
                    );
                }

                $this->execute(
                    sprintf(
                        "UPDATE applicant_form_fields SET `applicant_field_unique_id` = '%s', `label` = '%s' WHERE applicant_field_id = %d;",
                        $arrFieldInfoToFix['company_field_id'],
                        $arrFieldInfoToFix['label'],
                        $applicantFieldInfo['applicant_field_id']
                    )
                );
            }

            $this->execute("UPDATE `client_form_groups` SET `title`='Additional Information' WHERE title IN ('Contact information', 'Contact info')");

        } catch (\Exception $e) {
            echo 'Fatal error' . print_r($e->getTraceAsString(), 1);
            throw $e;
        }
    }

    public function down()
    {
    }
}