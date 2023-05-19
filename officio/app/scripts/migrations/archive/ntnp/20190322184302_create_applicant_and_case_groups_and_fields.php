<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Service\Log;
use Phinx\Migration\AbstractMigration;
use Tasks\Service\Tasks;

class CreateApplicantAndCaseGroupsAndFields extends AbstractMigration
{

    public function up()

    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        try {
            $db->query("DELETE FROM `applicant_form_fields`;");

            $db->query("DELETE FROM `applicant_form_blocks`;");

            $db->query("DELETE FROM `client_form_fields`;");

            $db->query("DELETE FROM `client_form_groups`;");

            $db->query("DELETE FROM `client_types`;");

            $db->query("DELETE FROM `divisions`;");

            $select = $db->select()
                ->from('company', 'company_id');

            $arrCompanyIds = $db->fetchCol($select);

            /** @var Clients $oClients */
            $oClients    = Zend_Registry::get('serviceManager')->get(Clients::class);
            $oFieldTypes = $oClients->getFieldTypes();
            /** @var AutomaticReminders $oAutomaticReminders */
            $oAutomaticReminders = Zend_Registry::get('serviceManager')->get(AutomaticReminders::class);
            /** @var Forms $forms */
            $forms = Zend_Registry::get('serviceManager')->get(Forms::class);
            /** @var Files $oFiles */
            $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);
            /** @var Tasks $oTasks */
            $oTasks = Zend_Registry::get('serviceManager')->get(Tasks::class);
            /** @var Company $oCompany */
            $oCompany         = Zend_Registry::get('serviceManager')->get(Company::class);
            $CompanyDivisions = $oCompany->getCompanyDivisions();

            foreach ($arrCompanyIds as $companyId) {
                // delete all applicants and cases
                $select = $db->select()
                    ->from('members', 'member_id')
                    ->where('userType IN (?)', array(3, 7, 8, 9, 10), 'INT');

                $arrMemberIds = $db->fetchCol($select);

                if (!empty($arrMemberIds)) {
                    $select = $db->select()
                        ->from('members', 'member_id')
                        ->where('userType IN (?)', array(3), 'INT');

                    $arrCaseIds = $db->fetchCol($select);

                    $oClients->deleteMember($companyId, $arrMemberIds, '', false);

                    if (!empty($arrCaseIds)) {
                        foreach ($arrCaseIds as $caseId) {
                            // Find all revisions and delete them
                            $arrAssignedFormIds = $forms->getFormAssigned()->getAssignedFormIdsByClientId($caseId);
                            if (is_array($arrAssignedFormIds) && count($arrAssignedFormIds)) {
                                $arrRevisionIds = $forms->getFormRevision()->getRevisionIdsByFormAssignedIds($arrAssignedFormIds);
                                if (is_array($arrRevisionIds) && count($arrRevisionIds)) {
                                    $forms->getFormRevision()->deleteRevision($caseId, $arrRevisionIds);
                                }
                            }
                            // And after that delete all assigned forms
                            $db->delete('FormAssigned', $db->quoteInto('ClientMemberId = ?', $caseId));


                            // Delete folders/files
                            // Always delete local and remote files/folders, if any
                            $oFiles->deleteFolder($oFiles->getMemberFolder($companyId, $caseId, true, false), false);
                            $oFiles->deleteFolder($oFiles->getMemberFolder($companyId, $caseId, true, true), true);


                            $oAutomaticReminders->getActions()->deleteClientActions($caseId);


                            // Delete tasks
                            $oTasks->deleteMemberTasks($caseId);
                        }
                        $strWhere = $db->quoteInto('member_id IN (?)', $arrCaseIds, 'INT');

                        // Delete all assigned invoices
                        $select      = $db->select()
                            ->from('u_invoice', 'invoice_id')
                            ->where($strWhere);
                        $arrInvoices = $db->fetchCol($select);

                        $db->delete('u_invoice', $strWhere);
                        if (is_array($arrInvoices) && !empty($arrInvoices)) {
                            $db->delete('u_assigned_withdrawals', sprintf('invoice_id IN (%s)', $db->quote($arrInvoices)));
                        }
                    }
                }

                // create queues

                $divisionGroupId  = $CompanyDivisions->getCompanyMainDivisionGroupId($companyId);
                $arrDivisionNames = array('BNP-Application Intake', 'BNP-Waiting for Applicant\'s Response', 'BNP-Archive', 'EDS-Application Intake', 'EDS-Waiting for Applicant\'s Response', 'EDS-Archive');
                $divisionOrder    = 0;

                foreach ($arrDivisionNames as $divisionName) {
                    $CompanyDivisions->createUpdateDivision(
                        $companyId,
                        $divisionGroupId,
                        0,
                        $divisionName,
                        $divisionOrder,
                        false,
                        false,
                        false
                    );
                    $divisionOrder++;
                }

                // creation fields for internal contact and IA

                $arrFieldsAdded         = array();
                $blockId                = $oClients->getApplicantFields()->createBlock($companyId, 9, 'general');
                $internalContactGroupId = $oClients->getApplicantFields()->createGroup($companyId, 9, $blockId, 'Main Group', 'N');

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'combo',
                    'label'          => 'Salutation',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'title';
                $fieldOptions     = array(
                    array(
                        'name'  => 'Mr.',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'Ms.',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'Mrs.',
                        'order' => 2
                    ),
                    array(
                        'name'  => 'Miss',
                        'order' => 3
                    ),
                    array(
                        'name'  => 'Dr.',
                        'order' => 4
                    )
                );

                list($strError, $salutationFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $salutationFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'First Name',
                    'required'       => 'Y',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'first_name';

                list($strError, $firstNameFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $firstNameFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Last Name',
                    'required'       => 'Y',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'last_name';

                list($strError, $lastNameFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $lastNameFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'combo',
                    'label'          => 'Gender',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'gender';
                $fieldOptions     = array(
                    array(
                        'name'  => 'Male',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'Female',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'X',
                        'order' => 2
                    )
                );

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'date_repeatable',
                    'label'          => 'Date of Birth',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'DOB';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $blockId = $oClients->getApplicantFields()->createBlock($companyId, 8, 'contact');
                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 8, $blockId, 'Foreign National Applicant Personal Info', 'N');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 8, $blockId, $groupId, $arrFieldsAdded, array());

                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 8,
                    'type'           => 'office_multi',
                    'label'          => 'Queue',
                    'required'       => 'Y',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'office';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $groupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    true
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'City of Birth',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'city_of_birth';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Country of Birth',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'country_of_birth';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Country of Citizenship',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'country_of_citizenship';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Passport Number',
                    'required'       => 'N',
                    'encrypted'      => 'N',
                    'maxlength'      => 20
                );
                $applicantFieldId = 'passport_number';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'date',
                    'label'          => 'Passport Issue Date',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'passport_issue_date';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'date',
                    'label'          => 'Passport Expiry Date',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'passport_expiry_date';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrToggleContactFields = array_slice($arrFieldsAdded, 1);

                $oClients->getApplicantFields()->toggleContactFields($companyId, 8, $blockId, $groupId, $arrToggleContactFields, array());

                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // another group


                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Address Line 1',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'address_line_1';

                list($strError, $address1FieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $address1FieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Address Line 2',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'address_line_2';

                list($strError, $address2FieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $address2FieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'City',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'city';

                list($strError, $cityFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $cityFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Province/State',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'state';

                list($strError, $stateFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $stateFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Country',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'country';

                list($strError, $countryFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $countryFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Postal/Zip code',
                    'required'       => 'N',
                    'encrypted'      => 'N',
                    'maxlength'      => 16
                );
                $applicantFieldId = 'zip_code';

                list($strError, $zipFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $zipFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'phone',
                    'label'          => 'Phone',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'phone';

                list($strError, $primaryPhoneFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $primaryPhoneFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'phone',
                    'label'          => 'Mobile Phone',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'mobile_phone';

                list($strError, $secondaryPhoneFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $secondaryPhoneFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'email',
                    'label'          => 'Email',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'email';

                list($strError, $primaryEmailFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $primaryEmailFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'email',
                    'label'          => 'Secondary Email',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'secondary_email';

                list($strError, $secondaryEmailFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );
                $arrFieldsAdded[] = $secondaryEmailFieldId;

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 8, $blockId, 'Applicant Residential Address', 'Y');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 8, $blockId, $groupId, $arrFieldsAdded, array());

                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Applicant Mailing Address',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_mailing_address_line';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Applicant Mailing City/Town',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_mailing_town';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Applicant Mailing Province/Territory',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_mailing_province';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Applicant Mailing Country',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_mailing_country';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Applicant Mailing Postal/ZIP code',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_mailing_postal';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 8, $blockId, 'Applicant Mailing Address', 'N');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 8, $blockId, $groupId, $arrFieldsAdded, array());

                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // another group
                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Spouse First Name',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'spouse_first_name';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Spouse Last Name',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'spouse_last_name';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'date_repeatable',
                    'label'          => 'Spouse Date of Birth',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'spouse_DOB';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 8, $blockId, 'Spouse Information', 'N');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 8, $blockId, $groupId, $arrFieldsAdded, array());
                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // another group
                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Family Name(s)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_family_name';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Given Name(s)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_given_name';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Firm/Organization',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_name_of_firm';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'email',
                    'label'          => 'Representative\'s Email',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_email_address';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'phone',
                    'label'          => 'Representative\'s Primary Phone',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_primary_phone';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'phone',
                    'label'          => 'Representative\'s Secondary Phone',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_secondary_phone';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Address Line',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_address_line';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s City/Town',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_city';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Province/State',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_state';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Postal/Zip Code',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_postal';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Representative\'s Country',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'applicant_representative_country';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 8, $blockId, 'Applicant Representative', 'N');
                $oClients->getApplicantFields()->toggleContactFields($companyId, 8, $blockId, $groupId, $arrFieldsAdded, array());
                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // another group
                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Profile Number',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'ee_profile_number';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'date',
                    'label'          => 'Profile Submission Expiry Date',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'ee_expiry_date';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 8, $blockId, 'Express Entry Information', 'N');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 8, $blockId, $groupId, $arrFieldsAdded, array());
                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // creation fields for internal contact and Employer

                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Registered Company Name',
                    'required'       => 'Y',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'entity_name';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Operating as',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_operating_as';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Company Website',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_company_website';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Company Owner(s)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_company_owners';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Type of Company (Industry/Sector)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'type_of_company';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'date',
                    'label'          => 'Employer Date Company Established',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_date_company_established';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'number',
                    'label'          => 'Employer Number of Employees',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_number_of_employees';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'number',
                    'label'          => 'Number of Foreign Workers or Nominees?',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_number_of_foreign_workers';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'combo',
                    'label'          => 'Employer Company type',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_company_type';
                $fieldOptions     = array(
                    array(
                        'name'  => 'Public',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'Private',
                        'order' => 1
                    )
                );

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'combo',
                    'label'          => 'Employer Primary Language of Business',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_primary_language_of_business';
                $fieldOptions     = array(
                    array(
                        'name'  => 'English',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'French',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'Both',
                        'order' => 2
                    )
                );

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );


                $blockId = $oClients->getApplicantFields()->createBlock($companyId, 7, 'contact');
                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 7, $blockId, 'Employer Company Information', 'N');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 7, $blockId, $groupId, $arrFieldsAdded, array());

                $arrFieldsInsert  = array(
                    'member_type_id' => 7,
                    'type'           => 'office_multi',
                    'label'          => 'Queue',
                    'required'       => 'Y',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'office';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $groupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    true
                );

                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }


                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Address line (Number and Street)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_address_line';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsAdded[] = $primaryEmailFieldId;

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'City/Town',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_town';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Province/Territory',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_province';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Country',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_country';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Postal/ZIP code',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_postal';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 7, $blockId, 'Employer Business Address', 'N');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 7, $blockId, $groupId, $arrFieldsAdded, array());

                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Mailing Address',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_mailing_address_line';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Mailing City/Town',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_mailing_town';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Mailing Province/Territory',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_mailing_province';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Employer Mailing Country',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_mailing_country';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Mailing Postal/ZIP code',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_mailing_postal';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 7, $blockId, 'Employer Mailing Address', 'N');

                $oClients->getApplicantFields()->toggleContactFields($companyId, 7, $blockId, $groupId, $arrFieldsAdded, array());

                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // another group
                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Family Name(s)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_family_name';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Given Name(s)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_given_name';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Firm/Organization',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_name_of_firm';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'email',
                    'label'          => 'Representative\'s Email',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_email_address';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'phone',
                    'label'          => 'Representative\'s Primary Phone',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_primary_phone';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'phone',
                    'label'          => 'Representative\'s Secondary Phone',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_secondary_phone';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Address Line',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_address_line';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s City/Town',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_city';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Province/State',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_state';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Representative\'s Postal/Zip Code',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_postal';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'country',
                    'label'          => 'Representative\'s Country',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_representative_country';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 7, $blockId, 'Employer Representative', 'N');
                $oClients->getApplicantFields()->toggleContactFields($companyId, 7, $blockId, $groupId, $arrFieldsAdded, array());
                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // Authorized Contact

                $arrFieldsAdded = array();

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Contact Title',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_contact_title';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Contact (with Signing Authority)',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_contact';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'email',
                    'label'          => 'Employer Contact Email',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_contact_email';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Contact Phone',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_contact_phone';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Employer Contact Fax',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'employer_contact_fax';

                list($strError, $arrFieldsAdded[]) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $groupId = $oClients->getApplicantFields()->createGroup($companyId, 7, $blockId, 'Contact Information of Employer\'s Authorized Signing Officer', 'N');
                $oClients->getApplicantFields()->toggleContactFields($companyId, 7, $blockId, $groupId, $arrFieldsAdded, array());
                foreach ($arrFieldsAdded as $fieldId) {
                    $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                }

                // contact fields creation

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Position',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'position';
                list($strError, $positionFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'text',
                    'label'          => 'Department',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'department';

                list($strError, $departmentFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 9,
                    'type'           => 'memo',
                    'label'          => 'Notes',
                    'required'       => 'N',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'notes';

                list($strError, $notesFieldId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    $internalContactGroupId,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    true,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    false
                );

                $arrFieldsInsert  = array(
                    'member_type_id' => 10,
                    'type'           => 'office_multi',
                    'label'          => 'Queue',
                    'required'       => 'Y',
                    'encrypted'      => 'N'
                );
                $applicantFieldId = 'office';

                list($strError, $queueId) = $oClients->getApplicantFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $oFieldTypes->getFieldTypeId($arrFieldsInsert['type']),
                    $applicantFieldId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false,
                    true
                );

                $arrContactTypeIds = $oClients->getApplicantTypes()->getTypes($companyId, true, 10);
                foreach ($arrContactTypeIds as $contactTypeId) {
                    $arrFieldsAdded = array($salutationFieldId, $lastNameFieldId, $firstNameFieldId, $positionFieldId, $departmentFieldId);
                    $blockId        = $oClients->getApplicantFields()->createBlock($companyId, 10, 'contact', $contactTypeId);
                    $groupId        = $oClients->getApplicantFields()->createGroup($companyId, 10, $blockId, 'Contact Information', 'N', 4);
                    $oClients->getApplicantFields()->toggleContactFields($companyId, 10, $blockId, $groupId, $arrFieldsAdded, array());

                    $query  = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE applicant_group_id = %d)', 'applicant_form_order', $groupId);
                    $maxRow = new Zend_Db_Expr($query);

                    $arrOrderInsert = array(
                        'applicant_group_id' => $groupId,
                        'applicant_field_id' => $queueId,
                        'use_full_row'       => 'N',
                        'field_order'        => $maxRow
                    );
                    $db->insert('applicant_form_order', $arrOrderInsert);

                    $arrFieldsAdded[] = $queueId;
                    foreach ($arrFieldsAdded as $fieldId) {
                        $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                    }

                    $arrFieldsAdded = array($address1FieldId, $address2FieldId, $cityFieldId, $stateFieldId, $countryFieldId, $zipFieldId, $primaryPhoneFieldId, $secondaryPhoneFieldId, $primaryEmailFieldId, $secondaryEmailFieldId);
                    $groupId        = $oClients->getApplicantFields()->createGroup($companyId, 10, $blockId, 'Address & Contact Details', 'N', 4);
                    $oClients->getApplicantFields()->toggleContactFields($companyId, 10, $blockId, $groupId, $arrFieldsAdded, array());

                    foreach ($arrFieldsAdded as $fieldId) {
                        $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                    }

                    $arrFieldsAdded = array($notesFieldId);
                    $groupId        = $oClients->getApplicantFields()->createGroup($companyId, 10, $blockId, 'Notes & Comments', 'N', 4);
                    $oClients->getApplicantFields()->toggleContactFields($companyId, 10, $blockId, $groupId, $arrFieldsAdded, array());

                    foreach ($arrFieldsAdded as $fieldId) {
                        $oClients->getApplicantFields()->allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId);
                    }
                }


                // cases fields creation
                $arrAdminRoles = $oClients->getCompanyAdminRoleId($companyId);
                $arrGroupIds   = array();
                $arrCaseTypes  = array();

                $arrCaseTypes[] = $businessTemplateId = $oClients->getCaseTemplates()->addTemplate(
                    $companyId,
                    'BNP-Application',
                    false,
                    array(3),
                    false,
                    true,
                    false,
                    array(8)
                );

                $arrCaseTypes[] = $employerTemplateId = $oClients->getCaseTemplates()->addTemplate(
                    $companyId,
                    'EDS-Application',
                    false,
                    array(1),
                    false,
                    true,
                    true,
                    array(7, 8)
                );

                $arrFieldsAdded = array();

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Case File #',
                    'required'  => 'N',
                    'encrypted' => 'N',
                    'maxlength' => 32
                );
                $fieldCompanyId  = 'file_number';
                list($strError, $arrFieldsAdded['file_number']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('categories'),
                    'label'     => 'Category',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'category';
                list($strError, $arrFieldsAdded['category']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Case Status',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'file_status';

                $fieldOptions = array(
                    array(
                        'name'  => 'BNP- Initial Registration Received',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'BNP- Interview Requested',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'BNP- Interview Scheduled',
                        'order' => 2
                    ),
                    array(
                        'name'  => 'BNP- Interview Result Confirmed',
                        'order' => 3
                    ),
                    array(
                        'name'  => 'BNP- Expression of Interest Requested',
                        'order' => 4
                    ),
                    array(
                        'name'  => 'BNP- Expression of Interest Received',
                        'order' => 5
                    ),
                    array(
                        'name'  => 'BNP- Formal Invite to Apply Issued',
                        'order' => 6
                    ),
                    array(
                        'name'  => 'BNP- Formal Application Submitted',
                        'order' => 7
                    ),
                    array(
                        'name'  => 'BNP- Application Review',
                        'order' => 8
                    ),
                    array(
                        'name'  => 'BNP- Application Denied',
                        'order' => 9
                    ),
                    array(
                        'name'  => 'BNP- Business Performance Agreement Development',
                        'order' => 10
                    ),
                    array(
                        'name'  => 'BNP- Good Faith Deposit Requested',
                        'order' => 11
                    ),
                    array(
                        'name'  => 'BNP- Letter of Support for Temporary Work Permit Issued',
                        'order' => 12
                    ),
                    array(
                        'name'  => 'BNP- Awaiting Applicant Arrival',
                        'order' => 13
                    ),
                    array(
                        'name'  => 'BNP- Arrival Report Submitted',
                        'order' => 14
                    ),
                    array(
                        'name'  => 'BNP- Arrival Interview Requested',
                        'order' => 15
                    ),
                    array(
                        'name'  => 'BNP- Arrival Interview Scheduled',
                        'order' => 16
                    ),
                    array(
                        'name'  => 'BNP- Waiting for BPA Interim Report',
                        'order' => 17
                    ),
                    array(
                        'name'  => 'BNP- BPA Interim Report Submitted',
                        'order' => 18
                    ),
                    array(
                        'name'  => 'BNP- Final Report Submitted',
                        'order' => 19
                    ),
                    array(
                        'name'  => 'BNP- Final Site Visit Scheduled',
                        'order' => 20
                    ),
                    array(
                        'name'  => 'BNP- Final Site Visit Completed',
                        'order' => 21
                    ),
                    array(
                        'name'  => 'BNP- Exit Interview Scheduled',
                        'order' => 22
                    ),
                    array(
                        'name'  => 'BNP- Exit Interview Completed',
                        'order' => 23
                    ),
                    array(
                        'name'  => 'BNP- Nomination Issued',
                        'order' => 24
                    ),
                    array(
                        'name'  => 'BNP- Good Faith Deposit Returned',
                        'order' => 25
                    ),
                    array(
                        'name'  => 'EDS- Employer Application Received',
                        'order' => 26
                    ),
                    array(
                        'name'  => 'EDS- Application Received',
                        'order' => 27
                    ),
                    array(
                        'name'  => 'EDS- Application Assessment',
                        'order' => 28
                    ),
                    array(
                        'name'  => 'EDS- Pending Employment Standards',
                        'order' => 29
                    ),
                    array(
                        'name'  => 'EDS- Requested Info',
                        'order' => 30
                    ),
                    array(
                        'name'  => 'EDS- Application Final Review',
                        'order' => 31
                    ),
                    array(
                        'name'  => 'EDS- Application Approved',
                        'order' => 32
                    ),
                    array(
                        'name'  => 'EDS- Application Denied',
                        'order' => 33
                    ),
                    array(
                        'name'  => 'EDS- Application Withdrawn',
                        'order' => 34
                    ),
                    array(
                        'name'  => 'EDS- Nomination Revoked',
                        'order' => 35
                    ),
                    array(
                        'name'  => 'EDS- Nomination Issued',
                        'order' => 36
                    ),
                    array(
                        'name'  => 'EDS- Reported to IRCC',
                        'order' => 37
                    ),
                    array(
                        'name'  => 'EDS- Pending MOU',
                        'order' => 38
                    ),
                    array(
                        'name'  => 'EDS- MOU Scheduled',
                        'order' => 39
                    ),
                    array(
                        'name'  => 'EDS- MOU Complete',
                        'order' => 40
                    ),
                    array(
                        'name'  => 'EDS- Appeal Received',
                        'order' => 41
                    ),
                    array(
                        'name'  => 'EDS- Appeal-Final Decision',
                        'order' => 42
                    )
                );

                list($strError, $arrFieldsAdded['file_status']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, $fieldOptions, false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('checkbox'),
                    'label'     => 'Active Case',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'Client_file_status';
                list($strError, $arrFieldsAdded['Client_file_status']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Step 2 Documents Received ',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'step_2_documents_received_date ';
                list($strError, $arrFieldsAdded['step_2_documents_received_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('checkbox'),
                    'label'     => 'Application Fee Paid',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'application_fee_paid ';
                list($strError, $arrFieldsAdded['application_fee_paid']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Business Concept Due Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'business_concept_due_date';
                list($strError, $arrFieldsAdded['business_concept_due_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Interview Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'interview_date';
                list($strError, $arrFieldsAdded['interview_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('memo'),
                    'label'     => 'Interview Results',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'interview_results';
                list($strError, $arrFieldsAdded['interview_results']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), true, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Step 3 Documents Received ',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'step_3_documents_received_date ';
                list($strError, $arrFieldsAdded['step_3_documents_received_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Invite to Apply Issued',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'invite_to_apply_issued_date';
                list($strError, $arrFieldsAdded['invite_to_apply_issued_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Employer Application Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'employer_application_date';
                list($strError, $arrFieldsAdded['employer_application_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Job Title',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'job_title';
                list($strError, $arrFieldsAdded['job_title']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), true, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Job\'s NOC code',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'job_noc_code';
                list($strError, $arrFieldsAdded['job_noc_code']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), true, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Type of Employment',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'type_of_employment';

                list($strError, $arrFieldsAdded['type_of_employment']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), true, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Received Labour Market Impact Assessment (LMIA)?',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'received_lmia';
                $fieldOptions    = array(
                    array(
                        'name'  => 'Yes',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'No',
                        'order' => 1
                    )
                );

                list($strError, $arrFieldsAdded['received_lmia']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, $fieldOptions, true, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Employment Standards Regulated',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'employment_standards_regulated';
                $fieldOptions    = array(
                    array(
                        'name'  => 'Yes',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'No',
                        'order' => 1
                    )
                );

                list($strError, $arrFieldsAdded['employment_standards_regulated']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    true,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Application Deadline',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'application_deadline';
                list($strError, $arrFieldsAdded['application_deadline']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Good Faith Deposit Received',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'good_faith_deposit_received';
                list($strError, $arrFieldsAdded['good_faith_deposit_received']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Work Permit Issuance Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'work_permit_issuance_date';
                list($strError, $arrFieldsAdded['work_permit_issuance_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Arrival Interview Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'arrival_interview_date';
                list($strError, $arrFieldsAdded['arrival_interview_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('memo'),
                    'label'     => 'Missing Documents',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'missing_documents';
                list($strError, $arrFieldsAdded['missing_documents']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), true, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Additional Info Requested Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'additional_info_requested_date';
                list($strError, $arrFieldsAdded['additional_info_requested_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Additional Info Requested Due Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'additional_info_requested_due_date';
                list($strError, $arrFieldsAdded['additional_info_requested_due_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'BPA Sent Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'bpa_sent_date';
                list($strError, $arrFieldsAdded['bpa_sent_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'BPA Commencement Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'bpa_commencement_date';
                list($strError, $arrFieldsAdded['bpa_commencement_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Next Report Due',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'next_report_due';
                $fieldOptions    = array(
                    array(
                        'name'  => '-',
                        'order' => 0
                    ),
                    array(
                        'name'  => '1st Interim Report',
                        'order' => 1
                    ),
                    array(
                        'name'  => '2nd Interim Report',
                        'order' => 2
                    ),
                    array(
                        'name'  => '3rd Interim Report',
                        'order' => 3
                    ),
                    array(
                        'name'  => 'Final Report',
                        'order' => 4
                    )
                );
                list($strError, $arrFieldsAdded['next_report_due']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, $fieldOptions, false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Next Report Due Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'next_report_due_date';
                list($strError, $arrFieldsAdded['next_report_due_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Final Site Visit',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'final_site_visit_date';
                list($strError, $arrFieldsAdded['final_site_visit_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Exit Interview ',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'exit_interview_date';
                list($strError, $arrFieldsAdded['exit_interview_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Good Faith Deposit Return Initiated',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'good_faith_deposit_return_initiated';
                list($strError, $arrFieldsAdded['good_faith_deposit_return_initiated']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Good Faith Deposit Returned',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'good_faith_deposit_returned_date';
                list($strError, $arrFieldsAdded['good_faith_deposit_returned_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Initial Work Permit or Extension',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'initial_work_permit';
                list($strError, $arrFieldsAdded['initial_work_permit']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Initial Work Permit or Extension',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'work_permit';
                $fieldOptions    = array(
                    array(
                        'name'  => '-',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'Initial',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'Extension',
                        'order' => 2
                    )
                );
                list($strError, $arrFieldsAdded['work_permit']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, $fieldOptions, false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Province/Territory Stream',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'province_stream';
                list($strError, $arrFieldsAdded['province_stream']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Type of Business',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'type_of_business';
                list($strError, $arrFieldsAdded['type_of_business']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Name of Business',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'name_of_business';
                list($strError, $arrFieldsAdded['name_of_business']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Business Concept',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'business_concept';
                list($strError, $arrFieldsAdded['business_concept']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Required Eligible Investment',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'required_eligible_investment';
                list($strError, $arrFieldsAdded['required_eligible_investment']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('memo'),
                    'label'     => 'Accompanying Dependent Details',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'accompanying_dependent_details';
                list($strError, $arrFieldsAdded['accompanying_dependent_details']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    true,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Location',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'location';
                list($strError, $arrFieldsAdded['location']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Intended Location of Business',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'intended_location_of_business';
                list($strError, $arrFieldsAdded['intended_location_of_business']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Job Location',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'job_location';
                list($strError, $arrFieldsAdded['job_location']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Nominee DOB',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'nominee_dob';
                list($strError, $arrFieldsAdded['nominee_dob']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Nomination Certificate Number',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'nomination_certificate_number';
                list($strError, $arrFieldsAdded['nomination_certificate_number']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Date Eligible for Nomination',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'date_eligible_nomination';
                list($strError, $arrFieldsAdded['date_eligible_nomination']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Nomination Certificate Expiry Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'nomination_certificate_expiry_date';
                list($strError, $arrFieldsAdded['nomination_certificate_expiry_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Employer',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'employer';
                list($strError, $arrFieldsAdded['employer']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Organization',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'organization';
                list($strError, $arrFieldsAdded['organization']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Occupation (NOC)',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'occupation';
                list($strError, $arrFieldsAdded['occupation']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('memo'),
                    'label'     => 'Restrictions on Employment',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'restrictions_on_employment';
                list($strError, $arrFieldsAdded['restrictions_on_employment']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    true,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Nomination Issued Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'nomination_issued_date';
                list($strError, $arrFieldsAdded['nomination_issued_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Date of Nomination Issuance',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'date_of_nomination_issuance';
                list($strError, $arrFieldsAdded['date_of_nomination_issuance']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Nomination Application Receipt Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'nomination_application_receipt_date';
                list($strError, $arrFieldsAdded['nomination_application_receipt_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('memo'),
                    'label'     => 'Explanation for Denial',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'explanation_for_denial';
                list($strError, $arrFieldsAdded['explanation_for_denial']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), true, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('memo'),
                    'label'     => 'Explanation for Employer Eligibility Denial',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'employer_explanation_for_denial';
                list($strError, $arrFieldsAdded['employer_explanation_for_denial']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    true,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Initial Submission Received',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'initial_submission_received_date';
                list($strError, $arrFieldsAdded['initial_submission_received_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('memo'),
                    'label'     => 'Missing Documents or Additional Info',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'missing_documents_or_additional_info';
                list($strError, $arrFieldsAdded['missing_documents_or_additional_info']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    true,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Submission Completed on',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'submission_completed_on_date';
                list($strError, $arrFieldsAdded['submission_completed_on_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Application Ready for Assessment',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'application_ready_for_assessment';
                list($strError, $arrFieldsAdded['application_ready_for_assessment']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Employment Standards Compliance Request Sent',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'employment_standards_compliance_request_sent_date';
                list($strError, $arrFieldsAdded['employment_standards_compliance_request_sent_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Employment Standards Compliance Status',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'employment_standards_compliance_status';
                $fieldOptions    = array(
                    array(
                        'name'  => '-',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'Compliance',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'Non-compliance',
                        'order' => 2
                    )
                );
                list($strError, $arrFieldsAdded['employment_standards_compliance_status']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'First Assessment Completed',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'first_assessment_completed_date';
                list($strError, $arrFieldsAdded['first_assessment_completed_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Final Review Completed',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'final_review_completed_date';
                list($strError, $arrFieldsAdded['final_review_completed_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Application Decision',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'application_decision';
                $fieldOptions    = array(
                    array(
                        'name'  => '-',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'Approved',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'Denied',
                        'order' => 2
                    ),
                    array(
                        'name'  => 'Withdrawn',
                        'order' => 3
                    ),
                    array(
                        'name'  => 'Revoked',
                        'order' => 4
                    ),
                    array(
                        'name'  => 'Switched',
                        'order' => 5
                    ),
                    array(
                        'name'  => 'Streams',
                        'order' => 6
                    )
                );
                list($strError, $arrFieldsAdded['application_decision']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Application Decision Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'application_decision_date';
                list($strError, $arrFieldsAdded['application_decision_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Decision Letter Sent',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'decision_letter_sent_date';
                list($strError, $arrFieldsAdded['decision_letter_sent_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'MOU Signing Appointment',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'MOU_signing_appointment';
                list($strError, $arrFieldsAdded['MOU_signing_appointment']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'MOU Signed On',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'MOU_signed_on_date';
                list($strError, $arrFieldsAdded['MOU_signed_on_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Appeal Date deadline',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'appeal_date_deadline';
                list($strError, $arrFieldsAdded['appeal_date_deadline']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Appeal Received',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'appeal_received_date';
                list($strError, $arrFieldsAdded['appeal_received_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Appeal Decision Date',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'appeal_decision_date';
                list($strError, $arrFieldsAdded['appeal_decision_date']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Appeal Decision',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'appeal_decision';
                $fieldOptions    = array(
                    array(
                        'name'  => '-',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'Stayed',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'Overturned',
                        'order' => 2
                    )
                );
                list($strError, $arrFieldsAdded['appeal_decision']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, $fieldOptions, false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Business Name',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'business_name';
                list($strError, $arrFieldsAdded['business_name']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Hours per Week',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'hours_per_week';
                list($strError, $arrFieldsAdded['hours_per_week']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('text'),
                    'label'     => 'Wage Rate',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'wage_rate';
                list($strError, $arrFieldsAdded['wage_rate']) = $oClients->getFields()->saveField($companyId, 0, 0, 0, $arrFieldsInsert['type'], $fieldCompanyId, $arrFieldsInsert, array(), false, 0, 0, false, '', false, false);

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('number'),
                    'label'     => 'Total Number of Family Members',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'total_number_of_family_members';
                list($strError, $arrFieldsAdded['total_number_of_family_members']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Pulled from Express Entry Portal (EE only)',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'pulled_from_express_entry_portal_date';
                list($strError, $arrFieldsAdded['pulled_from_express_entry_portal_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('date'),
                    'label'     => 'Work Permit Support Letter',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'work_permit_support_letter_date';
                list($strError, $arrFieldsAdded['work_permit_support_letter_date']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    array(),
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                $arrFieldsInsert = array(
                    'type'      => $oFieldTypes->getFieldTypeId('combo'),
                    'label'     => 'Language Required',
                    'required'  => 'N',
                    'encrypted' => 'N'
                );
                $fieldCompanyId  = 'language_required';
                $fieldOptions    = array(
                    array(
                        'name'  => 'English',
                        'order' => 0
                    ),
                    array(
                        'name'  => 'French',
                        'order' => 1
                    ),
                    array(
                        'name'  => 'Both',
                        'order' => 2
                    )
                );
                list($strError, $arrFieldsAdded['language_required']) = $oClients->getFields()->saveField(
                    $companyId,
                    0,
                    0,
                    0,
                    $arrFieldsInsert['type'],
                    $fieldCompanyId,
                    $arrFieldsInsert,
                    $fieldOptions,
                    false,
                    0,
                    0,
                    false,
                    '',
                    false,
                    false
                );

                foreach ($arrCaseTypes as $templateId) {
                    $groupId       = $oClients->getFields()->createGroup($companyId, 'Not Assigned', 3, $templateId, true, 'U');
                    $arrGroupIds[] = $groupId = $oClients->getFields()->createGroup($companyId, 'Case Details', 4, $templateId, false);

                    $arrGroupFields = array($arrFieldsAdded['file_number'], $arrFieldsAdded['category'], $arrFieldsAdded['file_status'], $arrFieldsAdded['Client_file_status']);

                    if ($templateId === $businessTemplateId) {
                        $arrGroupFields[] = $arrFieldsAdded['step_2_documents_received_date'];
                        $arrGroupFields[] = $arrFieldsAdded['application_fee_paid'];
                        $arrGroupFields[] = $arrFieldsAdded['business_concept_due_date'];
                        $arrGroupFields[] = $arrFieldsAdded['interview_date'];
                        $arrGroupFields[] = $arrFieldsAdded['interview_results'];
                        $arrGroupFields[] = $arrFieldsAdded['step_3_documents_received_date'];
                        $arrGroupFields[] = $arrFieldsAdded['invite_to_apply_issued_date'];
                        $arrGroupFields[] = $arrFieldsAdded['application_deadline'];
                        $arrGroupFields[] = $arrFieldsAdded['good_faith_deposit_received'];
                        $arrGroupFields[] = $arrFieldsAdded['work_permit_issuance_date'];
                        $arrGroupFields[] = $arrFieldsAdded['arrival_interview_date'];
                        $arrGroupFields[] = $arrFieldsAdded['missing_documents'];
                        $arrGroupFields[] = $arrFieldsAdded['additional_info_requested_date'];
                        $arrGroupFields[] = $arrFieldsAdded['additional_info_requested_due_date'];
                        $arrGroupFields[] = $arrFieldsAdded['bpa_sent_date'];
                        $arrGroupFields[] = $arrFieldsAdded['bpa_commencement_date'];
                        $arrGroupFields[] = $arrFieldsAdded['next_report_due'];
                        $arrGroupFields[] = $arrFieldsAdded['next_report_due_date'];
                        $arrGroupFields[] = $arrFieldsAdded['final_site_visit_date'];
                        $arrGroupFields[] = $arrFieldsAdded['exit_interview_date'];
                        $arrGroupFields[] = $arrFieldsAdded['date_eligible_nomination'];
                    } else {
                        $arrGroupFields[] = $arrFieldsAdded['initial_submission_received_date'];
                        $arrGroupFields[] = $arrFieldsAdded['missing_documents_or_additional_info'];
                        $arrGroupFields[] = $arrFieldsAdded['additional_info_requested_date'];
                        $arrGroupFields[] = $arrFieldsAdded['submission_completed_on_date'];
                    }

                    foreach ($arrGroupFields as $updateFieldId) {
                        $fullRow = in_array($updateFieldId, array($arrFieldsAdded['missing_documents'], $arrFieldsAdded['missing_documents_or_additional_info'], $arrFieldsAdded['interview_results'])) ? 'Y' : 'N';
                        // Create record in field orders table
                        $query         = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE group_id = %d)', 'client_form_order', $groupId);
                        $maxFieldOrder = new Zend_Db_Expr($query);

                        $db->insert('client_form_order', array('group_id' => $groupId, 'field_id' => $updateFieldId, 'use_full_row' => $fullRow, 'field_order' => $maxFieldOrder));
                    }

                    $arrGroupIds[] = $groupId = $oClients->getFields()->createGroup($companyId, 'Assessment Details', 4, $templateId, false);

                    $arrGroupFields = array();

                    if ($templateId === $businessTemplateId) {
                        $arrGroupFields[] = $arrFieldsAdded['type_of_business'];
                        $arrGroupFields[] = $arrFieldsAdded['intended_location_of_business'];
                        $arrGroupFields[] = $arrFieldsAdded['name_of_business'];
                        $arrGroupFields[] = $arrFieldsAdded['business_concept'];
                        $arrGroupFields[] = $arrFieldsAdded['required_eligible_investment'];
                        $arrGroupFields[] = $arrFieldsAdded['accompanying_dependent_details'];
                    } else {
                        $arrGroupFields[] = $arrFieldsAdded['employment_standards_compliance_request_sent_date'];
                        $arrGroupFields[] = $arrFieldsAdded['employment_standards_compliance_status'];
                        $arrGroupFields[] = $arrFieldsAdded['first_assessment_completed_date'];
                        $arrGroupFields[] = $arrFieldsAdded['final_review_completed_date'];
                        $arrGroupFields[] = $arrFieldsAdded['application_decision'];
                        $arrGroupFields[] = $arrFieldsAdded['application_decision_date'];
                        $arrGroupFields[] = $arrFieldsAdded['decision_letter_sent_date'];
                        $arrGroupFields[] = $arrFieldsAdded['MOU_signing_appointment'];
                        $arrGroupFields[] = $arrFieldsAdded['MOU_signed_on_date'];
                        $arrGroupFields[] = $arrFieldsAdded['appeal_date_deadline'];
                        $arrGroupFields[] = $arrFieldsAdded['appeal_received_date'];
                        $arrGroupFields[] = $arrFieldsAdded['appeal_decision_date'];
                        $arrGroupFields[] = $arrFieldsAdded['appeal_decision'];
                    }

                    foreach ($arrGroupFields as $updateFieldId) {
                        $fullRow = in_array($updateFieldId, array($arrFieldsAdded['accompanying_dependent_details'])) ? 'Y' : 'N';

                        // Create record in field orders table
                        $query         = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE group_id = %d)', 'client_form_order', $groupId);
                        $maxFieldOrder = new Zend_Db_Expr($query);

                        $db->insert('client_form_order', array('group_id' => $groupId, 'field_id' => $updateFieldId, 'use_full_row' => $fullRow, 'field_order' => $maxFieldOrder));
                    }

                    if ($templateId === $employerTemplateId) {
                        $arrGroupIds[]  = $groupId = $oClients->getFields()->createGroup($companyId, 'Job Information', 4, $templateId, false);
                        $arrGroupFields = array(
                            $arrFieldsAdded['job_title'],
                            $arrFieldsAdded['job_noc_code'],
                            $arrFieldsAdded['job_location'],
                            $arrFieldsAdded['business_name'],
                            $arrFieldsAdded['hours_per_week'],
                            $arrFieldsAdded['type_of_employment'],
                            $arrFieldsAdded['wage_rate'],
                            $arrFieldsAdded['language_required'],
                            $arrFieldsAdded['received_lmia'],
                            $arrFieldsAdded['employment_standards_regulated']
                        );

                        foreach ($arrGroupFields as $updateFieldId) {
                            // Create record in field orders table
                            $query         = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE group_id = %d)', 'client_form_order', $groupId);
                            $maxFieldOrder = new Zend_Db_Expr($query);

                            $db->insert('client_form_order', array('group_id' => $groupId, 'field_id' => $updateFieldId, 'use_full_row' => 'N', 'field_order' => $maxFieldOrder));
                        }
                    }

                    $arrGroupIds[] = $groupId = $oClients->getFields()->createGroup($companyId, 'Nomination Details', 4, $templateId, false);

                    $arrGroupFields = array();

                    if ($templateId === $businessTemplateId) {
                        $arrGroupFields[] = $arrFieldsAdded['initial_work_permit'];
                        $arrGroupFields[] = $arrFieldsAdded['date_of_nomination_issuance'];
                        $arrGroupFields[] = $arrFieldsAdded['nomination_certificate_expiry_date'];
                        $arrGroupFields[] = $arrFieldsAdded['nomination_certificate_number'];
                        $arrGroupFields[] = $arrFieldsAdded['good_faith_deposit_return_initiated'];
                        $arrGroupFields[] = $arrFieldsAdded['good_faith_deposit_returned_date'];
                    } else {
                        $arrGroupFields[] = $arrFieldsAdded['nomination_application_receipt_date'];
                        $arrGroupFields[] = $arrFieldsAdded['date_of_nomination_issuance'];
                        $arrGroupFields[] = $arrFieldsAdded['nomination_certificate_number'];
                        $arrGroupFields[] = $arrFieldsAdded['nomination_certificate_expiry_date'];
                        $arrGroupFields[] = $arrFieldsAdded['total_number_of_family_members'];
                        $arrGroupFields[] = $arrFieldsAdded['pulled_from_express_entry_portal_date'];
                        $arrGroupFields[] = $arrFieldsAdded['work_permit_support_letter_date'];
                        $arrGroupFields[] = $arrFieldsAdded['work_permit'];
                        $arrGroupFields[] = $arrFieldsAdded['restrictions_on_employment'];
                    }

                    foreach ($arrGroupFields as $updateFieldId) {
                        $fullRow = in_array($updateFieldId, array($arrFieldsAdded['restrictions_on_employment'])) ? 'Y' : 'N';
                        // Create record in field orders table
                        $query         = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE group_id = %d)', 'client_form_order', $groupId);
                        $maxFieldOrder = new Zend_Db_Expr($query);

                        $db->insert('client_form_order', array('group_id' => $groupId, 'field_id' => $updateFieldId, 'use_full_row' => $fullRow, 'field_order' => $maxFieldOrder));
                    }

                    if ($templateId === $businessTemplateId) {
                        $arrGroupIds[] = $groupId = $oClients->getFields()->createGroup($companyId, 'Denied or Review Details', 4, $templateId, false);

                        $arrGroupFields = array($arrFieldsAdded['explanation_for_denial']);

                        foreach ($arrGroupFields as $updateFieldId) {
                            $fullRow = in_array($updateFieldId, array($arrFieldsAdded['explanation_for_denial'])) ? 'Y' : 'N';

                            // Create record in field orders table
                            $query         = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE group_id = %d)', 'client_form_order', $groupId);
                            $maxFieldOrder = new Zend_Db_Expr($query);

                            $db->insert('client_form_order', array('group_id' => $groupId, 'field_id' => $updateFieldId, 'use_full_row' => $fullRow, 'field_order' => $maxFieldOrder));
                        }
                    }


                    $arrGroupAccess = array();
                    foreach ($arrGroupIds as $key => $groupId) {
                        $arrGroupAccess[$groupId] = 'on';
                    }

                    foreach ($arrAdminRoles as $adminRoleId) {
                        $oClients->getFields()->saveGroupAccessForRole($companyId, $adminRoleId, $arrGroupAccess);
                    }
                }

                if ($companyId !== 0) {
                    $arrUpdateCompany                     = array();
                    $arrUpdateCompany['companyName']      = 'NTNP Program';
                    $arrUpdateCompany['companyTimeZone']  = 'America/Denver';
                    $arrUpdateCompany['address']          = '1st Floor of the Lahm Ridge Tower
4501 – 50th Avenue
P.O. Box 1320';
                    $arrUpdateCompany['city']             = 'Yellowknife';
                    $arrUpdateCompany['state']            = 'Northwest Territories';
                    $arrUpdateCompany['country']          = 38;
                    $arrUpdateCompany['companyEmail']     = 'updatetoNTNPemail@uniques.ca';
                    $arrUpdateCompany['phone1']           = '1-855-440-5450';
                    $arrUpdateCompany['zip']              = 'X1A-2L9';
                    $arrUpdateCompany['storage_location'] = 'local';
                    $db->update('company', $arrUpdateCompany, sprintf('company_id = %d', $companyId));

                    $arrUpdateCompanyDetails                                        = array();
                    $arrUpdateCompanyDetails['support_and_training']                = 'Y';
                    $arrUpdateCompanyDetails['subscription']                        = 'ultimate_plus';
                    $arrUpdateCompanyDetails['default_label_office']                = 'queue';
                    $arrUpdateCompanyDetails['default_label_trust_account']         = 'client_account';
                    $arrUpdateCompanyDetails['next_billing_date']                   = '2099-03-31';
                    $arrUpdateCompanyDetails['free_users']                          = 1000;
                    $arrUpdateCompanyDetails['gst']                                 = 0.00;
                    $arrUpdateCompanyDetails['subscription_fee']                    = 0.00;
                    $arrUpdateCompanyDetails['free_storage']                        = 100000;
                    $arrUpdateCompanyDetails['trial']                               = 'N';
                    $arrUpdateCompanyDetails['allow_multiple_advanced_search_tabs'] = 'Y';
                    $arrUpdateCompanyDetails['allow_change_case_type']              = 'N';
                    $arrUpdateCompanyDetails['employers_module_enabled']            = 'Y';
                    $arrUpdateCompanyDetails['log_client_changes_enabled']          = 'Y';
                    $arrUpdateCompanyDetails['enable_case_management']              = 'Y';
                    $db->update('company_details', $arrUpdateCompanyDetails, sprintf('company_id = %d', $companyId));
                }
            }


            $db->commit();


            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }


    public function down()
    {
    }

}
//INSERT INTO `formversion` (`FormVersionId`, `FormId`, `VersionDate`, `UploadedDate`, `UploadedBy`, `FileName`) VALUES (2, 2, '2019-03-20 15:24:40', '2019-03-20 15:24:51', 1, 'EDS-Employer Eligibility Application');