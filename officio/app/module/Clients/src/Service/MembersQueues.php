<?php

namespace Clients\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class MembersQueues extends BaseService
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
        $this->_company = $services[Company::class];
    }

    /**
     * Load queue settings for specific member
     *
     * @param int $memberId
     * @return array
     */
    public function getMemberQueueSettings($memberId)
    {
        $select = (new Select())
            ->from(array('q' => 'members_queues'))
            ->where(['member_id' => (int)$memberId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Update queue settings for specific member
     *
     * @param int $memberId
     * @param string $strColumn
     * @param string $strSettings
     * @return bool true on success
     */
    public function saveMemberQueueSettings($memberId, $strColumn, $strSettings)
    {
        $booSuccess = false;

        try {
            if (in_array($strColumn, array('queue_member_allowed_queues', 'queue_member_selected_queues', 'queue_individual_columns', 'queue_employer_columns', 'queue_individual_show_active_cases', 'queue_employer_show_active_cases'))) {
                // Check if we need insert/update
                $arrSavedSettings = $this->getMemberQueueSettings($memberId);

                if (isset($arrSavedSettings['member_id']) && $arrSavedSettings['member_id'] == $memberId) {
                    $this->_db2->update(
                        'members_queues',
                        [$strColumn => $strSettings],
                        ['member_id' => (int)$memberId]
                    );
                } else {
                    $this->_db2->insert(
                        'members_queues',
                        [
                            'member_id' => (int)$memberId,
                            $strColumn  => $strSettings
                        ]
                    );
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booSuccess;
    }

    public function loadAllSettings()
    {
        $strError                     = '';
        $booShowIndividualActiveCases = true;
        $booShowEmployerActiveCases   = true;
        $arrFieldsOptions             = array();
        $arrIndividualColumns         = array();
        $arrEmployerColumns           = array();
        $arrQueueAllowed              = array();
        $arrQueueSelected             = array();
        $arrContactsColumns           = array();

        try {
            // Load allowed offices (queues) list for the current user
            $arrOffices       = $this->_clients->getDivisions();
            $arrOfficeOptions = array();
            if (is_array($arrOffices) && count($arrOffices)) {
                foreach ($arrOffices as $officeInfo) {
                    $arrOfficeOptions[] = array(
                        'option_id' => $officeInfo['division_id'],
                        'option_name' => $officeInfo['name']
                    );
                }
            }

            // Load saved queue settings for current user
            $arrQueueSettings = $this->getMemberQueueSettings(
                $this->_auth->getCurrentUserId()
            );

            // Load/check queues selected by admin
            // If there are no saved settings - allow access to all offices (queues) that user has access to
            if (isset($arrQueueSettings['queue_member_allowed_queues']) && !empty($arrQueueSettings['queue_member_allowed_queues'])) {
                $arrQueueAllowedIds = unserialize($arrQueueSettings['queue_member_allowed_queues']);
                foreach ($arrOfficeOptions as $arrOfficeOptionInfo) {
                    if (in_array($arrOfficeOptionInfo['option_id'], $arrQueueAllowedIds)) {
                        $arrQueueAllowed[] = $arrOfficeOptionInfo;
                    }
                }
            } else {
                // Select all allowed by default
                $arrQueueAllowed = $arrOfficeOptions;
            }

            // Load/check queues selected by user (from GUI in combo)
            // If there are no saved settings - allow access to all offices (queues) that user has access to
            if (isset($arrQueueSettings['queue_member_selected_queues']) && !empty($arrQueueSettings['queue_member_selected_queues'])) {
                $arrQueueSelected = unserialize($arrQueueSettings['queue_member_selected_queues']);

                // Make sure that user has access to all already saved queues
                foreach ($arrQueueSelected as $key => $queueId) {
                    $booQueueIdCorrect = false;
                    foreach ($arrOfficeOptions as $arrOfficeOptionInfo) {
                        if ($queueId == $arrOfficeOptionInfo['option_id']) {
                            $booQueueIdCorrect = true;
                            break;
                        }
                    }

                    if (!$booQueueIdCorrect) {
                        unset($arrQueueSelected[$key]);
                    }
                }
            } else {
                // Select all allowed by default
                foreach ($arrOfficeOptions as $arrOfficeOptionInfo) {
                    $arrQueueSelected[] = $arrOfficeOptionInfo['option_id'];
                }
            }


            // Load/check columns for the current user
            // If there are no saved settings - select/use default list of columns
            $arrIndividualQueueColumnsSerialized = isset($arrQueueSettings['queue_individual_columns']) && !empty($arrQueueSettings['queue_individual_columns']) ? unserialize($arrQueueSettings['queue_individual_columns']) : '';

            $booAustralia = $this->_config['site_version']['version'] == 'australia';
            $strClientLastNameKey = $booAustralia ? 'family_name' : 'last_name';
            $strClientFirstNameKey = $booAustralia ? 'given_names' : 'first_name';
            if (!empty($arrIndividualQueueColumnsSerialized)) {
                $arrIndividualColumns = $arrIndividualQueueColumnsSerialized;
            } else {
                // Use specific by default
                $arrIndividualColumns = array(
                    'individual_' . $strClientFirstNameKey,
                    'individual_' . $strClientLastNameKey,
                    'case_file_number',
                    'case_file_status',
                    'individual_DOB',
                    'individual_passport_exp_date',
                    'case_categories',
                    'case_date_client_signed',
                );
            }

            $arrEmployerQueueColumnsSerialized = isset($arrQueueSettings['queue_employer_columns']) && !empty($arrQueueSettings['queue_employer_columns']) ? unserialize($arrQueueSettings['queue_employer_columns']) : '';
            if (!empty($arrEmployerQueueColumnsSerialized)) {
                $arrEmployerColumns = $arrEmployerQueueColumnsSerialized;
            } else {
                // Use specific by default
                $arrEmployerColumns = array(
                    'case_file_number',
                    'case_file_status',
                    'employer_entity_name',
                    'case_categories',
                    'case_date_client_signed',
                );
            }

            $arrContactsColumns = array(
                'contact_' . $strClientFirstNameKey,
                'contact_' . $strClientLastNameKey,
            );

            if (isset($arrQueueSettings['queue_individual_show_active_cases'])) {
                $booShowIndividualActiveCases = (bool)$arrQueueSettings['queue_individual_show_active_cases'];
            } else {
                // Check checkbox by default
                $booShowIndividualActiveCases = true;
            }

            if (isset($arrQueueSettings['queue_employer_show_active_cases'])) {
                $booShowEmployerActiveCases = (bool)$arrQueueSettings['queue_employer_show_active_cases'];
            } else {
                // Check checkbox by default
                $booShowEmployerActiveCases = true;
            }

            $companyId         = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId   = $this->_auth->getCurrentUserDivisionGroupId();
            $arrCompanyOffices = $this->_company->getDivisions($companyId, $divisionGroupId);
            $arrAllOffices     = array();
            foreach ($arrCompanyOffices as $arrCompanyOffice) {
                $arrAllOffices[] = array(
                    'option_id'   => $arrCompanyOffice['division_id'],
                    'option_name' => $arrCompanyOffice['name']
                );
            }
            $arrFieldsOptions['office'] = $arrAllOffices;

            $arrMemberPullFromDivisions = $this->_clients->getMemberDivisions($this->_auth->getCurrentUserId(), 'pull_from');

            $arrMemberOfficesPullFrom = array();
            foreach ($arrCompanyOffices as $arrCompanyOffice) {
                if (in_array($arrCompanyOffice['division_id'], $arrMemberPullFromDivisions)) {
                    $arrMemberOfficesPullFrom[] = array(
                        'option_id' => $arrCompanyOffice['division_id'],
                        'option_name' => $arrCompanyOffice['name']
                    );
                }
            }
            $arrFieldsOptions['office_pull_from'] = $arrMemberOfficesPullFrom;

            $arrMemberResponsibleForDivisions = $this->_clients->getMemberDivisions($this->_auth->getCurrentUserId(), 'responsible_for');

            $arrMemberOfficesResponsibleFor = array();
            foreach ($arrCompanyOffices as $arrCompanyOffice) {
                if (in_array($arrCompanyOffice['division_id'], $arrMemberResponsibleForDivisions)) {
                    $arrMemberOfficesResponsibleFor[] = array(
                        'option_id' => $arrCompanyOffice['division_id'],
                        'option_name' => $arrCompanyOffice['name']
                    );
                }
            }
            $arrFieldsOptions['office_push_to'] = $arrMemberOfficesResponsibleFor;

            $arrMemberPushToDivisions = $this->_clients->getMemberDivisions($this->_auth->getCurrentUserId(), 'push_to');

            $arrMemberOfficesPushTo = array();
            foreach ($arrCompanyOffices as $arrCompanyOffice) {
                if (in_array($arrCompanyOffice['division_id'], $arrMemberPushToDivisions)) {
                    $arrMemberOfficesPushTo[] = array(
                        'option_id' => $arrCompanyOffice['division_id'],
                        'option_name' => $arrCompanyOffice['name']
                    );
                }
            }
            $arrFieldsOptions['office_push_to_queue'] = $arrMemberOfficesPushTo;

            $arrFieldsOptions['visa_subclass'] = $this->_clients->getFields()->getCompanyFieldOptions($companyId, $divisionGroupId, 'categories');


            $arrAssignedStaffFields = [];
            $arrAssignedStaffFieldsIds = ['registered_migrant_agent', 'accounting', 'processing', 'sales_and_marketing'];
            foreach ($arrAssignedStaffFieldsIds as $fieldId) {
                $arrFieldInfo = $this->_clients->getFields()->getCompanyFieldInfoByUniqueFieldId($fieldId, $companyId);
                if (!empty($arrFieldInfo)) {
                    $arrFieldInfo['field_name'] = $arrFieldInfo['label'];
                    $arrFieldInfo['field_id'] = $arrFieldInfo['company_field_id'];

                    $arrAssignedStaffFields[] = $arrFieldInfo;
                }
            }


            $arrFieldsOptions['assigned_staff_fields'] = $arrAssignedStaffFields;
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success'                            => empty($strError),
            'message'                            => $strError,
            'fields_options'                     => $arrFieldsOptions,
            'queue_allowed'                      => $arrQueueAllowed,
            'queue_selected'                     => implode(',', $arrQueueSelected),
            'queue_individual_columns'           => $arrIndividualColumns,
            'queue_employer_columns'             => $arrEmployerColumns,
            'queue_individual_show_active_cases' => $booShowIndividualActiveCases,
            'queue_employer_show_active_cases'   => $booShowEmployerActiveCases,
            'queue_contacts_columns'             => $arrContactsColumns,
        );
    }

    /**
     * Check if member has access to pulling cases
     * @param int $memberId
     * @return bool
     */
    public function hasMemberAccessToPullingCases($memberId = 0)
    {
        $booHasAccess = false;

        try {
            $memberId                         = (empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId);
            $arrMemberPullFromDivisions       = $this->_clients->getMemberDivisions($memberId, 'pull_from');
            $arrMemberResponsibleForDivisions = $this->_clients->getMemberDivisions($memberId, 'responsible_for');

            if (count($arrMemberPullFromDivisions) && count($arrMemberResponsibleForDivisions)) {
                $booHasAccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Pull the oldest applicant from specific queue and push it to another specific queue
     *
     * @param int $companyId
     * @param int $divisionGroupId
     * @param int $pullFromQueueId
     * @param int $pushToQueueId
     * @return string error, empty on success
     */
    public function pullApplicantFromQueue($companyId, $divisionGroupId, $pullFromQueueId, $pushToQueueId)
    {
        $strError = '';

        try {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(['member_id'])
                ->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT)
                ->where(
                    [
                        'm.status'       => 1,
                        'm.userType'     => $this->_clients::getMemberType('individual_employer_internal_contact'),
                        'md.division_id' => (int)$pullFromQueueId
                    ]
                )
                ->order('m.regTime')
                ->limit(1);

            $applicantId = $this->_db2->fetchOne($select);

            if (empty($applicantId)) {
                $strError = $this->_tr->translate('There are no cases in source queue.');
            }

            if (empty($strError)) {
                $arrSavedOffices = $this->_clients->getApplicantOffices(array($applicantId), $divisionGroupId);
                $arrDivisionsInfo = $this->_company->getCompanyDivisions()->getDivisionsByIds($arrSavedOffices);

                // Make sure that permanent offices will be not deleted
                $thisClientOfficesToAssign = array($pushToQueueId);
                foreach ($arrDivisionsInfo as $arrDivisionInfo) {
                    if ($arrDivisionInfo['access_permanent'] == 'Y' && !in_array($arrDivisionInfo['division_id'], $thisClientOfficesToAssign)) {
                        $thisClientOfficesToAssign[] = $arrDivisionInfo['division_id'];
                    }
                }

                list($booSuccess,) = $this->_clients->updateClientsOffices($companyId, array($applicantId), $thisClientOfficesToAssign);

                if (!$booSuccess) {
                    $strError = $this->_tr->translate('Internal error. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }
}
