<?php

use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Forms\FormAssigned;
use Phinx\Migration\AbstractMigration;

class FixScoringLanguages extends AbstractMigration
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
                ->where('ct.client_type_name = ?', 'Skills Immigration Registration')
                ->where('co.companyName = ?', 'BC PNP');

            $clients = $db->fetchAll($select);
            if (empty($clients)) {
                $output->writeln('<error>No SI Registrations found.</error>');
            } else {
                /** @var Forms $forms */
                $forms = Zend_Registry::get('serviceManager')->get(Forms::class);
                /** @var Files $oFiles */
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

                            $do_overwrite = false;
                            foreach ($arrData as $key => $value) {
                                if (in_array(
                                    $key,
                                    array(
                                        'BCPNP_App_LangTest_ResListening',
                                        'BCPNP_App_LangTest_ResReading',
                                        'BCPNP_App_LangTest_ResWriting',
                                        'BCPNP_App_LangTest_ResSpeaking'
                                    )
                                )) {
                                    $trimmedData = trim($value);
                                    if ($value != $trimmedData) {
                                        $do_overwrite = true;
                                    }
                                }
                            }

                            if ($do_overwrite) {
                                $output->writeln('Language test scores for registration #' . $client['fileNumber'] . ' contain spaces. Please check scores for this registration.');
                            }
                        }
                    } catch (\Exception $e) {
                        $strError = $e->getMessage();
                    }

                    if (!empty($strError)) {
                        $output->writeln('<error>Failed to check spaces in language scores for registration #' . $client['fileNumber'] . '. Reason: ' . $strError . '</error>');
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
