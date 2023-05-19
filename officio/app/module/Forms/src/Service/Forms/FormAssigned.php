<?php

namespace Forms\Service\Forms;

use Clients\Service\Clients;
use Exception;
use Forms\Service\Forms;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;

/**
 * Assigned forms model for specific client
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class FormAssigned extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Forms */
    protected $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Load assigned forms list for specific client
     *
     * @param int $clientId - client id for which we want load forms
     * @param string $orderByField
     * @param string $orderBy
     * @param int $start
     * @param int $limit
     *
     * @return array result - list of assigned forms
     */
    public function fetchByClientForms($clientId, $orderByField, $orderBy, $start, $limit)
    {
        // Check incoming data, use default values
        if (!is_numeric($start) || $start < 0) {
            $start = 0;
        }

        if (!is_numeric($limit) || $limit < 0) {
            $limit = 25;
        }

        $orderBy = strtoupper($orderBy);
        if ($orderBy !== 'DESC') {
            $orderBy = 'ASC';
        }

        switch ($orderByField) {
            case "file_name":
                $orderByField = 'fv.file_name';
                break;

            case "date_assigned":
                $orderByField = 'a.assign_date';
                break;

            case "date_completed":
                $orderByField = 'a.completed_date';
                break;

            case "date_finalized":
                $orderByField = 'a.finalized_date';
                break;

            case "updated_by":
                $orderByField = 'updated_by_name';
                break;

            case "updated_on":
                $orderByField = 'a.last_update_date';
                break;

            case "family_member_type":
            default:
                $orderByField = 'a.family_member_id';
                break;
        }

        $select = (new Select())
            ->from(array('a' => 'form_assigned'))
            ->join(
                array('m' => 'members'),
                'm.member_id = a.updated_by',
                array('lName', 'fName', 'updated_by_name' => new Expression('CONCAT(m.lName, " ", m.fName)')),
                Select::JOIN_LEFT_OUTER
            )
            ->join(array('c' => 'clients'), 'c.member_id = a.client_member_id', 'forms_locked', Select::JOIN_LEFT_OUTER)
            ->join(array('fv' => 'form_version'), 'fv.form_version_id = a.form_version_id', ['file_name', 'form_id', 'form_type', 'size', 'version_date'], Select::JOIN_LEFT_OUTER)
            ->join(array('fu' => 'form_upload'), 'fu.form_id = fv.form_id', [], Select::JOIN_LEFT_OUTER)
            ->where(['a.client_member_id' => $clientId])
            ->limit($limit)
            ->offset($start)
            ->order($orderByField . ' ' . $orderBy);

        $rowSet       = $this->_db2->fetchAll($select);
        $totalRecords = $this->_db2->fetchResultsCount($select);

        return array('rows' => $rowSet, 'totalCount' => $totalRecords);
    }

    /**
     * Load information about specific assigned pdf form
     *
     * @param int $assignedFormId
     * @param bool $booLoadMemberInfo
     * @return array info about assigned pdf form
     */
    public function getAssignedFormInfo($assignedFormId, $booLoadMemberInfo = false)
    {
        $arrFormInfo = array();

        if (!empty($assignedFormId) && is_numeric($assignedFormId)) {
            $select = (new Select())
                ->from(array('a' => 'form_assigned'))
                ->join(array('fv' => 'form_version'), 'fv.form_version_id = a.form_version_id', ['version_date', 'file_name'], Select::JOIN_LEFT_OUTER)
                ->where(['a.form_assigned_id' => $assignedFormId]);

            if ($booLoadMemberInfo) {
                $select->join(
                    array('m' => 'members'),
                    'm.member_id = a.client_member_id',
                    array('client_first_name' => 'fName', 'client_last_name' => 'lName'),
                    Select::JOIN_LEFT_OUTER
                );
            }

            $arrFormInfo = $this->_db2->fetchRow($select);
        }

        return $arrFormInfo;
    }


    /**
     * Load form member id by pdf form id
     *
     * @param $assignedFormId (array or int)
     * @return array|string
     */
    public function getFormMemberIdById($assignedFormId)
    {
        $select = (new Select())
            ->from(['a' => 'form_assigned'])
            ->columns(['client_member_id'])
            ->where(['a.form_assigned_id' => $assignedFormId]);

        return is_array($assignedFormId) ? $this->_db2->fetchCol($select) : $this->_db2->fetchOne($select);
    }


    /**
     * Create new record in DB
     *
     * @param array $arrFormDetails
     * @return int new record id
     */
    public function assignForm($arrFormDetails)
    {
        return $this->_db2->insert('form_assigned', $arrFormDetails);
    }

    /**
     * Assign form(s) to specific case
     *
     * @param int $memberId
     * @param string $familyMemberId
     * @param array $arrForms
     * @param string $other
     * @param int $assignedByMemberId
     * @return array
     */
    public function assignFormToCase($memberId, $familyMemberId, $arrForms, $other, $assignedByMemberId, $formSettings = '')
    {
        $strError    = '';
        $arrFormInfo = array();

        try {
            // pform1 --> 1
            $forms = array();
            foreach ($arrForms as $formId) {
                $forms[] = substr($formId, 5);
            }

            /** @var Clients $oClients */
            $oClients = $this->_serviceContainer->get(Clients::class);

            // Check if family member is correct
            if (empty($strError)) {
                $arrFamilyMembers = $oClients->getFamilyMembersForClient($memberId, true);

                $booCorrect = false;
                if (is_array($arrFamilyMembers) && count($arrFamilyMembers) > 0) {
                    foreach ($arrFamilyMembers as $fm) {
                        if ($fm['id'] == $familyMemberId) {
                            $booCorrect = true;
                            break;
                        }
                    }
                }

                if (!$booCorrect) {
                    $strError = $this->_tr->translate('Incorrectly selected family member');
                }
            }

            // Check pdf version id
            if (empty($strError)) {
                foreach ($forms as $formId) {
                    if (!$this->getParent()->getFormVersion()->formVersionExists($formId)) {
                        $strError = $this->_tr->translate('Incorrectly selected pdf form');
                        break;
                    }
                }
            }

            // All is okay, save new records in db
            if (empty($strError)) {
                // Get all already assigned forms
                $select = (new Select())
                    ->from('form_assigned')
                    ->columns(['family_member_id', 'form_version_id'])
                    ->where(['client_member_id' => (int)$memberId]);

                $arrClientFormsList = $this->_db2->fetchAll($select);

                // form is already assigned for this client,
                // but one form can be assigned to several 'other'
                if (preg_match('/^other\d*$/', $familyMemberId)) {
                    // Do nothing
                    $booOther = true;
                } else {
                    $booOther = false;

                    foreach ($forms as $key => $id) {
                        foreach ($arrClientFormsList as $form) {
                            if ($form['family_member_id'] == $familyMemberId && $form['form_version_id'] == $id) {
                                unset($forms[$key]);
                            }
                        }
                    }
                }

                foreach ($forms as $formId) {
                    $formInfo = $this->getParent()->getFormVersion()->getFormVersionInfo($formId);

                    $arrInsert = array(
                        'client_member_id' => $memberId,
                        'family_member_id' => $familyMemberId,
                        'form_version_id'  => $formId,
                        'use_revision'     => $formInfo['form_type'] == 'bar' ? 'Y' : 'N',
                        'assign_date'      => date('c'),
                        'assign_by'        => $assignedByMemberId,
                        'updated_by'       => $assignedByMemberId,
                        'assign_alias'     => $booOther ? $other : '',
                        'last_update_date' => date('c'),
                        'form_status'      => 1,
                        'form_settings'    => $formSettings ?? ''
                    );

                    $id = $this->assignForm($arrInsert);

                    $arrNewFormData = array(
                        'client_form_id'     => $id,
                        'family_member_type' => $familyMemberId,
                        'file_name'          => $formInfo['file_name']
                    );
                    $arrFormInfo[]  = array('data' => $arrNewFormData);

                    // Copy default xfdf files to these assigned pdf forms
                    // Generate path to new xfdf file we want copy to
                    $this->getParent()->copyDefaultXfdfToClient($this->_auth->getCurrentUserCompanyId(), $memberId, $arrNewFormData);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('msg' => $strError, 'forms_info' => $arrFormInfo);
    }

    /**
     * Load assigned form ids by client (member) id
     *
     * @param $memberId
     * @return array with form ids
     */
    public function getAssignedFormIdsByClientId($memberId)
    {
        $select = (new Select())
            ->from(['a' => 'form_assigned'])
            ->columns(['form_assigned_id'])
            ->where(['a.client_member_id' => (int)$memberId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Update form revision for specific assigned form
     *
     * @param $assignedFormId
     * @param $newFormVersionId
     * @return bool true on success
     */
    public function updateAssignedFormVersion($assignedFormId, $newFormVersionId)
    {
        $booSuccess = false;
        try {
            $this->_db2->update(
                'form_assigned',
                ['form_version_id' => $newFormVersionId],
                ['form_assigned_id' => (int)$assignedFormId]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load count of assigned forms by their versions
     *
     * @param $arrFormVersionIds
     * @return int
     */
    public function getAssignedFormsCountByVersions($arrFormVersionIds)
    {
        $count = 0;

        if (is_array($arrFormVersionIds) && count($arrFormVersionIds)) {
            $select = (new Select())
                ->from('form_assigned')
                ->columns(['forms_count' => new Expression('COUNT(*)')])
                ->where(['form_version_id' => $arrFormVersionIds]);

            $count = (int)$this->_db2->fetchOne($select);
        }

        return $count;
    }

    /**
     * Update text alias for assigned pdf form
     *
     * @param string $strAlias
     * @param int $assignedFormId
     * @param int $memberId
     * @return bool true on success
     */
    public function updateAliasForAssignedForm($strAlias, $assignedFormId, $memberId)
    {
        try {
            $this->_db2->update(
                'form_assigned',
                ['assign_alias' => $strAlias],
                [
                    'form_assigned_id' => $assignedFormId,
                    'client_member_id' => $memberId
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Update form_settings for assigned form
     *
     * @param string $settings
     * @param int $assignedFormId
     * @return bool true on success
     */
    public function updateFormSettings($settings, $assignedFormId)
    {
        try {
            $this->_db2->update(
                'form_assigned',
                ['form_settings' => $settings],
                [
                    'form_assigned_id' => $assignedFormId
                ]
            );
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if member has assigned form(s) from array of version ids
     *
     * @param int $memberId
     * @param array $arrFormVersionIds
     * @return bool true on success
     */
    public function hasMemberFormAssigned($memberId, $arrFormVersionIds)
    {
        $booHasFormAssigned = false;
        try {
            if (is_array($arrFormVersionIds) && count($arrFormVersionIds)) {
                $select = (new Select())
                    ->from('form_assigned')
                    ->columns(['form_assigned_id'])
                    ->where([
                        'client_member_id' => $memberId,
                        'form_version_id'  => $arrFormVersionIds
                    ]);

                $formsAssigned = $this->_db2->fetchCol($select);
                if (count($formsAssigned) > 0) {
                    $booHasFormAssigned = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasFormAssigned;
    }

    /**
     * Load members those have assigned form(s) from array of version ids
     *
     * @param array $arrFormVersionIds
     * @return array
     */
    public function getMembersByAssignedFormVersionIds($arrFormVersionIds)
    {
        $arrResult = array();
        try {
            if (is_array($arrFormVersionIds) && count($arrFormVersionIds)) {
                $select = (new Select())
                    ->from('form_assigned')
                    ->columns(['client_member_id'])
                    ->where(['form_version_id' => $arrFormVersionIds]);

                $arrResult = $this->_db2->fetchCol($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Load form setting by id
     *
     * @param int $id
     * @return string
     */
    public function getFormSettingById($id)
    {
        $select = (new Select())
            ->from('form_assigned')
            ->columns(['form_settings'])
            ->where(['form_assigned_id' => $id]);

        return $this->_db2->fetchOne($select);
    }
}// EO class
