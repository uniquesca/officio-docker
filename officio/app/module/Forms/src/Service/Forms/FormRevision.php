<?php

namespace Forms\Service\Forms;

use Files\Service\Files;
use Forms\Service\Forms;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * Assigned forms revision model for specific client
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class FormRevision extends BaseService implements SubServiceInterface
{

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

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_files = $services[Files::class];
    }


    /**
     * Load revisions list for specific assigned pdf form(s)
     *
     * @param $assignedFormId
     * @param bool $booLoadMemberInfo
     * @param bool $booLoadAssignedFormInfo
     * @param bool $booLoadClientInfo
     * @return array info about assigned pdf form revisions
     */
    public function getAssignedFormRevisions(
        $assignedFormId,
        $booLoadMemberInfo = true,
        $booLoadAssignedFormInfo = false,
        $booLoadClientInfo = false
    ) {
        $select = (new Select())
            ->from(array('r' => 'form_revision'))
            ->where(['r.form_assigned_id' => $assignedFormId])
            ->order('r.form_revision_number ASC');

        if ($booLoadMemberInfo) {
            $select->join(array('m' => 'members'), 'm.member_id = r.uploaded_by', array('fName', 'lName'));
        }

        if ($booLoadAssignedFormInfo) {
            $select->join(array('a' => 'form_assigned'), 'a.form_assigned_id = r.form_assigned_id', 'client_member_id');
        }

        if ($booLoadClientInfo) {
            $select->join(
                array('m2' => 'members'),
                'm2.member_id = a.client_member_id',
                array('client_first_name' => 'fName', 'client_last_name' => 'lName')
            );
        }

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load latest revision info for specific assigned pdf form
     *
     * @param $assignedFormId
     * @return array info about assigned pdf form revision
     */
    public function getAssignedFormLatestRevision($assignedFormId)
    {
        $select = (new Select())
            ->from(['r' => 'form_revision'])
            ->where(['r.form_assigned_id' => $assignedFormId])
            ->order('r.form_revision_number DESC')
            ->limit(1);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Load revision info for specific assigned form id
     *
     * @param int $assignedFormId
     * @param int $revisionId
     * @return array
     */
    public function loadRevisionInfo($assignedFormId, $revisionId)
    {
        $select = (new Select())
            ->from('form_revision')
            ->where([
                'form_revision_id' => $revisionId,
                'form_assigned_id' => $assignedFormId
            ]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Create new record in DB
     *
     * @param array $arrFormVersionDetails
     * @return int new record id
     */
    public function createNewFormVersion($arrFormVersionDetails)
    {
        return $this->_db2->insert('form_revision', $arrFormVersionDetails);
    }


    /**
     * Delete revision (record in DB and file)
     *
     * @param $memberId
     * @param $revisionId
     * @return void
     */
    public function deleteRevision($memberId, $revisionId)
    {
        $arrRevisions = is_array($revisionId) ? $revisionId : array($revisionId);
        foreach ($arrRevisions as $revisionId) {
            $pathToPdf = $this->_files->getClientBarcodedPDFFilePath($memberId, $revisionId);
            if (file_exists($pathToPdf)) {
                unlink($pathToPdf);
            }

            $this->_db2->delete('form_revision', ['form_revision_id' => (int)$revisionId]);
        }
    }


    /**
     * Load revision ids by assigned form ids
     *
     * @param $arrAssignedFormIds
     * @return array
     */
    public function getRevisionIdsByFormAssignedIds($arrAssignedFormIds)
    {
        $arrFormRevisionIds = array();
        if (is_array($arrAssignedFormIds) && count($arrAssignedFormIds)) {
            $select = (new Select())
                ->from('form_revision')
                ->columns(['form_revision_id'])
                ->where(['form_assigned_id' => $arrAssignedFormIds]);

            $arrFormRevisionIds = $this->_db2->fetchCol($select);
        }
        return $arrFormRevisionIds;
    }
}// EO class
