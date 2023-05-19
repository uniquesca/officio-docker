<?php

use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Forms\FormAssigned;
use Phinx\Migration\AbstractMigration;

class EiRegistrationMappingFixes extends AbstractMigration
{
    public function up()
    {
        $output = $this->getOutput();
        /** @var Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $this->execute(
            "
            DELETE fm 
            FROM FormMap fm
            INNER JOIN FormSynField fsf ON fsf.SynFieldId = fm.FromSynFieldId
            WHERE fsf.FieldName IN (
                    'BCPNP_ResAddrLine',
                    'BCPNP_ResCity',
                    'BCPNP_ResProvince',
                    'BCPNP_ResCountry',
                    'BCPNP_ResPostal',
                    'syncA_BusRegionalNAICS',
                    'syncA_BusRegionDistPilot',
                    'syncA_BusMunicipalityPilot',
                    'syncA_InvestmentTotal'
                );
            "
        );

        $this->execute(
            "
            DELETE FROM FormSynField WHERE FieldName IN (
                'BCPNP_ResAddrLine',
                'BCPNP_ResCity',
                'BCPNP_ResProvince',
                'BCPNP_ResCountry',
                'BCPNP_ResPostal',
                'syncA_BusRegionalNAICS',
                'syncA_BusRegionDistPilot',
                'syncA_BusMunicipalityPilot'
            );
        "
        );

        $this->execute(
            "
            INSERT INTO `FormSynField` (`FieldName`) VALUES
            ('syncA_BusMunicipality'),
            ('syncA_BusRegionDist');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusMunicipality'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusMunicipality';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'BusRegionDist'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_BusRegionDist';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'PropInvest_Total'
            FROM `FormSynField`
            WHERE `FieldName` = 'syncA_InvestmentTotal';
        "
        );

        try {
            $select = $db->select()
                ->from(array('c' => 'clients'), array('c.member_id', 'c.client_type_id', 'fa.FormAssignedId', 'ct.company_id', 'c.fileNumber'))
                ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id')
                ->join(array('co' => 'company'), 'co.company_id = ct.company_id')
                ->join(array('fa' => 'FormAssigned'), 'fa.ClientMemberId = c.member_id')
                ->join(array('m' => 'members_relations'), 'm.child_member_id = c.member_id')
                ->where('ct.client_type_name = ?', 'Business Immigration Registration')
                ->where('fa.FormVersionId = ?', 11)
                ->where('co.companyName = ?', 'BC PNP');

            $clients = $db->fetchAll($select);
            if (empty($clients)) {
                $output->writeln('<error>No EI Registrations v3 found.</error>');
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