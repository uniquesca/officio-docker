<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Settings;
use Officio\Common\SubServiceInterface;


/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CaseStatuses extends BaseService implements SubServiceInterface
{
    /** @var Clients */
    protected $_parent;

    /**
     * @param Clients $parent
     */
    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    /**
     * @return Clients
     */
    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Load the list of statuses for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyCaseStatuses($companyId)
    {
        $select = (new Select())
            ->from(array('c' => 'client_statuses'))
            ->where(['c.company_id' => (int)$companyId])
            ->order('c.client_status_name');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load the list of case statuses (all, grouped by categories, grouped by case types)
     *
     * @param int $companyId
     * @param array $arrCaseTypes
     * @return array
     */
    public function getCompanyCaseStatusesGrouped($companyId, $arrCaseTypes)
    {
        // Get the list of all statuses
        $arrAllStatuses     = [];
        $arrCompanyStatuses = $this->getCompanyCaseStatuses($companyId);
        foreach ($arrCompanyStatuses as $arrCompanyStatusInfo) {
            $arrAllStatuses[] = [
                'option_id'   => $arrCompanyStatusInfo['client_status_id'],
                'option_name' => $arrCompanyStatusInfo['client_status_name'],
            ];
        }

        // Get the list of all categories and statuses for them
        $arrGroupedByCategories   = [];
        $arrCompanyCaseCategories = $this->getParent()->getCaseCategories()->getCompanyCaseCategories($companyId);
        foreach ($arrCompanyCaseCategories as $arrCompanyCaseCategoryInfo) {
            $arrCategoryStatuses = [];

            // If there is no list linked to this category - we'll use the parent case type's default list
            $listId = $this->getCaseStatusListIdByCategoryId($arrCompanyCaseCategoryInfo['client_category_id']);
            if (!empty($listId)) {
                $arrListStatuses = $this->getCaseStatusListMappedStatuses($listId, false);
                foreach ($arrListStatuses as $arrListStatusInfo) {
                    $arrCategoryStatuses[] = [
                        'option_id'   => $arrListStatusInfo['client_status_id'],
                        'option_name' => $arrListStatusInfo['client_status_name'],
                    ];
                }
            }

            $arrGroupedByCategories[$arrCompanyCaseCategoryInfo['client_category_id']] = $arrCategoryStatuses;
        }


        // Get the list of all case types and statuses for them
        $arrGroupedByCaseTypes = [];
        foreach ($arrCaseTypes as $arrCaseTypeInfo) {
            $arrCaseTypeStatuses = [];

            $caseStatusListId = $arrCaseTypeInfo['case_template_client_status_list_id'];
            if (!empty($caseStatusListId)) {
                $arrListStatuses = $this->getCaseStatusListMappedStatuses($caseStatusListId, false);
                foreach ($arrListStatuses as $arrListStatusInfo) {
                    $arrCaseTypeStatuses[] = [
                        'option_id'   => $arrListStatusInfo['client_status_id'],
                        'option_name' => $arrListStatusInfo['client_status_name'],
                    ];
                }
            }

            $arrGroupedByCaseTypes[$arrCaseTypeInfo['case_template_id']] = $arrCaseTypeStatuses;
        }

        return [
            'all'        => $arrAllStatuses,
            'categories' => $arrGroupedByCategories,
            'case_types' => $arrGroupedByCaseTypes
        ];
    }

    /**
     * Load case statuses list by provided imploded string of ids
     *
     * @param string $caseStatuses
     * @return array
     */
    public function getCaseStatusesNames($caseStatuses)
    {
        $arrStatusesWithNames = [];
        if (!empty($caseStatuses)) {
            $arrCaseStatusesIds = explode(',', $caseStatuses);
            $arrCaseStatusesIds = Settings::arrayUnique(array_map('intval', $arrCaseStatusesIds));

            if (!empty($arrCaseStatusesIds)) {
                $select = (new Select())
                    ->from(array('c' => 'client_statuses'))
                    ->where([
                        (new Where())->in('c.client_status_id', $arrCaseStatusesIds)
                    ]);

                $arrStatuses = $this->_db2->fetchAll($select);

                foreach ($arrStatuses as $arrStatusInfo) {
                    $arrStatusesWithNames[$arrStatusInfo['client_status_id']] = $arrStatusInfo['client_status_name'];
                }
            }
        }

        return $arrStatusesWithNames;
    }

    /**
     * Load case status info
     *
     * @param int $caseStatusId
     * @return array
     */
    public function getCompanyCaseStatusInfo($caseStatusId)
    {
        $select = (new Select())
            ->from(array('c' => 'client_statuses'))
            ->where(['c.client_status_id' => (int)$caseStatusId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Check if current user has access to a specific status
     *
     * @param int $caseStatusId
     * @param bool $booView true - if company id is the same, otherwise check also if the status is linked to the default status
     * @return bool
     */
    public function hasAccessToCaseStatus($caseStatusId, $booView)
    {
        $booHasAccess = false;

        $arrSavedCaseStatusInfo = $this->getCompanyCaseStatusInfo($caseStatusId);
        if (!empty($arrSavedCaseStatusInfo)) {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } elseif ($arrSavedCaseStatusInfo['company_id'] == $this->_auth->getCurrentUserCompanyId()) {
                if ($booView) {
                    // Case Status can be viewed for the current user
                    $booHasAccess = true;
                } else {
                    // Case Status can be updated only if this is not a linked record
                    $booHasAccess = empty($arrSavedCaseStatusInfo['client_status_parent_id']);
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Create/update status info
     *
     * @param bool $booNewCaseStatus
     * @param array $arrCaseStatusInfo
     * @return int status id
     */
    public function saveCompanyCaseStatus($booNewCaseStatus, $arrCaseStatusInfo)
    {
        $arrUpdateData = [
            'company_id'              => $arrCaseStatusInfo['company_id'],
            'client_status_parent_id' => $arrCaseStatusInfo['client_status_parent_id'],
            'client_status_name'      => $arrCaseStatusInfo['client_status_name'],
        ];

        if ($booNewCaseStatus) {
            $caseStatusId = $this->_db2->insert('client_statuses', $arrUpdateData);
        } else {
            $caseStatusId = $arrCaseStatusInfo['client_status_id'];
            if (!empty($arrCaseStatusInfo['client_status_parent_id'])) {
                $arrWhere = ['client_status_parent_id' => $arrCaseStatusInfo['client_status_parent_id']];
            } else {
                $arrWhere = ['client_status_id' => $caseStatusId];
            }

            // These properties cannot be updated
            unset($arrUpdateData['company_id']);
            unset($arrUpdateData['client_status_parent_id']);

            $this->_db2->update('client_statuses', $arrUpdateData, $arrWhere);
        }

        return $caseStatusId;
    }

    /**
     * Create/update links for a default status for all companies
     *
     * @param bool $booNewCaseStatus
     * @param int $parentCaseStatusId
     * @param array $arrCaseStatusInfo
     * @param array $arrCompaniesIds
     */
    public function saveLinkedCompaniesCaseStatus($booNewCaseStatus, $parentCaseStatusId, $arrCaseStatusInfo, $arrCompaniesIds)
    {
        if ($booNewCaseStatus) {
            foreach ($arrCompaniesIds as $companyId) {
                $arrCompanyCaseStatusInfo = [
                    'company_id'              => $companyId,
                    'client_status_id'        => 0,
                    'client_status_parent_id' => $parentCaseStatusId,
                    'client_status_name'      => $arrCaseStatusInfo['client_status_name'],
                ];

                $this->saveCompanyCaseStatus(true, $arrCompanyCaseStatusInfo);
            }
        } else {
            $arrCaseStatusInfo['client_status_parent_id'] = $parentCaseStatusId;
            $this->saveCompanyCaseStatus(false, $arrCaseStatusInfo);
        }
    }


    /**
     * Delete a specific status (or several statuses)
     * If the provided status is a parent one - all sub statuses will be deleted automatically
     *
     * @param array $arrCaseStatusIds
     */
    public function deleteCaseStatuses($arrCaseStatusIds)
    {
        $this->_db2->delete('client_statuses', ['client_status_id' => $arrCaseStatusIds]);
    }

    /**
     * Load case status list info
     *
     * @param int $caseStatusListId
     * @return array
     */
    public function getCompanyCaseStatusListInfo($caseStatusListId)
    {
        $select = (new Select())
            ->from(array('l' => 'client_statuses_lists'))
            ->where(['l.client_status_list_id' => (int)$caseStatusListId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load case statuses lists for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyCaseStatusLists($companyId)
    {
        $select = (new Select())
            ->from(array('l' => 'client_statuses_lists'))
            ->where(['l.company_id' => (int)$companyId])
            ->order('l.client_status_list_name');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load case statuses lists for a specific company and parent list id
     *
     * @param int $companyId
     * @param int $parentCaseStatusListId
     * @return int id
     */
    public function getCompanyCaseStatusListIdByCompanyAndParentListId($companyId, $parentCaseStatusListId)
    {
        $listId = 0;

        if (!empty($parentCaseStatusListId)) {
            $select = (new Select())
                ->from(array('l' => 'client_statuses_lists'))
                ->columns(['client_status_list_id'])
                ->where([
                    'l.company_id'                   => (int)$companyId,
                    'l.client_status_list_parent_id' => (int)$parentCaseStatusListId
                ]);

            $listId = $this->_db2->fetchOne($select);
        }

        return $listId;
    }

    /**
     * Load ids of case status lists by provided parent case status lists ids
     *
     * @param array $arrParentCaseStatusListIds
     * @return array
     */
    public function getCaseStatusListIdsByParentListIds($arrParentCaseStatusListIds)
       {
           $arrChildListIds = [];
   
           if (!empty($arrParentCaseStatusListIds)) {
               $select = (new Select())
                   ->from(array('l' => 'client_statuses_lists'))
                   ->columns(['client_status_list_id'])
                   ->where([
                       'l.client_status_list_parent_id' => $arrParentCaseStatusListIds
                   ]);

               $arrChildListIds = $this->_db2->fetchCol($select);
           }

           return $arrChildListIds;
       }

    /**
     * Check if current user has access to a specific status list
     *
     * @param int $caseStatusListId
     * @param bool $booView true - if company id is the same, otherwise check also if the status list is linked to the default status list
     * @return bool
     */
    public function hasAccessToCaseStatusList($caseStatusListId, $booView)
    {
        $booHasAccess = false;

        $arrSavedCaseStatusListInfo = $this->getCompanyCaseStatusListInfo($caseStatusListId);
        if (!empty($arrSavedCaseStatusListInfo)) {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booHasAccess = true;
            } elseif ($arrSavedCaseStatusListInfo['company_id'] == $this->_auth->getCurrentUserCompanyId()) {
                if ($booView) {
                    // Case Status List can be viewed for the current user
                    $booHasAccess = true;
                } else {
                    // Case Status List can be updated only if this is not a linked record
                    $booHasAccess = empty($arrSavedCaseStatusListInfo['client_status_list_parent_id']);
                }
            }
        }

        return $booHasAccess;
    }


    /**
     * Create/update case status list info
     *
     * @param bool $booNewCaseStatusList
     * @param array $arrCaseStatusListInfo
     * @return int case status list id
     */
    public function saveCompanyCaseStatusList($booNewCaseStatusList, $arrCaseStatusListInfo)
    {
        $arrUpdateData = [
            'company_id'                   => $arrCaseStatusListInfo['company_id'],
            'client_status_list_parent_id' => $arrCaseStatusListInfo['client_status_list_parent_id'],
            'client_status_list_name'      => $arrCaseStatusListInfo['client_status_list_name'],
        ];

        if ($booNewCaseStatusList) {
            $caseStatusListId = $this->_db2->insert('client_statuses_lists', $arrUpdateData);
        } else {
            $caseStatusListId = $arrCaseStatusListInfo['client_status_list_id'];
            if (!empty($arrCaseStatusListInfo['client_status_list_parent_id'])) {
                $arrWhere = ['client_status_list_parent_id' => $arrCaseStatusListInfo['client_status_list_parent_id']];
            } else {
                $arrWhere = ['client_status_list_id' => $caseStatusListId];
            }

            // These properties cannot be updated
            unset($arrUpdateData['company_id']);
            unset($arrUpdateData['client_status_list_parent_id']);

            $this->_db2->update('client_statuses_lists', $arrUpdateData, $arrWhere);
        }

        return $caseStatusListId;
    }

    /**
     * Create/update links for a default case status list for all companies
     *
     * @param bool $booNewCaseStatusList
     * @param int $parentCaseStatusListId
     * @param array $arrCaseStatusListInfo
     * @param array $arrCompaniesIds
     */
    public function saveLinkedCompaniesCaseStatusList($booNewCaseStatusList, $parentCaseStatusListId, $arrCaseStatusListInfo, $arrCompaniesIds)
    {
        if ($booNewCaseStatusList) {
            foreach ($arrCompaniesIds as $companyId) {
                $arrCompanyCaseStatusInfo = [
                    'company_id'                   => $companyId,
                    'client_status_list_id'        => 0,
                    'client_status_list_parent_id' => $parentCaseStatusListId,
                    'client_status_list_name'      => $arrCaseStatusListInfo['client_status_list_name'],
                ];

                $this->saveCompanyCaseStatusList(true, $arrCompanyCaseStatusInfo);
            }
        } else {
            $arrCaseStatusListInfo['client_status_list_parent_id'] = $parentCaseStatusListId;
            $this->saveCompanyCaseStatusList(false, $arrCaseStatusListInfo);
        }
    }

    /**
     * Delete a specific case status list (or several lists)
     * If the provided list is a parent one - all sub lists will be deleted automatically
     * If the case status list is linked to the category - link this category to another case status list (use a default list from the linked case type)
     *
     * @param array $arrCaseStatusListIds
     */
    public function deleteCaseStatusLists($arrCaseStatusListIds)
    {
        // If any case status list was linked to the category -> get case type for the category and use the default case status list from it
        // Also, do the same for all child/linked case status lists
        $arrChildCaseListIds     = $this->getCaseStatusListIdsByParentListIds($arrCaseStatusListIds);
        $arrAllCaseStatusListIds = array_merge($arrCaseStatusListIds, $arrChildCaseListIds);
        $arrCategoriesToUpdate   = $this->getMappedCategoriesByCaseStatusLists($arrAllCaseStatusListIds);

        $oCaseCategories = $this->getParent()->getCaseCategories();
        foreach ($arrCategoriesToUpdate as $arrCategoryInfo) {
            if (!empty($arrCategoryInfo['case_type_client_status_list_id']) && $arrCategoryInfo['client_status_list_id'] != $arrCategoryInfo['case_type_client_status_list_id']) {
                $oCaseCategories->updateCategoryInfo(
                    ['client_status_list_id' => $arrCategoryInfo['case_type_client_status_list_id']],
                    ['client_category_id' => $arrCategoryInfo['client_category_id']]
                );
            }
        }

        $this->_db2->delete('client_statuses_lists', ['client_status_list_id' => $arrCaseStatusListIds]);
    }

    /**
     * Get the list of case categories linked to a specific "case status list"
     *
     * @param int $caseStatusListId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getCaseStatusListMappedCategories($caseStatusListId, $booIdsOnly = true)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->columns([$booIdsOnly ? 'client_category_id' : Select::SQL_STAR])
            ->where(
                (new Where())
                    ->equalTo('c.client_status_custom_list_id', (int)$caseStatusListId)
                    ->or
                    ->nest()
                    ->equalTo('c.client_status_list_id', (int)$caseStatusListId)
                    ->isNull('c.client_status_custom_list_id')
                    ->unnest()
            )
            ->order('c.client_category_name');

        if (!$booIdsOnly) {
            $select->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id', ['default_client_status_list_id' => 'client_status_list_id'], Select::JOIN_LEFT);
        }

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Get the list of case categories (with additional info from case types) linked to specific "case status lists"
     *
     * @param array $caseStatusListIds
     * @return array
     */
    public function getMappedCategoriesByCaseStatusLists($caseStatusListIds)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->columns(['client_category_id', 'client_status_list_id'])
            ->join(array('ct' => 'client_types'), 'c.client_type_id = ct.client_type_id', ['case_type_client_status_list_id' => 'client_status_list_id'], Select::JOIN_LEFT)
            ->where(['c.client_status_list_id' => $caseStatusListIds]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load the list of Case Types that:
     *  - are linked to the Case Status List
     *  - or linked to categories that are linked to the Case Status List
     *
     * @param int $caseStatusListId
     * @return array
     */
    public function getCaseStatusListMappedCaseTypes($caseStatusListId)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->columns(['client_type_id'])
            ->where(
                (new Where())
                    ->equalTo('c.client_status_custom_list_id', (int)$caseStatusListId)
                    ->or
                    ->nest()
                    ->equalTo('c.client_status_list_id', (int)$caseStatusListId)
                    ->isNull('c.client_status_custom_list_id')
                    ->unnest()
            )
            ->group('c.client_type_id');

        $listMappedCaseTypesByCategories = $this->_db2->fetchCol($select);

        if (!empty($listMappedCaseTypesByCategories)) {
            $arrWhere = (new Where())
                ->nest()
                ->equalTo('t.client_status_list_id', (int)$caseStatusListId)
                ->or
                ->in('t.client_type_id', array_map('intval', $listMappedCaseTypesByCategories))
                ->unnest();
        } else {
            $arrWhere = ['t.client_status_list_id' => (int)$caseStatusListId];
        }

        $select = (new Select())
            ->from(array('t' => 'client_types'))
            ->columns(['client_type_name'])
            ->where($arrWhere)
            ->order('t.client_type_name');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Get the list of case statuses linked to a specific "case status list"
     *
     * @param int $caseStatusListId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getCaseStatusListMappedStatuses($caseStatusListId, $booIdsOnly = true)
    {
        $select = (new Select())
            ->from(array('m' => 'client_statuses_lists_mapping_to_statuses'))
            ->columns([$booIdsOnly ? 'client_status_id' : Select::SQL_STAR])
            ->join(array('s' => 'client_statuses'), 's.client_status_id = m.client_status_id', ['client_status_name'], Select::JOIN_LEFT)
            ->where(['m.client_status_list_id' => (int)$caseStatusListId])
            ->order('m.client_status_list_order');

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }


    /**
     * Save/link the list of statuses to a specific "case status list"
     *
     * @param int $caseStatusListId
     * @param array $arrEnabledStatusesIds
     * @param array $arrStatusesIdsToSkip
     */
    public function saveCaseStatusListMappingToStatuses($caseStatusListId, $arrEnabledStatusesIds, $arrStatusesIdsToSkip = array())
    {
        $oWhere = (new Where())
            ->equalTo('client_status_list_id', (int)$caseStatusListId);

        if (!empty($arrStatusesIdsToSkip)) {
            $oWhere->notIn('client_status_id', $arrStatusesIdsToSkip);
        }

        $this->_db2->delete('client_statuses_lists_mapping_to_statuses', [$oWhere]);

        // Get the max order saved in the DB (if some non default categories were used)
        $select = (new Select())
            ->from('client_statuses_lists_mapping_to_statuses')
            ->columns(['row' => new Expression('MAX(client_status_list_order)')])
            ->where(['client_status_list_id' => (int)$caseStatusListId]);

        $order = $this->_db2->fetchOne($select);
        $order = empty($order) ? 0 : $order;

        foreach ($arrEnabledStatusesIds as $caseStatusId) {
            $this->_db2->insert(
                'client_statuses_lists_mapping_to_statuses',
                [
                    'client_status_list_id'    => (int)$caseStatusListId,
                    'client_status_id'         => (int)$caseStatusId,
                    'client_status_list_order' => $order++
                ]
            );
        }
    }

    /**
     * Create case statuses during new company creation
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @return array mapping between default and created case statuses' ids
     */
    public function createDefaultCaseStatuses($fromCompanyId, $toCompanyId)
    {
        $arrMappingDefaults = array();
        try {
            $arrDefaultStatuses = $this->getCompanyCaseStatuses($fromCompanyId);

            foreach ($arrDefaultStatuses as $arrDefaultCaseStatusInfo) {
                $arrCompanyCaseStatusInfo = [
                    'company_id'              => $toCompanyId,
                    'client_status_parent_id' => $arrDefaultCaseStatusInfo['client_status_id'],
                    'client_status_name'      => $arrDefaultCaseStatusInfo['client_status_name'],
                ];

                $arrMappingDefaults[$arrDefaultCaseStatusInfo['client_status_id']] = $this->saveCompanyCaseStatus(true, $arrCompanyCaseStatusInfo);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMappingDefaults;
    }

    /**
     * Get the id of the list that is linked to the category
     *
     * @param int $caseCategoryId
     * @return int
     */
    public function getCaseStatusListIdByCategoryId($caseCategoryId)
    {
        $select = (new Select())
            ->from(array('c' => 'client_categories'))
            ->columns(['client_status_list_id', 'client_status_custom_list_id'])
            ->where(['c.client_category_id' => (int)$caseCategoryId]);

        $arrListInfo = $this->_db2->fetchRow($select);

        $listId = 0;
        if (isset($arrListInfo['client_status_custom_list_id']) && !empty($arrListInfo['client_status_custom_list_id'])) {
            $listId = $arrListInfo['client_status_custom_list_id'];
        } elseif (isset($arrListInfo['client_status_list_id']) && !empty($arrListInfo['client_status_list_id'])) {
            $listId = $arrListInfo['client_status_list_id'];
        }

        return $listId;
    }

    /**
     * Create "case status lists" during new company creation
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @return array mapping between default and created case status lists' ids
     */
    public function createDefaultCaseStatusLists($fromCompanyId, $toCompanyId)
    {
        $arrMappingDefaults = array();
        try {
            $arrDefaultStatusLists = $this->getCompanyCaseStatusLists($fromCompanyId);

            foreach ($arrDefaultStatusLists as $arrDefaultCaseStatusListInfo) {
                $arrCompanyCaseStatusListInfo = [
                    'company_id'                   => $toCompanyId,
                    'client_status_list_parent_id' => $arrDefaultCaseStatusListInfo['client_status_list_id'],
                    'client_status_list_name'      => $arrDefaultCaseStatusListInfo['client_status_list_name'],
                ];

                $arrMappingDefaults[$arrDefaultCaseStatusListInfo['client_status_list_id']] = $this->saveCompanyCaseStatusList(true, $arrCompanyCaseStatusListInfo);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMappingDefaults;
    }

    /**
     * Create statuses mapping (to lists) during new company creation
     *
     * @param array $arrMappingCaseStatusLists
     * @param array $arrMappingDefaultStatuses
     */
    public function createDefaultListStatusesMapping($arrMappingCaseStatusLists, $arrMappingDefaultStatuses)
    {
        foreach ($arrMappingCaseStatusLists as $defaultListId => $newListId) {
            $arrDefaultMapping = $this->getCaseStatusListMappedStatuses($defaultListId, false);
            foreach ($arrDefaultMapping as $arrDefaultMappingInfo) {
                if (isset($arrMappingDefaultStatuses[$arrDefaultMappingInfo['client_status_id']])) {
                    $arrInsertData = [
                        'client_status_list_id'    => $newListId,
                        'client_status_id'         => $arrMappingDefaultStatuses[$arrDefaultMappingInfo['client_status_id']],
                        'client_status_list_order' => $arrDefaultMappingInfo['client_status_list_order'],
                    ];

                    $this->_db2->insert('client_statuses_lists_mapping_to_statuses', $arrInsertData);
                }
            }
        }
    }

    /**
     * Load a list if "child" case statuses for a specific "parent" status
     *
     * @param int $parentCaseStatusId
     * @param bool $booIdsOnly true to load ids only or false to load all details
     * @return array
     */
    public function getCaseStatusesByParentId($parentCaseStatusId, $booIdsOnly = false)
    {
        $select = (new Select())
            ->from('client_statuses')
            ->columns($booIdsOnly ? ['client_status_id'] : [Select::SQL_STAR])
            ->where(['client_status_parent_id' => (int)$parentCaseStatusId]);

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }
}
