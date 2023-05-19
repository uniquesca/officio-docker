<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Common\Service\Log;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Settings;
use Officio\Migration\AbstractMigration;

class ConvertAgentsCa extends AbstractMigration
{
    public function up()
    {
        // Took 3652s on local server...
        try {
            $this->getAdapter()->commitTransaction();

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('agents')
                ->order(['company_id' => 'ASC', 'agent_id' => 'ASC'])
                ->execute();

            $arrAgents = $statement->fetchAll('assoc');

            /** @var Country $oCountry */
            $oCountry = self::getService(Country::class);

            /** @var Files $oFiles */
            $oFiles = self::getService(Files::class);

            /** @var Clients $oClients */
            $oClients = self::getService(Clients::class);

            /** @var Company $oCompany */
            $oCompany = self::getService(Company::class);

            $oFields          = $oClients->getFields();
            $oApplicantFields = $oClients->getApplicantFields();

            $contactTypeId         = $oClients->getMemberTypeIdByName('contact');
            $internalContactTypeId = $oClients->getMemberTypeIdByName('internal_contact');
            $applicantTypeName     = 'Sales Agent';

            $strClientLastNameKey  = 'last_name';
            $strClientFirstNameKey = 'first_name';

            $totalCount = count($arrAgents);
            echo "TOTAL: " . $totalCount . PHP_EOL;

            $arrCachedInfo = array();

            $i = 0;

            $arrAssignToOffices   = [];
            $arrApplicantFormData = [];
            $arrMembersRelations  = [];
            foreach ($arrAgents as $arrAgentInfo) {
                echo "Company id: #$arrAgentInfo[company_id] Agent id: #$arrAgentInfo[agent_id] " . '(' . ++$i . '/' . $totalCount . ') ';

                $companyId = $arrAgentInfo['company_id'];

                // If is cached info - use it, otherwise load and save in "cache"
                $arrTitleOptions  = array();
                $arrStatusOptions = array();
                $booLocal         = false; // All companies use S3
                if (isset($arrCachedInfo[$companyId]) && count($arrCachedInfo[$companyId])) {
                    $adminId               = $arrCachedInfo[$companyId]['admin_id'];
                    $applicantTypeId       = $arrCachedInfo[$companyId]['applicant_type_id'];
                    $arrOffices            = $arrCachedInfo[$companyId]['offices'];
                    $arrParentClientFields = $arrCachedInfo[$companyId]['fields'];
                    $arrTitleOptions       = $arrCachedInfo[$companyId]['title_options'];
                    $arrStatusOptions      = $arrCachedInfo[$companyId]['status_options'];
                    $arrAgentFieldIds      = $arrCachedInfo[$companyId]['agent_fields'];
                    $divisionGroupId       = $arrCachedInfo[$companyId]['division_group_id'];
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

                    $statement = $this->getQueryBuilder()
                        ->select(['f.*', 'g.applicant_group_id', 'b.applicant_block_id'])
                        ->from(['f' => 'applicant_form_fields'])
                        ->innerJoin(['o' => 'applicant_form_order'], ['o.applicant_field_id = f.applicant_field_id'])
                        ->innerJoin(['g' => 'applicant_form_groups'], ['g.applicant_group_id = o.applicant_group_id'])
                        ->innerJoin(['b' => 'applicant_form_blocks'], ['g.applicant_block_id = b.applicant_block_id'])
                        ->where([
                            'f.company_id'        => (int)$companyId,
                            'b.member_type_id'    => (int)$contactTypeId,
                            'b.applicant_type_id' => (int)$applicantTypeId
                        ])
                        ->execute();

                    $arrParentClientFields = $arrCachedInfo[$companyId]['fields'] = $statement->fetchAll('assoc');

                    foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                        if ($arrParentClientFieldInfo['applicant_field_unique_id'] == 'title') {
                            $arrTitleOptions = $arrCachedInfo[$companyId]['title_options'] = $oApplicantFields->getFieldsOptions($arrParentClientFieldInfo['applicant_field_id']);
                        } elseif ($arrParentClientFieldInfo['applicant_field_unique_id'] == 'status_simple') {
                            $arrStatusOptions = $arrCachedInfo[$companyId]['status_options'] = $oApplicantFields->getFieldsOptions($arrParentClientFieldInfo['applicant_field_id']);
                        }
                    }

                    $arrAgentFieldIds = $arrCachedInfo[$companyId]['agent_fields'] = $oFields->getFieldIdByType($companyId, 'agent');
                    $divisionGroupId  = $arrCachedInfo[$companyId]['division_group_id'] = $oCompany->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId);
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
                    'phone_w'              => $arrAgentInfo['workPhone'],
                    'phone_h'              => $arrAgentInfo['homePhone'],
                    'phone_m'              => $arrAgentInfo['mobilePhone'],
                    'email'                => $arrAgentInfo['email1'],
                    'email_1'              => $arrAgentInfo['email2'],
                    'email_2'              => $arrAgentInfo['email3'],
                    'fax_h'                => $arrAgentInfo['faxHome'],
                    'fax_w'                => $arrAgentInfo['faxWork'],
                    'fax_o'                => $arrAgentInfo['faxOthers'],
                    'office'               => implode(',', $arrOffices),
                );

                $arrUnsetIfEmpty = array('notes', 'address_1', 'address_2', 'city', 'country', 'state', 'zip_code', 'phone_w', 'phone_h', 'phone_m', 'email', 'email_1', 'email_2', 'fax_h', 'fax_w', 'fax_o');
                foreach ($arrUnsetIfEmpty as $idToCheck) {
                    if (empty($arrFieldsMapping[$idToCheck])) {
                        unset($arrFieldsMapping[$idToCheck]);
                    }
                }

                $photoFieldId = 0;
                if (!empty($arrAgentInfo['logoFileName'])) {
                    foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                        if ($arrParentClientFieldInfo['type'] === 'photo') {
                            $arrFieldsMapping[$arrParentClientFieldInfo['applicant_field_unique_id']] = $arrAgentInfo['logoFileName'];

                            $photoFieldId = $arrParentClientFieldInfo['applicant_field_id'];
                            break;
                        }
                    }
                }

                if (!Settings::isDateEmpty($arrAgentInfo['dateSigned'])) {
                    $arrFieldsMapping['date_signed_up'] = $arrAgentInfo['dateSigned'];
                }

                if (!empty($arrAgentInfo['title'])) {
                    foreach ($arrTitleOptions as $arrTitleOptionInfo) {
                        if ($arrTitleOptionInfo['value'] == $arrAgentInfo['title'] || $arrTitleOptionInfo['value'] == $arrAgentInfo['title'] . '.') {
                            $arrFieldsMapping['title'] = $arrTitleOptionInfo['applicant_form_default_id'];
                            break;
                        }
                    }
                }

                if (!empty($arrAgentInfo['status'])) {
                    $checkStatus = $arrAgentInfo['status'] == 'I' ? 'Inactive' : 'Active';
                    foreach ($arrStatusOptions as $arrStatusOptionInfo) {
                        if ($arrStatusOptionInfo['value'] == $checkStatus) {
                            $arrFieldsMapping['status_simple'] = $arrStatusOptionInfo['applicant_form_default_id'];
                            break;
                        }
                    }
                }

                $arrInternalContacts = array();
                $arrClientData       = array();

                foreach ($arrFieldsMapping as $uniqueFieldId => $fieldVal) {
                    foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
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

                                if (!in_array($arrParentClientFieldInfo['applicant_group_id'], $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'])) {
                                    $arrInternalContacts[$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'][] = $arrParentClientFieldInfo['applicant_group_id'];
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

                // 1. Create a Contact
                $arrMemberInsertInfo = array(
                    'company_id'        => $companyId,
                    'division_group_id' => $divisionGroupId,
                    'userType'          => $contactTypeId,
                    'fName'             => $arrAgentInfo['fName'],
                    'lName'             => $arrAgentInfo['lName'],
                    'emailAddress'      => $arrAgentInfo['email1'],
                    'regTime'           => $arrAgentInfo['regTime'],
                    'status'            => 1,
                    'login_enabled'     => 'N'
                );

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrMemberInsertInfo))
                    ->into('members')
                    ->values($arrMemberInsertInfo)
                    ->execute();

                $applicantId = $statement->lastInsertId('members');

                $arrClientInfo = array(
                    'member_id'          => $applicantId,
                    'added_by_member_id' => $adminId,
                    'applicant_type_id'  => $applicantTypeId,
                );

                $this->getQueryBuilder()
                    ->insert(array_keys($arrClientInfo))
                    ->into('clients')
                    ->values($arrClientInfo)
                    ->execute();

                foreach ($arrOffices as $officeId) {
                    $arrAssignToOffices[] = [
                        'member_id'   => $applicantId,
                        'division_id' => $officeId,
                        'type'        => 'access_to'
                    ];
                }

                foreach ($arrClientData as $arrClientDataRow) {
                    $arrApplicantFormData[] = [
                        'applicant_id'       => $applicantId,
                        'applicant_field_id' => $arrClientDataRow['field_id'],
                        'value'              => $arrClientDataRow['value'],
                        'row'                => 0,
                    ];
                }

                // Now point to new Contact (for all already saved agent fields)
                if (!empty($arrAgentFieldIds)) {
                    $this->getQueryBuilder()
                        ->update('client_form_data')
                        ->set(['value' => $applicantId])
                        ->where([
                            'field_id IN' => $arrAgentFieldIds,
                            'value'       => $arrAgentInfo['agent_id']
                        ])
                        ->execute();
                }

                $this->getQueryBuilder()
                    ->update('company_prospects')
                    ->set(['agent_id' => $applicantId])
                    ->where(['agent_id' => $arrAgentInfo['agent_id']])
                    ->execute();

                $this->getQueryBuilder()
                    ->update('clients')
                    ->set(['agent_id' => $applicantId])
                    ->where(['agent_id' => $arrAgentInfo['agent_id']])
                    ->execute();

                $this->getQueryBuilder()
                    ->update('company_questionnaires')
                    ->set(['q_agent_id' => $applicantId])
                    ->where(['q_agent_id' => $arrAgentInfo['agent_id']])
                    ->execute();


                // 2. Create an Internal Contact record
                $arrMemberInsertInfo = array(
                    'company_id'        => $companyId,
                    'division_group_id' => $divisionGroupId,
                    'userType'          => $internalContactTypeId,
                    'fName'             => $arrAgentInfo['fName'],
                    'lName'             => $arrAgentInfo['lName'],
                    'emailAddress'      => $arrAgentInfo['email1'],
                    'regTime'           => $arrAgentInfo['regTime'],
                    'status'            => 1,
                    'login_enabled'     => 'N'
                );

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrMemberInsertInfo))
                    ->into('members')
                    ->values($arrMemberInsertInfo)
                    ->execute();

                $internalContactId = $statement->lastInsertId('members');

                $arrClientInfo = array(
                    'member_id'          => $internalContactId,
                    'added_by_member_id' => $adminId,
                );

                $this->getQueryBuilder()
                    ->insert(array_keys($arrClientInfo))
                    ->into('clients')
                    ->values($arrClientInfo)
                    ->execute();

                foreach ($arrOffices as $officeId) {
                    $arrAssignToOffices[] = [
                        'member_id'   => $internalContactId,
                        'division_id' => $officeId,
                        'type'        => 'access_to'
                    ];
                }

                foreach ($arrInternalContacts as $arrBlockInfo) {
                    foreach ($arrBlockInfo['data'] as $arrClientDataRow) {
                        $arrApplicantFormData[] = [
                            'applicant_id'       => $internalContactId,
                            'applicant_field_id' => $arrClientDataRow['field_id'],
                            'value'              => $arrClientDataRow['value'],
                            'row'                => 0,
                        ];
                    }

                    foreach ($arrBlockInfo['parent_group_id'] as $parentGroupId) {
                        $arrMembersRelations[] = [
                            'parent_member_id'   => $applicantId,
                            'child_member_id'    => $internalContactId,
                            'applicant_group_id' => $parentGroupId,
                            'row'                => 0,
                        ];
                    }
                }

                if (!empty($arrAgentInfo['logoFileName'])) {
                    $pathToOldFile = $oFiles->getAgentLogoPath($companyId, $booLocal) . '/' . 'field-' . $arrAgentInfo['agent_id'] . '-logo';
                    $newFolder     = $oFiles->getPathToClientImages($companyId, $internalContactId, $booLocal);
                    $pathToNewFile = $newFolder . '/' . 'field-' . $photoFieldId;

                    if ($booLocal) {
                        $oFiles->createFTPDirectory($newFolder);
                    } else {
                        $oFiles->createCloudDirectory($newFolder);
                    }

                    $oFiles->copyFile($pathToOldFile, $pathToNewFile, $booLocal);
                }

                echo "Converted." . PHP_EOL;
            }

            $this->table('members_divisions')
                ->insert($arrAssignToOffices)
                ->save();

            $this->table('applicant_form_data')
                ->insert($arrApplicantFormData)
                ->save();

            $this->table('members_relations')
                ->insert($arrMembersRelations)
                ->save();

            $this->execute('DROP TABLE `agents`');
        } catch (Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}
