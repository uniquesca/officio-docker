<?php

use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Forms\FormAssigned;
use Phinx\Migration\AbstractMigration;

class EiApplicationMappingFixes extends AbstractMigration
{
    public function up()
    {
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
                ->where('ct.client_type_name = ?', 'Business Immigration Application')
                ->where('fa.FormVersionId = ?', 12)
                ->where('co.companyName = ?', 'BC PNP');

            $clients = $db->fetchAll($select);
            if (empty($clients)) {
                $output->writeln('<error>No EI Applications v2 found.</error>');
            } else {
                /** @var Forms $forms */
                $forms = Zend_Registry::get('serviceManager')->get(Forms::class);
                /** @var \Files\Service\Files $oFiles */
                $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);

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

                            foreach ($arrData as $key => $value) {
                                if (in_array(
                                    $key,
                                    array(
                                        'BCPNP_ResAddrLine',
                                        'BCPNP_ResCity',
                                        'BCPNP_ResProvince',
                                        'BCPNP_ResCountry',
                                        'BCPNP_ResPostal'
                                    )
                                )) {
                                    $newKey = str_replace('BCPNP_', 'syncA_', $key);
                                    unset($arrData[$key]);
                                    $arrData[$newKey] = $value;
                                } else {
                                    if ($key == 'syncA_BusRegionDistBasic') {
                                        $newKey = 'syncA_BusRegionDist';
                                        unset($arrData[$key]);
                                        $arrData[$newKey] = $value;
                                    } else {
                                        if ($key == 'syncA_BusMunicipalityBasic') {
                                            $newKey = 'syncA_BusMunicipality';
                                            unset($arrData[$key]);
                                            $arrData[$newKey] = $value;
                                        }
                                    }
                                }
                            }

                            file_put_contents($jsonFilePath, json_encode($arrData));
                        }
                    } catch (\Exception $e) {
                        $strError = $e->getMessage();
                    }

                    if (!empty($strError)) {
                        $output->writeln('<error>Failed to update residential address for registration #' . $client['fileNumber'] . '. Reason: ' . $strError . '</error>');
                    }
                }
            }
        } catch (\Exception $e) {
            $output[] = 'Internal error. Reason: ' . $e->getMessage();
        }
    }

    public function down()
    {
    }
}