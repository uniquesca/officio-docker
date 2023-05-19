<?php

use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Forms\FormAssigned;
use Officio\Service\AuthHelper;
use Phinx\Migration\AbstractMigration;

class FixCountriesInFormsSiApp extends AbstractMigration
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
        $fieldsToConvert = array(
            'BCPNP_App_BirthPlace_Country',
            'syncA_App_Citizenship1',
            'BCPNP_App_Citizenship2',
            'syncA_ExtUsr_PassportCountry',
            'syncA_ExtUsr_MailCountry',
            'BCPNP_App_ResCountry',
            'BCPNP_App_CurResidence_Country',
            'BCPNP_App_Spouse_BirthPlace',
            'BCPNP_App_Spouse_Citizenship',
            'BCPNP_App_Mother_BirthPlace',
            'BCPNP_App_Father_BirthPlace',
            'BCPNP_App_Emp_MailCountry',
            'BCPNP_App_Emp_BusCountry',
        );

        $groupNames = array(
            'SecEduRecords'     => array('BCPNP_App_EduSec_Country'),
            'PostEduNCARecords' => array('BCPNP_App_EduPostSec_Country'),
            'WorkExpRecords'    => array('BCPNP_App_Work_CompCountry'),
            'ChildRecords'      => array('BCPNP_App_Child_BirthPlace', 'BCPNP_App_Child_Citizenship'),
            'SiblingRecords'    => array('BCPNP_App_Sibling_BirthPlace'),
            'OrgRecords'        => array('BCPNP_App_Member_Country'),
            'GovRecords'        => array('BCPNP_App_GovPosition_Country')
        );

        $countriesConversion = array(
            'Vatican'                                      => 'Vatican City State (Holy See)',
            'Palestina'                                    => 'Palestine',
            'Saint Helena, Ascension and Tristan da Cunha' => 'St. Helena',
            'Saint Pierre and Miquelon'                    => 'St. Pierre and Miquelon',
            'Timor-Leste'                                  => 'East Timor',
        );

        $output = $this->getOutput();
        /** @var Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        try {
            $select = $db->select()
                ->from(array('c' => 'clients'), array('c.member_id', 'c.client_type_id', 'fa.FormAssignedId', 'ct.company_id', 'c.fileNumber'))
                ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id')
                ->join(array('co' => 'company'), 'co.company_id = ct.company_id')
                ->join(array('fa' => 'FormAssigned'), 'fa.ClientMemberId = c.member_id')
                ->join(array('m' => 'members_relations'), 'm.child_member_id = c.member_id')
                ->where('ct.client_type_name = ?', 'Skills Immigration Application')
                ->where('co.companyName = ?', 'BC PNP');

            $clients = $db->fetchAll($select);
            if (empty($clients)) {
                $output->writeln('<error>No SI Applications found.</error>');
            } else {
                if (!$this->authenticateAsCompanyAdmin($db, 'BC PNP')) {
                    $output->writeln('<error>Unable to authenticate.</error>');
                    return false;
                }

                /** @var Forms $forms */
                $forms = Zend_Registry::get('serviceManager')->get(Forms::class);
                /** @var Files $oFiles */
                $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);

                foreach ($clients as $client) {
                    $strError   = '';
                    $do_rewrite = false;
                    $arrData    = false;

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
                                $arrData   = (array)json_decode($savedJson, true);
                            }

                            if (!empty($arrData)) {
                                foreach ($arrData as $fieldName => &$value) {
                                    if (in_array($fieldName, $fieldsToConvert) && in_array($value, array_keys($countriesConversion))) {
                                        $arrData[$fieldName] = $countriesConversion[$value];
                                        $do_rewrite          = true;
                                    }
                                    if (in_array($fieldName, array_keys($groupNames)) && is_array($value)) {
                                        foreach ($value as $sectionName => &$sectionValues) {
                                            foreach ($sectionValues as $sectionFieldName => $sectionFieldValue) {
                                                list($cleanFieldName, $delta) = explode('-', $sectionFieldName);
                                                if (in_array($cleanFieldName, $groupNames[$fieldName]) && in_array($sectionFieldValue, array_keys($countriesConversion))) {
                                                    $sectionValues[$sectionFieldName] = $countriesConversion[$sectionFieldValue];
                                                    $do_rewrite                       = true;
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($do_rewrite) {
                                    file_put_contents($jsonFilePath, json_encode($arrData));
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $strError = $e->getMessage();
                    }

                    if (empty($strError)) {
                        if ($do_rewrite) {
                            $output->writeln('Countries for SI application #' . $client['fileNumber'] . ' are successfully fixed.');
                        }
                    } else {
                        $output->writeln('<error>Failed to check application #' . $client['fileNumber'] . '. Reason: ' . $strError . '</error>');
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
