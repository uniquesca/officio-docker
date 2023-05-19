<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Service\Company;
use Officio\Service\Country;
use Officio\Common\Service\Log;
use Phinx\Migration\AbstractMigration;

class ConvertAgentsCa extends AbstractMigration
{
    public function up()
    {
        // Took 3793s on local server...
        try {
            /** @var Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from(array('a' => 'agents'))
                ->order(array('company_id ASC', 'agent_id ASC'));

            $arrAgents = $db->fetchAll($select);

            /** @var Country $oCountry */
            $oCountry         = Zend_Registry::get('serviceManager')->get(Country::class);
            /** @var Files $oFiles */
            $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);
            /** @var Clients $oClients */
            $oClients          = Zend_Registry::get('serviceManager')->get(Clients::class);
            /** @var Company $oCompany */
            $oCompany = Zend_Registry::get('serviceManager')->get(Company::class);
            $contactTypeId         = $oClients->getMemberTypeIdByName('contact');
            $internalContactTypeId = $oClients->getMemberTypeIdByName('internal_contact');
            $applicantTypeName     = 'Sales Agent';

            $booAustralia          = Zend_Registry::get('serviceManager')->get('config')['site_version']['version'] == 'australia';
            $strClientLastNameKey  = $booAustralia ? 'family_name' : 'last_name';
            $strClientFirstNameKey = $booAustralia ? 'given_names' : 'first_name';


            echo "TOTAL: " . count($arrAgents) . PHP_EOL;

            $arrCachedInfo    = array();
            $arrFilesToDelete = array();

            $i          = 0;
            $totalCount = count($arrAgents);
            foreach ($arrAgents as $arrAgentInfo) {
                echo "Agent id: #$arrAgentInfo[agent_id] " . '(' . ++$i . '/' . $totalCount . ') ';

                $companyId = $arrAgentInfo['company_id'];

                // If is cached info - use it, otherwise load and save in "cache"
                $arrTitleOptions = array();
                if (isset($arrCachedInfo[$companyId]) && count($arrCachedInfo[$companyId])) {
                    $adminId               = $arrCachedInfo[$companyId]['admin_id'];
                    $applicantTypeId       = $arrCachedInfo[$companyId]['applicant_type_id'];
                    $arrOffices            = $arrCachedInfo[$companyId]['offices'];
                    $arrParentClientFields = $arrCachedInfo[$companyId]['fields'];
                    $arrTitleOptions       = $arrCachedInfo[$companyId]['title_options'];
                    $booLocal              = $arrCachedInfo[$companyId]['is_local_storage'];
                } else {
                    // Get the real admin of the company
                    $adminId = $arrCachedInfo[$companyId]['admin_id'] = $oCompany->getCompanyAdminId($companyId, 0);
                    if (empty($adminId)) {
                        echo "!!!!! Admin does not exists !!!!!" . PHP_EOL;
                        continue;
                    }

                    $applicantTypeId = $arrCachedInfo[$companyId]['applicant_type_id'] = $oClients->getApplicantTypes()->getTypeIdByName($companyId, $contactTypeId, $applicantTypeName);
                    if (empty($applicantTypeId)) {
                        echo sprintf("!!!!! Applicant type %s does not exists !!!!!", $applicantTypeName) . PHP_EOL;
                        continue;
                    }

                    $arrOffices = $arrCachedInfo[$companyId]['offices'] = $oCompany->getDivisions($companyId, 0, true);

                    $arrParentClientFields = $arrCachedInfo[$companyId]['fields'] = $oClients->getApplicantFields()->getCompanyFields($companyId, $contactTypeId, $applicantTypeId);

                    foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                        if ($arrParentClientFieldInfo['applicant_field_unique_id'] == 'title') {
                            $arrTitleOptions = $arrCachedInfo[$companyId]['title_options'] = $oClients->getApplicantFields()->getFieldsOptions($arrParentClientFieldInfo['applicant_field_id']);
                            break;
                        }
                    }

                    $booLocal = $arrCachedInfo[$companyId]['is_local_storage'] = $oCompany->isCompanyStorageLocationLocal($companyId);
                }

                $arrFieldsMapping = array(
                    $strClientLastNameKey  => $arrAgentInfo['lName'],
                    $strClientFirstNameKey => $arrAgentInfo['fName'],
                    'notes'                => $arrAgentInfo['notes'],
                    'address_1'            => $arrAgentInfo['address1'],
                    'address_2'            => $arrAgentInfo['address2'],
                    'city'                 => $arrAgentInfo['city'],
                    'country'              => $oCountry->getCountryName($arrAgentInfo['country']),
                    'state'                => $arrAgentInfo['state'],
                    'zip_code'             => $arrAgentInfo['zip'] == '0' ? '' : $arrAgentInfo['zip'],
                    'phone_main'           => $arrAgentInfo['workPhone'],
                    'phone_secondary'      => $arrAgentInfo['homePhone'],
                    'email'                => $arrAgentInfo['email1'],
                    'email_1'              => $arrAgentInfo['email2'],
                    'email_2'              => $arrAgentInfo['email3'],
                    'fax_home'             => $arrAgentInfo['faxHome'],
                    'fax_w'                => $arrAgentInfo['faxWork'],
                    'fax_other'            => $arrAgentInfo['faxOthers'],
                    'office'               => implode(',', $arrOffices),
                );

                foreach ($arrTitleOptions as $arrTitleOptionInfo) {
                    if ($arrTitleOptionInfo['value'] == $arrAgentInfo['title'] || $arrTitleOptionInfo['value'] == $arrAgentInfo['title'] . '.') {
                        $arrFieldsMapping['title'] = $arrTitleOptionInfo['applicant_form_default_id'];
                        break;
                    }
                }

                $arrInternalContacts = array();
                $arrClientData       = array();

                foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                    foreach ($arrFieldsMapping as $uniqueFieldId => $fieldVal) {
                        if ($arrParentClientFieldInfo['applicant_field_unique_id'] == $uniqueFieldId) {
                            // Group fields by parent client type
                            // i.e. internal contact info and main client info
                            if ($arrParentClientFieldInfo['member_type_id'] == $internalContactTypeId) {
                                if (!array_key_exists($arrParentClientFieldInfo['applicant_block_id'], $arrInternalContacts)) {
                                    $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']] = array(
                                        'parent_group_id' => array(),
                                        'data'            => array()
                                    );
                                }

                                if (!in_array($arrParentClientFieldInfo['group_id'], $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'])) {
                                    $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'][] = $arrParentClientFieldInfo['group_id'];
                                }

                                $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['data'][] = array(
                                    'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                    'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                    'value'           => $fieldVal,
                                    'row'             => 0,
                                    'row_id'          => 0
                                );
                            } else {
                                $arrClientData[] = array(
                                    'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                    'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                    'value'           => $fieldVal,
                                    'row'             => 0,
                                    'row_id'          => 0
                                );
                            }
                        }
                    }
                }

                // Convert Agent to Contact
                $arrNewClientInfo = array(
                    'createdBy'  => $adminId,
                    'createdOn'  => $arrAgentInfo['regTime'],
                    'arrParents' => array(
                        array(
                            // Parent client info
                            'arrParentClientInfo' => array(
                                'emailAddress'     => $arrAgentInfo['email1'],
                                'fName'            => $arrAgentInfo['fName'],
                                'lName'            => $arrAgentInfo['lName'],
                                'createdBy'        => $adminId,
                                'createdOn'        => $arrAgentInfo['regTime'],
                                'memberTypeId'     => $contactTypeId,
                                'applicantTypeId'  => $applicantTypeId,
                                'arrApplicantData' => $arrClientData,
                                'arrOffices'       => $arrOffices,
                            ),

                            // Internal contact(s) info
                            'arrInternalContacts' => $arrInternalContacts,
                        )
                    )
                );

                $arrCreationResult = $oClients->createClient($arrNewClientInfo, $companyId, 0);

                if (!empty($arrCreationResult['strError'])) {
                    echo sprintf("!!!!! Error: %s !!!!!", $arrCreationResult['strError']) . PHP_EOL;
                } else {
                    // Now point to new Contact (for all already saved agent fields)
                    $arrCreatedClientIds = $arrCreationResult['arrCreatedClientIds'];
                    foreach ($arrCreatedClientIds as $createdMemberId) {
                        $arrMemberInfo = $oClients->getMemberInfo($createdMemberId);
                        if ($arrMemberInfo['userType'] == $contactTypeId) {
                            $arrAgentFieldIds = $oClients->getFields()->getFieldIdByType($arrAgentInfo['company_id'], 'agent');

                            if (is_array($arrAgentFieldIds) && count($arrAgentFieldIds)) {
                                $db->update(
                                    'client_form_data',
                                    array('value' => $createdMemberId),
                                    $db->quoteInto('field_id IN (?)', $arrAgentFieldIds) .
                                    $db->quoteInto(' AND value = ?', $arrAgentInfo['agent_id'])
                                );
                            }

                            $db->update(
                                'company_prospects',
                                array('agent_id' => $createdMemberId),
                                $db->quoteInto('agent_id = ?', $arrAgentInfo['agent_id'])
                            );

                            $db->update(
                                'clients',
                                array('agent_id' => $createdMemberId),
                                $db->quoteInto('agent_id = ?', $arrAgentInfo['agent_id'])
                            );

                            $db->update(
                                'company_questionnaires',
                                array('q_agent_id' => $createdMemberId),
                                $db->quoteInto('q_agent_id = ?', $arrAgentInfo['agent_id'])
                            );
                        }
                    }

                    // Delete agent from agents table + delete logo
                    $this->execute(sprintf('DELETE FROM `agents` WHERE agent_id = %d', $arrAgentInfo['agent_id']));

                    if (!empty($arrAgentInfo['logoFileName'])) {
                        $arrFilesToDelete[] = array(
                            'local' => (int)$booLocal,
                            'path'  => $oFiles->getAgentLogoPath($companyId, $booLocal) . '/' . 'field-' . $arrAgentInfo['agent_id'] . '-logo'
                        );
                    }

                    echo sprintf("Converted.") . PHP_EOL;
                }
            }

            $this->execute('DROP TABLE `agents`');

            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile('Delete such converted agents files: ', print_r($arrFilesToDelete, 1), 'agents_converted_delete_files');

        } catch (\Exception $e) {
            echo 'Fatal error' . print_r($e->getTraceAsString(), 1);
            throw $e;
        }
    }

    public function down()
    {
    }
}