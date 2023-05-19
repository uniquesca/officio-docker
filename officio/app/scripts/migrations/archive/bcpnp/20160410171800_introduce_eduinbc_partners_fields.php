<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Forms\FormAssigned;
use Officio\Service\AuthHelper;
use Phinx\Migration\AbstractMigration;

class IntroduceEduinbcPartnersFields extends AbstractMigration
{
    public function authenticateAsCompanyAdmin(Zend_Db_Adapter_Abstract $db, $companyName)
    {
        $_SERVER['HTTP_HOST']   = 'localhost';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $adminQuery = $db->select()
            ->from(array('m' => 'members'), array('m.username', 'm.password'))
            ->join(array('mt' => 'members_types'), 'mt.member_type_id = m.userType')
            ->join(array('c' => 'company'), 'c.company_id = m.company_id')
            ->where('mt.member_type_name = ?', 'admin')
            ->where('c.companyName = ?', $companyName)
            ->limit(1);
        if (!$admin = $db->fetchRow($adminQuery)) {
            return false;
        }

        $username          = $admin['username'];
        $passwordEncrypted = $admin['password'];
        $password          = Officio\Encryption::decode($passwordEncrypted);

        /** @var AuthHelper $auth */
        $auth = Zend_Registry::get('serviceManager')->get(AuthHelper::class);

        return $auth->login($username, $password, false);
    }


    public function up()
    {
        $output = $this->getOutput();
        /** @var Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        try {
            if (!$this->authenticateAsCompanyAdmin($db, 'BC PNP')) {
                $output->writeln('<error>Unable to authenticate.</error>');
                return false;
            }

            /** @var Forms $forms */
            $forms = Zend_Registry::get('serviceManager')->get(Forms::class);
            /** @var Clients $oClients */
            $oClients = Zend_Registry::get('serviceManager')->get(Clients::class);
            /** @var \Files\Service\Files $oFiles */
            $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);


            $db->query(
                "
                INSERT INTO `FormSynField` (`FieldName`)
                VALUES
                  ('syncA_Partner0_LastName'),
                  ('syncA_Partner0_FirstName'),
                  ('syncA_Partner1_LastName'),
                  ('syncA_Partner1_FirstName'),
                  ('syncA_Partner2_LastName'),
                  ('syncA_Partner2_FirstName'),
                  ('syncA_App_Edu_HighestLevel_BC');
                  
                INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
                  SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'partner_lname_1' 
                  FROM `FormSynField` WHERE `FieldName` = 'syncA_Partner0_LastName' 
                  
                  UNION
                  
                  SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'partner_fname_1' 
                  FROM `FormSynField` WHERE `FieldName` = 'syncA_Partner0_FirstName' 
                  
                  UNION
                  
                  SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'partner_lname_2'
                  FROM `FormSynField` WHERE `FieldName` = 'syncA_Partner1_LastName' 
                  
                  UNION
                  
                  SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'partner_fname_2' 
                  FROM `FormSynField` WHERE `FieldName` = 'syncA_Partner1_FirstName'
                  
                  UNION
                  
                  SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'partner_lname_3'
                  FROM `FormSynField` WHERE `FieldName` = 'syncA_Partner2_LastName' 
                  
                  UNION
                  
                  SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'partner_fname_3'
                  FROM `FormSynField` WHERE `FieldName` = 'syncA_Partner2_FirstName' 
                  
                  UNION
                  
                  SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'ed_in_BC'
                  FROM `FormSynField` WHERE `FieldName` = 'syncA_App_Edu_HighestLevel_BC';
                "
            );

            $select = $db->select()
                ->from(array('c' => 'clients'), array('c.member_id', 'c.client_type_id', 'fa.FormAssignedId', 'ct.company_id', 'c.fileNumber'))
                ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id')
                ->join(array('co' => 'company'), 'co.company_id = ct.company_id')
                ->join(array('fa' => 'FormAssigned'), 'fa.ClientMemberId = c.member_id')
                ->join(array('m' => 'members_relations'), 'm.child_member_id = c.member_id')
                ->where('ct.client_type_name = ?', 'Skills Immigration Registration')
                ->where('co.companyName = ?', 'BC PNP');

            $clients = $db->fetchAll($select);
            if (empty($clients)) {
                $output->writeln('<error>No SI Registrations found.</error>');
            } else {
                $skipped   = 0;
                $processed = 0;
                foreach ($clients as $client) {
                    $strError = '';

                    try {
                        $arrData = array();

                        // Get assigned form info by id
                        $assignedFormInfo = $forms->getFormAssigned()->getAssignedFormInfo($client['FormAssignedId']);
                        if (!$assignedFormInfo) {
                            $strError = 'There is no form with this assigned id.';
                        }

                        if (empty($strError)) {
                            // Return xfdf for specific member id
                            $caseId         = $assignedFormInfo['ClientMemberId'];
                            $familyMemberId = $assignedFormInfo['FamilyMemberId'];

                            // Check if we need load data from json or xfdf file
                            $jsonFilePath = $oFiles->getClientJsonFilePath($caseId, $familyMemberId, $client['FormAssignedId']);
                            if (file_exists($jsonFilePath)) {
                                $savedJson = file_get_contents($jsonFilePath);
                                $arrData   = (array)json_decode($savedJson);
                            }

                            if (empty($arrData['BCPNP_App_Edu_HighestLevel_BC'])) {
                                $skipped++;
                                continue;
                            }

                            $newArrData                                  = $arrData;
                            $newArrData['syncA_App_Edu_HighestLevel_BC'] = $arrData['BCPNP_App_Edu_HighestLevel_BC'];
                            unset($newArrData['BCPNP_App_Edu_HighestLevel_BC']);
                            file_put_contents($jsonFilePath, json_encode($newArrData));

                            // Load sync fields
                            $arrMappedParams = array(
                                'ed_in_BC' => $newArrData['syncA_App_Edu_HighestLevel_BC']
                            );

                            // Note: this is not PHP date constants, but ISO
                            // check details here: http://framework.zend.com/manual/1.12/en/zend.date.constants.html
                            $zendDateFormat = 'yyyy-MM-dd';

                            // Create/update case for just created/updated IA
                            if (empty($strError)) {
                                $arrCaseParams = array();

                                // Load grouped fields
                                $arrGroupedCaseFields = $oClients->getFields()->getGroupedCompanyFields($client['client_type_id']);

                                // Load all company fields for specific case type,
                                // which are available for the current user
                                $arrCaseFields = $oClients->getFields()->getCaseTemplateFields($client['company_id'], $client['client_type_id']);

                                $currentRowId            = '';
                                $previousBlockContact    = '';
                                $previousBlockRepeatable = '';
                                foreach ($arrGroupedCaseFields as $arrGroupInfo) {
                                    if (!isset($arrGroupInfo['fields'])) {
                                        continue;
                                    }

                                    if ($previousBlockContact != $arrGroupInfo['group_contact_block'] || $previousBlockRepeatable != $arrGroupInfo['group_repeatable']) {
                                        $currentRowId = $oClients->generateRowId();
                                    }
                                    $previousBlockContact    = $arrGroupInfo['group_contact_block'];
                                    $previousBlockRepeatable = $arrGroupInfo['group_repeatable'];
                                    $groupId                 = 'case_group_row_' . $arrGroupInfo['group_id'];

                                    foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                                        $fieldValToSave = '';
                                        if (empty($caseId) && !array_key_exists($groupId, $arrCaseParams)) {
                                            $arrCaseParams[$groupId] = array($currentRowId);
                                        }

                                        if (array_key_exists($arrFieldInfo['field_unique_id'], $arrMappedParams)) {
                                            // Convert fields data from readable format to the correct one (e.g. use ids for office field)
                                            $arrFieldValResult = $oClients->getFields()->getFieldValue(
                                                $arrCaseFields,
                                                $arrFieldInfo['field_unique_id'],
                                                trim($arrMappedParams[$arrFieldInfo['field_unique_id']]),
                                                null,
                                                $client['company_id'],
                                                true,
                                                false,
                                                $zendDateFormat
                                            );

                                            if ($arrFieldValResult['error']) {
                                                $strError .= $arrFieldValResult['error-msg'];
                                            } else {
                                                $fieldValToSave = $arrFieldValResult['result'];
                                            }

                                            $arrCaseParams[$arrFieldInfo['field_id']] = $fieldValToSave;
                                        }
                                    }
                                }

                                if (empty($strError)) {
                                    $arrUpdateResult = $oClients->saveClientData($caseId, $arrCaseParams);
                                    if (!$arrUpdateResult['success']) {
                                        $strError = 'Internal error.';
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $strError = $e->getMessage();
                    }

                    if (!empty($strError)) {
                        $output->writeln('<error>Failed to handle SI registration #' . $client['fileNumber'] . '. Reason: ' . $strError . '</error>');
                    } else {
                        $processed++;
                    }
                }

                $output->writeln("$skipped SI registrations skipped having no 'Education in BC field'.");
                $output->writeln("$processed SI registrations processed.");
            }

            $skipped   = 0;
            $processed = 0;
            $select    = $db->select()
                ->from(array('c' => 'clients'), array('c.member_id', 'c.client_type_id', 'fa.FormAssignedId', 'ct.company_id', 'c.fileNumber'))
                ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id')
                ->join(array('co' => 'company'), 'co.company_id = ct.company_id')
                ->join(array('fa' => 'FormAssigned'), 'fa.ClientMemberId = c.member_id')
                ->join(array('m' => 'members_relations'), 'm.child_member_id = c.member_id')
                ->where('ct.client_type_name = ?', 'Business Immigration Registration')
                ->where('co.companyName = ?', 'BC PNP');

            $clients = $db->fetchAll($select);
            if (empty($clients)) {
                $output->writeln('<error>No EI Registrations found.</error>');
            } else {
                foreach ($clients as $client) {
                    $strError = '';

                    try {
                        $arrData = array();

                        // Get assigned form info by id
                        $assignedFormInfo = $forms->getFormAssigned()->getAssignedFormInfo($client['FormAssignedId']);
                        if (!$assignedFormInfo) {
                            $strError = 'There is no form with this assigned id.';
                        }

                        if (empty($strError)) {
                            // Return xfdf for specific member id
                            $caseId         = $assignedFormInfo['ClientMemberId'];
                            $familyMemberId = $assignedFormInfo['FamilyMemberId'];

                            // Check if we need load data from json or xfdf file
                            $jsonFilePath = $oFiles->getClientJsonFilePath($caseId, $familyMemberId, $client['FormAssignedId']);
                            if (file_exists($jsonFilePath)) {
                                $savedJson = file_get_contents($jsonFilePath);
                                $arrData   = (array)json_decode($savedJson);
                            }

                            $arrMappedParams = array();
                            foreach ($arrData as $key => $values) {
                                if ($key == 'Partners') {
                                    foreach ($values as $delta => $partner) {
                                        foreach ($partner as $fieldName => $fieldValue) {
                                            list($fieldName, $index) = explode('-', $fieldName);
                                            $index++;
                                            if ($fieldName == 'BCPNP_Reg_PartnerLname') {
                                                $arrMappedParams["partner_lname_{$index}"] = $fieldValue;
                                            } elseif ($fieldName == 'BCPNP_Reg_PartnerFname') {
                                                $arrMappedParams["partner_fname_{$index}"] = $fieldValue;
                                            }
                                        }
                                    }
                                    break;
                                }
                            }

                            if (!$arrMappedParams) {
                                $skipped++;
                                continue;
                            }

                            // Note: this is not PHP date constants, but ISO
                            // check details here: http://framework.zend.com/manual/1.12/en/zend.date.constants.html
                            $zendDateFormat = 'yyyy-MM-dd';

                            // Create/update case for just created/updated IA
                            if (empty($strError)) {
                                $arrCaseParams = array();

                                // Load grouped fields
                                $arrGroupedCaseFields = $oClients->getFields()->getGroupedCompanyFields($client['client_type_id']);

                                // Load all company fields for specific case type,
                                // which are available for the current user
                                $arrCaseFields = $oClients->getFields()->getCaseTemplateFields($client['company_id'], $client['client_type_id']);

                                $currentRowId            = '';
                                $previousBlockContact    = '';
                                $previousBlockRepeatable = '';
                                foreach ($arrGroupedCaseFields as $arrGroupInfo) {
                                    if (!isset($arrGroupInfo['fields'])) {
                                        continue;
                                    }

                                    if ($previousBlockContact != $arrGroupInfo['group_contact_block'] || $previousBlockRepeatable != $arrGroupInfo['group_repeatable']) {
                                        $currentRowId = $oClients->generateRowId();
                                    }
                                    $previousBlockContact    = $arrGroupInfo['group_contact_block'];
                                    $previousBlockRepeatable = $arrGroupInfo['group_repeatable'];
                                    $groupId                 = 'case_group_row_' . $arrGroupInfo['group_id'];

                                    foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                                        $fieldValToSave = '';
                                        if (empty($caseId) && !array_key_exists($groupId, $arrCaseParams)) {
                                            $arrCaseParams[$groupId] = array($currentRowId);
                                        }

                                        if (array_key_exists($arrFieldInfo['field_unique_id'], $arrMappedParams)) {
                                            // Convert fields data from readable format to the correct one (e.g. use ids for office field)
                                            $arrFieldValResult = $oClients->getFields()->getFieldValue(
                                                $arrCaseFields,
                                                $arrFieldInfo['field_unique_id'],
                                                trim($arrMappedParams[$arrFieldInfo['field_unique_id']]),
                                                null,
                                                $client['company_id'],
                                                true,
                                                false,
                                                $zendDateFormat
                                            );

                                            if ($arrFieldValResult['error']) {
                                                $strError .= $arrFieldValResult['error-msg'];
                                            } else {
                                                $fieldValToSave = $arrFieldValResult['result'];
                                            }

                                            $arrCaseParams[$arrFieldInfo['field_id']] = $fieldValToSave;
                                        }
                                    }
                                }

                                if (empty($strError)) {
                                    $arrUpdateResult = $oClients->saveClientData($caseId, $arrCaseParams);
                                    if (!$arrUpdateResult['success']) {
                                        $strError = 'Internal error.';
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $strError = $e->getMessage();
                    }

                    if (!empty($strError)) {
                        $output->writeln('<error>Failed to handle EI registration #' . $client['fileNumber'] . '. Reason: ' . $strError . '</error>');
                    } else {
                        $processed++;
                    }
                }

                $output->writeln("$skipped EI registrations skipped having no 'Partners'.");
                $output->writeln("$processed EI registrations processed.");
            }
        } catch (\Exception $e) {
            $output->writeln('<error>Internal error happen. Reason: ' . $e->getMessage() . '</error>');
        }
    }

    public function down()
    {
    }

}
