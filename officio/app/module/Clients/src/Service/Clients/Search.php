<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Clients\Service\Members;
use DateInterval;
use DateTime;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\IsNull;
use Laminas\Db\Sql\Predicate\NotIn;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Service\Users;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Shared\Drawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Search extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Clients */
    private $_parent;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_files = $services[Files::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Check if current user has access to saved search
     * @param int $searchId
     * @return bool true if user has access
     */
    public function hasAccessToSavedSearch($searchId)
    {
        $booHasAccess = false;
        if (!empty($searchId) && is_numeric($searchId)) {
            $arrSearchInfo = $this->getSearchInfo($searchId);
            if (is_array($arrSearchInfo) && count($arrSearchInfo)) {
                $currentMemberCompanyId = $this->_auth->getCurrentUserCompanyId();
                $booHasAccess           = $currentMemberCompanyId == $arrSearchInfo['company_id'];
            }
        }

        return $booHasAccess;
    }

    /**
     * Get information about saved search
     *
     * @param int $searchId
     * @param int $companyId
     * @return array
     */
    public function getSearchInfo($searchId, $companyId = null)
    {
        $select = (new Select())
            ->from('searches')
            ->where(['search_id' => (int)$searchId]);

        if (!is_null($companyId)) {
            $select->where->equalTo('company_id', (int)$companyId);
        }

        return $this->_db2->fetchRow($select);
    }

    /**
     * Get all searches for specific company
     *
     * @param int $companyId
     * @param string|array $arrFields - fields list we need to load
     * @param string|array $searchType - searches we need to load
     * @return array
     */
    public function getCompanySearches($companyId, $arrFields = [Select::SQL_STAR], $searchType = ['clients'])
    {
        $select = (new Select())
            ->from('searches')
            ->columns($arrFields)
            ->where(
                [
                    'search_type' => $searchType,
                    'company_id' => (int)$companyId
                ]
            )
            ->order('title ASC');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Check if field is used in saved searches
     * @param int $companyId
     * @param string $fieldCompanyId
     * @return bool true if is used
     */
    public function isFieldUsedInSearch($companyId, $fieldCompanyId)
    {
        $booUsed = false;
        $arrCompanySearches = $this->getCompanySearches($companyId);
        foreach ($arrCompanySearches as $arrCompanySearchInfo) {
            if (preg_match('/^(.*)"' . $fieldCompanyId . '"(.*)$/si', $arrCompanySearchInfo['query'])) {
                $booUsed = true;
                break;
            }
        }

        return $booUsed;
    }


    public function saveSearchInfo($searchId, $searchTitle, $searchType, $searchQuery, $searchColumns)
    {
        try {
            $arrData = array(
                'title' => $searchTitle,
                'search_type' => $searchType,
                'query' => $searchQuery,
                'columns' => $searchColumns
            );

            if (empty($searchId)) {
                $arrData['author_id']  = $this->_auth->getCurrentUserId();
                $arrData['company_id'] = $this->_auth->getCurrentUserCompanyId();

                $searchId = $this->_db2->insert('searches', $arrData);
            } else {
                $this->_db2->update('searches', $arrData, ['search_id' => $searchId]);
            }
        } catch (Exception $e) {
            $searchId = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $searchId;
    }

    public function saveAdvancedSearch($arrNotFilteredPOST)
    {
        $filter = new StripTags();

        $POST = array();
        foreach ($arrNotFilteredPOST as $key => $val) {
            $POST[$key] = $filter->filter($val);
        }

        // Check if all incoming params are correct
        $arrRequiredKeys = array('cols', 'title', 'mode', 'cntr');
        if (count(array_intersect($arrRequiredKeys, array_keys($POST))) != count($arrRequiredKeys)) {
            return false;
        }

        $cols = $POST['cols'];
        $title = $POST['title'];
        $mode = $POST['mode'];

        $searchId = 0;
        if ($mode != 'add') {
            $searchId = $POST['search_id'];

            // Check if current user has access to this search and can edit it
            $select = (new Select())
                ->from('searches')
                ->columns(array('search_id'))
                ->where(
                    [
                        'company_id' => $this->_auth->getCurrentUserCompanyId()
                    ]
                );

            $arrIds = $this->_db2->fetchCol($select);

            if (!is_array($arrIds) || empty($arrIds) || !in_array($searchId, $arrIds)) {
                return false;
            }
        }


        $form = array();
        $form['cntr'] = $POST['cntr'];

        //get fields
        for ($i = 0; $i < $form['cntr']; $i++) {
            $srchField = array_key_exists('srchField-' . $i, $POST) ? $POST['srchField-' . $i] : '';
            if (empty($srchField)) {
                continue;
            }

            $srchFieldArr = explode('|', $srchField);
            $company_field_id = $srchFieldArr[0];

            switch ((int)$srchFieldArr[1]) {
                case 4 :
                    $form['srchCountryConditions-' . $i] = $POST['srchCountryConditions-' . $i];
                    $form['srchCountryList-' . $i] = $POST['srchCountryList-' . $i];
                    break;

                case 5 :
                    $form['srchNumConditions-' . $i] = $POST['srchNumConditions-' . $i];
                    $form['txtSrchNum-' . $i] = $POST['txtSrchNum-' . $i];
                    break;

                case 8 :
                case 15 :
                    $form['srchDateCondition-' . $i] = $POST['srchDateCondition-' . $i];
                    $form['txtSrchDate-' . $i] = $POST['txtSrchDate-' . $i];
                    $form['txtSrchDateTo-' . $i] = $POST['txtSrchDateTo-' . $i];
                    $form['txtNextNum-' . $i] = $POST['txtNextNum-' . $i];
                    $form['txtNextPeriod-' . $i] = $POST['txtNextPeriod-' . $i];
                    break;

                case 13 :
                    $form['srchDivisionList-' . $i] = $POST['srchDivisionList-' . $i];
                    $form['srchDivisionConditions-' . $i] = $POST['srchDivisionConditions-' . $i];
                    break;

                case 12 :
                    $form['srchAgentList-' . $i] = $POST['srchAgentList-' . $i];
                    $form['srchAgentConditions-' . $i] = $POST['srchAgentConditions-' . $i];
                    break;

                case 14 :
                    $form['srchStaffList-' . $i] = $POST['srchStaffList-' . $i];
                    $form['srchStaffConditions-' . $i] = $POST['srchStaffConditions-' . $i];
                    break;

                case 0 :
                    break;

                case 3 : // combobox
                    // @NOTE: we use same keys as for text fields
                    // because we don't want update fields in DB
                    $form['srcTxtCondition-' . $i] = $POST['srchComboCondition-' . $i . '-' . $company_field_id];
                    $form['txtSrchClient-' . $i] = $POST['srchComboList-' . $i . '-' . $company_field_id];
                    break;

                default :
                    $form['srcTxtCondition-' . $i] = $POST['srcTxtCondition-' . $i];
                    $form['txtSrchClient-' . $i] = $POST['txtSrchClient-' . $i];
                    break;
            }

            $form['srchField-' . $i] = $srchField;

            if ($i != 0) {
                $form['match-' . $i] = $POST['match-' . $i];
            }
        }

        return $this->saveSearchInfo($searchId, $title, 'clients', Json::encode($form), $cols);
    }

    /**
     * Get field types allowed for search (fields will be showed in advanced search)
     * @return array
     */
    public function getSearchAllowedTypes()
    {
        $arrFields = $this->_parent->getFieldTypes()->getFieldTypes();

        $arrSearchableFieldIds = array();
        foreach ($arrFields as $arrFieldInfo) {
            if ($arrFieldInfo['booCanBeSearched']) {
                $arrSearchableFieldIds[] = $arrFieldInfo['id'];
            }
        }

        return $arrSearchableFieldIds;
    }

    /**
     * Delete saved search
     *
     * @param int $searchId
     * @return bool true on success
     */
    public function delete($searchId)
    {
        try {
            $this->_db2->delete(
                'searches',
                [
                    'search_id' => $searchId,
                    'company_id' => $this->_auth->getCurrentUserCompanyId()
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
     * Create/update default search for current user
     *
     * @param int $memberId
     * @param string $searchId
     * @param string $searchType
     * @return bool true on success
     */
    public function setMemberDefaultSearch($memberId = null, $searchId = 'LAST4ALL', $searchType = 'clients')
    {
        try {
            $memberId = is_null($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $arrData  = array(
                'member_id'           => $memberId,
                'default_search'      => $searchId,
                'default_search_type' => $searchType,
            );

            if (!$this->getMemberDefaultSearch($memberId, $searchType)) {
                $this->_db2->insert('default_searches', $arrData);
            } else {
                // Some info we don't need to update
                unset($arrData['member_id'], $arrData['default_search_type']);

                $this->_db2->update(
                    'default_searches',
                    $arrData,
                    [
                        'member_id'           => (int)$memberId,
                        'default_search_type' => $searchType
                    ]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load default search for current user
     * @param int $memberId
     * @param string $searchType
     * @param bool $booReturnEmpty - true to return empty value if there is no record in DB
     * @return string
     */
    public function getMemberDefaultSearch($memberId = null, $searchType = 'clients', $booReturnEmpty = true)
    {
        $memberId = !is_null($memberId) ? $memberId : $this->_auth->getCurrentUserId();

        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(['userType'])
            ->join(array('s' => 'default_searches'), 'm.member_id = s.member_id', Select::SQL_STAR, Select::JOIN_LEFT)
            ->where(
                [
                    'm.member_id'           => (int)$memberId,
                    's.default_search_type' => $searchType
                ]
            );

        $arrSearchInfo = $this->_db2->fetchRow($select);
        $defaultSearch = is_array($arrSearchInfo) && array_key_exists('default_search', $arrSearchInfo) ? $arrSearchInfo['default_search'] : '';

        if (!$booReturnEmpty && empty($defaultSearch)) {
            $defaultSearch = 'last4all';
            if (is_array($arrSearchInfo) && array_key_exists('userType', $arrSearchInfo) && in_array($arrSearchInfo['userType'], Members::getMemberType('client'))) {
                $defaultSearch = 'all';
            }
        }

        return $defaultSearch;
    }

    /**
     * Get search name by id (system types only)
     *
     * @param $searchId
     * @return string
     */
    public function getMemberDefaultSearchName($searchId)
    {
        $viewLastXCases = 50;
        switch ($searchId) {
            case 'last4me':
                $searchName = sprintf($this->_tr->translate('Last %d cases accessed by me'), $viewLastXCases);
                break;

            case 'last4all':
                $searchName = sprintf($this->_tr->translate('Last %d cases accessed by all'), $viewLastXCases);
                break;

            case 'all':
                $searchName = $this->_tr->translate('All cases');
                break;

            default:
                $searchName = $this->_tr->translate('Saved search ' . $searchId);
                break;
        }

        return $searchName;
    }

    /**
     * Delete default searches for specific members
     * @param array $arrMemberIds
     * @return bool true on success
     */
    public function deleteMembersDefaultSearch($arrMemberIds)
    {
        $booSuccess = false;
        try {
            $arrMemberIds = is_array($arrMemberIds) ? $arrMemberIds : array($arrMemberIds);
            if (count($arrMemberIds)) {
                $this->_db2->delete('default_searches', ['member_id' => $arrMemberIds]);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * The quickest search possible
     *
     * @param array $arrQueryWords
     * @param int $limit
     * @param bool $booSearchByCasesOnly
     * @param bool $booSearchByDependents
     * @param bool $booAllClients
     * @param bool $booLoadTotalCount
     * @return array
     */
    public function runQuickSearchByStaticFields($arrQueryWords, $limit = 50, $booSearchByCasesOnly = false, $booSearchByDependents = false, $booAllClients = false, $booLoadTotalCount = true)
    {
        $arrApplicants = array();
        $totalCount    = 0;

        try {
            list($oStructQuery, $booUseDivisionsTable) = $this->_parent->getMemberStructureQuery();

            $companyId = $this->_auth->getCurrentUserCompanyId();
            $arrOrder  = array('m2.lName', 'm2.fName');

            $select = (new Select())
                ->from(array('m' => 'members'))
                ->quantifier(Select::QUANTIFIER_DISTINCT)
                ->columns(array('member_id'))
                ->join(array('c' => 'clients'), 'c.member_id = m.member_id', $booSearchByCasesOnly ? [] : array('fileNumber', 'client_type_id'))
                ->join(array('mr' => 'members_relations'), 'mr.child_member_id = m.member_id', [], Select::JOIN_LEFT)
                ->join(
                    array('m2' => 'members'),
                    new PredicateExpression(sprintf('mr.parent_member_id = m2.member_id AND m2.userType IN (%s)', implode(Clients::getMemberType('individual')))),
                    $booSearchByCasesOnly ? [] : array('individual_member_id' => 'member_id', 'individual_first_name' => 'fName', 'individual_last_name' => 'lName'),
                    Select::JOIN_LEFT
                )
                ->where([
                    $oStructQuery,
                    'm.userType' => $this->_parent->getMemberTypeIdByName('case')
                ]);

            // Join divisions table only if it is used
            $booJoinedDivisionsTable = false;
            if ($booUseDivisionsTable) {
                $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
                $booJoinedDivisionsTable = true;
            }

            if ($booSearchByDependents) {
                $select->join(array('dep' => 'client_form_dependents'), 'dep.member_id = c.member_id', [], Select::JOIN_LEFT);
                $arrOrder[] = 'dep.lName';
            }
            $select->order($arrOrder);

            // Preload employer's info too, if module is enabled
            $booEmployersModuleEnabled = $this->_company->isEmployersModuleEnabledToCompany($companyId);
            if ($booEmployersModuleEnabled) {
                $select->join(
                    array('m3' => 'members'),
                    new PredicateExpression(sprintf('mr.parent_member_id = m3.member_id AND m3.userType IN (%s)', implode(Clients::getMemberType('employer')))),
                    $booSearchByCasesOnly ? [] : array('employer_member_id' => 'member_id', 'employer_first_name' => 'fName', 'employer_last_name' => 'lName'),
                    Select::JOIN_LEFT
                );
            }

            if (!empty($limit)) {
                $select->limit($limit);
            }


            $DOBFieldId = 0;

            // Check if at least one word is the "date" in 'Y-m-d' format
            $booIsAtLeastOneDateValue = false;
            foreach ($arrQueryWords as $word) {
                if ($this->_settings->isValidDate($word, 'Y-m-d')) {
                    $booIsAtLeastOneDateValue = true;
                    break;
                }
            }

            if (!$booSearchByCasesOnly && $booIsAtLeastOneDateValue) {
                // Search by DOB field if it exists in the company
                // We expect that it is assigned to the internal contact
                $arrInternalContactType = Members::getMemberType('internal_contact');

                $DOBFieldId = $this->_parent->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('DOB', $arrInternalContactType, $companyId);
                if (!empty($DOBFieldId)) {
                    $select->join(
                        array('mr4' => 'members_relations'),
                        'mr4.parent_member_id = m2.member_id',
                        [],
                        Select::JOIN_LEFT
                    );

                    $select->join(
                        array('m4' => 'members'),
                        new PredicateExpression(sprintf('mr4.child_member_id = m4.member_id AND m4.userType IN (%s)', implode($arrInternalContactType))),
                        [],
                        Select::JOIN_LEFT
                    );

                    $select->join(
                        array('afd4' => 'applicant_form_data'),
                        new PredicateExpression(sprintf('afd4.applicant_id = m4.member_id AND afd4.row = 0 AND afd4.applicant_field_id = %d', $DOBFieldId)),
                        [],
                        Select::JOIN_LEFT
                    );

                    if ($booEmployersModuleEnabled) {
                        $select->join(
                            array('mr5' => 'members_relations'),
                            'mr5.parent_member_id = m3.member_id',
                            [],
                            Select::JOIN_LEFT
                        );

                        $select->join(
                            array('m5' => 'members'),
                            new PredicateExpression(sprintf('mr5.child_member_id = m5.member_id AND m5.userType IN (%s)', implode($arrInternalContactType))),
                            [],
                            Select::JOIN_LEFT
                        );

                        $select->join(
                            array('afd5' => 'applicant_form_data'),
                            new PredicateExpression(sprintf('afd5.applicant_id = m5.member_id AND afd5.row = 0 AND afd5.applicant_field_id = %d', $DOBFieldId)),
                            [],
                            Select::JOIN_LEFT
                        );
                    }
                }
            }

            // Search by "case status" field - search for Active cases only
            if (!$booAllClients) {
                $clientStatusFieldId = $this->_parent->getFields()->getClientStatusFieldId($companyId);
                if (!empty($clientStatusFieldId)) {
                    $select
                        ->join(array('fd' => 'client_form_data'), 'fd.member_id = m.member_id', [], Select::JOIN_LEFT)
                        ->where
                        ->equalTo('fd.field_id', $clientStatusFieldId)
                        ->equalTo('fd.value', 'Active');
                }
            }

            // Search by the office(s)
            $arrOffices = $this->_parent->getDivisions();

            foreach ($arrQueryWords as $word) {
                $booIsDate     = $this->_settings->isValidDate($word, 'Y-m-d');
                $arrWhereQuery = (new Where())->nest();

                if (!$booIsDate) {
                    $arrWhereQuery->like('c.fileNumber', '%' . $word . '%');
                }

                // Search by the office(s)
                if (!empty($arrOffices)) {
                    $arrFoundDivisions = array();

                    foreach ($arrOffices as $arrOfficeInfo) {
                        if (empty($word)) {
                            continue;
                        }

                        if (mb_stripos($arrOfficeInfo['name'], $word) !== false) {
                            $arrFoundDivisions[] = $arrOfficeInfo['division_id'];
                        }
                    }

                    if (!empty($arrFoundDivisions)) {
                        // Join divisions table only if it is used
                        if (!$booJoinedDivisionsTable) {
                            $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
                            $booJoinedDivisionsTable = true;
                        }

                        $arrFoundDivisions = Settings::arrayUnique($arrFoundDivisions);
                        $arrWhereQuery->in('md.division_id', $arrFoundDivisions);
                    }
                }

                if (!$booSearchByCasesOnly) {
                    if ($booIsDate) {
                        // Do a search only if this is a date
                        if (!empty($DOBFieldId)) {
                            $arrWhereQuery->or->equalTo('afd4.value', $word);

                            if ($booEmployersModuleEnabled) {
                                $arrWhereQuery->or->equalTo('afd5.value', $word);
                            }
                        }
                    } else {
                        $arrWhereQuery->or->like('m2.fName', '%' . $word . '%');
                        $arrWhereQuery->or->like('m2.lName', '%' . $word . '%');
                        $arrWhereQuery->or->like('m2.emailAddress', '%' . $word . '%');

                        if ($booEmployersModuleEnabled) {
                            $arrWhereQuery->or->like('m3.fName', '%' . $word . '%');
                            $arrWhereQuery->or->like('m3.lName', '%' . $word . '%');
                            $arrWhereQuery->or->like('m3.emailAddress', '%' . $word . '%');
                        }
                    }
                }

                if ($booSearchByDependents) {
                    if ($booIsDate) {
                        $arrWhereQuery->or->like(new PredicateExpression('CAST(dep.DOB AS CHAR)'), '%' . $word . '%');
                        $arrWhereQuery->or->like(new PredicateExpression('CAST(dep.passport_date AS CHAR)'), '%' . $word . '%');
                    } else {
                        $arrWhereQuery->or->like('dep.fName', '%' . $word . '%');
                        $arrWhereQuery->or->like('dep.lName', '%' . $word . '%');
                        $arrWhereQuery->or->like('dep.city_of_residence', '%' . $word . '%');
                        $arrWhereQuery->or->like('dep.country_of_residence', '%' . $word . '%');
                        $arrWhereQuery->or->like('dep.passport_num', '%' . $word . '%');
                    }
                }

                $arrWhereQuery = $arrWhereQuery->unnest();
                $select->where([$arrWhereQuery]);
            }

            $arrFoundRecords = $booSearchByCasesOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);

            // Don't load the total count if this is not needed
            // because this is a slow query anyway
            $totalCount = count($arrFoundRecords);
            if ($booLoadTotalCount && !empty($limit) && $totalCount >= $limit) {
                $totalCount = $this->_db2->fetchResultsCount($select);
            }

            if (!$booSearchByCasesOnly) {
                foreach ($arrFoundRecords as $arrFoundRecord) {
                    // Generate name of the main parent (IA or Employer)
                    if (!empty($arrFoundRecord['individual_member_id'])) {
                        $arrApplicantInfo = array(
                            'fName' => $arrFoundRecord['individual_first_name'],
                            'lName' => $arrFoundRecord['individual_last_name'],
                        );
                    } else {
                        $arrApplicantInfo = array(
                            'fName' => $arrFoundRecord['employer_first_name'],
                            'lName' => $arrFoundRecord['employer_last_name'],
                        );
                    }
                    $arrApplicantInfo = $this->_parent->generateClientName($arrApplicantInfo);

                    $arrApplicantFullInfo = array(
                        'user_id'        => empty($arrFoundRecord['employer_member_id']) ? $arrFoundRecord['individual_member_id'] : $arrFoundRecord['employer_member_id'],
                        'user_name'      => $arrApplicantInfo['full_name'],
                        'user_type'      => empty($arrFoundRecord['employer_member_id']) ? 'individual' : 'employer',
                        'case_type_id'   => $arrFoundRecord['client_type_id'],
                        'applicant_id'   => $arrFoundRecord['member_id'],
                        'applicant_name' => $arrFoundRecord['fileNumber'],
                        'applicant_type' => 'case'
                    );


                    // There is Employer for this case
                    if (!empty($arrFoundRecord['employer_member_id'])) {
                        $arrApplicantInfo = array(
                            'fName' => $arrFoundRecord['employer_first_name'],
                            'lName' => $arrFoundRecord['individual_last_name'],
                        );
                        $arrApplicantInfo = $this->_parent->generateClientName($arrApplicantInfo);

                        $arrApplicantFullInfo['user_parent_id']   = $arrFoundRecord['employer_member_id'];
                        $arrApplicantFullInfo['user_parent_name'] = $arrApplicantInfo['full_name'];
                    }

                    $arrApplicants[] = $arrApplicantFullInfo;
                }
            } else {
                $arrApplicants = Settings::arrayUnique($arrFoundRecords);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrApplicants, $totalCount);
    }

    /**
     * Parse string, split by words, use only first 3
     *
     * @param string $searchQuery
     * @return array
     */
    public function getSearchStringExploded($searchQuery)
    {
        $arrExploded = array();

        $searchQuery = trim($searchQuery);
        if ($searchQuery != '') {
            if (preg_match('/^\"(.*)\"$/', $searchQuery, $regs)) {
                // A "text phrase" was entered - count it as one word, search as entered
                $arrExploded = array($regs[1]);
            } else {
                $searchQuery = str_replace([',', ';'], ' ', $searchQuery);
                $searchQuery = preg_replace('/[\s]+/mu', ' ', $searchQuery);
                $arrExploded = explode(' ', $searchQuery);
                $arrExploded = Settings::arrayUnique($arrExploded);
                $arrExploded = array_slice($arrExploded, 0, 5);
            }
        }

        return $arrExploded;
    }


    public function runSearch($arrQueryWords, $booAllClients = false)
    {
        $arrGroupedIdsByWords = array();
        if (empty($arrQueryWords)) {
            return $arrGroupedIdsByWords;
        }

        // Search in dynamic fields
        $companyId                  = $this->_auth->getCurrentUserCompanyId();
        $arrAllowedFieldIds         = $this->_parent->getFields()->getUserAllowedFieldIds($companyId);
        $arrSearchAllowedFieldTypes = $this->getSearchAllowedTypes();

        if (!empty($arrAllowedFieldIds) && !empty($arrSearchAllowedFieldTypes)) {
            $arrCompanyFields = $this->_parent->getFields()->getCompanyFields($companyId);

            $arrGroupedFieldsIds  = array();
            $arrFieldTypesToGroup = array(
                'country'           => $this->_parent->getFieldTypes()->getFieldTypeId('country'),
                'assigned_to'       => $this->_parent->getFieldTypes()->getFieldTypeId('assigned_to'),
                'agents'            => $this->_parent->getFieldTypes()->getFieldTypeId('agents'),
                'active_users'      => $this->_parent->getFieldTypes()->getFieldTypeId('active_users'),
                'authorized_agents' => $this->_parent->getFieldTypes()->getFieldTypeId('authorized_agents'),
                'categories'        => $this->_parent->getFieldTypes()->getFieldTypeId('categories'),
                'case_status'       => $this->_parent->getFieldTypes()->getFieldTypeId('case_status'),
            );

            // Search if "custom" fields are used by the company
            foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                if (!in_array($arrCompanyFieldInfo['field_id'], $arrAllowedFieldIds)) {
                    continue;
                }

                foreach ($arrFieldTypesToGroup as $textFieldId => $fieldTypeId) {
                    if ($arrCompanyFieldInfo['type'] == $fieldTypeId) {
                        $arrGroupedFieldsIds[$textFieldId][] = $arrCompanyFieldInfo['field_id'];
                    }
                }
            }

            /** @var Users $oUsers */
            $oUsers = $this->_serviceContainer->get(Users::class);

            // Load the list of possible options for the "Assigned To" field
            $arrOptionsForCustomFields = array();
            if (isset($arrGroupedFieldsIds['assigned_to']) && !empty($arrGroupedFieldsIds['assigned_to'])) {
                $arrOptionsForCustomFields['assigned_to'] = $oUsers->getAssignList('search', null, true);
            }

            // Load the list of possible options for the "Agents" field
            if (isset($arrGroupedFieldsIds['agents']) && !empty($arrGroupedFieldsIds['agents'])) {
                $arrAgents = $this->_parent->getAgents();

                $arrOptionsForCustomFields['agents'] = array();
                if (is_array($arrAgents) && count($arrAgents) > 0) {
                    foreach ($arrAgents as $agentInfo) {
                        $arrOptionsForCustomFields['agents'][] = array(
                            'option_id'   => $agentInfo['agent_id'],
                            'option_name' => $this->_parent->generateAgentName($agentInfo, false)
                        );
                    }
                }
            }

            // Load the list of possible options for the "Active Users" field
            if (isset($arrGroupedFieldsIds['active_users']) && !empty($arrGroupedFieldsIds['active_users'])) {
                $arrOptionsForCustomFields['active_users'] = $oUsers->getAssignedToUsers(false, null, 0, true);
            }

            // Load the list of possible options for the "Authorized Agents" field
            if (isset($arrGroupedFieldsIds['authorized_agents']) && !empty($arrGroupedFieldsIds['authorized_agents'])) {
                $arrOptionsForCustomFields['authorized_agents'] = array();

                $arrCompanyDivisionsGroups = $this->_company->getCompanyDivisions()->getDivisionsGroups($companyId);
                foreach ($arrCompanyDivisionsGroups as $arrCompanyDivisionsGroupInfo) {
                    $arrGroupDivisions = $this->_company->getCompanyDivisions()->getDivisionsByGroupId($arrCompanyDivisionsGroupInfo['division_group_id']);
                    if (!empty($arrCompanyDivisionsGroupInfo['division_group_company']) && !empty($arrGroupDivisions)) {
                        $arrOptionsForCustomFields['authorized_agents'][] = array(
                            'option_id'   => $arrGroupDivisions,
                            'option_name' => $arrCompanyDivisionsGroupInfo['division_group_company']
                        );
                    }
                }
            }

            // Load the list of possible options for the "Categories" (Subclasses) field
            if (isset($arrGroupedFieldsIds['categories']) && !empty($arrGroupedFieldsIds['categories'])) {
                $arrOptionsForCustomFields['categories'] = $this->_parent->getCaseCategories()->getCompanyCaseCategories($companyId);
            }

            if (isset($arrGroupedFieldsIds['case_status']) && !empty($arrGroupedFieldsIds['case_status'])) {
                $arrOptionsForCustomFields['case_status'] = $this->_parent->getCaseStatuses()->getCompanyCaseStatuses($companyId);
            }

            $arrAllowedCasesIds       = $this->_parent->getMembersWhichICanAccess($this->_parent::getMemberType('case'));
            $booHasAccessToDependants = !empty($this->_parent->getFields()->getAccessToDependants());
            foreach ($arrQueryWords as $word) {
                // Search by static fields
                list($arrStaticIds,) = $this->runQuickSearchByStaticFields(array($word), 0, true, $booHasAccessToDependants, $booAllClients, false);
                $arrStaticIds = empty($arrStaticIds) ? array() : $arrStaticIds;

                $arrWhereQuery = (new Where())->nest();

                // Search by comboboxes/radios
                $select = (new Select())
                    ->from(array('fd' => 'client_form_default'))
                    ->columns(array('field_id', 'form_default_id'))
                    ->where(
                        [
                            (new Where())->like('fd.value', '%' . $word . '%')
                        ]
                    )
                    ->where(
                        [
                            'fd.field_id' => $arrAllowedFieldIds
                        ]
                    );

                $arrComboValues = $this->_db2->fetchAll($select);

                $arrComboFieldsIds = array();
                if (is_array($arrComboValues) && count($arrComboValues)) {
                    foreach ($arrComboValues as $arrComboInfo) {
                        $arrWhereQuery
                            ->or
                            ->nest()
                            ->equalTo('fd.field_id', $arrComboInfo['field_id'])
                            ->and
                            ->like('fd.value', '%' . $arrComboInfo['form_default_id'] . '%')
                            ->unnest();

                        $arrComboFieldsIds[] = $arrComboInfo['field_id'];
                    }
                }

                // Search by countries
                if (isset($arrGroupedFieldsIds['country']) && !empty($arrGroupedFieldsIds['country'])) {
                    $select = (new Select())
                        ->from(array('c' => 'country_master'))
                        ->columns(array('countries_id'))
                        ->where(
                            [
                                (new Where())
                                    ->like('c.countries_name', '%' . $word . '%')
                                    ->equalTo('c.type', 'general')
                            ]
                        );

                    $arrFoundCountriesIds = $this->_db2->fetchCol($select);

                    if (!empty($arrFoundCountriesIds)) {
                        foreach ($arrGroupedFieldsIds['country'] as $countryFieldId) {
                            if (!is_array($arrFoundCountriesIds)) {
                                $arrFoundCountriesIds = [$arrFoundCountriesIds];
                            }

                            $arrWhereQuery
                                ->or
                                ->nest()
                                ->equalTo('fd.field_id', (int)$countryFieldId)
                                ->and
                                ->in('fd.value', $arrFoundCountriesIds)
                                ->unnest();
                        }
                    }
                }

                // Search by "Assigned To" staff
                if (isset($arrGroupedFieldsIds['assigned_to']) && !empty($arrGroupedFieldsIds['assigned_to'])) {
                    $arrFoundAssignedTo = array();
                    foreach ($arrOptionsForCustomFields['assigned_to'] as $arrAssignedToInfo) {
                        if (empty($word)) {
                            continue;
                        }

                        if (mb_stripos($arrAssignedToInfo['assign_to_name'], $word) !== false) {
                            $arrFoundAssignedTo[] = $arrAssignedToInfo['assign_to_id'];
                        }
                    }

                    if (!empty($arrFoundAssignedTo)) {
                        foreach ($arrGroupedFieldsIds['assigned_to'] as $assignedToFieldId) {
                            if (!is_array($arrFoundAssignedTo)) {
                                $arrFoundAssignedTo = [$arrFoundAssignedTo];
                            }
                            $arrWhereQuery
                                ->or
                                ->nest()
                                ->equalTo('fd.field_id', (int)$assignedToFieldId)
                                ->and
                                ->in('fd.value', $arrFoundAssignedTo)
                                ->unnest();
                        }
                    }
                }

                // Search by "Agents" - only when we have access to such field
                if (isset($arrGroupedFieldsIds['agents']) && !empty($arrGroupedFieldsIds['agents'])) {
                    $arrFoundAgents = array();
                    foreach ($arrOptionsForCustomFields['agents'] as $arrAssignedToInfo) {
                        if (empty($word)) {
                            continue;
                        }

                        if (mb_stripos($arrAssignedToInfo['option_name'], $word) !== false) {
                            $arrFoundAgents[] = $arrAssignedToInfo['option_id'];
                        }
                    }

                    if (!empty($arrFoundAgents)) {
                        if (!is_array($arrFoundAgents)) {
                            $arrFoundAgents = [$arrFoundAgents];
                        }

                        $select = (new Select())
                            ->from(array('c' => 'clients'))
                            ->columns(array('member_id'))
                            ->where([(new Where())->in('c.agent_id', $arrFoundAgents)]);

                        $arrAgentsCasesIds = $this->_db2->fetchCol($select);
                        if (!empty($arrAgentsCasesIds)) {
                            $arrWhereQuery
                                ->or
                                ->in('fd.member_id', Settings::arrayUnique($arrAgentsCasesIds));
                        }
                    }
                }

                // Search by "Active Users"
                if (isset($arrGroupedFieldsIds['active_users']) && !empty($arrGroupedFieldsIds['active_users'])) {
                    $arrFoundActiveUsers = array();
                    foreach ($arrOptionsForCustomFields['active_users'] as $arrActiveUserInfo) {
                        if (empty($word)) {
                            continue;
                        }

                        if (mb_stripos($arrActiveUserInfo['option_name'], $word) !== false) {
                            $arrFoundActiveUsers[] = $arrActiveUserInfo['option_id'];
                        }
                    }

                    if (!empty($arrFoundActiveUsers)) {
                        foreach ($arrGroupedFieldsIds['active_users'] as $activeUserFieldId) {
                            if (!is_array($arrFoundActiveUsers)) {
                                $arrFoundActiveUsers = [$arrFoundActiveUsers];
                            }

                            $arrWhereQuery
                                ->or
                                ->nest()
                                ->equalTo('fd.field_id', (int)$activeUserFieldId)
                                ->and
                                ->in('fd.value', $arrFoundActiveUsers)
                                ->unnest();
                        }
                    }
                }

                // Search by "Authorized Agent"
                if (isset($arrGroupedFieldsIds['authorized_agents']) && !empty($arrGroupedFieldsIds['authorized_agents'])) {
                    $arrFoundAuthorizedAgents = array();
                    foreach ($arrOptionsForCustomFields['authorized_agents'] as $arrAuthorizedAgentInfo) {
                        if (empty($word)) {
                            continue;
                        }

                        if (mb_stripos($arrAuthorizedAgentInfo['option_name'], $word) !== false) {
                            $arrFoundAuthorizedAgents = array_merge($arrFoundAuthorizedAgents, $arrAuthorizedAgentInfo['option_id']);
                        }
                    }

                    if (!empty($arrFoundAuthorizedAgents)) {
                        $select = (new Select())
                            ->from(array('md' => 'members_divisions'))
                            ->columns(array('member_id'))
                            ->where(
                                [
                                    (new Where())
                                        ->in('md.division_id', $arrFoundAuthorizedAgents)
                                        ->equalTo('md.type', 'access_to')
                                ]
                            );

                        $arrAuthorizedAgentsCasesIds = $this->_db2->fetchCol($select);

                        if (!empty($arrAuthorizedAgentsCasesIds)) {
                            $arrWhereQuery
                                ->or
                                ->in('fd.member_id', Settings::arrayUnique($arrAuthorizedAgentsCasesIds));
                        }
                    }
                }

                // Search by "Categories" (Subclasses)
                if (isset($arrGroupedFieldsIds['categories']) && !empty($arrGroupedFieldsIds['categories'])) {
                    $arrFoundCategories = array();
                    foreach ($arrOptionsForCustomFields['categories'] as $arrActiveUserInfo) {
                        if (empty($word)) {
                            continue;
                        }

                        if (mb_stripos($arrActiveUserInfo['client_category_name'], $word) !== false) {
                            $arrFoundCategories[] = $arrActiveUserInfo['client_category_id'];
                        }
                    }

                    if (!empty($arrFoundCategories)) {
                        foreach ($arrGroupedFieldsIds['categories'] as $categoriesFieldId) {
                            if (!is_array($arrFoundCategories)) {
                                $arrFoundCategories= [$arrFoundCategories];
                            }

                            $arrWhereQuery
                                ->or
                                ->nest()
                                ->equalTo('fd.field_id', $categoriesFieldId)
                                ->and
                                ->in('fd.value', $arrFoundCategories)
                                ->unnest();
                        }
                    }
                }

                // Search by "Case Statuses"
                if (isset($arrGroupedFieldsIds['case_status']) && !empty($arrGroupedFieldsIds['case_status'])) {
                    $arrFoundCaseStatuses = array();
                    foreach ($arrOptionsForCustomFields['case_status'] as $arrCaseStatus) {
                        if (empty($word)) {
                            continue;
                        }

                        if (mb_stripos($arrCaseStatus['client_status_name'], $word) !== false) {
                            $arrFoundCaseStatuses[] = $arrCaseStatus['client_status_id'];
                        }
                    }

                    if (!empty($arrFoundCaseStatuses)) {
                        foreach ($arrGroupedFieldsIds['case_status'] as $caseStatusFieldId) {
                            if (!is_array($arrFoundCaseStatuses)) {
                                $arrFoundCaseStatuses= [$arrFoundCaseStatuses];
                            }

                            if ($this->_config['site_version']['case_status_field_multiselect']) {
                                // multiple combo
                                $arrWhereQuery
                                    ->or
                                    ->nest()
                                    ->equalTo('fd.field_id', $caseStatusFieldId)
                                    ->and
                                    ->expression('fd.value REGEXP ?', '(^|,)(' . implode('|', $arrFoundCaseStatuses) . ')(,|$)')
                                    ->unnest();
                            } else {
                                // regular combo
                                $arrWhereQuery
                                    ->or
                                    ->nest()
                                    ->equalTo('fd.field_id', $caseStatusFieldId)
                                    ->and
                                    ->in('fd.value', $arrFoundCaseStatuses)
                                    ->unnest();
                            }
                        }
                    }
                }

                // Search by the text, don't search by fields that we already checked
                $searchByFields = array_diff($arrAllowedFieldIds, $arrComboFieldsIds);
                foreach ($arrGroupedFieldsIds as $arrFieldsToSkip) {
                    $searchByFields = array_diff($arrAllowedFieldIds, $arrFieldsToSkip);
                }

                $arrWhereQuery
                    ->or
                    ->nest()
                    ->in('fd.field_id', $searchByFields)
                    ->and
                    ->like('fd.value', '%' . $word . '%')
                    ->unnest();

                $select = (new Select())
                    ->from(array('fd' => 'client_form_data'))
                    ->columns(['member_id'])
                    ->join(array('f' => 'client_form_fields'), 'f.field_id = fd.field_id', [], Select::JOIN_LEFT)
                    ->where(
                        [
                            $arrWhereQuery,
                            'f.encrypted' => 'N'
                        ]
                    );
                $select->where(
                    [
                        'f.type' => $arrSearchAllowedFieldTypes
                    ]
                );

                $arrDynamicResult = $this->_db2->fetchCol($select);
                $arrDynamicResult = array_map('intval', $arrDynamicResult);
                $arrDynamicResult = empty($arrDynamicResult) ? array() : Settings::arrayUnique($arrDynamicResult);
                $arrDynamicResult = array_intersect($arrDynamicResult, $arrAllowedCasesIds);

                $arrGroupedIdsByWords[$word] = array_merge($arrStaticIds, $arrDynamicResult);
                $arrGroupedIdsByWords[$word] = Settings::arrayUnique($arrGroupedIdsByWords[$word]);

                // Search only among active clients
                if (!empty($arrGroupedIdsByWords[$word])) {
                    $arrGroupedIdsByWords[$word] = $this->_parent->getActiveClientsList($arrGroupedIdsByWords[$word], true, 0, $booAllClients);
                }
            }
        }

        return $arrGroupedIdsByWords;
    }

    /**
     * Create new searches for new companies (copies of searches for company 0)
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @param array $arrMappingCaseGroupsAndFields
     * @param array $arrMappingClientGroupsAndFields
     * @param array $arrMappingCategories
     */
    public function createDefaultSearches($fromCompanyId, $toCompanyId, $arrMappingCaseGroupsAndFields, $arrMappingClientGroupsAndFields, $arrMappingCategories, $arrMappingCaseStatuses)
    {
        //get default searches
        $fromCompanyId      = is_null($fromCompanyId) ? 0 : $fromCompanyId;
        $toCompanyId        = empty($toCompanyId) ? 0 : $toCompanyId;
        $arrDefaultSearches = $this->getCompanySearches($fromCompanyId);

        if ($arrDefaultSearches) {
            // Load fields list - will be used later to identify which mapping array we need to use
            $arrCaseFields         = $this->_parent->getFields()->getCompanyFields($fromCompanyId);
            $arrClientFields       = $this->_parent->getApplicantFields()->getCompanyAllFields($fromCompanyId);
            $categoriesFieldTypeId = $this->_parent->getFieldTypes()->getFieldTypeId('categories');
            $caseStatusFieldTypeId = $this->_parent->getFieldTypes()->getFieldTypeId('case_status');

            // Check/parse each query and update options if needed
            foreach ($arrDefaultSearches as $defaultSearchInfo) {
                $defaultSearchInfo['search_id']  = 0;
                $defaultSearchInfo['company_id'] = $toCompanyId;

                $booUpdate = false;

                try {
                    /** @var array $arrQuery */
                    $arrQuery = Json::decode($defaultSearchInfo['query'], Json::TYPE_ARRAY);
                } catch (Exception $e) {
                    $arrQuery = [];
                }

                if (!empty($arrQuery)) {
                    for ($i = 0; $i <= $arrQuery['max_rows_count']; $i++) {
                        if (array_key_exists('field_client_type_' . $i, $arrQuery) && array_key_exists('option_' . $i, $arrQuery) && is_numeric($arrQuery['option_' . $i])) {
                            $arrToCheck = array();
                            switch ($arrQuery['field_client_type_' . $i]) {
                                case 'case':
                                    foreach ($arrCaseFields as $arrCaseFieldInfo) {
                                        if ($arrCaseFieldInfo['company_field_id'] == $arrQuery['field_' . $i]) {
                                            switch ($arrCaseFieldInfo['type']) {
                                                case $categoriesFieldTypeId:
                                                    $arrToCheck = $arrMappingCategories;
                                                    break;

                                                case $caseStatusFieldTypeId:
                                                    $arrToCheck = $arrMappingCaseStatuses;
                                                    break;

                                                default:
                                                    $arrToCheck = $arrMappingCaseGroupsAndFields['mappingDefaults'];
                                                    break;
                                            }
                                            break;
                                        }
                                    }
                                    break;

                                default:
                                    foreach ($arrClientFields as $arrClientFieldInfo) {
                                        if ($arrClientFieldInfo['applicant_field_unique_id'] == $arrQuery['field_' . $i]) {
                                            switch ($arrClientFieldInfo['type']) {
                                                case 'categories':
                                                    $arrToCheck = $arrMappingCategories;
                                                    break;

                                                case 'case_status':
                                                    $arrToCheck = $arrMappingCaseStatuses;
                                                    break;

                                                default:
                                                    $arrToCheck = $arrMappingClientGroupsAndFields['mappingDefaults'];
                                                    break;
                                            }
                                            break;
                                        }
                                    }
                                    break;
                            }

                            if (array_key_exists($arrQuery['option_' . $i], $arrToCheck)) {
                                $arrQuery['option_' . $i] = $arrToCheck[$arrQuery['option_' . $i]];
                                $booUpdate                = true;
                            }
                        }
                    }

                    if ($booUpdate) {
                        $defaultSearchInfo['query'] = Json::encode($arrQuery);
                    }

                    //save
                    $this->_db2->insert('searches', $defaultSearchInfo);
                }
            }
        }
    }

    public function advancedSearchProcess($form)
    {
        $strMessage       = '';
        $arrResultMembers = array();

        try {
            $companyId                       = $this->_auth->getCurrentUserCompanyId();
            $booActiveClients                = (isset($form['active-clients']) && $form['active-clients'] == 'on');
            $now                             = date('Y-m-d H:i:s'); //PHP Date Stamp
            $arrCasesIds                     = $form['all-cases'];
            $specialFields                   = array();
            $booLoadDivisions                = false;
            $booLoadCompletedForms           = false;
            $booLoadClientsUploadedDocuments = false;
            $booLoadClientsHavePaymentsDue   = false;
            $booLoadTAInfo                   = false;
            $arrCompanyFields                = $this->_parent->getFields()->getCompanyFields($companyId);

            $arrEncryptedCompanyFieldIds = array();
            foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                if ($arrCompanyFieldInfo['encrypted'] == 'Y') {
                    $arrEncryptedCompanyFieldIds[] = $arrCompanyFieldInfo['field_id'];
                }
            }

            for ($i = 0; $i < $form['cntr']; $i++) {
                //0 - field id and 1 - field type
                if (!isset($form['srchField-' . $i]) || empty($form['srchField-' . $i])) {
                    continue;
                }

                $srchField = explode('|', $form['srchField-' . $i] ?? '');

                switch ($srchField[0]) {
                    case 'division':
                        $booLoadDivisions = true;
                        break;

                    case 'clients_uploaded_documents':
                        $booLoadClientsUploadedDocuments = true;
                        break;

                    case 'clients_have_payments_due':
                        $booLoadClientsHavePaymentsDue = true;
                        break;

                    case 'clients_completed_forms':
                        $booLoadCompletedForms = true;
                        break;

                    case 'ob_total':
                    case 'ta_total':
                        $booLoadTAInfo = true;
                        break;

                    default:
                        break;
                }
            }

            if ($booLoadTAInfo) {
                // Load outstanding balance, a/c balance - if used in "search row" OR there is a column
                $arrMembersTa = $this->_parent->getAccounting()->getMembersTA($arrCasesIds);
                foreach ($arrCasesIds as $caseId) {
                    $specialFields[$caseId]['outstanding_balance_primary']     = 0;
                    $specialFields[$caseId]['outstanding_balance_secondary']   = 0;
                    $specialFields[$caseId]['trust_account_summary_primary']   = 0;
                    $specialFields[$caseId]['trust_account_summary_secondary'] = 0;

                    if (isset($arrMembersTa[$caseId])) {
                        foreach ($arrMembersTa[$caseId] as $arrMemberTA) {
                            $booPrimaryTA = empty($arrMemberTA['order']);
                            $obKey        = $booPrimaryTA ? 'outstanding_balance_primary' : 'outstanding_balance_secondary';
                            $stKey        = $booPrimaryTA ? 'trust_account_summary_primary' : 'trust_account_summary_secondary';

                            $specialFields[$caseId][$obKey] = max($arrMemberTA['outstanding_balance'], 0);
                            $specialFields[$caseId][$stKey] = max($arrMemberTA['sub_total'], 0);
                        }
                    }
                }
            }

            if ($booLoadCompletedForms || $booLoadClientsUploadedDocuments || $booLoadClientsHavePaymentsDue) {
                $arrMemberTasks = $this->_parent->getTasks()->getTasksForMember([
                    'companyId'             => $companyId,
                    'memberId'              => $this->_auth->getCurrentUserId(),
                    'memberOffices'         => $this->_parent->getMembersDivisions([$this->_auth->getCurrentUserId()]),
                    'booLoadAccessToMember' => true,
                ], false);

                if ($booLoadCompletedForms && !empty($arrMemberTasks['tasks_completed_form'])) {
                    foreach ($arrMemberTasks['tasks_completed_form'] as $arrTaskInfo) {
                        $specialFields[$arrTaskInfo['member_id']]['clients_completed_forms'] = 1;
                    }
                }

                if ($booLoadClientsUploadedDocuments && !empty($arrMemberTasks['tasks_uploaded_docs'])) {
                    foreach ($arrMemberTasks['tasks_uploaded_docs'] as $arrTaskInfo) {
                        $specialFields[$arrTaskInfo['member_id']]['clients_uploaded_documents'] = 1;
                    }
                }

                if ($booLoadClientsHavePaymentsDue && !empty($arrMemberTasks['tasks_payment_due'])) {
                    foreach ($arrMemberTasks['tasks_payment_due'] as $arrTaskInfo) {
                        $specialFields[$arrTaskInfo['member_id']]['clients_have_payments_due'] = 1;
                    }
                }
            }

            // Load clients divisions only if this is needed - if used in "search row" OR there is a column
            if ($booLoadDivisions) {
                $arrClientsDivision = $this->_parent->getClientsDivision($arrCasesIds);
                $arrClientsDivision = $this->_settings::arrayColumnAsKey('member_id', $arrClientsDivision);
                foreach ($arrClientsDivision as $client_id => $division) {
                    $specialFields[$client_id]['division']    = $division['name'];
                    $specialFields[$client_id]['division_id'] = (int)$division['division_id'];
                }
            }

            $cols                     = 0;
            $dateFormatFull           = $this->_settings->variableGet('dateFormatFull');
            $booHasAccessToDependants = !empty($this->_parent->getFields()->getAccessToDependants());

            for ($i = 0; $i < $form['cntr']; $i++) {
                //0 - field id and 1 - field type
                if (!isset($form['srchField-' . $i]) || empty($form['srchField-' . $i])) {
                    continue;
                }

                $srchField = explode('|', $form['srchField-' . $i] ?? '');
                ++$cols;

                //field id & field type
                $companyFieldId = $srchField[0];
                $field_type     = (int)$srchField[1];

                // Get condition and values
                $searchCondition     = '';
                $searchText          = '';
                $srchTxtTo           = '';
                $searchListValue     = '';
                $srchNextNum         = '';
                $srchNextPeriod      = '';
                $booSpecialFields    = false;
                $booConvertTextValue = true;
                $booIsDateField      = false;

                if ($field_type == $this->_parent->getFieldTypes()->getFieldTypeId('case_status')) {
                    $field_type = $this->_config['site_version']['case_status_field_multiselect'] ? 'multiple_combo' : 'combo';
                    $field_type = $this->_parent->getFieldTypes()->getFieldTypeId($field_type);
                }

                switch ($field_type) {
                    case $this->_parent->getFieldTypes()->getFieldTypeId('combo'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('staff_responsible_rma'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('active_users'):
                        $searchCondition = $form['srchComboCondition-' . $i . '-' . $companyFieldId] ?? $form['srcTxtCondition-' . $i];
                        $searchText      = $form['srchComboList-' . $i . '-' . $companyFieldId] ?? $form['txtSrchClient-' . $i];
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('multiple_combo'):
                        $searchCondition = $form['srchComboCondition-' . $i . '-' . $companyFieldId] ?? $form['srcTxtCondition-' . $i];

                        if (in_array($searchCondition, array("=", "is", "equal", "is_one_of", "is one of"))) {
                            $searchCondition = 'is_one_of_multi';
                        } elseif (in_array($searchCondition, array("<>", "is not", "is_not", "not_equal", "is_none_of", "is none of"))) {
                            $searchCondition = 'is_none_of_multi';
                        }

                        $searchText = $form['srchComboList-' . $i . '-' . $companyFieldId] ?? $form['txtSrchClient-' . $i];
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('country'):
                        $searchCondition = $form['srchCountryConditions-' . $i];
                        $searchText      = $form['srchCountryList-' . $i];
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('number'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('auto_calculated'):
                        $searchCondition     = $form['srchNumConditions-' . $i];
                        $searchText          = (float)$form['txtSrchNum-' . $i];
                        $booConvertTextValue = false;
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('date'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('date_repeatable'):
                        $searchCondition = $form['srchDateCondition-' . $i];
                        $searchText      = $this->_settings->reformatDate($form['txtSrchDate-' . $i], $dateFormatFull, Settings::DATE_UNIX);
                        $srchTxtTo       = $this->_settings->reformatDate($form['txtSrchDateTo-' . $i], $dateFormatFull, Settings::DATE_UNIX);
                        $srchNextNum     = (int)$form['txtNextNum-' . $i];
                        $srchNextPeriod  = $form['txtNextPeriod-' . $i];
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('agents'):
                        $searchText      = $form['srchAgentList-' . $i];
                        $searchCondition = $form['srchAgentConditions-' . $i];
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('office'):
                        $searchText       = $form['txtSrchDivision-' . $i];
                        $searchListValue  = (int)$form['srchDivisionList-' . $i];
                        $searchCondition  = $form['srchDivisionConditions-' . $i];
                        $booSpecialFields = true;
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('applicant_internal_id'):
                        $searchCondition  = $form['srcTxtCondition-' . $i];
                        $searchText       = array_key_exists('txtSrchClient-' . $i, $form) ? $form['txtSrchClient-' . $i] : '';
                        $booSpecialFields = true;
                        break;

                    case $this->_parent->getFieldTypes()->getFieldTypeId('assigned_to'):
                        $searchText      = $form['srchStaffList-' . $i];
                        $searchCondition = $form['srchStaffConditions-' . $i];
                        break;

                    case 0 : //OB & TA Summary
                        $booSpecialFields = true;
                        break;

                    default : //text field
                        $searchCondition = $form['srcTxtCondition-' . $i];
                        $searchText      = array_key_exists('txtSrchClient-' . $i, $form) ? $form['txtSrchClient-' . $i] : '';
                        break;
                }


                $searchText      = $booConvertTextValue ? sprintf("%s", $searchText) : $searchText;
                $srchTxtTo       = sprintf("%s", $srchTxtTo);
                $srchNextNum     = sprintf("%s", $srchNextNum);
                $srchNextPeriod  = sprintf("%s", $srchNextPeriod);
                $searchCondition = stripslashes($searchCondition);

                $searchDbRow             = '';
                $nonEmptyMembers         = null;
                $isExcept                = false;
                $booJoinClientsTable     = false;
                $booJoinClientsDataTable = false;

                $searchQuery    = new Where();
                $searchCriteria = new Where();
                if (!$booSpecialFields) {
                    //static or dynamic field
                    $booIsDynamicField = !$this->_parent->getFields()->isStaticField($companyFieldId);

                    if ($booIsDynamicField) {
                        $arrFieldInfo = $this->_parent->getFields()->getCompanyFieldInfoByUniqueFieldId($companyFieldId);
                        if (isset($arrFieldInfo['type']) && $arrFieldInfo['type'] == $this->_parent->getFieldTypes()->getFieldTypeId('authorized_agents')) {
                            $booIsDynamicField = false;
                            $companyFieldId    = 'authorized_agents';
                        }
                    }

                    if ($booIsDynamicField) {
                        // dynamic field
                        $fieldId = $this->_parent->getFields()->getCompanyFieldIdByUniqueFieldId($companyFieldId, $companyId);

                        // If field was saved in advanced search, but was deleted later - skip this field
                        // Also, don't run search on encrypted fields
                        if (empty($fieldId) || in_array($fieldId, $arrEncryptedCompanyFieldIds)) {
                            continue;
                        }

                        $searchQuery->equalTo('fd.field_id', $fieldId);

                        //repeatable date
                        if ($field_type == $this->_parent->getFieldTypes()->getFieldTypeId('date_repeatable')) {
                            $searchDbRow = new Expression("CONVERT(CONCAT('" . date('Y') . "', '-', MONTH(fd.value), '-', DAY(fd.value)), DATE)");
                        } else {
                            $searchDbRow = "fd.value";
                        }
                        $booJoinClientsDataTable = true;
                    } else {
                        // static field
                        switch ($companyFieldId) {
                            case 'file_number':
                                $booJoinClientsTable = true;
                                $searchDbRow         = 'c.fileNumber';
                                break;

                            case 'case_type':
                                $booJoinClientsTable = true;
                                $searchDbRow         = 'c.client_type_id';
                                break;

                            case 'case_internal_id':
                                $booJoinClientsTable = true;
                                $searchDbRow         = 'c.member_id';
                                break;

                            case 'agent':
                                $booJoinClientsTable = true;
                                $searchDbRow         = 'c.agent_id';
                                break;

                            case 'authorized_agents':
                                $searchDbRow = 'm.division_group_id';
                                break;

                            default:
                                $searchDbRow = 'm.' . $this->_parent->getFields()->getStaticColumnName($companyFieldId);
                                if ($companyFieldId == 'created_on') {
                                    $searchDbRow    = new Expression("DATE(FROM_UNIXTIME($searchDbRow))");
                                    $booIsDateField = true;
                                }
                                break;
                        }
                    }

                    //except criteria
                    $isExcept = in_array($searchCondition, array("<>", "is not", 'is_not', 'not_equal', "is none of", "is_none_of", "does not contain", 'does_not_contain', "is empty", 'is_empty', 'is_none_of_multi')) || (in_array($searchCondition, array("=", "is", "contains")) && empty($searchText));
                    if ($booIsDynamicField && $isExcept) {
                        $select = (new Select())
                            ->from(array('cd' => 'client_form_data'))
                            ->columns(['member_id'])
                            ->join(array('cf' => 'client_form_fields'), 'cf.field_id = cd.field_id', [], Select::JOIN_LEFT)
                            ->where(
                                [
                                    'cf.company_field_id' => $companyFieldId,
                                    'cf.company_id'       => $companyId
                                ]
                            );

                        $nonEmptyMembers = $this->_db2->fetchCol($select);
                        $nonEmptyMembers = array_map('intval', $nonEmptyMembers);
                    }
                }

                if ($booSpecialFields) {
                    //Special fields conditions or Divisions (Offices)
                    $membersArr = array();
                    foreach ($arrCasesIds as $caseId) {
                        switch ($companyFieldId) {
                            case 'division' :
                                switch ($searchCondition) {
                                    case "is" :
                                    default :
                                        if ($searchListValue == $specialFields[$caseId]['division_id']) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "is one of" :
                                    case "is_one_of" :
                                        $arrOptionIds = explode(";", $searchListValue ?? '');
                                        if (in_array($specialFields[$caseId]['division_id'], $arrOptionIds)) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "is not" :
                                    case "is_not" :
                                        if ($searchListValue != $specialFields[$caseId]['division_id']) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "is none of" :
                                    case "is_none_of" :
                                        $arrOptionIds = explode(";", $searchListValue ?? '');
                                        if (!in_array($specialFields[$caseId]['division_id'], $arrOptionIds)) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "contains" :
                                        if (stripos($specialFields[$caseId]['division'] ?? '', $searchText) !== false) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;
                                }
                                break;

                            case 'applicant_internal_id' :
                                $arrParents          = $this->_parent->getParentsForAssignedApplicants(array($caseId), false, false);
                                $applicantInternalId = '';
                                foreach ($arrParents as $arrParentInfo) {
                                    $applicantInternalId = $arrParentInfo['parent_member_id'];
                                    if ($arrParentInfo['member_type_name'] == 'individual') {
                                        break;
                                    }
                                }

                                switch ($searchCondition) {
                                    case "is" :
                                        if ($searchText == $applicantInternalId) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "is_not" :
                                    case "is not" :
                                        if ($searchText != $applicantInternalId && !empty($applicantInternalId)) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "contains" :
                                        if (stripos($applicantInternalId, $searchText) !== false) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "does_not_contain" :
                                    case "does not contain" :
                                        if (!stripos($applicantInternalId, $searchText) !== false && !empty($applicantInternalId)) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "starts_with" :
                                    case "starts with" :
                                        if (strpos($applicantInternalId, $searchText) === 0) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "ends_with" :
                                    case "ends with" :
                                        $length = strlen($searchText);
                                        if (substr($applicantInternalId, -$length) === $searchText) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "is_empty" :
                                    case "is empty" :
                                        if (empty($applicantInternalId)) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;

                                    case "is_not_empty" :
                                    case "is not empty" :
                                        if (!empty($applicantInternalId)) {
                                            $membersArr[] = $caseId;
                                        }
                                        break;
                                }
                                break;

                            case 'ob_total' :
                                if ($specialFields[$caseId]['outstanding_balance_secondary'] > 0 || $specialFields[$caseId]['outstanding_balance_primary'] > 0) {
                                    $membersArr[] = $caseId;
                                }
                                break;

                            case 'ta_total' :
                                if ($specialFields[$caseId]['trust_account_summary_secondary'] > 0 || $specialFields[$caseId]['trust_account_summary_primary'] > 0) {
                                    $membersArr[] = $caseId;
                                }
                                break;

                            case 'clients_completed_forms' :
                                if (isset($specialFields[$caseId]['clients_completed_forms'])) {
                                    $membersArr[] = $caseId;
                                }
                                break;

                            case 'clients_uploaded_documents' :
                                if (isset($specialFields[$caseId]['clients_uploaded_documents'])) {
                                    $membersArr[] = $caseId;
                                }
                                break;

                            case 'clients_have_payments_due' :
                                if (isset($specialFields[$caseId]['clients_have_payments_due'])) {
                                    $membersArr[] = $caseId;
                                }
                                break;
                        }
                    }
                } else //profile fields
                {
                    //dependants mapping
                    $dependentColumnName    = "";
                    $isNullPredicate        = null;
                    $booJoinDependentsTable = false;
                    if ($booHasAccessToDependants && $companyFieldId) {
                        $booJoinDependentsTable = true;
                        switch ($companyFieldId) {
                            case 'given_names' :
                            case 'first_name' :
                                $dependentColumnName = 'fdep.fName';
                                $isNullPredicate     = $isExcept ? new IsNull($dependentColumnName) : null;
                                break;

                            case 'family_name' :
                            case 'last_name' :
                                $dependentColumnName = 'fdep.lName';
                                $isNullPredicate     = $isExcept ? new IsNull($dependentColumnName) : null;
                                break;

                            case 'DOB' :
                                $dependentColumnName = "CONVERT(CONCAT('" . date('Y') . "', '-', MONTH(fdep.DOB), '-', DAY(fdep.DOB)), DATE)";
                                $isNullPredicate     = $isExcept ? new IsNull('fdep.lName') : null;
                                $booIsDateField      = true;
                                break;

                            case 'city_of_residence' :
                                $dependentColumnName = 'fdep.city_of_residence';
                                $isNullPredicate     = $isExcept ? new IsNull($dependentColumnName) : null;
                                break;

                            case 'country_of_residence' :
                                $dependentColumnName = 'fdep.country_of_residence';
                                $isNullPredicate     = $isExcept ? new IsNull($dependentColumnName) : null;
                                break;

                            case 'passport_number' :
                                $dependentColumnName = 'fdep.passport_num';
                                $isNullPredicate     = $isExcept ? new IsNull($dependentColumnName) : null;
                                break;

                            case 'passport_exp_date' :
                            case 'passport_expiry_date' :
                                $dependentColumnName = 'fdep.passport_date';
                                $isNullPredicate     = $isExcept ? new IsNull($dependentColumnName) : null;
                                $booIsDateField      = true;
                                break;

                            default:
                                $booJoinDependentsTable = false;
                                break;
                        }
                    }

                    //Search Conditions
                    switch ($searchCondition) {
                        case "=" :
                        case "is" :
                        case "equal" :
                            $searchQuery->equalTo($searchDbRow, $searchText);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->equalTo($dependentColumnName, $searchText);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }

                            break;

                        case "is one of" :
                        case "is_one_of" :
                            $arrOptionIds = explode(";", $searchText ?? '');

                            $searchQuery->in($searchDbRow, $arrOptionIds);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->in($dependentColumnName, $arrOptionIds);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_one_of_multi" :
                            $text = str_replace(";", "|", $searchText);
                            $text = '(^|,)(' . $text . ')(,|$)';

                            $searchQuery->expression($searchDbRow . ' REGEXP ?', $text);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->expression($dependentColumnName . ' REGEXP ?', $text);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "<>" :
                        case "is_not" :
                        case "is not" :
                        case "not_equal" :
                            $searchQuery->notEqualTo($searchDbRow, $searchText);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->notEqualTo($dependentColumnName, $searchText);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_none_of" :
                        case "is none of" :
                            $arrOptionIds = explode(";", $searchText ?? '');
                            $searchQuery->notIn($searchDbRow, $arrOptionIds);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->notIn($dependentColumnName, $arrOptionIds);
                                if ($isNullPredicate) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_none_of_multi" :
                            $text = str_replace(";", "|", $searchText);
                            $text = '(^|,)(' . $text . ')(,|$)';

                            $searchQuery->expression($searchDbRow . ' NOT REGEXP ?', $text);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->expression($dependentColumnName . ' NOT REGEXP ?', $text);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "contains" :
                            $searchQuery->like($searchDbRow, "%$searchText%");

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->like($dependentColumnName, "%$searchText%");
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "does_not_contain" :
                        case "does not contain" :
                            $searchQuery->notLike($searchDbRow, "%$searchText%");

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->notLike($dependentColumnName, "%$searchText%");
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "starts_with" :
                        case "starts with" :
                            $searchQuery->like($searchDbRow, "$searchText%");

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->like($dependentColumnName, "$searchText%");
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "ends_with" :
                        case "ends with" :
                            $searchQuery->like($searchDbRow, "%$searchText");

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->like($dependentColumnName, "%$searchText");
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_empty" :
                        case "is empty" :
                            if ($booIsDateField) {
                                $searchQuery->isNull($searchDbRow);

                                if (!empty($dependentColumnName)) {
                                    $searchCriteria->isNull($dependentColumnName);
                                    if (!is_null($isNullPredicate)) {
                                        $searchCriteria->orPredicate($isNullPredicate);
                                    }
                                }
                            } else {
                                $searchQuery->equalTo($searchDbRow, '');

                                if (!empty($dependentColumnName)) {
                                    $searchCriteria->equalTo($dependentColumnName, '');
                                    if (!is_null($isNullPredicate)) {
                                        $searchCriteria->orPredicate($isNullPredicate);
                                    }
                                }
                            }
                            break;

                        case "is_not_empty" :
                        case "is not empty" :
                            if ($booIsDateField) {
                                $searchQuery->isNotNull($searchDbRow);

                                if (!empty($dependentColumnName)) {
                                    $searchCriteria->isNotNull($dependentColumnName);
                                    if (!is_null($isNullPredicate)) {
                                        $searchCriteria->orPredicate($isNullPredicate);
                                    }
                                }
                            } else {
                                $searchQuery->notEqualTo($searchDbRow, '');

                                if (!empty($dependentColumnName)) {
                                    $searchCriteria->notEqualTo($dependentColumnName, '');
                                    if (!is_null($isNullPredicate)) {
                                        $searchCriteria->orPredicate($isNullPredicate);
                                    }
                                }
                            }
                            break;

                        case "is before" :
                        case "is_before" :
                        case "<" :
                        case "less" :
                            $searchQuery->lessThan($searchDbRow, $searchText);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->lessThan($dependentColumnName, $searchText);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_after" :
                        case "is after" :
                        case ">" :
                        case "more" :
                            $searchQuery->greaterThan($searchDbRow, $searchText);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->greaterThan($dependentColumnName, $searchText);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "<=" :
                        case "less_or_equal" :
                            $searchQuery->lessThanOrEqualTo($searchDbRow, $searchText);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->lessThanOrEqualTo($dependentColumnName, $searchText);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case ">=" :
                        case "more_or_equal" :
                            $searchQuery->greaterThanOrEqualTo($searchDbRow, $searchText);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->greaterThanOrEqualTo($dependentColumnName, $searchText);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_between_2_dates" :
                        case "is between two dates" :
                            $searchQuery->between($searchDbRow, $searchText, $srchTxtTo);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, $searchText, $srchTxtTo);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_between_today_and_date" :
                        case "is between today and a date" :
                            $searchQuery->between($searchDbRow, $now, $searchText);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, $now, $searchText);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_between_date_and_today" :
                        case "is between a date and today" :
                            $searchQuery->between($searchDbRow, $searchText, $now);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, $searchText, $now);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is in the next" :
                        case "is_in_the_next" :
                            $date = new DateTime();
                            $date->add(new DateInterval('P' . $srchNextNum . $srchNextPeriod));

                            $searchQuery->between($searchDbRow, $now, $date->format('Y-m-d'));

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, $now, $date->format('Y-m-d'));
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is in the previous" :
                        case "is_in_the_previous" :
                            $date = new DateTime();
                            $date->sub(new DateInterval('P' . $srchNextNum . $srchNextPeriod));

                            $searchQuery->between($searchDbRow, $date->format('Y-m-d'), $now);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, $date->format('Y-m-d'), $now);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_since_start_of_the_year_to_now" :
                        case "is since the start of the year to now" :
                            $searchQuery->between($searchDbRow, date('Y-01-01'), $now);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, date('Y-01-01'), $now);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is from today to end of the year" :
                        case "is_from_today_to_the_end_of_year" :
                            $searchQuery->between($searchDbRow, $now, date('Y-12-31'));

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, $now, date('Y-12-31'));
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_in_this_month" :
                        case "is in this month" :
                            $date1 = date('Y-m-d', strtotime(date('m') . "/1/" . date('Y')));
                            $date2 = date('Y-m-d', (strtotime('next month', strtotime(date('m/01/y'))) - 1));

                            $searchQuery->between($searchDbRow, $date1, $date2);

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, $date1, $date2);
                                if (!is_null($isNullPredicate)) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;

                        case "is_in_this_year" :
                        case "is in this year" :
                            $searchQuery->between($searchDbRow, date('Y-01-01'), date('Y-12-31'));

                            if (!empty($dependentColumnName)) {
                                $searchCriteria->between($dependentColumnName, date('Y-01-01'), date('Y-12-31'));
                                if ($isNullPredicate) {
                                    $searchCriteria->orPredicate($isNullPredicate);
                                }
                            }
                            break;
                    }

                    //gen search query
                    if (!empty($searchQuery->count())) {
                        if (!empty($searchCriteria->count())) {
                            $searchQuery = (new Where())->nest()->addPredicate($searchQuery)->unnest()->addPredicate($searchCriteria, $isExcept ? Where::OP_AND : Where::OP_OR);
                        }
                    }

                    // Generate query
                    $select = (new Select())
                        ->from(array('m' => 'members'))
                        ->columns(['member_id']);

                    $oAccessRightsQuery = $this->_parent->getStructureQueryForClient();
                    if ($oAccessRightsQuery) {
                        $select->where($oAccessRightsQuery);
                    }

                    if ($searchQuery->count()) {
                        $select->where([$searchQuery]);
                    }

                    // Join divisions table only if it is used
                    $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
                    if (!empty($divisionGroupId)) {
                        $arrDivisions = $this->_company->getCompanyDivisions()->getDivisionsByGroupId($divisionGroupId);
                        if (!empty($arrDivisions)) {
                            $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
                        }
                    }

                    if ($booJoinClientsDataTable) {
                        $select->join(array('fd' => 'client_form_data'), 'fd.member_id = m.member_id', [], Select::JOIN_LEFT);
                    }

                    if ($booJoinClientsTable) {
                        $select->join(array('c' => 'clients'), 'c.member_id = m.member_id', [], Select::JOIN_LEFT);
                    }

                    // Join dependents table only if it is used
                    if ($booJoinDependentsTable) {
                        $select->join(array('fdep' => 'client_form_dependents'), 'fdep.member_id = m.member_id', [], Select::JOIN_LEFT);
                    }

                    try {
                        $membersArr = Settings::arrayUnique($this->_db2->fetchCol($select));
                        $membersArr = array_map('intval', $membersArr);

                        // Apply exceptions (so, we'll use empty cases (cases without data in the client_form_data table)
                        if (!is_null($nonEmptyMembers)) {
                            $arrEmptyMembers = array_diff($arrCasesIds, $nonEmptyMembers);
                            $membersArr      = Settings::arrayUnique(array_merge($arrEmptyMembers, $membersArr));
                        }
                    } catch (Exception $e) {
                        $membersArr = array();
                        $this->_log->debugErrorToFile('Wrong search', (new Sql($this->_db2))->buildSqlString($select), 'search');
                    }
                }

                //MERGE | INTERSECT MEMBERS ARRAY
                if (isset($form['match-' . $i])) {
                    if ($form['match-' . $i] == 'OR') { //OR
                        $arrResultMembers = array_merge($arrResultMembers, $membersArr);
                    } else { //AND
                        $arrResultMembers = array_intersect($arrResultMembers, $membersArr);
                    }

                    //remove duplications
                    $arrResultMembers = Settings::arrayUnique($arrResultMembers);
                } else { //First line does not contains AND|OR option
                    $arrResultMembers = $membersArr;
                }
            }

            if ($cols) {
                //show only Active Clients
                if (!empty($arrResultMembers) && $booActiveClients) {
                    $arrResultMembers = $this->_parent->getCompanyActiveClientsList($companyId, $arrResultMembers);
                }

                // Make sure that if there are provided parents - found cases will have these parents
                if (!empty($arrResultMembers) && isset($form['cases_parents'])) {
                    $arrCasesWithParents = $this->_parent->getAssignedCases($form['cases_parents'], false);
                    $arrResultMembers    = array_intersect($arrResultMembers, $arrCasesWithParents);
                }

                $booSuccess = true;
            } else {
                $booSuccess = false;
                $strMessage = $this->_tr->translate('Nothing to search. Please fill search form.');
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $strMessage = $this->_tr->translate('An error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'error'   => !$booSuccess,
            'content' => $strMessage,
            'members' => $arrResultMembers
        );
    }

    /**
     * Run advanced search based on clients (IA/Employer/Contact/Internal contact)
     *
     * @param array $arrParams
     * @return array
     */
    public function runApplicantsAdvancedSearch($arrParams)
    {
        $strError   = '';
        $arrMembers = array();
        try {
            $arrMemberIds = $arrParams['all_allowed_members'];

            if (count($arrMemberIds)) {
                $arrWhereRows = new Where();
                $now          = date('Y-m-d H:i:s'); //PHP Date Stamp
                $prefix       = 'd1';
                $companyId    = $this->_auth->getCurrentUserCompanyId();

                $arrWhere = ['m.company_id' => $companyId];
                if (!empty($arrParams['userType'])) {
                    $arrWhere[] = (new Where())->in('m.userType', $arrParams['userType']);
                }

                $select = (new Select())
                    ->from(array('m' => 'members'))
                    ->columns(['member_id'])
                    ->join(array($prefix => 'applicant_form_data'), 'm.member_id = ' . $prefix . '.applicant_id', [], Select::JOIN_LEFT)
                    ->where($arrWhere)
                    ->group($prefix . '.applicant_id');

                $arrExceptMembersFromAllRows = array();
                for ($i = 1; $i <= $arrParams['max_rows_count']; $i++) {
                    $prefix = 'd' . $i;

                    if (array_key_exists('field_' . $i, $arrParams)) {
                        $fieldTypeId = isset($arrParams['field_type_' . $i]) ? $this->_parent->getFieldTypes()->getFieldTypeIdByTextId($arrParams['field_type_' . $i], 'applicant', true) : 0;

                        if (empty($fieldTypeId)) {
                            $strError = $this->_tr->translate('Incorrectly selected field type.');
                        }

                        if (empty($strError) && !array_key_exists('filter_' . $i, $arrParams)) {
                            $strError = $this->_tr->translate('Incorrectly selected filter.');
                        }

                        $text                = '';
                        $date                = '';
                        $dateFrom            = '';
                        $dateTo              = '';
                        $dateNextNum         = 0;
                        $dateNextPeriod      = '';
                        $booOfficeMultiField = false;

                        switch ($arrParams['field_type_' . $i]) {
                            case 'date':
                            case 'date_repeatable':
                            case 'office_change_date_time':
                                switch ($arrParams['filter_' . $i]) {
                                    case 'is in the next' :
                                    case 'is_in_the_next' :
                                    case 'is in the previous' :
                                    case 'is_in_the_previous' :
                                        if (empty($strError) && (!array_key_exists('date_next_num_' . $i, $arrParams) || !array_key_exists('date_next_period_' . $i, $arrParams))) {
                                            $strError = $this->_tr->translate('Incorrectly selected date period.');
                                        } else {
                                            $dateNextNum = (int)$arrParams['date_next_num_' . $i];
                                            $dateNextPeriod = $arrParams['date_next_period_' . $i];
                                        }

                                        if (empty($strError) && $dateNextNum < 0) {
                                            $strError = $this->_tr->translate('Incorrectly selected "date how long" period.');
                                        }

                                        if (empty($strError) && !in_array($dateNextPeriod, array('D', 'W', 'M', 'Y'))) {
                                            $strError = $this->_tr->translate('Incorrectly selected combobox option for the date field.');
                                        }
                                        break;

                                    case 'is between two dates':
                                    case 'is_between_2_dates':
                                        if (empty($strError) && (!array_key_exists('date_from_' . $i, $arrParams) || !array_key_exists('date_to_' . $i, $arrParams))) {
                                            $strError = $this->_tr->translate('Incorrectly selected date from/to.');
                                        } else {
                                            $dateFrom = $arrParams['date_from_' . $i];
                                            $dateTo = $arrParams['date_to_' . $i];
                                        }
                                        break;

                                    case 'is empty':
                                    case 'is_empty':
                                    case 'is not empty':
                                    case 'is_not_empty':
                                    case "is since the start of the year to now" :
                                    case "is_since_start_of_the_year_to_now" :
                                    case "is from today to end of the year" :
                                    case "is_from_today_to_the_end_of_year" :
                                    case "is in this month" :
                                    case "is_in_this_month" :
                                    case "is in this year" :
                                    case "is_in_this_year" :
                                        // Don't check for incoming date
                                        break;

                                    case 'is' :
                                        if (empty($strError) && (!array_key_exists('date_' . $i, $arrParams))) {
                                            $strError = $this->_tr->translate('Incorrectly selected date.');
                                        } else {
                                            $text = $arrParams['date_' . $i];
                                            if ($arrParams['field_type_' . $i] == 'office_change_date_time') {
                                                $arrParams['filter_' . $i] = 'contains';
                                            }
                                        }
                                        break;

                                    case 'is_not' :
                                    case "is not" :
                                        if (empty($strError) && (!array_key_exists('date_' . $i, $arrParams))) {
                                            $strError = $this->_tr->translate('Incorrectly selected date.');
                                        } else {
                                            $text = $arrParams['date_' . $i];
                                            if ($arrParams['field_type_' . $i] == 'office_change_date_time') {
                                                $arrParams['filter_' . $i] = 'does_not_contain';
                                            }
                                        }
                                        break;

                                    case 'is before' :
                                    case 'is_before' :
                                    case 'is after' :
                                    case 'is_after' :
                                    default:
                                        if (empty($strError) && (!array_key_exists('date_' . $i, $arrParams))) {
                                            $strError = $this->_tr->translate('Incorrectly selected date.');
                                        } else {
                                            $text = $date = $arrParams['date_' . $i];
                                        }
                                        break;
                                }
                                break;

                            case 'combo':
                            case 'multiple_combo':
                            case 'agents':
                            case 'office':
                            case 'office_multi':
                            case 'assigned_to':
                                if (empty($strError) && (!array_key_exists('option_' . $i, $arrParams) || $arrParams['option_' . $i] == '')) {
                                    $strError = $this->_tr->translate('Incorrectly selected combobox option.');
                                } else {
                                    $text = $arrParams['option_' . $i];

                                    if ($arrParams['field_type_' . $i] == 'office_multi' || $arrParams['field_type_' . $i] == 'multiple_combo') {
                                        if (in_array($arrParams['filter_' . $i], array("=", "is", "equal", "is_one_of", "is one of"))) {
                                            $arrParams['filter_' . $i] = 'is_one_of_multi';
                                        } elseif (in_array($arrParams['filter_' . $i], array("<>", "is not", "is_not", "not_equal", "is_none_of", "is none of"))) {
                                            $arrParams['filter_' . $i] = 'is_none_of_multi';
                                        }
                                    }

                                    if ($arrParams['field_type_' . $i] == 'office_multi') {
                                        $booOfficeMultiField = true;
                                    }
                                }
                                break;

                            case 'country':
                                if (empty($strError) && (array_key_exists('text_' . $i, $arrParams) || array_key_exists('option_' . $i, $arrParams))) {
                                    $text = array_key_exists('text_' . $i, $arrParams) ? $arrParams['text_' . $i] : $arrParams['option_' . $i];
                                }
                                break;

                            case 'applicant_internal_id':
                                if (empty($strError)) {
                                    if (array_key_exists('text_' . $i, $arrParams)) {
                                        $text = $arrParams['text_' . $i];
                                    }
                                    $arrParams['filter_' . $i] = 'applicant_internal_id_' . $arrParams['filter_' . $i];
                                }
                                break;

                            case 'float':
                            case 'number':
                            case 'text':
                            default:
                                if (empty($strError) && array_key_exists('text_' . $i, $arrParams)) {
                                    $text = $arrParams['text_' . $i];
                                }
                                break;
                        }

                        // We need to be sure that clients without data will be loaded too
                        $exceptCriteria = new Where();
                        if (empty($strError) && in_array($arrParams['filter_' . $i], array("<>", "is not", 'is_not', 'is none of', 'is_none_of', "does not contain", 'does_not_contain', "is empty", 'is_empty', 'is_none_of_multi'))) {
                            $innerSelect = (new Select())
                                ->from(array('d' => 'applicant_form_data'))
                                ->columns(['applicant_id'])
                                ->where(
                                    [
                                        'd.applicant_field_id' => (int)$arrParams['field_' . $i],
                                        (new Where())->notEqualTo('d.value', '')
                                    ]
                                );

                            $nonEmptyMembers = Settings::arrayUnique(array_map('intval', $this->_db2->fetchCol($innerSelect)));

                            if ($booOfficeMultiField) {
                                $arrOptionIds = explode(";", $text ?? '');

                                $selectOffice = (new Select())
                                    ->from('members_divisions')
                                    ->columns(['member_id'])
                                    ->where(
                                        [
                                            'type'        => 'access_to',
                                            'division_id' => array_map('intval', $arrOptionIds),
                                            'member_id'   => $arrMemberIds
                                        ]
                                    )
                                    ->group('member_id');

                                $arrFoundMemberIdsOffice = array_map('intval', $this->_db2->fetchCol($selectOffice));

                                $nonEmptyMembers = Settings::arrayUnique(array_merge($nonEmptyMembers, $arrFoundMemberIdsOffice));
                            }

                            // Make sure that we'll do a search even if there are no records with a saved value
                            $nonEmptyMembers = empty($nonEmptyMembers) ? array(0) : $nonEmptyMembers;
                            if (!empty($nonEmptyMembers)) {
                                $arrExceptMembersFromAllRows = array_map('intval', $arrExceptMembersFromAllRows);
                                $arrExceptMembersFromAllRows = Settings::arrayUnique(array_merge($arrExceptMembersFromAllRows, $nonEmptyMembers));
                                $exceptCriteria->notIn('m.member_id', $nonEmptyMembers);
                            }
                        }

                        if ($arrParams['filter_' . $i] == 'is_none_of') {
                            $arrParams['filter_' . $i] = 'does_not_contain';
                        }

                        $searchDbRow = $prefix . '.value';
                        if ($arrParams['field_type_' . $i] == 'date_repeatable') {
                            $searchDbRow = new Expression("CONVERT(CONCAT('" . date('Y') . "', '-', MONTH($searchDbRow), '-', DAY($searchDbRow)), DATE)");
                        }

                        $oWhereOffice = new Where();
                        // Search Conditions
                        $booUseExceptCriteria = false;
                        switch ($arrParams['filter_' . $i]) {
                            case "=" :
                            case "is" :
                            case "equal" :
                                $strWhere = (new Where())->equalTo($prefix . '.value', $text);
                                break;

                            case "applicant_internal_id_is" :
                                $strWhere = (new Where())->equalTo('m.member_id', (int)$text);
                                break;

                            case "is one of" :
                            case "is_one_of" :
                                $arrOptionIds = explode(";", $text ?? '');
                                if (!is_array($arrOptionIds)) {
                                    $arrOptionIds = [$arrOptionIds];
                                }

                                $strWhere = (new Where())->in($prefix . '.value', $arrOptionIds);
                                break;

                            case "is_one_of_multi" :
                                if ($booOfficeMultiField) {
                                    // Don't use search based on the applicant_form_data table - the correct list is in the members_divisions table
                                    $strWhere     = (new Where())->equalTo($prefix . '.value', 0);
                                    $arrOptionIds = explode(";", $text ?? '');

                                    $selectOffice = (new Select())
                                        ->from('members_divisions')
                                        ->columns(['member_id'])
                                        ->where(
                                            [
                                                'type'        => 'access_to',
                                                'division_id' => $arrOptionIds,
                                                'member_id'   => $arrMemberIds
                                            ]
                                        )
                                        ->group('member_id');

                                    $arrFoundMemberIdsOffice = $this->_db2->fetchCol($selectOffice);
                                    if (!empty($arrFoundMemberIdsOffice)) {
                                        if (!is_array($arrFoundMemberIdsOffice)) {
                                            $arrFoundMemberIdsOffice = [$arrFoundMemberIdsOffice];
                                        }

                                        $oWhereOffice->in($prefix . '.applicant_id', $arrFoundMemberIdsOffice);
                                    }
                                } else {
                                    $value    = str_replace(";", "|", $text);
                                    $strWhere = (new Where())->expression($prefix . '.value REGEXP ?', '(^|,)(' . $value . ')(,|$)');
                                }
                                break;

                            case "<>" :
                            case "is not" :
                            case "is_not" :
                            case "not_equal" :
                                $strWhere = (new Where())->notEqualTo($prefix . '.value', $text);

                                $booUseExceptCriteria = true;
                                break;

                            case "applicant_internal_id_is_not" :
                            case "applicant_internal_id is not" :
                                $strWhere = (new Where())->notEqualTo('m.member_id', $text);

                                $booUseExceptCriteria = true;
                                break;

                            case "is none of" :
                            case "is_none_of" :
                                $arrOptionIds = explode(";", $text ?? '');
                                $strWhere = (new Where())->notIn($prefix . '.value', $arrOptionIds);

                                $booUseExceptCriteria = true;
                                break;

                            case "is_none_of_multi" :
                                $text = str_replace(";", "|", $text);
                                $strWhere = (new Where())->expression($prefix . '.value NOT REGEXP ?', '(^|,)(' . $text . ')(,|$)');

                                $booUseExceptCriteria = true;
                                break;

                            case "contains" :
                                $strWhere = (new Where())->like($prefix . '.value', '%' . $text . '%');
                                break;

                            case "applicant_internal_id_contains" :
                                $strWhere = (new Where())->like('m.member_id', '%' . $text . '%');
                                break;

                            case "does not contain" :
                            case "does_not_contain" :
                                $strWhere = (new Where())->notLike($prefix . '.value', '%' . $text . '%');

                                $booUseExceptCriteria = true;
                                break;

                            case "applicant_internal_id does not contain" :
                            case "applicant_internal_id_does_not_contain" :
                                $strWhere = (new Where())->notLike('m.member_id', '%' . $text . '%');

                                $booUseExceptCriteria = true;
                                break;

                            case "starts with" :
                            case "starts_with" :
                                $strWhere = (new Where())->like($prefix . '.value', $text . '%');
                                break;

                            case "applicant_internal_id_starts with" :
                            case "applicant_internal_id_starts_with" :
                                $strWhere = (new Where())->like('m.member_id', $text . '%');
                                break;

                            case "ends with" :
                            case "ends_with" :
                                $strWhere = (new Where())->like($prefix . '.value', '%' . $text);
                                break;

                            case "applicant_internal_id ends with" :
                            case "applicant_internal_id_ends_with" :
                                $strWhere = (new Where())->like('m.member_id', '%' . $text);
                                break;

                            case "is empty" :
                            case "is_empty" :
                                $strWhere = (new Where())->equalTo($prefix . '.value', '');

                                $booUseExceptCriteria = true;
                                break;

                            case "applicant_internal_id is empty" :
                            case "applicant_internal_id_is_empty" :
                                $strWhere = (new Where())->isNull('m.member_id') ;

                                $booUseExceptCriteria = true;
                                break;

                            case "is not empty" :
                            case "is_not_empty" :
                                $strWhere = (new Where())->notEqualTo($prefix . '.value', '');
                                break;

                            case "applicant_internal_id is not empty" :
                            case "applicant_internal_id_is_not_empty" :
                                $strWhere = (new Where())->isNotNull('m.member_id');
                                break;

                            case "is before" :
                            case "is_before" :
                            case "<" :
                            case "less" :
                                $strWhere = (new Where())->lessThan($prefix . '.value', $text);
                                break;

                            case ">" :
                            case "is after" :
                            case "is_after" :
                            case "more" :
                                $strWhere = (new Where())->greaterThan($prefix . '.value', $text);
                                break;

                            case "<=" :
                            case "less_or_equal" :
                                $strWhere = (new Where())->lessThanOrEqualTo($prefix . '.value', $text);
                                break;

                            case ">=" :
                            case "more_or_equal" :
                                $strWhere = (new Where())->greaterThanOrEqualTo($prefix . '.value', $text);
                                break;

                            case "is between two dates" :
                            case "is_between_2_dates" :
                                $strWhere = (new Where())->between($searchDbRow, $dateFrom, $dateTo);
                                break;

                            case "is in the next" :
                            case "is_in_the_next" :
                                $date = new DateTime();
                                $date->add(new DateInterval('P' . $dateNextNum . $dateNextPeriod));
                                $strWhere = (new Where())->between($searchDbRow, $now, $date->format('Y-m-d'));
                                break;

                            case "is in the previous" :
                            case "is_in_the_previous" :
                                $date = new DateTime();
                                $date->sub(new DateInterval('P' . $dateNextNum . $dateNextPeriod));
                                $strWhere = (new Where())->between($searchDbRow, $date->format('Y-m-d'), $now);
                                break;

                            case "is between today and a date" :
                            case "is_between_today_and_date" :
                                $strWhere = (new Where())->between($searchDbRow, $now, $date);
                                break;

                            case "is between a date and today" :
                            case "is_between_date_and_today" :
                                $strWhere = (new Where())->between($searchDbRow, $date, $now);
                                break;

                            case "is since the start of the year to now" :
                            case "is_since_start_of_the_year_to_now" :
                                $strWhere = (new Where())->between($prefix . '.value', date('Y-01-01'), $now);
                                break;

                            case "is from today to end of the year" :
                            case "is_from_today_to_the_end_of_year" :
                                $strWhere = (new Where())->between($prefix . '.value', $now, date('Y-12-31'));
                                break;

                            case "is in this month" :
                            case "is_in_this_month" :
                                $strWhere = (new Where())->between(
                                    $searchDbRow,
                                    date('Y-m-d', strtotime(date('m') . "/1/" . date('Y'))),
                                    date(
                                        'Y-m-d',
                                        strtotime('next month', strtotime(date('m/01/y'))) - 1
                                    )
                                );
                                break;

                            case "is in this year" :
                            case "is_in_this_year" :
                                $strWhere = (new Where())->between($prefix . '.value', date('Y-01-01'), date('Y-12-31'));
                                break;

                            default:
                                $strError = $this->_tr->translate('Incorrect filter.');
                                $strWhere = null;
                                break;
                        }


                        if (empty($strError)) {
                            if ($i > 1) {
                                $select->join(array($prefix => 'applicant_form_data'), $prefix . '.applicant_id = d1.applicant_id', [], Select::JOIN_LEFT);
                            }

                            $prefixOperator = $i == 1 ? 'AND' : ($arrParams['operator_' . $i] == 'and' ? ' AND ' : ' OR ');
                            if ($arrParams['field_type_' . $i] != 'applicant_internal_id') {
                                $where = (new Where())->nest()->equalTo($prefix . '.applicant_field_id', (int)$arrParams['field_' . $i]);
                                if (!empty($strWhere)) {
                                    $where->andPredicate($strWhere);
                                }
                                $where = $where->unnest();

                                if ($booUseExceptCriteria && $exceptCriteria->count()) {
                                    $where->orPredicate($exceptCriteria);
                                }

                                $arrWhereRows->addPredicate($where, $prefixOperator);
                                if ($oWhereOffice->count()) {
                                    $arrWhereRows->addPredicate($oWhereOffice, Where::OP_OR);
                                }
                            } else {
                                $where = (new Where())->nest()->equalTo('m.userType', (int)$this->_parent->getMemberTypeIdByName($arrParams['field_client_type_' . $i]));
                                if (!empty($strWhere)) {
                                    $where->andPredicate($strWhere);
                                }
                                $where = $where->unnest();

                                if ($booUseExceptCriteria && $exceptCriteria->count()) {
                                    $where->orPredicate($exceptCriteria);
                                }

                                $arrWhereRows->addPredicate($where, $prefixOperator);
                            }
                        }
                    }
                }

                if (empty($strError) && !$arrWhereRows->count()) {
                    $strError = $this->_tr->translate('Incorrect incoming data.');
                }

                if (empty($strError)) {
                    $select->where([$arrWhereRows]);
                    $arrFoundMemberIds     = array_map('intval', $this->_db2->fetchCol($select));
                    $arrFoundMemberIdsCopy = $arrFoundMemberIds = array_intersect($arrFoundMemberIds, $arrMemberIds);

                    $arrFoundMemberIdsCopyValues = array_values($arrFoundMemberIdsCopy);

                    foreach ($arrFoundMemberIdsCopyValues as $v) {
                        $arrFoundMemberIdsCopy[$v] = 1;
                    }

                    if (count($arrExceptMembersFromAllRows)) {
                        $arrExceptMembersFromAllRowsValues = array_values($arrExceptMembersFromAllRows);

                        foreach ($arrExceptMembersFromAllRowsValues as $v) {
                            $arrExceptMembersFromAllRows[$v] = 1;
                        }

                        $userType = $this->_parent->getMemberTypeIdByName('internal_contact');

                        $arrAllChildren = $this->_parent->getAssignedApplicants($arrFoundMemberIds, $userType, true);

                        $arrInternalContactsGrouped = array();
                        foreach ($arrAllChildren as $child) {
                            $booExists = isset($arrInternalContactsGrouped[$child['parent_member_id']]);
                            $arrInternalContactsGrouped[$child['parent_member_id']][] = $child['child_member_id'];

                            if ($booExists) {
                                $arrInternalContactsGrouped[$child['parent_member_id']] = Settings::arrayUnique($arrInternalContactsGrouped[$child['parent_member_id']]);
                            }
                        }

                        foreach ($arrFoundMemberIds as $key => $value) {
                            if (isset($arrInternalContactsGrouped[$value])) {
                                foreach ($arrInternalContactsGrouped[$value] as $childId) {
                                    if (isset($arrExceptMembersFromAllRows[$childId]) && !isset($arrFoundMemberIdsCopy[$childId])) {
                                        unset($arrFoundMemberIds[$key]);
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    $arrMembers = array_intersect($arrFoundMemberIds, $arrMemberIds);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('An error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'error'   => $strError,
            'members' => $arrMembers
        );
    }

    /**
     * @param array $arrParams
     * @param string $searchType
     * @return array
     */
    public function runAdvancedSearch($arrParams, $searchType = 'applicants')
    {
        $arrResultMembersWithData = array();
        $arrAllMemberIds          = array();
        $booSearchForCases        = false;
        $totalCount               = 0;
        $strError                 = '';

        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            $arrColumns = $arrParams['columns'] ?? array();
            unset($arrParams['columns']);

            // If at least one case field's column was selected - we want to find clients + cases
            foreach ($arrColumns as $strColumnId) {
                if (preg_match('/^case_(.*)$/', $strColumnId, $regs)) {
                    $booSearchForCases = true;
                    break;
                }
            }

            $arrSortInfo = $arrParams['arrSortInfo'] ?? array();
            unset($arrParams['arrSortInfo']);

            $arrParams['active-clients'] = isset($arrParams['active-clients']) && !empty($arrParams['active-clients']) ? 'on' : 'off';
            $arrParams['related-cases']  = isset($arrParams['related-cases']) && !empty($arrParams['related-cases']) ? 'on' : 'off';

            // If "Display Related Cases & Profiles with a Case" checkbox is checked -> search for cases too
            if ($arrParams['related-cases'] === 'on') {
                $booSearchForCases = true;
            }


            $filterClientType = 'all';
            if (isset($arrParams['filter_client_type_radio'])) {
                $filterClientType = $arrParams['filter_client_type_radio'];
                unset($arrParams['filter_client_type_radio']);

                if (!in_array($filterClientType, ['individual', 'employer']) || !$this->_company->isEmployersModuleEnabledToCompany($companyId)) {
                    $filterClientType = 'individual';
                }
            }

            $filter = new StripTags();
            foreach ($arrParams as $key => $val) {
                if (preg_match('/field_client_type_radio/', $key)) {
                    unset($arrParams[$key]);
                } else {
                    $arrParams[$key] = trim(substr($filter->filter($val), 0, 1000));
                }
            }

            if (!array_key_exists('max_rows_count', $arrParams) || !is_numeric($arrParams['max_rows_count']) || $arrParams['max_rows_count'] < 1 || $arrParams['max_rows_count'] > 100) {
                $strError = $this->_tr->translate('Incorrect parameters.');
            }

            $countRowsToSearch     = 0;
            $arrAllowedMemberTypes = array();
            if (empty($strError)) {
                $arrAllowedClientTypes = $searchType == 'contacts' ? array('contact') : $this->_parent->getApplicantFields()->getAdvancedSearchTypesList(true);

                for ($i = 1; $i <= $arrParams['max_rows_count']; $i++) {
                    if (!array_key_exists('field_client_type_' . $i, $arrParams)) {
                        continue;
                    }

                    if (!in_array($arrParams['field_client_type_' . $i], $arrAllowedClientTypes)) {
                        $strError = $this->_tr->translate('Incorrectly selected client type.');
                        break;
                    }

                    if (!array_key_exists('operator_' . $i, $arrParams) || !in_array($arrParams['operator_' . $i], array('and', 'or'))) {
                        $strError = $this->_tr->translate('Incorrectly selected field type.');
                        break;
                    }

                    $arrAllowedFieldTypes = array('special');
                    $arrAllAllowedFieldTypes = $this->_parent->getFieldTypes()->getFieldTypes($arrParams['field_client_type_' . $i]);
                    foreach ($arrAllAllowedFieldTypes as $arrFieldTypeInfo) {
                        if (!in_array($arrFieldTypeInfo['text_id'], $arrAllowedFieldTypes)) {
                            $arrAllowedFieldTypes[] = $arrFieldTypeInfo['text_id'];
                        }
                    }
                    if (!array_key_exists('field_type_' . $i, $arrParams) || !in_array($arrParams['field_type_' . $i], $arrAllowedFieldTypes)) {
                        $strError = $this->_tr->translate('Incorrectly selected field type.');
                        break;
                    }

                    if ($arrParams['field_client_type_' . $i] == 'case') {
                        $booSearchForCases = true;
                    }

                    // Search for IA/Employer/Contact records only
                    switch ($arrParams['field_client_type_' . $i]) {
                        case 'contact':
                        case 'individual':
                            $arrAllowedMemberTypes[] = $this->_parent->getMemberTypeIdByName($arrParams['field_client_type_' . $i]);
                            break;

                        case 'employer':
                            if ($this->_auth->isCurrentMemberCompanyEmployerModuleEnabled()) {
                                $arrAllowedMemberTypes[] = $this->_parent->getMemberTypeIdByName('employer');
                            }
                            break;

                        default:
                            break;
                    }

                    if (count($arrAllowedMemberTypes)) {
                        $arrAllowedMemberTypes[] = $this->_parent->getMemberTypeIdByName('internal_contact');
                    }

                    $countRowsToSearch++;
                }
            }

            if ($filterClientType != 'all') {
                if ($filterClientType == 'employer') {
                    $arrAllowedMemberTypes[] = $this->_parent->getMemberTypeIdByName('employer');
                } else {
                    $arrAllowedMemberTypes[] = $this->_parent->getMemberTypeIdByName('individual');
                }

                $arrAllowedMemberTypes[] = $this->_parent->getMemberTypeIdByName('internal_contact');
            }

            // Remove duplicates if any
            $arrAllowedMemberTypes = Settings::arrayUnique($arrAllowedMemberTypes);

            if (empty($strError) && empty($countRowsToSearch)) {
                $strError = $this->_tr->translate('Nothing to search.');
            }

            if (empty($strError)) {
                $arrApplicantFields = $this->_parent->getApplicantFields()->getCompanyAllFields($companyId);

                $arrApplicantEncodedFields = [];
                $arrApplicantFieldsGrouped = [];
                foreach ($arrApplicantFields as $arrApplicantFieldInfo) {
                    if ($arrApplicantFieldInfo['encrypted'] == 'Y') {
                        $arrApplicantEncodedFields[] = $arrApplicantFieldInfo['applicant_field_id'];
                    }

                    $arrApplicantFieldsGrouped[$arrApplicantFieldInfo['applicant_field_id']] = $arrApplicantFieldInfo;
                }

                $arrCompanyFields = $this->_parent->getFields()->getCompanyFields($companyId);
                $arrGroupedFields = array();
                foreach ($arrCompanyFields as $arrFieldInfo) {
                    $arrGroupedFields[$arrFieldInfo['field_id']] = $arrFieldInfo;
                }

                $arrClientsOrCasesIdsGrouped = array();
                if (isset($arrParams['saved_search_id'])) {
                    switch ($arrParams['saved_search_id']) {
                        case 'quick_search':
                            $arrQueryWords = $arrParams['search_query'] !== '' ? $this->getSearchStringExploded($arrParams['search_query']) : [];

                            $arrClientsOrCasesIdsGrouped = $this->_parent->getApplicantsAndCases(0, $arrQueryWords, $searchType, false, true);
                            break;

                        case 'last4all':
                        case 'last4me':
                            $arrClientsOrCasesIdsGrouped = $this->_parent->getLastViewedClients(50, $arrParams['saved_search_id']);
                            break;

                        case 'all':
                            $searchFor = $this->_parent->getMemberTypeIdByName($searchType === 'contacts' ? 'contact' : 'case');

                            $arrClientsOrCasesIdsGrouped = $this->_parent->getMembersWhichICanAccess($searchFor);
                            $arrClientsOrCasesIdsGrouped = array_map('intval', $arrClientsOrCasesIdsGrouped);
                            break;

                        default:
                            break;
                    }
                } else {
                    // Load allowed clients/cases only once (used when we search for clients)
                    $arrMemberIds = array();
                    if (is_array($arrAllowedMemberTypes) && count($arrAllowedMemberTypes)) {
                        $arrMemberIds = $this->_parent->getMembersWhichICanAccess($arrAllowedMemberTypes);
                    }

                    // Make sure that we don't load internal contacts for parents that we don't want to load
                    $arrCasesIds       = [];
                    $booLoadedCasesIds = false;
                    if (count($arrMemberIds)) {
                        if ($arrParams['related-cases'] == 'on') {
                            $arrCasesIds        = $this->_parent->getMembersWhichICanAccess(Members::getMemberType('case'));
                            $booLoadedCasesIds  = true;
                            $arrCasesParentsIds = array();
                            if (!empty($arrCasesIds)) {
                                $select = (new Select())
                                    ->from(array('r' => 'members_relations'))
                                    ->columns(['parent_member_id'])
                                    ->join(array('m' => 'members'), 'r.parent_member_id = m.member_id')
                                    ->where(['r.child_member_id' => $arrCasesIds]);

                                $arrCasesParentsIds = $this->_db2->fetchCol($select);
                                $arrCasesParentsIds = Settings::arrayUnique(array_map('intval', $arrCasesParentsIds));
                            }

                            $arrInternalContactsIds = array();
                            if (!empty($arrCasesParentsIds)) {
                                $select = (new Select())
                                    ->from(array('r' => 'members_relations'))
                                    ->columns(['child_member_id'])
                                    ->join(array('m' => 'members'), 'r.child_member_id = m.member_id')
                                    ->where(
                                        [
                                            'r.parent_member_id' => $arrCasesParentsIds,
                                            'm.userType' => Members::getMemberType('internal_contact')
                                        ]
                                    );

                                $arrInternalContactsIds = $this->_db2->fetchCol($select);
                                $arrInternalContactsIds = array_map('intval', $arrInternalContactsIds);
                            }

                            $arrAllMembersWithCases = Settings::arrayUnique(array_merge($arrCasesParentsIds, $arrInternalContactsIds));
                            $arrMemberIds           = array_intersect($arrMemberIds, $arrAllMembersWithCases);
                        }

                        // Get internal contacts
                        $select = (new Select())
                            ->from(array('m' => 'members'))
                            ->columns(['member_id'])
                            ->where([
                                'm.userType'   => Members::getMemberType('internal_contact'),
                                'm.company_id' => $this->_auth->getCurrentUserCompanyId()
                            ]);

                        $arrInternalContacts = $this->_db2->fetchCol($select);

                        // Get parents of these contacts
                        if (count($arrInternalContacts)) {
                            $select = (new Select())
                                ->from(array('r' => 'members_relations'))
                                ->columns(array('child_member_id'))
                                ->join(array('m' => 'members'), 'r.parent_member_id = m.member_id', [], Select::JOIN_LEFT)
                                ->where([
                                    'r.child_member_id' => $arrInternalContacts,
                                    new NotIn('m.userType', $arrAllowedMemberTypes)
                                ])
                                ->group('r.child_member_id');

                            $arrLinks = $this->_db2->fetchCol($select);
                            $arrLinks = array_map('intval', $arrLinks);

                            // If parent type is not in provided $arrAllowedMemberTypes - remove that internal contact
                            $arrMemberIds = array_diff($arrMemberIds, $arrLinks);
                        }
                    }


                    // Run search for each search row
                    $arrSearchResultGrouped = array();
                    for ($i = 1; $i <= $arrParams['max_rows_count']; $i++) {
                        if (!array_key_exists('field_client_type_' . $i, $arrParams)) {
                            continue;
                        }

                        if ($arrParams['field_client_type_' . $i] == 'case') {
                            // Load the list of cases once
                            if (empty($arrCaseIds) && !$booLoadedCasesIds) {
                                $arrCasesIds       = $this->_parent->getMembersWhichICanAccess(Members::getMemberType('case'));
                                $booLoadedCasesIds = true;
                            }

                            // Convert new search form to the old variant... ;-(
                            $arrPreparedParams                   = array();
                            $arrPreparedParams['all-cases']      = $arrCasesIds;
                            $arrPreparedParams['active-clients'] = $arrParams['active-clients'];
                            $arrPreparedParams['cntr']           = 1;

                            if (!empty($arrMemberIds)) {
                                $arrPreparedParams['cases_parents'] = $arrMemberIds;
                            }

                            $currentRow = 0;
                            if (array_key_exists('field_' . $i, $arrParams)) {
                                if (in_array($arrParams['field_' . $i], array('ob_total', 'ta_total', 'clients_completed_forms', 'clients_uploaded_documents', 'clients_have_payments_due'))) {
                                    $arrPreparedParams['srchField-' . $currentRow] = $arrParams['field_' . $i] . '|' . '0';
                                } elseif ($arrParams['field_' . $i] == 'created_on') {
                                    $arrPreparedParams['srchField-' . $currentRow] = $arrParams['field_' . $i] . '|' . $this->_parent->getFieldTypes()->getFieldTypeIdByTextId($arrParams['field_type_' . $i]);
                                } else {
                                    $arrPreparedParams['srchField-' . $currentRow] = $arrGroupedFields[$arrParams['field_' . $i]]['company_field_id'] . '|' . $this->_parent->getFieldTypes()->getFieldTypeIdByTextId($arrParams['field_type_' . $i]);
                                }

                                switch ($arrParams['field_type_' . $i]) {
                                    case 'country' :
                                        $arrPreparedParams['srchCountryConditions-' . $currentRow] = $arrParams['filter_' . $i];
                                        $arrPreparedParams['srchCountryList-' . $currentRow]       = $arrParams['text_' . $i] ?? '';
                                        if (empty($arrPreparedParams['srchCountryList-' . $currentRow])) {
                                            $arrPreparedParams['srchCountryList-' . $currentRow] = $arrParams['option_' . $i] ?? '';
                                        }
                                        break;

                                    case 'number' :
                                    case 'auto_calculated' :
                                        $arrPreparedParams['srchNumConditions-' . $currentRow] = $arrParams['filter_' . $i];
                                        $arrPreparedParams['txtSrchNum-' . $currentRow]        = $arrParams['text_' . $i];
                                        break;

                                    case 'date' :
                                    case 'date_repeatable':
                                        $arrPreparedParams['srchDateCondition-' . $currentRow] = $arrParams['filter_' . $i];
                                        if (array_key_exists('date_to_' . $i, $arrParams)) {
                                            $arrPreparedParams['txtSrchDate-' . $currentRow]   = $arrParams['date_from_' . $i];
                                            $arrPreparedParams['txtSrchDateTo-' . $currentRow] = $arrParams['date_to_' . $i];
                                        } else {
                                            $arrPreparedParams['txtSrchDate-' . $currentRow]   = $arrParams['date_' . $i] ?? '';
                                            $arrPreparedParams['txtSrchDateTo-' . $currentRow] = '';
                                        }

                                        $arrPreparedParams['txtNextNum-' . $currentRow]    = $arrParams['date_next_num_' . $i] ?? '';
                                        $arrPreparedParams['txtNextPeriod-' . $currentRow] = $arrParams['date_next_period_' . $i] ?? '';
                                        break;

                                    case 'agents' :
                                        $arrPreparedParams['srchAgentConditions-' . $currentRow] = $arrParams['filter_' . $i];
                                        $arrPreparedParams['srchAgentList-' . $currentRow]       = $arrParams['option_' . $i];
                                        break;

                                    case 'office' :
                                    case 'office_multi' :
                                        $arrPreparedParams['srchDivisionConditions-' . $currentRow] = $arrParams['filter_' . $i];
                                        $arrPreparedParams['txtSrchDivision-' . $currentRow]        = '';
                                        $arrPreparedParams['srchDivisionList-' . $currentRow]       = $arrParams['option_' . $i];
                                        break;

                                    case 'assigned_to' :
                                        $arrPreparedParams['srchStaffConditions-' . $currentRow] = $arrParams['filter_' . $i];
                                        $arrPreparedParams['srchStaffList-' . $currentRow]       = $arrParams['option_' . $i];
                                        break;

                                    case 'combo' :
                                    case 'multiple_combo' :
                                    case 'staff_responsible_rma' :
                                    case 'active_users' :
                                    case 'categories' :
                                    case 'case_type' :
                                    case 'case_status' :
                                    case 'contact_sales_agent' :
                                    case 'authorized_agents' :
                                    case 'employer_contacts' :
                                        $arrPreparedParams['srcTxtCondition-' . $currentRow] = $arrParams['filter_' . $i];
                                        $arrPreparedParams['txtSrchClient-' . $currentRow]   = $arrParams['option_' . $i];
                                        break;

                                    default : //text field
                                        $arrPreparedParams['srcTxtCondition-' . $currentRow] = array_key_exists('filter_' . $i, $arrParams) ? $arrParams['filter_' . $i] : '';
                                        $arrPreparedParams['txtSrchClient-' . $currentRow]   = array_key_exists('text_' . $i, $arrParams) ? $arrParams['text_' . $i] : '';
                                        break;
                                }
                            }

                            $arrSearchResult = $this->advancedSearchProcess($arrPreparedParams);
                            if ($arrSearchResult['error']) {
                                $strError = $arrSearchResult['content'];
                                break;
                            } else {
                                $arrSearchResultGrouped[$i] = $arrSearchResult['members'];
                            }
                        } else {
                            // Prepare search fields for this current row
                            $arrThisRowParams = array(
                                'all_allowed_members' => $arrMemberIds,
                                'userType'            => $arrAllowedMemberTypes,
                                'max_rows_count'      => 1,
                            );

                            foreach ($arrParams as $key => $val) {
                                if (preg_match('/^(operator_|field_|filter_|text_|option_|date_|date_from_|date_to_|date_next_num_|date_next_period_|field_type_|field_client_type_)(\d+)$/', $key, $regs)) {
                                    if ($regs[2] == $i) {
                                        $arrThisRowParams[$regs[1] . '1'] = $val;
                                    }
                                }
                            }

                            $arrSearchResult = array(
                                'error'   => '',
                                'members' => []
                            );

                            // Run search only if there are members and this field isn't encrypted
                            if (!empty($arrMemberIds) && (!isset($arrThisRowParams['field_1']) || !in_array($arrThisRowParams['field_1'], $arrApplicantEncodedFields))) {
                                // Run search for IA/Employer/Contact (and their internal contacts) only
                                $arrSearchResult = $this->runApplicantsAdvancedSearch($arrThisRowParams);
                            }

                            if (!empty($arrSearchResult['error'])) {
                                $strError = $arrSearchResult['error'];
                                break;
                            }

                            if ($booSearchForCases) {
                                $arrParentIds               = $this->_parent->getParentsForAssignedApplicant($arrSearchResult['members']);
                                $arrParentIds               = Settings::arrayUnique(array_merge($arrParentIds, $arrSearchResult['members']));
                                $arrSearchResultGrouped[$i] = $this->_parent->getAssignedApplicants($arrParentIds, $this->_parent->getMemberTypeIdByName('case'));
                            } else {
                                $arrSearchResultGrouped[$i] = $arrSearchResult['members'];
                            }
                        }
                    }


                    $currentRow = 0;
                    for ($i = 1; $i <= $arrParams['max_rows_count']; $i++) {
                        if (array_key_exists('operator_' . $i, $arrParams) && array_key_exists($i, $arrSearchResultGrouped)) {
                            $currentRow++;
                            if ($currentRow == 1) {
                                $arrClientsOrCasesIdsGrouped = $arrSearchResultGrouped[$i];
                            } else {
                                switch ($arrParams['operator_' . $i]) {
                                    case 'and':
                                        if (count($arrClientsOrCasesIdsGrouped)) {
                                            $arrClientsOrCasesIdsGrouped = array_intersect($arrClientsOrCasesIdsGrouped, $arrSearchResultGrouped[$i]);
                                        }
                                        break;

                                    case 'or':
                                        $arrClientsOrCasesIdsGrouped = Settings::arrayUnique(array_merge($arrClientsOrCasesIdsGrouped, $arrSearchResultGrouped[$i]));
                                        break;

                                    default:
                                        break;
                                }
                            }
                        }
                    }
                }

                $arrSavedLinks = [];
                if ($filterClientType == 'employer' && !empty($arrClientsOrCasesIdsGrouped)) {
                    // Remove cases that are not assigned to an employer

                    // Filter cases from the whole list
                    $arrCaseIds = $this->_parent->filterCasesFromTheList($arrClientsOrCasesIdsGrouped);
                    if (!empty($arrCaseIds)) {
                        // Firstly find cases that are assigned to Employers
                        $arrCasesAssignedToEmployers = $this->_parent->getParentsForAssignedApplicants($arrCaseIds, true);

                        // And find out which cases are assigned to individuals only
                        $arrIndividualOnlyCases = array_diff($arrCaseIds, array_keys($arrCasesAssignedToEmployers));

                        // Remove these cases from the result
                        $arrClientsOrCasesIdsGrouped = array_diff($arrClientsOrCasesIdsGrouped, $arrIndividualOnlyCases);
                    }

                    // Load cases which are linked to specific cases only
                    $arrSavedLinks      = $this->_parent->getCasesLinkedEmployerCases($arrClientsOrCasesIdsGrouped);
                    $arrSavedLinksCases = Settings::arrayUnique(Settings::arrayColumn($arrSavedLinks, 'linkedCaseId'));
                    if (!empty($arrSavedLinksCases)) {
                        $arrClientsOrCasesIdsGrouped = Settings::arrayUnique(array_merge($arrClientsOrCasesIdsGrouped, $arrSavedLinksCases));
                    }
                }

                $totalCount = count($arrClientsOrCasesIdsGrouped);
                if ($totalCount) {
                    // Sort records
                    if ($booSearchForCases) {
                        // These are case ids

                        $booLoadCasesParents = false;
                        if ($arrSortInfo['sort'] == 'applicant_internal_id' || in_array('applicant_internal_id', $arrColumns)) {
                            $booLoadCasesParents = true;
                        }

                        // Load all info for all found cases - will be used later
                        $arrCasesDetailedInfo = $this->_parent->getCasesStaticInfo($arrClientsOrCasesIdsGrouped, $booLoadCasesParents);

                        // Filter out clients, leave only cases
                        $caseTypeId = $this->_parent->getMemberTypeIdByName('case');
                        foreach ($arrCasesDetailedInfo as $key => $arrCaseDetailedInfo) {
                            if ($arrCaseDetailedInfo['userType'] != $caseTypeId) {
                                unset($arrCasesDetailedInfo[$key]);
                            }
                        }

                        list($strError, $arrMembersWithData, , $arrAllMembersLoaded) = $this->loadDetailedClientsInfo(
                            $arrCasesDetailedInfo,
                            array($arrSortInfo['sort']),
                            true,
                            $arrSortInfo['start'],
                            $arrSortInfo['limit'],
                            $arrSortInfo['sort'],
                            $arrSortInfo['dir']
                        );

                        // Return case ids only
                        foreach ($arrAllMembersLoaded as $arrAllMembersLoadedInfo) {
                            if (!empty($arrAllMembersLoadedInfo['case_id'])) {
                                $arrAllMemberIds[] = $arrAllMembersLoadedInfo['case_id'];
                            }
                        }
                        $totalCount = count($arrAllMemberIds);

                        if (empty($strError)) {
                            $arrClientsOrCasesIdsGrouped = array();
                            foreach ($arrMembersWithData as $arrCaseSavedData) {
                                if (isset($arrCaseSavedData['case_id'])) {
                                    $arrClientsOrCasesIdsGrouped[] = $arrCaseSavedData['case_id'];
                                }
                            }

                            // Load cases + their parents
                            $arrCasesDetailedInfo = array_filter(
                                $arrCasesDetailedInfo,
                                function ($arrInfo) use ($arrClientsOrCasesIdsGrouped) {
                                    return in_array($arrInfo['member_id'], $arrClientsOrCasesIdsGrouped);
                                }
                            );

                            foreach ($arrCasesDetailedInfo as $clientInfo) {
                                if ($clientInfo['member_id']) {
                                    $arrCasesDetailedInfo[$clientInfo['member_id']] = $this->_parent->generateClientName($clientInfo);
                                }
                            }

                            $applicantColumn = $this->_config['site_version']['version'] == 'australia' ? 'individual_family_name' : 'individual_last_name';
                            if (!in_array($applicantColumn, $arrColumns)) {
                                $arrColumns[] = $applicantColumn;
                            }

                            list($strError, $arrResultMembersWithData, ,) = $this->loadDetailedClientsInfo($arrCasesDetailedInfo, $arrColumns);
                        }
                    } else {
                        // These are clients only
                        $applicantSortFieldId    = 0;
                        $arrSupportedSearchTypes = $searchType == 'contacts' ? array('contact') : $this->_parent->getApplicantFields()->getAdvancedSearchTypesList(true);
                        $applicantSortFieldType  = '';

                        foreach ($arrColumns as $strColumnId) {
                            foreach ($arrSupportedSearchTypes as $searchTypeId) {
                                if (!preg_match('/^' . $searchTypeId . '_(.*)$/', $strColumnId, $regs)) {
                                    continue;
                                }

                                if (!in_array($searchTypeId, array('case', 'accounting')) && $arrSortInfo['sort'] == $searchTypeId . '_' . $regs[1]) {
                                    foreach ($arrApplicantFields as $arrApplicantFieldInfo) {
                                        if ($arrApplicantFieldInfo['applicant_field_unique_id'] == $regs[1]) {
                                            $applicantSortFieldId = $arrApplicantFieldInfo['applicant_field_id'];
                                            if ($regs[1] == 'applicant_internal_id') {
                                                $applicantSortFieldType = $regs[1];
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        $arrAllMemberTypes = $this->_parent->getMemberTypes();
                        $arrAllMemberTypes = Settings::arrayColumnAsKey('member_type_id', $arrAllMemberTypes, 'member_type_name');
                        $arrAllClientsIds = $arrClientsOrCasesIdsGrouped;

                        $arrAssignedInternalContacts = $this->_parent->getAssignedApplicants($arrAllClientsIds, $this->_parent->getMemberTypeIdByName('internal_contact'));
                        if (count($arrAssignedInternalContacts)) {
                            $arrAllClientsIds = Settings::arrayUnique(array_merge($arrAllClientsIds, $arrAssignedInternalContacts));
                        }
                        if (!empty($applicantSortFieldId)) {
                            // Load saved data for found clients

                            $arrParents = $this->_parent->getParentsForAssignedApplicants($arrAllClientsIds);
                            $arrParentIds = array();

                            foreach ($arrParents as $parent) {
                                $arrParentIds[] = $parent['parent_member_id'];
                            }

                            $arrAllAssignedInternalContacts = $this->_parent->getAssignedApplicants($arrParentIds, $this->_parent->getMemberTypeIdByName('internal_contact'));

                            if (count($arrAllAssignedInternalContacts)) {
                                $arrAllClientsIds = Settings::arrayUnique(array_merge($arrAllAssignedInternalContacts, $arrAllClientsIds));
                            }

                            $arrAllApplicantsSavedData = $this->_parent->getApplicantData($companyId, $arrAllClientsIds, true, true, true, false, array($applicantSortFieldId), false, null, true);
                            $arrAllApplicantsSavedData = $this->removeUnnecessarySearchResultValues($arrAllApplicantsSavedData, $arrAllClientsIds, $arrAllowedMemberTypes);

                            if (count($arrAllApplicantsSavedData)) {
                                $arrSort = array();
                                foreach ($arrAllApplicantsSavedData as $key => $arrApplicantSavedData) {
                                    if (isset($arrApplicantSavedData['field_type_text_id']) && $arrApplicantSavedData['field_type_text_id'] == 'date') {
                                        $arrSort[$key] = empty($arrApplicantSavedData['value']) ? '' : strtotime($arrApplicantSavedData['value']);
                                    } else {
                                        if ($applicantSortFieldType == 'applicant_internal_id') {
                                            if ($arrApplicantSavedData['original_user_type'] == $this->_parent->getMemberTypeIdByName('internal_contact')) {
                                                $arrSort[$key] = $arrApplicantSavedData['parent_user_id'];
                                            } else {
                                                $arrSort[$key] = $arrApplicantSavedData['applicant_id'];
                                            }
                                        } else {
                                            $arrSort[$key] = strtolower($arrApplicantSavedData['value'] ?? '');
                                        }
                                    }
                                }

                                // Apply sorting, if there is at least one value
                                array_multisort($arrSort, $arrSortInfo['dir'] == 'ASC' ? SORT_ASC : SORT_DESC, SORT_STRING, $arrAllApplicantsSavedData);
                            }
                        } else {
                            // Load saved data for found clients
                            $arrAllApplicantsSavedData = $this->_parent->getApplicantData($companyId, $arrAllClientsIds, true, true, true, false, array(), false, null, true);
                            $arrAllApplicantsSavedData = $this->removeUnnecessarySearchResultValues($arrAllApplicantsSavedData, $arrAllClientsIds, $arrAllowedMemberTypes);
                        }

                        if (count($arrAllApplicantsSavedData)) {
                            $arrClientsOrCasesIdsGrouped = array();
                            foreach ($arrAllApplicantsSavedData as $arrApplicantSavedData) {
                                if ($arrAllMemberTypes[$arrApplicantSavedData['original_user_type']] == 'internal_contact') {
                                    $parentId = $arrApplicantSavedData['parent_user_id'];
                                } else {
                                    $parentId = $arrApplicantSavedData['applicant_id'];
                                }

                                $arrClientsOrCasesIdsGrouped[] = $parentId;
                            }
                        }

                        // Data is already sorted, we can trim it
                        $arrAllMemberIds = $arrClientsOrCasesIdsGrouped;
                        $totalCount = count($arrAllMemberIds);

                        if (!empty($arrSortInfo['limit'])) {
                            $arrClientsOrCasesIdsGrouped = array_splice($arrClientsOrCasesIdsGrouped, $arrSortInfo['start'], $arrSortInfo['limit']);
                        }


                        // Load clients data only
                        $arrApplicantFieldIds = array();

                        // Group columns - they are required to load data only for them
                        foreach ($arrColumns as $strColumnId) {
                            foreach ($arrSupportedSearchTypes as $searchTypeId) {
                                if (!preg_match('/^' . $searchTypeId . '_(.*)$/', $strColumnId, $regs)) {
                                    continue;
                                }

                                if (!in_array($searchTypeId, array('case', 'accounting'))) {
                                    foreach ($arrApplicantFields as $arrApplicantFieldInfo) {
                                        if ($arrApplicantFieldInfo['applicant_field_unique_id'] == $regs[1]) {
                                            $arrApplicantFieldIds[] = $arrApplicantFieldInfo['applicant_field_id'];
                                        }
                                    }
                                }
                            }
                        }

                        // Try to load internal contacts for found clients
                        $arrAssignedInternalContacts = $this->_parent->getAssignedApplicants($arrClientsOrCasesIdsGrouped, $this->_parent->getMemberTypeIdByName('internal_contact'));
                        if (count($arrAssignedInternalContacts)) {
                            $arrClientsOrCasesIdsGrouped = array_unique(array_merge($arrClientsOrCasesIdsGrouped, $arrAssignedInternalContacts));
                        }

                        // Load saved data for found clients
                        $arrApplicantsData = $this->_parent->getApplicantData($companyId, $arrClientsOrCasesIdsGrouped, true, true, true, false, $arrApplicantFieldIds, true);

                        foreach ($arrApplicantsData as $arrData) {
                            if ($arrAllMemberTypes[$arrData['original_user_type']] == 'internal_contact') {
                                $parentId = $arrData['parent_user_id'];
                                $memberTypeId = $arrData['parent_user_type'];

                                $arrParentNameInfo = array(
                                    'fName' => $arrData['parent_first_name'],
                                    'lName' => $arrData['parent_last_name']
                                );

                                $parentName = $this->_parent->generateApplicantName($arrParentNameInfo);
                            } else {
                                $parentId = $arrData['applicant_id'];
                                $memberTypeId = $arrData['original_user_type'];
                                $parentName = $this->_parent->generateApplicantName($arrData);
                            }

                            // For some reason this internal contact exists without parent
                            if (!in_array($memberTypeId, $arrAllowedMemberTypes)) {
                                continue;
                            }

                            if (isset($arrData['applicant_field_unique_id'])) {
                                $columnId = $arrAllMemberTypes[$memberTypeId] . '_' . $arrData['applicant_field_unique_id'];
                                if (!isset($arrResultMembersWithData[$parentId][$columnId])) {
                                    $arrResultMembersWithData[$parentId][$columnId] = $arrData['value'];
                                }
                            }

                            $arrResultMembersWithData[$parentId][$arrAllMemberTypes[$memberTypeId] . '_' . 'member_id'] = $parentId;
                            $arrResultMembersWithData[$parentId][$arrAllMemberTypes[$memberTypeId] . '_' . 'full_name'] = $parentName;

                            // Generate correct client info
                            // so, we can open the tab in js correctly
                            $arrResultMembersWithData[$parentId]['applicant_type'] = $arrAllMemberTypes[$memberTypeId];
                            $arrResultMembersWithData[$parentId]['applicant_id'] = $parentId;
                            $arrResultMembersWithData[$parentId]['applicant_name'] = $parentName;
                        }
                    }

                    if (empty($strError)) {
                        // Sort result in relation to the clients' ids order (as it was sorted before)
                        uksort(
                            $arrResultMembersWithData,
                            function ($key1, $key2) use ($arrClientsOrCasesIdsGrouped) {
                                return (array_search($key1, $arrClientsOrCasesIdsGrouped) > array_search($key2, $arrClientsOrCasesIdsGrouped));
                            }
                        );

                        if ($filterClientType == 'employer' && !empty($arrResultMembersWithData)) {
                            // Try to show linked cases under the parent case
                            if (!empty($arrSavedLinks)) {
                                $arrTopLevel    = [];
                                $arrSecondLevel = [];
                                foreach ($arrResultMembersWithData as $arrMemberInfo) {
                                    if (isset($arrSavedLinks[$arrMemberInfo['case_id']])) {
                                        $arrMemberInfo['employer_sub_case'] = true;

                                        $arrSecondLevel[$arrSavedLinks[$arrMemberInfo['case_id']]['linkedCaseId']][] = $arrMemberInfo;
                                    } else {
                                        $arrTopLevel[] = $arrMemberInfo;
                                    }
                                }

                                $arrRealSortedData = [];
                                foreach ($arrTopLevel as $arrTopLevelRecord) {
                                    $arrRealSortedData[] = $arrTopLevelRecord;
                                    if (isset($arrSecondLevel[$arrTopLevelRecord['case_id']])) {
                                        foreach ($arrSecondLevel[$arrTopLevelRecord['case_id']] as $arrSecondLevelRecord) {
                                            $arrRealSortedData[] = $arrSecondLevelRecord;
                                        }
                                    }
                                }
                                $arrResultMembersWithData = $arrRealSortedData;
                            }

                            // Additionally, load employer's Doing Business field's value - will be used in the group's title
                            $fieldId = $this->_parent->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('trading_name', Members::getMemberType('employer'), $companyId);
                            if (!empty($fieldId)) {
                                foreach ($arrResultMembersWithData as $key => $arrMemberInfo) {
                                    if (!empty($arrMemberInfo['employer_member_id'])) {
                                        $arrInternalContactIds = $this->_parent->getAssignedApplicants($arrMemberInfo['employer_member_id'], $this->_parent->getMemberTypeIdByName('internal_contact'));
                                        $arrAllIds             = Settings::arrayUnique(array_merge([$arrMemberInfo['employer_member_id']], $arrInternalContactIds));
                                        $arrSavedFieldData     = $this->_parent->getApplicantFields()->getFieldData($fieldId, $arrAllIds);
                                        if (!empty($arrSavedFieldData[0]['value'])) {
                                            $arrResultMembersWithData[$key]['employer_doing_business'] = 'DBA: ' . $arrSavedFieldData[0]['value'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, array_values($arrResultMembersWithData), $totalCount, $arrAllMemberIds);
    }

    /**
     * @param array $arrAllApplicantsSavedData
     * @param array $arrAllClientsIds
     * @param array $arrAllowedMemberTypes
     * @return array
     */
    public function removeUnnecessarySearchResultValues($arrAllApplicantsSavedData, $arrAllClientsIds, $arrAllowedMemberTypes)
    {
        $arrParents = $this->_parent->getParentsForAssignedApplicants($arrAllClientsIds);
        $arrParentIds = array();

        $arrAllApplicantIds = array();

        foreach ($arrAllApplicantsSavedData as $clientData) {
            $arrAllApplicantIds[] = $clientData['applicant_id'];
        }

        $arrAllApplicantsInfo = $this->_parent->getMembersSimpleInfo(array_unique($arrAllApplicantIds));

        foreach ($arrAllApplicantsSavedData as $key => $clientData) {
            $memberId = $clientData['applicant_id'];
            $arrMemberInfo = $arrAllApplicantsInfo[$memberId];
            $memberTypeId = $arrMemberInfo['userType'];

            if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                if (!array_key_exists($memberId, $arrParents)) {
                    unset($arrAllApplicantsSavedData[$key]);
                    continue;
                } else {
                    $parentId = $arrParents[$memberId]['parent_member_id'];
                }
            } else {
                $parentId = $memberId;
            }

            if (isset($arrParentIds[$parentId])) {
                unset($arrAllApplicantsSavedData[$key]);
                continue;
            } else {
                $arrParentIds[$parentId] = 1;
            }

            if (!in_array($memberTypeId, $arrAllowedMemberTypes)) {
                unset($arrAllApplicantsSavedData[$key]);
            }
        }

        return $arrAllApplicantsSavedData;
    }

    /**
     * @param array $arrColumns
     * @param array $arrData
     * @param string $title
     * @return bool|Spreadsheet
     */
    public function exportSearchData($arrColumns, $arrData, $title = '')
    {
        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '-1');
        try {
            $title = $this->_files::checkPhpExcelFileName($title);
            $title = (empty($title) ? 'Search Result' : $title);

            $objPHPExcel = new Spreadsheet();

            $objPHPExcel->setActiveSheetIndex(0);
            $sheet = $objPHPExcel->getActiveSheet()->setTitle($title);

            // Set columns width
            $phpFont = new Font();
            foreach ($arrColumns as $col => $arrColumnInfo) {
                // We pass width in pixels, so we need to convert to excel used format
                $width = Drawing::pixelsToCellDimension($arrColumnInfo['width'], $phpFont);
                $sheet->getColumnDimensionByColumn($col + 1)->setWidth($width);
            }

            $row = 1;

            // Show main title
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                    'color' => array('rgb' => '0000FF'),
                    'size' => 16
                )
            );
            $sheet->setCellValueByColumnAndRow(3, $row, $title);
            $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(3, $row)->getCoordinate();
            $sheet->getStyle($strRow)->applyFromArray($styleArray);
            $sheet->getStyle($strRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;

            // Table Headers
            foreach ($arrColumns as $col => $arrColumnInfo) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $arrColumnInfo['name']);
            }
            $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' .
                $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();

            $sheet->getStyle($strRow)
                ->getFont()
                ->setBold(true);

            $sheet->getStyle($strRow)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;


            // Data
            $dateFormatFull = $this->_settings->variable_get('dateFormatFull');
            $arrDateCells = array();
            foreach ($arrData as $arrRow) {
                foreach ($arrColumns as $col => $arrColumnInfo) {
                    $val = !empty($arrRow[$arrColumnInfo['id']]) ? $arrRow[$arrColumnInfo['id']] : '';
                    if (preg_match('/tag_percentage_(.*)/', $arrColumnInfo['id']) && !empty($val)) {
                        $val .= '%';
                    }

                    // Remember date cells to apply date format for them
                    if (!empty($val) && Settings::isValidDateFormat($val, $dateFormatFull) && strtotime($val)) {
                        $d = DateTime::createFromFormat($dateFormatFull, $val);
                        if ($d && $d->format($dateFormatFull) === $val) {
                            $arrDateCells[] = array($col + 1, $row);

                            // @NOTE: use GMT to be sure timezone is correct...
                            $val = Date::PHPToExcel(strtotime($val . ' GMT'));
                        }
                    }

                    if (is_string($val) && str_starts_with($val, '=')) {
                        // Don't try to save the text as a formula, save as a string
                        $sheet->setCellValueExplicit(
                            $sheet->getCellByColumnAndRow($col + 1, $row)->getCoordinate(),
                            $val,
                            DataType::TYPE_STRING
                        );
                    } else {
                        $sheet->setCellValueByColumnAndRow($col + 1, $row, $val);
                    }
                }

                // Use text format for all cells
                $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' .
                    $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
                $sheet->getStyle($strRow)
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_TEXT);

                $row++;
            }

            // Set date format for date cells
            $excelDateFormat = Settings::getExcelDateFormatFromPhpDateFormat($dateFormatFull);
            foreach ($arrDateCells as $arrCells) {
                $sheet->getStyleByColumnAndRow($arrCells[0], $arrCells[1])
                    ->getNumberFormat()
                    ->setFormatCode($excelDateFormat);
            }

            return $objPHPExcel;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return false;
    }

    /**
     * @param $arrColumns
     * @param $arrData
     * @return array|false
     */
    public function exportSearchDataCSV($arrColumns, $arrData)
    {
        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '-1');

        $arrRows = [];
        try {
            // redirect output to client browser
            $arrColumnsRow = array();
            foreach ($arrColumns as $arrColumnInfo) {
                $arrColumnsRow[] = $arrColumnInfo['name'];
            }
            $arrRows[] = $arrColumnsRow;

            foreach ($arrData as $arrRow) {
                $arrCSVRow = array();
                foreach ($arrColumns as $col => $arrColumnInfo) {
                    $val = !empty($arrRow[$arrColumnInfo['id']]) ? $arrRow[$arrColumnInfo['id']] : '';
                    if (preg_match('/tag_percentage_(.*)/', $arrColumnInfo['id']) && !empty($val)) {
                        $val .= '%';
                    }

                    $arrCSVRow[$col] = $val;
                }
                $arrRows[] = $arrCSVRow;
            }
        } catch (Exception $e) {
            $arrRows = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrRows;
    }

    public function loadDetailedClientsInfo($arrCasesInSelectedOffices, $arrColumns, $booReturnShortInfo = false, $start = 0, $limit = 0, $sort = null, $dir = null, $booLoadParentsWithoutFields = true, $companyId = null, $userId = null)
    {
        $strError = '';
        $arrMembers = array();
        $arrAllMemberIds = array();
        $totalCount = 0;

        try {
            $arrGroupedCasesData = array();

            if (is_null($companyId)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            $arrCaseIds = array_keys($arrCasesInSelectedOffices);
            foreach ($arrCasesInSelectedOffices as $arrClientInfo) {
                if (isset($arrGroupedCasesData[$arrClientInfo['member_id']]) || empty($arrClientInfo['client_type_id'])) {
                    continue;
                }

                $arrGroupedCasesData[$arrClientInfo['member_id']]['case_id']   = $arrClientInfo['member_id'];
                $arrGroupedCasesData[$arrClientInfo['member_id']]['case_name'] = $arrClientInfo['full_name_with_file_num'] ?? '';
                $arrGroupedCasesData[$arrClientInfo['member_id']]['case_type'] = $arrClientInfo['client_type_id'];
            }

            // Load cases' saved data - for visible columns only
            if (count($arrCaseIds)) {
                // Authorized Agent field is a special one
                // It is some kind of the static field, but we need to load data for it in other way
                $authorizedAgentType = $this->_parent->getFieldTypes()->getFieldTypeId('authorized_agents');
                $arrCompanyDivisionGroups = $this->_company->getCompanyDivisions()->getDivisionsGroups($companyId);
                $arrDivisionGroupsNames = array();
                foreach ($arrCompanyDivisionGroups as $arrDivisionGroupInfo) {
                    $arrDivisionGroupsNames[$arrDivisionGroupInfo['division_group_id']] = $arrDivisionGroupInfo['division_group_company'];
                }

                $arrCaseFields = $this->_parent->getFields()->getCompanyFields($companyId);
                $arrStaticFields = $this->_parent->getFields()->getStaticCompanyFieldId();

                // Preload the list of case types, use this info if we want to show in the column
                $arrGroupedCompanyCaseTemplates = [];
                if (in_array('case_case_type', $arrColumns)) {
                    $arrCompanyCaseTemplates = $this->_parent->getCaseTemplates()->getTemplates($companyId, false, null, false, false);
                    foreach ($arrCompanyCaseTemplates as $arrCompanyCaseTemplateInfo) {
                        $arrGroupedCompanyCaseTemplates[$arrCompanyCaseTemplateInfo['case_template_id']] = $arrCompanyCaseTemplateInfo['case_template_name'];
                    }
                }

                $arrFieldIds = array();
                $arrStaticFieldsToLoad = array();
                foreach ($arrColumns as $strColumnId) {
                    // Get real field id by its unique name
                    if (preg_match('/case_(.*)/', $strColumnId, $regs)) {
                        if (in_array($regs[1], $arrStaticFields)) {
                            $arrStaticFieldsToLoad[] = $regs[1];
                        } else {
                            foreach ($arrCaseFields as $arrCaseFieldInfo) {
                                if ('case_' . $arrCaseFieldInfo['company_field_id'] == $strColumnId) {
                                    if ($arrCaseFieldInfo['type'] == $authorizedAgentType) {
                                        foreach ($arrCaseIds as $caseId) {
                                            if (isset($arrDivisionGroupsNames[$arrCasesInSelectedOffices[$caseId]['division_group_id']])) {
                                                $arrGroupedCasesData[$caseId][$strColumnId] = $arrDivisionGroupsNames[$arrCasesInSelectedOffices[$caseId]['division_group_id']];
                                            }
                                        }
                                    } else {
                                        $arrFieldIds[] = $arrCaseFieldInfo['field_id'];
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }

                // Replace static data
                if (count($arrStaticFieldsToLoad)) {
                    $arrStaticFieldsToLoad = array_unique($arrStaticFieldsToLoad);

                    foreach ($arrStaticFieldsToLoad as $staticFieldId) {
                        $staticColumnName = $this->_parent->getFields()->getStaticColumnName($staticFieldId);
                        foreach ($arrCaseIds as $caseId) {
                            if (isset($arrCasesInSelectedOffices[$caseId][$staticColumnName])) {
                                if ($staticFieldId == 'case_type') {
                                    $arrGroupedCasesData[$caseId]['case_' . $staticFieldId] = $arrGroupedCompanyCaseTemplates[$arrCasesInSelectedOffices[$caseId][$staticColumnName]] ?? '';
                                } else {
                                    $arrGroupedCasesData[$caseId]['case_' . $staticFieldId] = $arrCasesInSelectedOffices[$caseId][$staticColumnName];
                                }
                            }
                        }
                    }
                }

                // Load case's client a/c data
                $booLoadAccountingBalancePrimary   = in_array('accounting_outstanding_balance_primary', $arrColumns);
                $booLoadAccountingBalanceSecondary = in_array('accounting_outstanding_balance_secondary', $arrColumns);
                $booLoadAccountingSummaryPrimary   = in_array('accounting_trust_account_summary_primary', $arrColumns);
                $booLoadAccountingSummarySecondary = in_array('accounting_trust_account_summary_secondary', $arrColumns);
                $booLoadAccountingTotalFees        = in_array('accounting_total_fees', $arrColumns);
                $booLoadAccountingTotalFeesPaid    = $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled() && in_array('accounting_total_fees_paid', $arrColumns);

                if ($booLoadAccountingBalancePrimary || $booLoadAccountingBalanceSecondary || $booLoadAccountingSummaryPrimary || $booLoadAccountingSummarySecondary || $booLoadAccountingTotalFees || $booLoadAccountingTotalFeesPaid) {
                    // Load T/A Data only if needed
                    $arrMembersTa = array();
                    $oAccounting  = $this->_parent->getAccounting();
                    if ($booLoadAccountingBalancePrimary || $booLoadAccountingBalanceSecondary || $booLoadAccountingSummaryPrimary || $booLoadAccountingSummarySecondary) {
                        $arrMembersTa = $oAccounting->getMembersTA($arrCaseIds);
                    }

                    foreach ($arrCaseIds as $caseId) {
                        $primaryOB = $secondaryOB = $primaryTASummary = $secondaryTASummary = '';
                        if (isset($arrMembersTa[$caseId])) {
                            foreach ($arrMembersTa[$caseId] as $arrMemberTA) {
                                $booPrimaryTA = empty($arrMemberTA['order']);
                                $currency     = $arrMemberTA['currencyLabel'];

                                if ($booPrimaryTA) {
                                    $primaryOB        = $oAccounting::formatPrice($arrMemberTA['outstanding_balance']) . ' ' . $currency;
                                    $primaryTASummary = $oAccounting::formatPrice($arrMemberTA['sub_total']) . ' ' . $currency;
                                } else {
                                    $secondaryOB        = $oAccounting::formatPrice($arrMemberTA['outstanding_balance']) . ' ' . $currency;
                                    $secondaryTASummary = $oAccounting::formatPrice($arrMemberTA['sub_total']) . ' ' . $currency;
                                }
                            }
                        }

                        // Output only data that was requested
                        if ($booLoadAccountingBalancePrimary) {
                            $arrGroupedCasesData[$caseId]['accounting_outstanding_balance_primary'] = $primaryOB;
                        }

                        if ($booLoadAccountingBalanceSecondary) {
                            $arrGroupedCasesData[$caseId]['accounting_outstanding_balance_secondary'] = $secondaryOB;
                        }

                        if ($booLoadAccountingSummaryPrimary) {
                            $arrGroupedCasesData[$caseId]['accounting_trust_account_summary_primary'] = $primaryTASummary;
                        }

                        if ($booLoadAccountingSummarySecondary) {
                            $arrGroupedCasesData[$caseId]['accounting_trust_account_summary_secondary'] = $secondaryTASummary;
                        }

                        if ($booLoadAccountingTotalFees) {
                            $arrFeesResult                                         = $oAccounting->getClientAccountingFeesList($caseId);
                            $arrGroupedCasesData[$caseId]['accounting_total_fees'] = $oAccounting::formatPrice($arrFeesResult['total'] + $arrFeesResult['total_gst']);
                        }

                        if ($booLoadAccountingTotalFeesPaid) {
                            $arrGroupedCasesData[$caseId]['accounting_total_fees_paid'] = $oAccounting::formatPrice($oAccounting->calculateDepositsTotalFeesPaidByMemberId($caseId));
                        }
                    }
                }

                $arrTagPercentageColumns = array();
                foreach ($arrColumns as $strColumnId) {
                    if (preg_match('/tag_percentage_(.*)/', $strColumnId, $regs)) {
                        $arrTagPercentageColumns[] = $regs[1];
                    }
                }

                if (count($arrTagPercentageColumns)) {
                    $arrAllCasesUploadedFileTags = $this->_parent->getClientDependents()->getClientsUploadedFileTags($arrCaseIds);
                    $arrAllCasesUploadedFilesCount = $this->_parent->getClientDependents()->getClientsUploadedFilesCount($arrCaseIds);

                    foreach ($arrCaseIds as $caseId) {
                        if (isset($arrAllCasesUploadedFilesCount[$caseId]) && isset($arrAllCasesUploadedFileTags[$caseId])) {
                            $arrCaseUploadedFileTags = $arrAllCasesUploadedFileTags[$caseId];
                            $caseUploadedFilesCount = $arrAllCasesUploadedFilesCount[$caseId];
                            $arrUsedTags = array();
                            $arrTagPercentage = array();

                            foreach ($arrCaseUploadedFileTags as $tag) {
                                if (!in_array($tag, $arrUsedTags)) {
                                    $count = 0;
                                    foreach ($arrCaseUploadedFileTags as $caseUploadedFileTag) {
                                        if ($caseUploadedFileTag == $tag) {
                                            $count++;
                                        }
                                    }
                                    $arrTagPercentage[$tag] = round($count / $caseUploadedFilesCount * 100, 2);
                                    $arrUsedTags[] = $tag;
                                }
                            }
                            foreach ($arrTagPercentageColumns as $tagPercentageColumn) {
                                if (isset($arrTagPercentage[$tagPercentageColumn])) {
                                    $arrGroupedCasesData[$caseId]['tag_percentage_' . $tagPercentageColumn] = $arrTagPercentage[$tagPercentageColumn];
                                }
                            }
                        }
                    }
                }

                if (count($arrFieldIds)) {
                    $arrCasesSavedData = $this->_parent->getFields()->getFieldsDataForUserAllowedFields($companyId, $arrCaseIds, $arrFieldIds);

                    foreach ($arrCasesSavedData as $arrSavedData) {
                        $arrGroupedCasesData[$arrSavedData['member_id']]['case_' . $arrSavedData['company_field_id']] = $arrSavedData['value'];
                    }
                }
            }


            // Load saved data for IA, Employer, Contact records and their internal contacts
            // Get parents for found cases
            $arrParents = $this->_parent->getParentsForAssignedApplicants($arrCaseIds, false, false);

            $arrClientIds = array();
            foreach ($arrParents as $arrParentInfo) {
                $arrClientIds[] = (int)$arrParentInfo['parent_member_id'];
            }
            $arrClientIds = Settings::arrayUnique($arrClientIds);

            if (is_array($arrClientIds) && count($arrClientIds)) {
                $arrSupportedSearchTypes = $this->_parent->getApplicantFields()->getAdvancedSearchTypesList(true, true);
                $arrSupportedSearchTypes[] = 'contact';
                $arrApplicantFields = $this->_parent->getApplicantFields()->getCompanyAllFields($companyId);

                $arrApplicantFieldIds = array();
                foreach ($arrColumns as $strColumnId) {
                    foreach ($arrApplicantFields as $arrApplicantFieldInfo) {
                        foreach ($arrSupportedSearchTypes as $searchTypeId) {
                            if ($searchTypeId . '_' . $arrApplicantFieldInfo['applicant_field_unique_id'] == $strColumnId) {
                                $arrApplicantFieldIds[] = $arrApplicantFieldInfo['applicant_field_id'];
                            }
                        }
                    }
                }


                $arrApplicantsData = array();
                $arrAllMemberTypes = array();
                if (count($arrApplicantFieldIds)) {
                    $arrAllMemberTypes = $this->_parent->getMemberTypes();
                    $arrAllMemberTypes = Settings::arrayColumnAsKey('member_type_id', $arrAllMemberTypes, 'member_type_name');

                    // Get internal contact records for parents
                    $arrInternalContactIds = $this->_parent->getAssignedApplicants($arrClientIds, $this->_parent->getMemberTypeIdByName('internal_contact'));

                    $arrApplicantIds = Settings::arrayUnique(array_merge($arrClientIds, $arrInternalContactIds));

                    // Load data for parents + their internal contacts
                    $arrApplicantsData = $this->_parent->getApplicantData($companyId, $arrApplicantIds, true, true, true, false, $arrApplicantFieldIds, $booLoadParentsWithoutFields, $arrInternalContactIds, false, $userId);
                }

                // Group data by cases
                $arrApplicantsGroupedData = array();
                foreach ($arrApplicantsData as $arrData) {
                    if ($arrAllMemberTypes[$arrData['original_user_type']] == 'internal_contact') {
                        $parentId = $arrData['parent_user_id'];
                        $memberTypeId = $arrData['parent_user_type'];

                        $arrParentNameInfo = array(
                            'fName' => $arrData['parent_first_name'],
                            'lName' => $arrData['parent_last_name']
                        );

                        $parentName = $this->_parent->generateApplicantName($arrParentNameInfo);
                    } else {
                        $parentId = $arrData['applicant_id'];
                        $memberTypeId = $arrData['original_user_type'];

                        $parentName = $this->_parent->generateApplicantName($arrData);
                    }

                    if (empty($parentId) || empty($arrAllMemberTypes[$memberTypeId])) {
                        continue;
                    }

                    if (isset($arrData['applicant_field_unique_id'])) {
                        $columnId = $arrAllMemberTypes[$memberTypeId] . '_' . $arrData['applicant_field_unique_id'];
                        $arrApplicantsGroupedData[$parentId][$columnId] = $arrData['value'];
                    }

                    $arrApplicantsGroupedData[$parentId][$arrAllMemberTypes[$memberTypeId] . '_' . 'member_id'] = $parentId;
                    $arrApplicantsGroupedData[$parentId][$arrAllMemberTypes[$memberTypeId] . '_' . 'full_name'] = $parentName;
                }

                $arrResult = array();
                $arrSort = array();
                $booThereIsValueForSorting = false;
                $inputDateFormat = $this->_settings->variable_get('dateFormatFull');
                $inputDateFormatWithTime = $this->_settings->variable_get('dateFormatFull') . ' H:i:s';
                $parentsCount = count($arrParents);
                $sortColumnType = null;
                for ($i = 0; $i < $parentsCount; $i++) {
                    $caseId                      = $arrParents[$i]['child_member_id'];
                    $arrGroupedCaseAndClientData = $arrResult[$caseId] ?? array();

                    if (isset($arrGroupedCasesData[$caseId])) {
                        $arrGroupedCaseAndClientData = array_merge($arrGroupedCaseAndClientData, $arrGroupedCasesData[$caseId]);
                    }

                    if (isset($arrApplicantsGroupedData[$arrParents[$i]['parent_member_id']])) {
                        $arrGroupedCaseAndClientData = array_merge($arrGroupedCaseAndClientData, $arrApplicantsGroupedData[$arrParents[$i]['parent_member_id']]);
                    }

                    // Generate correct parent info
                    // so, we can open the tab in js correctly
                    if (array_key_exists('individual_full_name', $arrGroupedCaseAndClientData)) {
                        $arrGroupedCaseAndClientData['applicant_type'] = 'individual';
                        $arrGroupedCaseAndClientData['applicant_id'] = $arrGroupedCaseAndClientData['individual_member_id'];
                        $arrGroupedCaseAndClientData['applicant_name'] = $arrGroupedCaseAndClientData['individual_full_name'];

                        if (array_key_exists('employer_full_name', $arrGroupedCaseAndClientData)) {
                            $arrGroupedCaseAndClientData['employer_id'] = $arrGroupedCaseAndClientData['employer_member_id'];
                            $arrGroupedCaseAndClientData['employer_name'] = $arrGroupedCaseAndClientData['employer_full_name'];
                        }
                    } elseif (array_key_exists('employer_full_name', $arrGroupedCaseAndClientData)) {
                        $arrGroupedCaseAndClientData['applicant_type'] = 'employer';
                        $arrGroupedCaseAndClientData['applicant_id'] = $arrGroupedCaseAndClientData['employer_member_id'];
                        $arrGroupedCaseAndClientData['applicant_name'] = $arrGroupedCaseAndClientData['employer_full_name'];
                    }

                    if (!empty($arrGroupedCaseAndClientData)) {
                        if (!empty($sort)) {
                            $booThereIsValueForSorting = $booThereIsValueForSorting || isset($arrGroupedCaseAndClientData[$sort]);

                            $arrSort[$caseId] = '';
                            if (isset($arrGroupedCaseAndClientData[$sort])) {
                                if (is_null($sortColumnType) && !empty($arrGroupedCaseAndClientData[$sort])) {
                                    if (Settings::isValidDateFormat($arrGroupedCaseAndClientData[$sort], $inputDateFormat) || Settings::isValidDateFormat($arrGroupedCaseAndClientData[$sort], $inputDateFormatWithTime)) {
                                        $sortColumnType = 'date';
                                    } else {
                                        $sortColumnType = '';
                                    }
                                }

                                switch ($sortColumnType) {
                                    case 'date':
                                        $arrSort[$caseId] = empty($arrGroupedCaseAndClientData[$sort]) ? '' : strtotime($arrGroupedCaseAndClientData[$sort]);
                                        break;

                                    default:
                                        $arrSort[$caseId] = strtolower($arrGroupedCaseAndClientData[$sort] ?? '');
                                        break;
                                }
                            }
                        }

                        $arrResult[$caseId] = $arrGroupedCaseAndClientData;
                    }
                }

                // Apply sorting, if there is at least one value
                if ($booThereIsValueForSorting) {
                    array_multisort($arrSort, $dir == 'ASC' ? SORT_ASC : SORT_DESC, $arrResult);
                }

                $totalCount = count($arrResult);
                if ($booReturnShortInfo) {
                    foreach ($arrResult as $arrResultForShortInfo) {
                        $arrAllMemberIds[] = array(
                            'applicant_id'   => $arrResultForShortInfo['applicant_id'] ?? 0,
                            'applicant_type' => $arrResultForShortInfo['applicant_type'] ?? '',
                            'applicant_name' => $arrResultForShortInfo['applicant_name'] ?? '',
                            'case_id'        => !empty($arrResultForShortInfo['case_id']) ? $arrResultForShortInfo['case_id'] : '',
                            'case_name'      => $arrResultForShortInfo['case_name'] ?? '',
                        );
                    }
                }

                // Use "paging" if needed
                if (empty($limit)) {
                    $arrMembers = $arrResult;
                } else {
                    $arrMembers = array_splice($arrResult, $start, $limit);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $arrMembers, $totalCount, $arrAllMemberIds);
    }


    /**
     * Load clients list for selected offices for provided columns only
     *
     * 1. Load list of clients (cases and their parents) that are in selected offices
     * 2. Load data (differently for cases and for parents)
     * 3. Sort by column
     * 4. Trim
     *
     * @param $arrParams
     * @param bool $booReturnShortInfo
     * @param bool $booAllOffices
     * @param null $companyId
     * @param null $userId
     * @return array
     */
    public function loadClientsForQueueTab($arrParams, $booReturnShortInfo = true, $booAllOffices = false, $companyId = null, $userId = null)
    {
        $strError        = '';
        $arrMembers      = array();
        $arrAllMemberIds = array();
        $totalCount      = 0;

        try {
            $searchType         = isset($arrParams['panelType']) ? Json::decode($arrParams['panelType'], Json::TYPE_ARRAY) : 'applicants';
            $searchType         = in_array($searchType, array('applicants', 'contacts')) ? $searchType : 'applicants';
            $arrSelectedOffices = isset($arrParams['arrOffices']) ? Json::decode($arrParams['arrOffices'], Json::TYPE_ARRAY) : '';
            $arrSelectedOffices = !empty($arrSelectedOffices) ? explode(',', $arrSelectedOffices) : array();
            $arrColumns         = isset($arrParams['arrColumns']) ? Json::decode($arrParams['arrColumns'], Json::TYPE_ARRAY) : array();
            $booShowActiveCases = isset($arrParams['booShowActiveCases']) ? Json::decode($arrParams['booShowActiveCases'], Json::TYPE_ARRAY) : false;
            $arrCaseTypes       = isset($arrParams['caseTypes']) ? Json::decode($arrParams['caseTypes'], Json::TYPE_ARRAY) : array();
            $start              = $arrParams['start'] ?? 0;
            $limit              = $arrParams['limit'] ?? 0;
            $sort               = $arrParams['sort'] ?? 'case_file_number';
            $dir                = $arrParams['dir'] ?? 'DESC';
            $dir                = $dir == 'ASC' ? 'ASC' : 'DESC';

            $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');
            if (!$booAllOffices && (!is_array($arrSelectedOffices) || !count($arrSelectedOffices))) {
                $strError = sprintf($this->_tr->translate('Please select at least one %s.'), $officeLabel);
            }

            // Check access to selected offices
            if (empty($strError)) {
                $arrOfficeIds = $this->_parent->getDivisions(true);
                foreach ($arrSelectedOffices as $selectedOfficeId) {
                    if (!in_array($selectedOfficeId, $arrOfficeIds)) {
                        $strError = sprintf($this->_tr->translate('Incorrectly selected %s.'), $officeLabel);
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $companyId = !is_null($companyId) ? $companyId : $this->_auth->getCurrentUserCompanyId();

                if ($searchType === 'contacts') {
                    // For contacts simulate advanced search
                    $arrCompanyFields = $this->_parent->getApplicantFields()->getCompanyFields(
                        $companyId,
                        $this->_parent->getMemberTypeIdByName('contact')
                    );

                    $arrOfficeFieldInfo = array();
                    foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                        if ($arrCompanyFieldInfo['contact_block'] == 'Y' && in_array($arrCompanyFieldInfo['type'], array('office', 'office_multi'))) {
                            $arrOfficeFieldInfo = $arrCompanyFieldInfo;
                            break;
                        }
                    }

                    if (!empty($arrOfficeFieldInfo)) {
                        $arrSearchParams = array(
                            'max_rows_count'      => 1,
                            'field_client_type_1' => 'contact',
                            'field_type_1'        => 'office_multi',
                            'operator_1'          => 'and',
                            'field_1'             => $arrOfficeFieldInfo['applicant_field_id'],
                            'filter_1'            => 'is_one_of',
                            'option_1'            => implode(';', $arrSelectedOffices),
                            'active-clients'      => false,
                            'related-cases'       => false,

                            'arrSortInfo' => array(
                                'start' => $start,
                                'limit' => $limit,
                                'sort'  => $sort,
                                'dir'   => $dir,
                            ),
                            'searchType'  => $searchType,
                            'columns'     => $arrColumns,
                        );

                        list($strError, $arrMembers, $totalCount, $arrAllMemberIds) = $this->runAdvancedSearch($arrSearchParams, $searchType);
                    }
                } else {
                    // Make sure that search will be done on active cases only
                    $arrFilterCasesIds = $booShowActiveCases ? $this->_parent->getCompanyActiveClientsList($companyId) : array();

                    if ($booAllOffices) {
                        $arrSelectedOffices = null;
                    }

                    $memberTypeName = 'all';
                    if (isset($arrParams['clientType'])) {
                        $memberTypeName = Json::decode($arrParams['clientType'], Json::TYPE_ARRAY);

                        if (!in_array($memberTypeName, ['individual', 'employer']) || !$this->_company->isEmployersModuleEnabledToCompany($companyId)) {
                            $memberTypeName = 'individual';
                        }
                    }

                    // All clients (IA, Employer, Case) that are assigned to selected offices
                    $arrCasesInSelectedOffices = $this->_parent->getClientsList(false, $arrFilterCasesIds, $arrSelectedOffices, false, false, true, $userId, false, $memberTypeName);

                    $arrSavedLinks = [];
                    if ($memberTypeName == 'employer' && !empty($arrCasesInSelectedOffices)) {
                        // Load cases which are linked to specific cases only
                        $arrSavedLinks      = $this->_parent->getCasesLinkedEmployerCases(array_keys($arrCasesInSelectedOffices));
                        $arrSavedLinksCases = Settings::arrayUnique(Settings::arrayColumn($arrSavedLinks, 'linkedCaseId'));
                        if (!empty($arrSavedLinksCases)) {
                            $arrSubCasesInSelectedOffices = $this->_parent->getClientsList(false, $arrSavedLinksCases, $arrSelectedOffices, false, false, true, $userId, false, $memberTypeName);
                            foreach ($arrSubCasesInSelectedOffices as $key => $caseInSelectedOffice) {
                                if (!isset($arrCasesInSelectedOffices[$key])) {
                                    $arrCasesInSelectedOffices[$key] = $caseInSelectedOffice;
                                }
                            }
                        }
                    }

                    if (is_array($arrCaseTypes) && !empty($arrCaseTypes)) {
                        // Make sure that passed case types are correct, remove incorrect ones
                        $arrCompanyCaseTypes = $this->_parent->getCaseTemplates()->getTemplates($companyId, true, null, true, false);
                        $arrCaseTypes        = array_intersect($arrCompanyCaseTypes, $arrCaseTypes);

                        // Don't try to check by case type if all case types were selected
                        if (!empty($arrCaseTypes) && count($arrCaseTypes) != count($arrCompanyCaseTypes)) {
                            foreach ($arrCasesInSelectedOffices as $key => $caseInSelectedOffice) {
                                if (!in_array($caseInSelectedOffice['client_type_id'], $arrCaseTypes)) {
                                    unset($arrCasesInSelectedOffices[$key]);
                                }
                            }
                        }
                    }

                    $totalCount = count($arrCasesInSelectedOffices);

                    if ($totalCount <= 500) {
                        list($strError, $arrMembers, $totalCount, $arrAllMemberIds) = $this->loadDetailedClientsInfo($arrCasesInSelectedOffices, $arrColumns, true, $start, $limit, $sort, $dir, true, $companyId, $userId);
                        // Return case ids only
                        if (!$booReturnShortInfo) {
                            $arrAllMemberIds = array_map(
                                function ($element) {
                                    return $element['case_id'];
                                },
                                $arrAllMemberIds
                            );
                        }
                    } else {
                        list($strError, $arrResultMembersWithData, , $arrAllMemberIds) = $this->loadDetailedClientsInfo(
                            $arrCasesInSelectedOffices,
                            array($sort),
                            true,
                            $start,
                            $limit,
                            $sort,
                            $dir,
                            false,
                            $companyId,
                            $userId
                        );

                        // Return case ids only
                        if (empty($strError) && !$booReturnShortInfo) {
                            $arrAllMemberIds = array_map(
                                function ($element) {
                                    return $element['case_id'];
                                },
                                $arrAllMemberIds
                            );

                            $arrClientsOrCasesIdsGrouped = array();
                            $arrClientsOrCasesIdsGrouped1 = array();
                            foreach ($arrResultMembersWithData as $arrCaseSavedData) {
                                $arrClientsOrCasesIdsGrouped[] = $arrCaseSavedData['case_id'];
                                $arrClientsOrCasesIdsGrouped1[$arrCaseSavedData['case_id']] = '';
                            }

                            // Load cases + their parents
                            $arrCasesInSelectedOffices = array_filter(
                                $arrCasesInSelectedOffices,
                                function ($arrInfo) use ($arrClientsOrCasesIdsGrouped1) {
                                    return isset($arrClientsOrCasesIdsGrouped1[$arrInfo['member_id']]);
                                }
                            );

                            foreach ($arrCasesInSelectedOffices as $clientInfo) {
                                if ($clientInfo['member_id']) {
                                    $arrCasesInSelectedOffices[$clientInfo['member_id']] = $this->_parent->generateClientName($clientInfo);
                                }
                            }

                            list($strError, $arrMembers, ,) = $this->loadDetailedClientsInfo($arrCasesInSelectedOffices, $arrColumns, false, 0, 0, null, null, true, $companyId, $userId);

                            if (empty($strError)) {
                                // Sort result in relation to the clients' ids order (as it was sorted before)
                                $arrMembers = array_replace(array_flip($arrClientsOrCasesIdsGrouped), $arrMembers);
                            }
                        }
                    }

                    if ($memberTypeName == 'employer' && !empty($arrMembers)) {
                        // Try to show linked cases under the parent case
                        $arrTopLevel    = [];
                        $arrSecondLevel = [];
                        foreach ($arrMembers as $arrMemberInfo) {
                            if (isset($arrSavedLinks[$arrMemberInfo['case_id']])) {
                                $arrMemberInfo['employer_sub_case'] = true;

                                $arrSecondLevel[$arrSavedLinks[$arrMemberInfo['case_id']]['linkedCaseId']][] = $arrMemberInfo;
                            } else {
                                $arrTopLevel[] = $arrMemberInfo;
                            }
                        }

                        $arrRealSortedData = [];
                        foreach ($arrTopLevel as $arrTopLevelRecord) {
                            $arrRealSortedData[] = $arrTopLevelRecord;
                            if (isset($arrSecondLevel[$arrTopLevelRecord['case_id']])) {
                                foreach ($arrSecondLevel[$arrTopLevelRecord['case_id']] as $arrSecondLevelRecord) {
                                    $arrRealSortedData[] = $arrSecondLevelRecord;
                                }
                            }
                        }
                        $arrMembers = $arrRealSortedData;

                        // Additionally, load employer's Doing Business field's value - will be used in the group's title
                        $fieldId = $this->_parent->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('trading_name', Members::getMemberType('employer'), $companyId);
                        if (!empty($fieldId)) {
                            foreach ($arrMembers as $key => $arrMemberInfo) {
                                if (!empty($arrMemberInfo['employer_member_id'])) {
                                    $arrInternalContactIds = $this->_parent->getAssignedApplicants($arrMemberInfo['employer_member_id'], $this->_parent->getMemberTypeIdByName('internal_contact'));
                                    $arrAllIds             = Settings::arrayUnique(array_merge([$arrMemberInfo['employer_member_id']], $arrInternalContactIds));
                                    $arrSavedFieldData     = $this->_parent->getApplicantFields()->getFieldData($fieldId, $arrAllIds);
                                    if (!empty($arrSavedFieldData[0]['value'])) {
                                        $arrMembers[$key]['employer_doing_business'] = 'DBA: ' . $arrSavedFieldData[0]['value'];
                                    }
                                }
                            }
                        }
                    }

                    // Reset keys
                    $arrMembers = array_merge($arrMembers);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success' => empty($strError),
            'message' => $strError,
            'items'   => $arrMembers,
            'count'   => $totalCount,
            'all_ids' => $arrAllMemberIds
        );
    }

    /**
     * Load list of favorite searches for the user
     *
     * @param int $memberId
     * @return array
     */
    public function getMemberFavoriteSearches($memberId)
    {
        $arrSearches = array();
        try {
            $select = (new Select())
                ->from(array('s' => 'searches_favorites'))
                ->columns(['search_id'])
                ->where(
                    [
                        's.member_id' => (int)$memberId
                    ]
                );

            $arrSearches = $this->_db2->fetchCol($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $arrSearches;
    }

    /**
     * Check if search is already marked as favorite for the user - unmark it.
     * Otherwise, mark as favorite
     *
     * @param int $memberId
     * @param int $searchId
     * @return bool true if search is now marked as favorite
     */
    public function toggleMemberFavoriteSearch($memberId, $searchId)
    {
        $booIsFavorite = true;
        if (!empty($memberId) && !empty($searchId)) {
            $arrFavoriteSearches = $this->getMemberFavoriteSearches($memberId);
            $booIsFavorite = in_array($searchId, $arrFavoriteSearches);
            if ($booIsFavorite) {
                $this->_db2->delete(
                    'searches_favorites',
                    [
                        'member_id' => (int)$memberId,
                        'search_id' => (int)$searchId
                    ]
                );
            } else {
                $this->_db2->insert(
                    'searches_favorites',
                    [
                        'member_id' => (int)$memberId,
                        'search_id' => (int)$searchId,
                    ]
                );
            }
        }
        return !$booIsFavorite;
    }

}
