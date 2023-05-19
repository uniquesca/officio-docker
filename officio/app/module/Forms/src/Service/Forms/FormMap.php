<?php

namespace Forms\Service\Forms;

use Forms\Service\Forms;
use Laminas\Cache\Storage\TaggableInterface;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * PDF sync fields mapping
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class FormMap extends BaseService implements SubServiceInterface
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

    public function getMappedProfileFields()
    {
        $cacheId    = 'pdf_form_map';
        $cacheTagId = 'tagPdfMap';
        if (!($arrResult = $this->_cache->getItem($cacheId))) {
            // Not in cache
            $select = (new Select())
                ->from(['m' => 'form_map'])
                ->columns([
                    'from_family_member_id',
                    'from_syn_field_id',
                    'to_profile_family_member_id',
                    'to_profile_field_id',
                    'form_map_type',
                    'parent_member_type',
                ])
                ->join(
                    ['mt' => 'members_types'],
                    'mt.member_type_id = m.parent_member_type',
                    ['parent_member_type_name' => 'member_type_name']
                );

            $arrResult = $this->_db2->fetchAll($select);

            $this->_cache->setItem($cacheId, $arrResult);
            if ($this->_cache instanceof TaggableInterface) {
                $this->_cache->setTags($cacheId, array($cacheTagId));
            }
        }

        return $arrResult;
    }

    /**
     * Load mapped fields list for specific family member and fields
     *
     * @param string $fromFamilyMemberId
     * @param array $arrFormSynFieldIds
     * @return array
     */
    public function getMappedFieldsForFamilyMember($fromFamilyMemberId, $arrFormSynFieldIds = null)
    {
        $arrResult = array();

        if (!empty($fromFamilyMemberId) && !is_null($arrFormSynFieldIds)) {
            $arrWhere = [];
            $arrWhere['FM.from_family_member_id'] = $fromFamilyMemberId;

            if (is_array($arrFormSynFieldIds) && count($arrFormSynFieldIds)) {
                $arrWhere['FM.from_syn_field_id'] = $arrFormSynFieldIds;
            }

            $select = (new Select())
                ->from(array('FM' => 'form_map'))
                ->columns([
                    'to_family_member_id',
                    'to_profile_family_member_id',
                    'to_profile_field_id',
                    'to_family_member_id',
                    'to_syn_field_id',
                    'form_map_type',
                    'parent_member_type'
                ])
                ->join(array('FS' => 'form_syn_field'), 'FS.syn_field_id = FM.from_syn_field_id', ["from_field_name" => "field_name", Select::SQL_STAR], Select::JOIN_LEFT_OUTER)
                ->join(array('FS2' => 'form_syn_field'), 'FS2.syn_field_id = FM.to_syn_field_id', ["to_field_name" => "field_name", Select::SQL_STAR], Select::JOIN_LEFT_OUTER)
                ->where($arrWhere);

            $arrResult = $this->_db2->fetchAll($select);
        }

        return $arrResult;
    }

    public function getMappedXfdfFields($applicant, $profileFieldId)
    {
        $arrMappingFields = $this->getMappedProfileFields();

        $arrResult = array();
        if (is_array($arrMappingFields) && !empty($arrMappingFields)) {
            foreach ($arrMappingFields as $arrFieldInfo) {
                if ($arrFieldInfo['to_profile_family_member_id'] == $applicant && $arrFieldInfo['to_profile_field_id'] == $profileFieldId) {
                    $arrResult[] = array(
                        'from_family_member_id'   => $arrFieldInfo['from_family_member_id'],
                        'from_syn_field_id'       => $arrFieldInfo['from_syn_field_id'],
                        'form_map_type'           => $arrFieldInfo['form_map_type'],
                        'parent_member_type'      => $arrFieldInfo['parent_member_type'],
                        'parent_member_type_name' => $arrFieldInfo['parent_member_type_name'],
                    );
                }
            }
        }

        return $arrResult;
    }


    public function getMappedFields($orderByField, $orderBy, $start, $limit)
    {
        if (!is_numeric($start)) {
            $start = 0;
        }

        if (!is_numeric($limit)) {
            $limit = 25;
        }

        $orderBy = strtoupper($orderBy ?? '');
        if ($orderBy !== 'DESC') {
            $orderBy = 'ASC';
        }

        switch ($orderByField) {
            case "from_field_name":
                $orderByField = 'F.from_syn_field_id';
                break;

            case "to_family_member_name":
            case "to_family_member_id":
                $orderByField = 'F.to_family_member_id';
                break;

            case "to_field_name":
                $orderByField = 'F.to_syn_field_id';
                break;

            case "profile_field_member":
                $orderByField = 'F.to_profile_family_member_id';
                break;

            case "profile_field_name":
                $orderByField = 'F.to_profile_field_id';
                break;

            case "profile_mapping_type":
                $orderByField = 'F.form_map_type';
                break;

            case "from_family_member_id":
            default:
                $orderByField = 'F.from_family_member_id';
                break;
        }

        $selectMain = (new Select())
            ->from(array('F' => 'form_map'))
            ->join(array('FS' => 'form_syn_field'), 'FS.syn_field_id = F.from_syn_field_id', ['field_from' => 'field_name', Select::SQL_STAR], Select::JOIN_LEFT_OUTER)
            ->join(array('FS1' => 'form_syn_field'), 'FS1.syn_field_id = F.to_syn_field_id', [ 'field_to' => 'field_name', Select::SQL_STAR], Select::JOIN_LEFT_OUTER)
            ->join(array('F1' => 'form_map'),
                new PredicateExpression('F.from_family_member_id = F1.to_family_member_id AND F.from_syn_field_id = F1.to_syn_field_id'),
                   ['form_map_id_direction' => 'form_map_id'], Select::JOIN_LEFT_OUTER)
            ->group('F.form_map_id')
            ->limit($limit)
            ->offset($start)
            ->order(array($orderByField . ' ' . $orderBy, 'F.form_map_id'));

        $arrResult    = $this->_db2->fetchAll($selectMain);
        $totalRecords = $this->_db2->fetchResultsCount($selectMain);

        if (!is_array($arrResult)) {
            $arrResult = array();
        }

        return array('rows' => $arrResult, 'totalCount' => $totalRecords);
    }

    public function deleteMaps($arrMapIds)
    {
        if (is_array($arrMapIds) && count($arrMapIds)) {
            $this->_db2->delete('form_map', ['form_map_id' => $arrMapIds]);
        }
    }
}
