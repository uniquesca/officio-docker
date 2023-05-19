<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Forms\FormAssigned;
use Officio\Service\AuthHelper;
use Officio\Service\Settings;
use Phinx\Migration\AbstractMigration;

class FixScoringDistricts extends AbstractMigration
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
            'Albas'                  => 'Columbia-Shuswap',
            'Albert Canyon'          => 'Columbia-Shuswap',
            'Almond Gardens'         => 'Kootenay Boundary',
            'Anaconda'               => 'Kootenay Boundary',
            'Anglemont'              => 'Columbia-Shuswap',
            'Annis'                  => 'Columbia-Shuswap',
            'Arrowhead'              => 'Columbia-Shuswap',
            'Balmoral'               => 'Columbia-Shuswap',
            'Bastion Bay'            => 'Columbia-Shuswap',
            'Bear Creek'             => 'Columbia-Shuswap',
            'Beard\'s Creek'         => 'Columbia-Shuswap',
            'Beaton'                 => 'Columbia-Shuswap',
            'Beaver Falls'           => 'Kootenay Boundary',
            'Beaverdell'             => 'Kootenay Boundary',
            'Beavermouth'            => 'Columbia-Shuswap',
            'Big Eddy'               => 'Columbia-Shuswap',
            'Big White Village'      => 'Kootenay Boundary',
            'Billings'               => 'Kootenay Boundary',
            'Birchbank'              => 'Kootenay Boundary',
            'Blaeberry'              => 'Columbia-Shuswap',
            'Blind Bay'              => 'Columbia-Shuswap',
            'Boundary Falls'         => 'Kootenay Boundary',
            'Bridesville'            => 'Kootenay Boundary',
            'Broadview'              => 'Columbia-Shuswap',
            'Cambie'                 => 'Columbia-Shuswap',
            'Camborne'               => 'Columbia-Shuswap',
            'Camp McKinney'          => 'Kootenay Boundary',
            'Canoe'                  => 'Columbia-Shuswap',
            'Canyon Hot Springs'     => 'Columbia-Shuswap',
            'Carlin'                 => 'Columbia-Shuswap',
            'Carmi'                  => 'Kootenay Boundary',
            'Carson'                 => 'Kootenay Boundary',
            'Cascade'                => 'Kootenay Boundary',
            'Casino'                 => 'Kootenay Boundary',
            'Castledale'             => 'Columbia-Shuswap',
            'Cathedral'              => 'Columbia-Shuswap',
            'Cedar Heights Estates'  => 'Columbia-Shuswap',
            'Celista'                => 'Columbia-Shuswap',
            'Christian Valley'       => 'Kootenay Boundary',
            'Christina Lake'         => 'Kootenay Boundary',
            'Clanwilliam'            => 'Columbia-Shuswap',
            'Columbia Gardens'       => 'Kootenay Boundary',
            'Copper Cove'            => 'Columbia-Shuswap',
            'Craigellachie'          => 'Columbia-Shuswap',
            'Day\'s Subdivision'     => 'Columbia-Shuswap',
            'Deadwood'               => 'Kootenay Boundary',
            'Deep Creek'             => 'Columbia-Shuswap',
            'Dolan Road Subdivision' => 'Columbia-Shuswap',
            'Donald'                 => 'Columbia-Shuswap',
            'Downie'                 => 'Columbia-Shuswap',
            'Eagle Bay'              => 'Columbia-Shuswap',
            'East Trail'             => 'Kootenay Boundary',
            'Edelweiss'              => 'Columbia-Shuswap',
            'Eholt'                  => 'Kootenay Boundary',
            'Falkland'               => 'Columbia-Shuswap',
            'Ferguson'               => 'Columbia-Shuswap',
            'Field'                  => 'Columbia-Shuswap',
            'Fife'                   => 'Kootenay Boundary',
            'Five Mile'              => 'Columbia-Shuswap',
            'Flat Creek'             => 'Columbia-Shuswap',
            'Forde'                  => 'Columbia-Shuswap',
            'Fraine'                 => 'Columbia-Shuswap',
            'Fruitvale'              => 'Kootenay Boundary',
            'Galena'                 => 'Columbia-Shuswap',
            'Genelle'                => 'Kootenay Boundary',
            'Gerrard'                => 'Columbia-Shuswap',
            'Gilpin'                 => 'Kootenay Boundary',
            'Glacier'                => 'Columbia-Shuswap',
            'Gleneden'               => 'Columbia-Shuswap',
            'Glenemma'               => 'Columbia-Shuswap',
            'Glenmerry'              => 'Kootenay Boundary',
            'Glenogle'               => 'Columbia-Shuswap',
            'Golden'                 => 'Columbia-Shuswap',
            'Grand Forks'            => 'Kootenay Boundary',
            'Greeley'                => 'Columbia-Shuswap',
            'Greenwood'              => 'Kootenay Boundary',
            'Griffith'               => 'Columbia-Shuswap',
            'Harrogate'              => 'Columbia-Shuswap',
            'Hector'                 => 'Columbia-Shuswap',
            'Horse Creek'            => 'Columbia-Shuswap',
            'Illecillewaet'          => 'Columbia-Shuswap',
            'Kerr Creek'             => 'Kootenay Boundary',
            'Kettle Valley'          => 'Kootenay Boundary',
            'Lafferty'               => 'Kootenay Boundary',
            'Lauretta'               => 'Columbia-Shuswap',
            'Leanchoil'              => 'Columbia-Shuswap',
            'Lee Creek'              => 'Columbia-Shuswap',
            'Lower China Creek'      => 'Kootenay Boundary',
            'Macdonald'              => 'Columbia-Shuswap',
            'Magna Bay'              => 'Columbia-Shuswap',
            'Malakwa'                => 'Columbia-Shuswap',
            'Marsh Creek Area'       => 'Kootenay Boundary',
            'McMurdo'                => 'Columbia-Shuswap',
            'Mica Creek'             => 'Columbia-Shuswap',
            'Midway'                 => 'Kootenay Boundary',
            'Mile 19 Overhead'       => 'Columbia-Shuswap',
            'Moberly'                => 'Columbia-Shuswap',
            'Montrose'               => 'Kootenay Boundary',
            'Mount Baldy'            => 'Kootenay Boundary',
            'Niagara'                => 'Kootenay Boundary',
            'Nicholson'              => 'Columbia-Shuswap',
            'Notch Hill'             => 'Columbia-Shuswap',
            'Nursery'                => 'Kootenay Boundary',
            'Oasis'                  => 'Kootenay Boundary',
            'Ottertail'              => 'Columbia-Shuswap',
            'Palliser'               => 'Columbia-Shuswap',
            'Paradise Point'         => 'Columbia-Shuswap',
            'Park Siding'            => 'Kootenay Boundary',
            'Parson'                 => 'Columbia-Shuswap',
            'Paterson'               => 'Kootenay Boundary',
            'Paulson'                => 'Kootenay Boundary',
            'Phoenix'                => 'Kootenay Boundary',
            'Poupore'                => 'Kootenay Boundary',
            'Ranchero'               => 'Columbia-Shuswap',
            'Red Mountain'           => 'Kootenay Boundary',
            'Redgrave'               => 'Columbia-Shuswap',
            'Revelstoke'             => 'Columbia-Shuswap',
            'Rhone'                  => 'Kootenay Boundary',
            'Rivervale'              => 'Kootenay Boundary',
            'Rock Creek'             => 'Kootenay Boundary',
            'Rogers'                 => 'Columbia-Shuswap',
            'Rogers Pass'            => 'Columbia-Shuswap',
            'Ross Peak'              => 'Columbia-Shuswap',
            'Rossland'               => 'Kootenay Boundary',
            'Salmon Arm'             => 'Columbia-Shuswap',
            'Sandy Point'            => 'Columbia-Shuswap',
            'Scotch Creek'           => 'Columbia-Shuswap',
            'Seeney'                 => 'Columbia-Shuswap',
            'Seymour Arm'            => 'Columbia-Shuswap',
            'Shelter Bay'            => 'Columbia-Shuswap',
            'Sicamous'               => 'Columbia-Shuswap',
            'Sidley'                 => 'Kootenay Boundary',
            'Silica'                 => 'Kootenay Boundary',
            'Silver Creek'           => 'Columbia-Shuswap',
            'Six Mile Point'         => 'Columbia-Shuswap',
            'Solsqua'                => 'Columbia-Shuswap',
            'Sorrento'               => 'Columbia-Shuswap',
            'South Canoe'            => 'Columbia-Shuswap',
            'Squilax'                => 'Columbia-Shuswap',
            'St. Ives'               => 'Columbia-Shuswap',
            'Stephen'                => 'Columbia-Shuswap',
            'Stoney Creek'           => 'Columbia-Shuswap',
            'Sundance Subdivision'   => 'Columbia-Shuswap',
            'Sunningdale'            => 'Kootenay Boundary',
            'Sunnybrae'              => 'Columbia-Shuswap',
            'Sweetsbridge'           => 'Columbia-Shuswap',
            'Tadanac'                => 'Kootenay Boundary',
            'Taft'                   => 'Columbia-Shuswap',
            'Tappen'                 => 'Columbia-Shuswap',
            'Three Valley'           => 'Columbia-Shuswap',
            'Tiilis Landing'         => 'Columbia-Shuswap',
            'Trail'                  => 'Kootenay Boundary',
            'Trout Lake'             => 'Columbia-Shuswap',
            'Twin Butte'             => 'Columbia-Shuswap',
            'Upper China Creek'      => 'Kootenay Boundary',
            'Wakely'                 => 'Columbia-Shuswap',
            'Waneta'                 => 'Kootenay Boundary',
            'Warfield'               => 'Kootenay Boundary',
            'West Mara Lake'         => 'Columbia-Shuswap',
            'West Midway'            => 'Kootenay Boundary',
            'West Trail'             => 'Kootenay Boundary',
            'Westbridge'             => 'Kootenay Boundary',
            'White Lake'             => 'Columbia-Shuswap',
            'Woods Landing'          => 'Columbia-Shuswap',
            'Yankee Flats'           => 'Columbia-Shuswap',
            'Yoho'                   => 'Columbia-Shuswap',
            'Zamora'                 => 'Kootenay Boundary',
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
                /** @var Settings $settings */
                $settings = Zend_Registry::get('serviceManager')->get(Settings::class);
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
                                    $district = $citiesToFix[$city];
                                    $newScore = ($district == 'Kootenay Boundary') ? 10 : 8;
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

                                $dateFormatFull = $settings->variable_get('dateFormatFull');
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
                            $output->writeln('Scoring district for registration #' . $client['fileNumber'] . ' is successfully fixed.');
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
