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
class CaseCategories extends BaseService implements SubServiceInterface
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
     * Load the list of categories for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyCaseCategories($companyId)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id', ['client_type_name'], Select::JOIN_LEFT)
            ->join(array('l' => 'client_statuses_lists'), 'c.client_status_list_id = l.client_status_list_id', ['client_status_list_name'], Select::JOIN_LEFT)
            ->where(['c.company_id' => (int)$companyId])
            ->order('c.client_category_order');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load a list of categories grouped by case types for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCategoriesGroupedByCaseTypes($companyId)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->columns(['client_type_id', 'client_category_id', 'client_category_name', 'client_category_link_to_employer'])
            ->where(['c.company_id' => (int)$companyId])
            ->order('c.client_category_order');

        $arrSavedRecords = $this->_db2->fetchAll($select);

        $arrGroupedCategories = [];
        foreach ($arrSavedRecords as $arrSavedRecordInfo) {
            $arrGroupedCategories[$arrSavedRecordInfo['client_type_id']][] = [
                'option_id'        => $arrSavedRecordInfo['client_category_id'],
                'option_name'      => $arrSavedRecordInfo['client_category_name'],
                'link_to_employer' => $arrSavedRecordInfo['client_category_link_to_employer'],
            ];
        }

        return $arrGroupedCategories;
    }

    /**
     * Load a list of categories linked to a specific case type
     *
     * @param int $caseTypeId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getCaseCategoriesMappingForCaseType($caseTypeId, $booIdsOnly = true)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->columns($booIdsOnly ? ['client_category_id'] : ['client_category_id', 'client_category_parent_id', 'client_category_name', 'client_category_abbreviation', 'client_category_link_to_employer', 'client_category_order'])
            ->where(['c.client_type_id' => (int)$caseTypeId])
            ->order('c.client_category_order');

        if (!$booIdsOnly) {
            $select->join(array('l1' => 'client_statuses_lists'), 'l1.client_status_list_id = c.client_status_list_id', ['client_category_assigned_list_id' => 'client_status_list_id', 'client_category_assigned_list_name' => 'client_status_list_name'], Select::JOIN_LEFT);
            $select->join(array('l2' => 'client_statuses_lists'), 'l2.client_status_list_id = c.client_status_custom_list_id', ['client_category_custom_assigned_list_id' => 'client_status_list_id', 'client_category_custom_assigned_list_name' => 'client_status_list_name'], Select::JOIN_LEFT);
        }

        if ($booIdsOnly) {
            $arrResult = $this->_db2->fetchCol($select);
        } else {
            $arrResult = $this->_db2->fetchAll($select);

            if (!empty($arrResult)) {
                foreach ($arrResult as $key => $arrCategoryInfo) {
                    // Use custom list id/name if was set for this category and not a default one
                    if (!empty($arrCategoryInfo['client_category_custom_assigned_list_id'])) {
                        $arrResult[$key]['client_category_assigned_list_id']   = $arrResult[$key]['client_category_custom_assigned_list_id'];
                        $arrResult[$key]['client_category_assigned_list_name'] = $arrResult[$key]['client_category_custom_assigned_list_name'];
                    }
                    unset($arrResult[$key]['client_category_custom_assigned_list_id']);
                    unset($arrResult[$key]['client_category_custom_assigned_list_name']);
                }
            }
        }

        return $arrResult;
    }

    /**
     * Load case category info
     *
     * @param int $caseCategoryId
     * @return array
     */
    public function getCompanyCaseCategoryInfo($caseCategoryId)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->where(['c.client_category_id' => (int)$caseCategoryId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Check if current user has access to a specific category
     *
     * @param int $caseCategoryId
     * @param bool $booView if true - check if company id is the same, otherwise check also if the category is linked to the default category
     * @return bool
     */
    public function hasAccessToCaseCategory($caseCategoryId, $booView)
    {
        $booHasAccess = false;

        $arrSavedCaseCategoryInfo = $this->getCompanyCaseCategoryInfo($caseCategoryId);
        if (!empty($arrSavedCaseCategoryInfo)) {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } elseif ($arrSavedCaseCategoryInfo['company_id'] == $this->_auth->getCurrentUserCompanyId()) {
                if ($booView) {
                    // Case Category can be viewed for the current user
                    $booHasAccess = true;
                } else {
                    // Case Category can be updated only if this is not a linked record
                    $booHasAccess = empty($arrSavedCaseCategoryInfo['client_category_parent_id']);
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Create/update category info
     *
     * @param bool $booNewCaseCategory
     * @param array $arrCaseCategoryInfo
     * @return int category id
     */
    public function saveCompanyCaseCategory($booNewCaseCategory, $arrCaseCategoryInfo)
    {
        $arrUpdateData = [
            'company_id'                       => $arrCaseCategoryInfo['company_id'],
            'client_type_id'                   => $arrCaseCategoryInfo['client_type_id'],
            'client_status_list_id'            => $arrCaseCategoryInfo['client_status_list_id'],
            'client_category_parent_id'        => $arrCaseCategoryInfo['client_category_parent_id'],
            'client_category_name'             => $arrCaseCategoryInfo['client_category_name'],
            'client_category_abbreviation'     => $arrCaseCategoryInfo['client_category_abbreviation'],
            'client_category_link_to_employer' => $arrCaseCategoryInfo['client_category_link_to_employer'],
            'client_category_order'            => $arrCaseCategoryInfo['client_category_order'],
        ];

        if (array_key_exists('client_status_custom_list_id', $arrCaseCategoryInfo)) {
            $arrUpdateData['client_status_custom_list_id'] = $arrCaseCategoryInfo['client_status_custom_list_id'];
        }

        if ($booNewCaseCategory) {
            $caseCategoryId = $this->_db2->insert('client_categories', $arrUpdateData);
        } else {
            $caseCategoryId = $arrCaseCategoryInfo['client_category_id'];
            if (!empty($arrCaseCategoryInfo['client_category_parent_id'])) {
                $arrWhere = [
                    'company_id'                => $arrCaseCategoryInfo['company_id'],
                    'client_category_parent_id' => $arrCaseCategoryInfo['client_category_parent_id']
                ];
            } else {
                $arrWhere = ['client_category_id' => $caseCategoryId];
            }

            // These properties cannot be updated
            unset(
                $arrUpdateData['company_id'],
                $arrUpdateData['client_category_parent_id'],
                $arrUpdateData['client_type_id']
            );

            $this->updateCategoryInfo($arrUpdateData, $arrWhere);
        }

        return $caseCategoryId;
    }

    /**
     * Update category's info
     *
     * @param array $arrUpdateData
     * @param array $arrWhere
     * @return void
     */
    public function updateCategoryInfo($arrUpdateData, $arrWhere)
    {
        $this->_db2->update('client_categories', $arrUpdateData, $arrWhere);
    }

    /**
     * Update category's simple info (e.g. name - that is not company dependent) based on the parent category id
     *
     * @param int $parentCategoryId
     * @param array $arrUpdateData
     * @return void
     */
    public function updateCategoryByParentId($parentCategoryId, $arrUpdateData)
    {
        $this->_db2->update(
            'client_categories',
            $arrUpdateData,
            ['client_category_parent_id' => $parentCategoryId]
        );
    }

    /**
     * Delete a specific category (or several categories)
     * If the provided category is a parent one - all sub categories will be deleted automatically
     *
     * @param array $arrCaseCategoryIds
     */
    public function deleteCaseCategories($arrCaseCategoryIds)
    {
        $this->_db2->delete('client_categories', ['client_category_id' => $arrCaseCategoryIds]);
    }

    /**
     * Create categories during new company creation
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @param array $arrMappingCaseTemplates
     * @param array $arrMappingDefaultCaseStatusLists
     * @return array mapping between default and created categories' ids
     */
    public function createDefaultCategories($fromCompanyId, $toCompanyId, $arrMappingCaseTemplates, $arrMappingDefaultCaseStatusLists)
    {
        $arrMappingDefaults = array();
        try {
            $arrDefaultCategories = $this->getCompanyCaseCategories($fromCompanyId);

            foreach ($arrDefaultCategories as $arrDefaultCategoryInfo) {
                if (!empty($arrMappingCaseTemplates[$arrDefaultCategoryInfo['client_type_id']])) {
                    $arrCompanyCaseCategoryInfo = [
                        'company_id'                       => $toCompanyId,
                        'client_type_id'                   => $arrMappingCaseTemplates[$arrDefaultCategoryInfo['client_type_id']],
                        'client_status_list_id'            => $arrMappingDefaultCaseStatusLists[$arrDefaultCategoryInfo['client_status_list_id']],
                        'client_category_parent_id'        => $arrDefaultCategoryInfo['client_category_id'],
                        'client_category_name'             => $arrDefaultCategoryInfo['client_category_name'],
                        'client_category_abbreviation'     => $arrDefaultCategoryInfo['client_category_abbreviation'],
                        'client_category_link_to_employer' => $arrDefaultCategoryInfo['client_category_link_to_employer'],
                        'client_category_order'            => $arrDefaultCategoryInfo['client_category_order'],
                    ];

                    $arrMappingDefaults[$arrDefaultCategoryInfo['client_category_id']] = $this->saveCompanyCaseCategory(true, $arrCompanyCaseCategoryInfo);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMappingDefaults;
    }

    /**
     * Load a list if "child" case categories for a specific "parent" category
     *
     * @param int $parentCaseCategoryId
     * @param bool $booIdsOnly true to load ids only or false to load all details
     * @return array
     */
    public function getCaseCategoriesByParentId($parentCaseCategoryId, $booIdsOnly = false)
    {
        $select = (new Select())
            ->from('client_categories')
            ->columns($booIdsOnly ? ['client_category_id'] : [Select::SQL_STAR])
            ->where(['client_category_parent_id' => (int)$parentCaseCategoryId]);

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }
}
