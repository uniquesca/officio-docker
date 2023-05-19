<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Forms\FormAssigned;
use Officio\Service\AuthHelper;
use Officio\Common\Service\Settings;
use Phinx\Migration\AbstractMigration;

class FixScoring1 extends AbstractMigration
{

    public function authenticateAsSuperadmin(Zend_Db_Adapter_Abstract $db)
    {
        $_SERVER['HTTP_HOST']   = 'localhost';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $superAdminQuery = $db->select()
            ->from(array('m' => 'members'), array('m.username', 'm.password'))
            ->join(array('mt' => 'members_types'), 'mt.member_type_id = m.userType')
            ->where('mt.member_type_name = ?', 'superadmin')
            ->limit(1);
        if (!$superadmin = $db->fetchRow($superAdminQuery)) {
            return false;
        }

        $username          = $superadmin['username'];
        $passwordEncrypted = $superadmin['password'];
        $password          = Officio\Encryption::decode($passwordEncrypted);

        /** @var AuthHelper $auth */
        $auth = Zend_Registry::get('serviceManager')->get(AuthHelper::class);

        return $auth->login($username, $password, false, true);
    }

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

        if (!$this->authenticateAsCompanyAdmin($db, 'BC PNP')) {
            $output->writeln('<error>Unable to authenticate.</error>');
            return false;
        }

        $citiesToFix = array(
            'Northern Rockies' => 10,
            'Lake Country'     => 2,
        );

        try {
            $select = $db->select()
                ->from(array('c' => 'clients'), array('c.member_id', 'c.client_type_id', 'fa.FormAssignedId', 'ct.company_id', 'c.fileNumber'))
                ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id')
                ->join(array('co' => 'company'), 'co.company_id = ct.company_id')
                ->join(array('fa' => 'FormAssigned'), 'fa.ClientMemberId = c.member_id')
                ->where('ct.client_type_name = ?', 'Skills Immigration Registration')
                ->where('co.companyName = ?', 'BC PNP');

            $clients = $db->fetchAll($select);
            if (empty($clients)) {
                $output->writeln('<error>No SI Registrations found.</error>');
            } else {
                /** @var Forms $forms */
                $forms = Zend_Registry::get('serviceManager')->get(Forms::class);
                /** @var Settings $oSettings */
                $oSettings = Zend_Registry::get('serviceManager')->get(Settings::class);
                /** @var Clients $oClients */
                $oClients = Zend_Registry::get('serviceManager')->get(Clients::class);
                /** @var Files $oFiles */
                $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);

                foreach ($clients as $client) {
                    $strError = '';
                    $arrData  = array();
                    $newScore = false;

                    try {
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

                            if (!empty($arrData['syncA_App_Job_WorkLocationCity'])) {
                                $city = $arrData['syncA_App_Job_WorkLocationCity'];
                                if (!empty($citiesToFix[$city])) {
                                    $newScore = $citiesToFix[$city];
                                }
                            }
                            if ($newScore) {
                                $arrData = array('Officio_scoreDistrictSI' => $newScore);

                                // Load sync fields
                                $arrMappedParams = array();

                                // Load Officio and sync fields - only they will be used
                                // during IA/Case creation/update, other fields will be used in xfdf files only
                                $strOfficioFieldPrefix = 'Officio_';
                                foreach ($arrData as $fieldId => $fieldVal) {
                                    $fieldId = trim($fieldId);

                                    // Check which field is related to:
                                    // - Officio field, that is not in the mapping table, but must be provided
                                    if (preg_match("/^$strOfficioFieldPrefix(.*)/", $fieldId, $regs)) {
                                        $arrMappedParams[$regs[1]] = $fieldVal;
                                    }
                                }

                                $dateFormatFull = $oSettings->variable_get('dateFormatFull');
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

                                                    // Date must be in the same format as it is passed from the client side
                                                    if (!empty($fieldValToSave) && in_array(
                                                            $arrFieldInfo['field_type'],
                                                            array(
                                                                'date',
                                                                'date_repeatable'
                                                            )
                                                        )
                                                    ) {
                                                        $fieldValToSave = date($dateFormatFull, strtotime($fieldValToSave));
                                                    }
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
                        }
                    } catch (\Exception $e) {
                        $strError = $e->getMessage();
                    }

                    if (empty($strError)) {
                        if ($newScore) {
                            $output->writeln('Scoring for registration #' . $client['fileNumber'] . ' is successfully fixed.');
                        }
                    } else {
                        $output->writeln('<error>Failed to check registration #' . $client['fileNumber'] . '. Reason: ' . $strError . '</error>');
                    }
                }
            }
        } catch (\Exception $e) {
            $output->writeln('<error>Internal error. Reason: ' . $e->getMessage() . '</error>');
        }
    }

    public function down()
    {
    }
}
