<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ApplicantTypes extends BaseService implements SubServiceInterface
{
    /** @var Clients */
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
     * Load types list for specific company
     *
     * @param int $companyId
     * @param bool $booIdsOnly
     * @param int $memberTypeId
     * @return array
     */
    public function getTypes($companyId, $booIdsOnly = false, $memberTypeId = null)
    {
        $select = (new Select())
            ->from(array('t' => 'applicant_types'))
            ->where(['t.company_id' => (int)$companyId]);

        if (!is_null($memberTypeId)) {
            $select->where->equalTo('t.member_type_id', (int)$memberTypeId);
        }

        $arrTemplates = $this->_db2->fetchAll($select);

        // Collect ids
        $arrTemplateIds = array();
        foreach ($arrTemplates as $arrCaseTemplateInfo) {
            $arrTemplateIds[] = $arrCaseTemplateInfo['applicant_type_id'];
        }

        return $booIdsOnly ? $arrTemplateIds : $arrTemplates;
    }

    /**
     * Search applicant type by provided name (for specific company/member type)
     *
     * @param $companyId
     * @param $memberTypeId
     * @param $typeName
     * @return int type id, if not found - false
     */
    public function getTypeIdByName($companyId, $memberTypeId, $typeName)
    {
        $typeId = false;
        $arrTypes = $this->getTypes($companyId, false, $memberTypeId);
        foreach ($arrTypes as $arrTypeInfo) {
            if ($arrTypeInfo['applicant_type_name'] == $typeName) {
                $typeId = $arrTypeInfo['applicant_type_id'];
                break;
            }
        }

        return $typeId;
    }

    /**
     * Load specific type info
     *
     * @param int $typeId - template id to load info for
     * @return array template info
     */
    public function getTypeInfo($typeId)
    {
        $select = (new Select())
            ->from('applicant_types')
            ->where(['applicant_type_id' => (int)$typeId]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Check if current user has access to specific type
     *
     * @param int $typeId - template id to check
     * @return bool true if has access, otherwise false
     */
    public function hasAccessToType($typeId)
    {
        $booHasAccess = false;

        if (!empty($typeId) && is_numeric($typeId)) {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } else {
                $arrTypeInfo = $this->getTypeInfo($typeId);
                if (is_array($arrTypeInfo) && array_key_exists('company_id', $arrTypeInfo) && $arrTypeInfo['company_id'] == $this->_auth->getCurrentUserCompanyId()) {
                    $booHasAccess = true;
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Create a new applicant type record
     *
     * @param int $companyId
     * @param int $memberTypeId
     * @param string $typeName
     * @param int $typeCopyId
     * @return int|string
     */
    public function addType($companyId, $memberTypeId, $typeName, $typeCopyId)
    {
        try {
            $arrInsert = array(
                'company_id'          => $companyId,
                'member_type_id'      => $memberTypeId,
                'applicant_type_name' => $typeName,
                'is_system'           => 'N',
            );
            $typeId = $this->_db2->insert('applicant_types', $arrInsert);


            // Create copy of all fields/groups/blocks from specific type
            if ($typeCopyId && !$this->_parent->getApplicantFields()->createApplicantTypeCopy($typeCopyId, $typeId)) {
                $this->_db2->delete('applicant_types', ['applicant_type_id' => (int)$typeId]);
                $typeId = 0;
            }
        } catch (Exception $e) {
            $typeId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $typeId;
    }

    /**
     * Update applicant type's record info
     *
     * @param int $typeId
     * @param string $typeName
     * @return bool true on success
     */
    public function updateType($typeId, $typeName)
    {
        try {
            $this->_db2->update('applicant_types', ['applicant_type_name' => $typeName], ['applicant_type_id' => (int)$typeId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete applicant type by id
     *
     * @param int $typeId
     * @return bool true on success
     */
    public function deleteType($typeId)
    {
        try {
            // TODO: delete access from roles
            $arrTables = array(
                'applicant_types'
            );

            foreach ($arrTables as $table) {
                $this->_db2->delete($table, ['applicant_type_id' => (int)$typeId]);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create default applicant types for specific company
     *
     * @param int $companyId
     * @return array of mapped types (old id -> new id)
     */
    public function createCompanyDefaultApplicantTypes($companyId)
    {
        $arrMappingTypes = array();

        // Get default Case Templates
        $arrDefaultTypes = $this->getTypes(0);

        // Create a copy and save a mapping
        foreach ($arrDefaultTypes as $arrTypeInfo) {
            $newTypeId = $this->addType($companyId, $arrTypeInfo['member_type_id'], $arrTypeInfo['applicant_type_name'], 0);
            if (!empty($newTypeId)) {
                $arrMappingTypes[$arrTypeInfo['applicant_type_id']] = $newTypeId;
            }
        }

        return $arrMappingTypes;
    }
}
