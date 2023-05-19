<?php

namespace Clients\Service;

use DateInterval;
use DateTime;
use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Settings;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Analytics extends BaseService
{

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
    }

    /**
     * Check if current user has access to specific analytics type
     * e.g. can has access to "applicants" but not to "contacts"
     *
     * @param string $analyticsType
     * @param $what
     * @return bool
     */
    public function hasAccessToAnalyticsType($analyticsType, $what)
    {
        $booHasAccess = false;

        if (in_array($analyticsType, array('applicants', 'contacts'))) {
            $booHasAccess = $this->_acl->isAllowed($analyticsType . '-' . $what);
        }

        return $booHasAccess;
    }


    /**
     * Check if current user has access to saved analytics
     *
     * @param int $analyticsId
     * @param string $analyticsType
     * @return bool true if user has access
     */
    public function hasAccessToSavedAnalytics($analyticsId, $analyticsType)
    {
        $booHasAccess = false;
        if (!empty($analyticsId) && is_numeric($analyticsId)) {
            $arrSearchInfo = $this->getAnalyticsInfo($analyticsId, $analyticsType);
            if (is_array($arrSearchInfo) && count($arrSearchInfo)) {
                $booHasAccess = $this->_auth->getCurrentUserCompanyId() == $arrSearchInfo['company_id'];
            }
        }

        return $booHasAccess;
    }

    /**
     * Get information about saved analytics
     *
     * @param int $analyticsId
     * @param string $analyticsType
     * @param int $companyId
     * @return array
     */
    public function getAnalyticsInfo($analyticsId, $analyticsType, $companyId = null)
    {
        $select = (new Select())
            ->from('analytics')
            ->where(
                [
                    'analytics_type' => $analyticsType,
                    'analytics_id' => (int)$analyticsId
                ]
            );

        if (!is_null($companyId)) {
            $select->where(['company_id' => (int)$companyId]);
        }

        return $this->_db2->fetchRow($select);
    }

    /**
     * Get all analytics for specific company
     *
     * @param int $companyId
     * @param string|array $analyticsType - analytics we need to load
     * @return array
     */
    public function getCompanyAnalytics($companyId, $analyticsType = 'clients')
    {
        $select = (new Select())
            ->from('analytics')
            ->columns(array('analytics_id', 'analytics_name', 'analytics_type', 'analytics_params'))
            ->where(['company_id' => (int)$companyId])
            ->order('analytics_name ASC');

        if (!empty($analyticsType)) {
            $select->where(['analytics_type' => $analyticsType]);
        }

        $arrRecords = $this->_db2->fetchAll($select);
        foreach ($arrRecords as $key => $arrRecord) {
            $arrRecords[$key]['analytics_params'] = Json::decode($arrRecord['analytics_params'], Json::TYPE_ARRAY);
        }

        return $arrRecords;
    }

    /**
     * Create/update analytics record
     *
     * @param int $companyId
     * @param string|int $analyticsId
     * @param string $analyticsName
     * @param string $analyticsType
     * @param array $arrAnalyticsParams
     * @return string|int generated/used id
     */
    public function createUpdateAnalytics($companyId, $analyticsId, $analyticsName, $analyticsType, $arrAnalyticsParams)
    {
        if (empty($analyticsId)) {
            $analyticsId = $this->_db2->insert(
                'analytics',
                [
                    'analytics_type'   => $analyticsType,
                    'company_id'       => $companyId,
                    'analytics_name'   => $analyticsName,
                    'analytics_params' => Json::encode($arrAnalyticsParams),
                ]
            );
        } else {
            $this->_db2->update(
                'analytics',
                [
                    'analytics_name' => $analyticsName,
                    'analytics_params' => Json::encode($arrAnalyticsParams)
                ],
                ['analytics_id' => (int)$analyticsId]
            );
        }

        return $analyticsId;
    }

    /**
     * Check if "breakdown field params" are correct
     *
     * @param array $arrAllSettings
     * @param array $arrBreakdownFieldData
     * @return bool
     */
    private function checkIsCorrectIncomingAnalyticsFieldData($arrAllSettings, $arrBreakdownFieldData)
    {
        $booFoundField = false;

        if ($arrBreakdownFieldData['field_client_type'] == 'case') {
            if (in_array($arrBreakdownFieldData['field_unique_id'], array('created_on', 'ob_total', 'ta_total'))) {
                // Don't check this special field
                $booFoundField = true;
            } elseif (isset($arrAllSettings['case_group_templates'])) {
                foreach ($arrAllSettings['case_group_templates'] as $arrClientTypeInfo) {
                    foreach ($arrClientTypeInfo as $arrClientTypeGroups) {
                        foreach ($arrClientTypeGroups['fields'] as $arrFieldInfo) {
                            if ($arrFieldInfo['field_unique_id'] == $arrBreakdownFieldData['field_unique_id']) {
                                $booFoundField = true;
                                break 3;
                            }
                        }
                    }
                }
            }
        } elseif (isset($arrAllSettings['groups_and_fields'][$arrBreakdownFieldData['field_client_type']])) {
            foreach ($arrAllSettings['groups_and_fields'][$arrBreakdownFieldData['field_client_type']] as $arrClientTypeInfo) {
                if (isset($arrClientTypeInfo['fields'])) {
                    foreach ($arrClientTypeInfo['fields'] as $arrClientTypeGroups) {
                        if (isset($arrClientTypeGroups['group_title'], $arrClientTypeGroups['fields']) && $arrClientTypeGroups['group_title'] == $arrBreakdownFieldData['field_group_name']) {
                            foreach ($arrClientTypeGroups['fields'] as $arrFieldInfo) {
                                if ($arrFieldInfo['field_unique_id'] == $arrBreakdownFieldData['field_unique_id']) {
                                    $booFoundField = true;
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
        }


        return $booFoundField;
    }


    /**
     * Check if filter params for date fields are correct
     *
     * @param array $arrFilterData
     * @return array
     */
    public function checkDateFilters($arrFilterData)
    {
        $strError = '';

        try {
            $arrDateQuarterOptions = array('1', '2', '3', '4');
            $arrDateMonthOptions = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12');
            $arrGoBackPeriodOptions = array('years', 'quarters', 'months');


            switch ($arrFilterData['operator']) {
                case 'years':
                    $booCheckQuarter = false;
                    $booCheckMonth = false;
                    $booCheckYear = !empty($arrFilterData['starting_year']);
                    $booCheckGoBackNumber = !empty($arrFilterData['starting_year']);
                    $booCheckGoBackYear = true;
                    break;

                case 'quarters':
                case 'same_quarter_last_years':
                    $booCheckQuarter = $arrFilterData['starting_year'] !== 'QTD';
                    $booCheckMonth = false;
                    $booCheckYear = !empty($arrFilterData['starting_year']);
                    $booCheckGoBackNumber = !empty($arrFilterData['starting_year']);
                    $booCheckGoBackYear = true;
                    break;

                case 'months':
                case 'same_month_last_years':
                    $booCheckQuarter = false;
                    $booCheckMonth = $arrFilterData['starting_year'] !== 'MTD';
                    $booCheckYear = !empty($arrFilterData['starting_year']);
                    $booCheckGoBackNumber = !empty($arrFilterData['starting_year']);
                    $booCheckGoBackYear = true;
                    break;

                case  'week_days':
                case  'month_days':
                    $booCheckQuarter = false;
                    $booCheckMonth = false;
                    $booCheckYear = false;
                    $booCheckGoBackNumber = false;
                    $booCheckGoBackYear = false;
                    break;

                default:
                    $booCheckQuarter = false;
                    $booCheckMonth = false;
                    $booCheckYear = false;
                    $booCheckGoBackNumber = false;
                    $booCheckGoBackYear = false;

                    $strError = $this->_tr->translate('Incorrect field operator.');
                    break;
            }

            if (empty($strError) && $booCheckQuarter) {
                if (!in_array($arrFilterData['starting_quarter'], $arrDateQuarterOptions)) {
                    $strError = $this->_tr->translate('Incorrect field starting quarter.');
                }
            } else {
                $arrFilterData['starting_quarter'] = '';
            }

            if (empty($strError) && $booCheckMonth) {
                if (!in_array($arrFilterData['starting_month'], $arrDateMonthOptions)) {
                    $strError = $this->_tr->translate('Incorrect field starting month.');
                }
            } else {
                $arrFilterData['starting_month'] = '';
            }

            if (empty($strError) && $booCheckYear) {
                if (is_numeric($arrFilterData['starting_year'])) {
                    $booCorrectYear = $arrFilterData['starting_year'] >= 1900 && $arrFilterData['starting_year'] <= (date('Y', strtotime('+100 years')));
                } else {
                    $booCorrectYear = in_array($arrFilterData['starting_year'], array('YTD', 'QTD', 'MTD'));
                }

                if (!$booCorrectYear) {
                    $strError = $this->_tr->translate('Incorrect field starting year.');
                }
            } else {
                $arrFilterData['starting_year'] = '';
            }


            if (empty($strError) && $booCheckGoBackNumber) {
                if (!is_numeric($arrFilterData['go_back_number']) || $arrFilterData['go_back_number'] < 0 || $arrFilterData['go_back_number'] > 100) {
                    $strError = $this->_tr->translate('Incorrect field "go back number".');
                }
            } else {
                $arrFilterData['go_back_number'] = '';
            }

            if (empty($strError) && $booCheckGoBackYear) {
                if (!in_array($arrFilterData['go_back_period'], $arrGoBackPeriodOptions)) {
                    $strError = $this->_tr->translate('Incorrect field "go back options".');
                }
            } else {
                $arrFilterData['go_back_period'] = '';
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return array($strError, $arrFilterData);
    }


    /**
     * Check all params passed from the Analytics section
     *
     * @param string $panelType
     * @param array $arrParams
     * @return array of string error, "breakdown field data", "breakdown field 2 data", array of filters used for both breakdown fields
     */
    public function checkAnalyticsParams($panelType, $arrParams)
    {
        $strError = '';
        $arrBreakdownFieldData = array();
        $arrBreakdownField2Data = array();
        $arrBreakdownFieldsFilters = array();

        try {
            $oApplicantFields = $this->_clients->getApplicantFields();

            $arrBreakdownFieldData = isset($arrParams['breakdown_field_1']) ? Json::decode($arrParams['breakdown_field_1'], Json::TYPE_ARRAY) : array();
            $arrBreakdownField2Data = isset($arrParams['breakdown_field_2']) ? Json::decode($arrParams['breakdown_field_2'], Json::TYPE_ARRAY) : array();
            for ($i = 1; $i <= 2; $i++) {
                $arrBreakdownFieldsFilters['breakdown_field_' . $i . '_filter'] = array(
                    'operator' => isset($arrParams['breakdown_field_operator_' . $i]) ? Json::decode($arrParams['breakdown_field_operator_' . $i], Json::TYPE_ARRAY) : null,
                    'starting_quarter' => isset($arrParams['breakdown_field_date_starting_quarter_' . $i]) ? Json::decode($arrParams['breakdown_field_date_starting_quarter_' . $i], Json::TYPE_ARRAY) : null,
                    'starting_month' => isset($arrParams['breakdown_field_date_starting_month_' . $i]) ? Json::decode($arrParams['breakdown_field_date_starting_month_' . $i], Json::TYPE_ARRAY) : null,
                    'starting_year' => isset($arrParams['breakdown_field_date_starting_year_' . $i]) ? Json::decode($arrParams['breakdown_field_date_starting_year_' . $i], Json::TYPE_ARRAY) : null,
                    'go_back_number' => isset($arrParams['breakdown_field_go_back_number_' . $i]) ? (int)Json::decode($arrParams['breakdown_field_go_back_number_' . $i], Json::TYPE_ARRAY) : null,
                    'go_back_period' => isset($arrParams['breakdown_field_go_back_period_' . $i]) ? Json::decode($arrParams['breakdown_field_go_back_period_' . $i], Json::TYPE_ARRAY) : null,
                );
            }

            if (empty($arrBreakdownFieldData) || !is_array($arrBreakdownFieldData) || !isset($arrBreakdownFieldData['field_client_type'])) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            $arrAllowedClientTypes = $panelType === 'contacts' ? array('contact') : $oApplicantFields->getAdvancedSearchTypesList(true);
            if (empty($strError) && !in_array($arrBreakdownFieldData['field_client_type'], $arrAllowedClientTypes)) {
                $strError = $this->_tr->translate('Incorrect field client type.');
            }

            if (empty($strError)) {
                $id = isset($arrBreakdownFieldData['field_type']) ? $this->_clients->getFieldTypes()->getFieldTypeIdByTextId($arrBreakdownFieldData['field_type'], 'all', true) : '';
                if (empty($id) && $arrBreakdownFieldData['field_type'] != 'special') {
                    $strError = $this->_tr->translate('Incorrect field type.');
                }
            }

            if (empty($strError) && $this->_clients->getFieldTypes()->isDateFieldByTextType($arrBreakdownFieldData['field_type'])) {
                list($strError, $arrBreakdownFieldsFilters['breakdown_field_1_filter']) = $this->checkDateFilters($arrBreakdownFieldsFilters['breakdown_field_1_filter']);
            }

            $arrAllSettings = array();
            if (empty($strError)) {
                if (empty($arrBreakdownFieldData['field_unique_id'])) {
                    $strError = $this->_tr->translate('Incorrect field id.');
                } else {
                    // Load all fields/groups for all case/client types (with correct access rights)
                    // And check if fields that were passed are correct
                    $arrAllSettings = $this->_clients->getSettings(
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_auth->getCurrentUserDivisionGroupId()
                    );

                    if (!in_array($arrBreakdownFieldData['field_unique_id'], array('ob_total', 'ta_total')) && !$this->checkIsCorrectIncomingAnalyticsFieldData($arrAllSettings, $arrBreakdownFieldData)) {
                        $strError = $this->_tr->translate('Incorrect field id.');
                    }
                }
            }

            if (empty($strError)) {
                if (!empty($arrBreakdownField2Data) && is_array($arrBreakdownField2Data) && isset($arrBreakdownField2Data['field_client_type'])) {
                    if (!in_array($arrBreakdownField2Data['field_client_type'], $arrAllowedClientTypes)) {
                        $strError = $this->_tr->translate('Incorrect field client type.');
                    }

                    if (empty($strError)) {
                        $breakdownField2Type = $arrBreakdownField2Data['field_type'] ?? '';
                        $id                  = !empty($breakdownField2Type) ? $this->_clients->getFieldTypes()->getFieldTypeIdByTextId($breakdownField2Type, 'all', true) : '';
                        if (empty($id) && $breakdownField2Type != 'special') {
                            $strError = $this->_tr->translate('Incorrect field 2 type.');
                        }
                    }

                    if (empty($strError) && !$this->checkIsCorrectIncomingAnalyticsFieldData($arrAllSettings, $arrBreakdownField2Data)) {
                        $strError = $this->_tr->translate('Incorrect field 2 id.');
                    }

                    if (empty($strError) && $this->_clients->getFieldTypes()->isDateFieldByTextType($arrBreakdownField2Data['field_type'])) {
                        list($strError, $arrBreakdownFieldsFilters['breakdown_field_2_filter']) = $this->checkDateFilters($arrBreakdownFieldsFilters['breakdown_field_2_filter']);
                    }
                } else {
                    $arrBreakdownField2Data = array();
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return array(
            $strError,
            $arrBreakdownFieldData,
            $arrBreakdownField2Data,
            $arrBreakdownFieldsFilters
        );
    }


    /**
     * Check and get analytics fields prepared for saving
     *
     * @param string $panelType
     * @param array $arrAnalyticsParams
     * @return array
     */
    public function getAnalyticsParams($panelType, $arrAnalyticsParams)
    {
        $arrCheckedParams = array();

        try {
            list($strError, $arrBreakdownFieldData, $arrBreakdownField2Data, $arrBreakdownFieldsFilters) = $this->checkAnalyticsParams($panelType, $arrAnalyticsParams);

            if (empty($strError)) {
                for ($i = 1; $i <= 2; $i++) {
                    $analyticsFieldToCheck = 'breakdown_field_' . $i;

                    $arrFieldInfo = $i == 1 ? $arrBreakdownFieldData : $arrBreakdownField2Data;

                    if (!empty($arrFieldInfo)) {
                        $arrCheckedParams[$analyticsFieldToCheck] = $arrFieldInfo['field_client_type'] . '_' . $arrFieldInfo['field_unique_id'];

                        $arrResultChecked = $arrBreakdownFieldsFilters[$analyticsFieldToCheck . '_filter'];
                        if (isset($arrResultChecked['operator'])) {
                            $arrCheckedParams['breakdown_field_operator_' . $i] = $arrResultChecked['operator'];
                        }

                        if (isset($arrResultChecked['starting_quarter'])) {
                            $arrCheckedParams['breakdown_field_date_starting_quarter_' . $i] = $arrResultChecked['starting_quarter'];
                        }

                        if (isset($arrResultChecked['starting_month'])) {
                            $arrCheckedParams['breakdown_field_date_starting_month_' . $i] = $arrResultChecked['starting_month'];
                        }

                        if (isset($arrResultChecked['starting_year'])) {
                            $arrCheckedParams['breakdown_field_date_starting_year_' . $i] = $arrResultChecked['starting_year'];
                        }

                        if (isset($arrResultChecked['go_back_number'])) {
                            $arrCheckedParams['breakdown_field_go_back_number_' . $i] = $arrResultChecked['go_back_number'];
                        }

                        if (isset($arrResultChecked['go_back_period'])) {
                            $arrCheckedParams['breakdown_field_go_back_period_' . $i] = $arrResultChecked['go_back_period'];
                        }
                    }
                }

                if (!empty($arrCheckedParams) && isset($arrAnalyticsParams['show_chart'])) {
                    $arrCheckedParams['show_chart'] = (bool)$arrAnalyticsParams['show_chart'];
                }

                if (!empty($arrCheckedParams) && isset($arrAnalyticsParams['chart_type']) && in_array($arrAnalyticsParams['chart_type'], array('bar', 'doughnut_full', 'doughnut_half', 'pie_full', 'pie_half'))) {
                    $arrCheckedParams['chart_type'] = $arrAnalyticsParams['chart_type'];
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $arrCheckedParams);
    }

    /**
     * Load and group client's/case's data for specific field
     *
     * @param int $companyId
     * @param array $arrMemberIds
     * @param string $breakdownFieldFullId
     * @param string $breakdownFieldType
     * @param array $arrBreakdownFieldsFilters
     * @return array
     */
    private function getGroupedAnalyticsData($companyId, $arrMemberIds, $breakdownFieldFullId, $breakdownFieldType, $arrBreakdownFieldsFilters)
    {
        // Load all info for all found cases - will be used later
        $arrCasesDetailedInfo = $this->_clients->getCasesStaticInfo($arrMemberIds);

        list($strError, $arrMembersWithData) = $this->_clients->getSearch()->loadDetailedClientsInfo(
            $arrCasesDetailedInfo,
            array($breakdownFieldFullId),
            false,
            null,
            null,
            null,
            null,
            true,
            $companyId
        );

        $arrDataGrouped = array();
        if (empty($strError)) {
            $arrPeriods = array();
            switch ($arrBreakdownFieldsFilters['operator']) {
                case 'years':
                    if (!empty($arrBreakdownFieldsFilters['starting_year']) && $arrBreakdownFieldsFilters['go_back_number'] >= 0) {
                        if ($arrBreakdownFieldsFilters['starting_year'] === 'YTD') {
                            // @Example: Today is Nov 11, 2018. Filter for: (Jan 1 to Nov 11, 2018), (Jan 1 to Nov 11, 2017), (Jan 1 to Nov 11, 2016), etc.
                            for ($i = 0; $i <= $arrBreakdownFieldsFilters['go_back_number']; $i++) {
                                $year = date('Y') - $i;

                                $arrPeriods[] = array(
                                    'from' => new DateTime($year . '-01-01 00:00:00'),
                                    'to' => new DateTime($year . '-' . date('m-d') . ' 23:59:59')
                                );
                            }
                        } else {
                            // @Example: Today is Nov 11, 2018. Filter for: (Jan 1 2018 - X to Dec 31, 2018)
                            $arrPeriods[] = array(
                                'from' => new DateTime(($arrBreakdownFieldsFilters['starting_year'] - $arrBreakdownFieldsFilters['go_back_number']) . '-01-01 00:00:00'),
                                'to' => new DateTime($arrBreakdownFieldsFilters['starting_year'] . '-12-31 23:59:59')
                            );
                        }
                    }
                    break;

                case 'quarters':
                case 'same_quarter_last_years':
                    if (!empty($arrBreakdownFieldsFilters['starting_year']) && $arrBreakdownFieldsFilters['go_back_number'] >= 0) {
                        if ($arrBreakdownFieldsFilters['starting_year'] === 'QTD') {
                            // @Example: Today is Nov 11, 2018. Filter for: (Oct 1 to Nov 11, 2018), (Jul 1 to Aug 11, 2018), (Apr 1 to May 11, 2018), etc.
                            $now = new DateTime();
                            $date = new DateTime();
                            $months = $arrBreakdownFieldsFilters['operator'] === 'quarters' ? 3 : 12;

                            // We will use this quarter during the checking
                            $arrBreakdownFieldsFilters['starting_quarter'] = ceil($now->format('n') / 3);
                            for ($i = 0; $i <= $arrBreakdownFieldsFilters['go_back_number']; $i++) {
                                $year = $date->format('Y');
                                $month = $date->format('m');
                                switch (ceil($date->format('n') / 3)) {
                                    case 1:
                                        $arrPeriods[] = array(
                                            'from' => new DateTime($year . '-01-01 00:00:00'),
                                            'to' => new DateTime($year . '-' . $month . '-' . $date->format('d') . ' 23:59:59')
                                        );
                                        break;

                                    case 2:
                                        $arrPeriods[] = array(
                                            'from' => new DateTime($year . '-04-01 00:00:00'),
                                            'to' => new DateTime($year . '-' . $month . '-' . $date->format('d') . ' 23:59:59')
                                        );
                                        break;

                                    case 3:
                                        $arrPeriods[] = array(
                                            'from' => new DateTime($year . '-07-01 00:00:00'),
                                            'to' => new DateTime($year . '-' . $month . '-' . $date->format('d') . ' 23:59:59')
                                        );
                                        break;

                                    case 4:
                                        $arrPeriods[] = array(
                                            'from' => new DateTime($year . '-10-01 00:00:00'),
                                            'to' => new DateTime($year . '-' . $month . '-' . $date->format('d') . ' 23:59:59')
                                        );
                                        break;

                                    default:
                                        break;
                                }

                                // Roll back for the quarter (3 months) or year (12 months)
                                $date = Settings::addMonths(-1 * $months * ($i + 1), $now->format('Y-m-d'));
                            }
                        } elseif (!empty($arrBreakdownFieldsFilters['starting_quarter'])) {
                            // @Example: Starting 2018 Q4, go back 1 Q. Filter for: (Jul 1 to Dec 31, 2018)
                            switch ($arrBreakdownFieldsFilters['starting_quarter']) {
                                case 1:
                                    $dateFrom = $arrBreakdownFieldsFilters['starting_year'] . '-01-01';
                                    $dateTo = $arrBreakdownFieldsFilters['starting_year'] . '-03-31';
                                    break;

                                case 2:
                                    $dateFrom = $arrBreakdownFieldsFilters['starting_year'] . '-04-01';
                                    $dateTo = $arrBreakdownFieldsFilters['starting_year'] . '-06-30';
                                    break;

                                case 3:
                                    $dateFrom = $arrBreakdownFieldsFilters['starting_year'] . '-07-01';
                                    $dateTo = $arrBreakdownFieldsFilters['starting_year'] . '-09-30';
                                    break;

                                case 4:
                                    $dateFrom = $arrBreakdownFieldsFilters['starting_year'] . '-10-01';
                                    $dateTo = $arrBreakdownFieldsFilters['starting_year'] . '-12-31';
                                    break;

                                default:
                                    $dateFrom = '';
                                    $dateTo = '';
                                    break;
                            }

                            if (!empty($dateFrom) && !empty($dateTo)) {
                                $from = new DateTime($dateFrom . ' 00:00:00');

                                if (!empty($arrBreakdownFieldsFilters['go_back_number'])) {
                                    $period = $arrBreakdownFieldsFilters['operator'] === 'quarters' ? ($arrBreakdownFieldsFilters['go_back_number'] * 3) . 'M' : $arrBreakdownFieldsFilters['go_back_number'] . 'Y';
                                    $from->sub(new DateInterval('P' . $period));
                                }

                                $arrPeriods[] = array(
                                    'from' => $from,
                                    'to' => new DateTime($dateTo . ' 23:59:59')
                                );
                            }
                        }
                    }
                    break;

                case 'months':
                case 'same_month_last_years':
                    if (!empty($arrBreakdownFieldsFilters['starting_year']) && $arrBreakdownFieldsFilters['go_back_number'] >= 0) {
                        if ($arrBreakdownFieldsFilters['starting_year'] === 'MTD') {
                            // @Example: Today is Nov 11, 2018. Filter for: (Nov 1 to Nov 11, 2018), (Oct 1 to Oct 11, 2018), (Sep 1 to Sep 11, 2018), etc.
                            $now = new DateTime();
                            $date = new DateTime();
                            $months = $arrBreakdownFieldsFilters['operator'] === 'months' ? 1 : 12;

                            // We will use this month during the checking
                            $arrBreakdownFieldsFilters['starting_month'] = $now->format('n');
                            for ($i = 0; $i <= $arrBreakdownFieldsFilters['go_back_number']; $i++) {
                                $arrPeriods[] = array(
                                    'from' => new DateTime($date->format('Y-m') . '-01 00:00:00'),
                                    'to' => new DateTime($date->format('Y-m-d') . ' 23:59:59')
                                );

                                $date = Settings::addMonths(-1 * $months * ($i + 1), $now->format('Y-m-d'));
                            }
                        } elseif (!empty($arrBreakdownFieldsFilters['starting_month'])) {
                            // @Example: Starting 2018 Nov, go back 1 Month. Filter for: (Oct 1, 2018 to Nov 30, 2018)
                            $from = new DateTime($arrBreakdownFieldsFilters['starting_year'] . '-' . $arrBreakdownFieldsFilters['starting_month'] . '-01 00:00:00');
                            $to = new DateTime($from->format('Y-m-t 23:59:59'));

                            if (!empty($arrBreakdownFieldsFilters['go_back_number'])) {
                                $period = $arrBreakdownFieldsFilters['operator'] === 'months' ? $arrBreakdownFieldsFilters['go_back_number'] . 'M' : $arrBreakdownFieldsFilters['go_back_number'] . 'Y';
                                $from->sub(new DateInterval('P' . $period));
                            }

                            $arrPeriods[] = array(
                                'from' => $from,
                                'to' => $to
                            );
                        }
                    }
                    break;

                case 'month_days':
                case 'week_days':
                default:
                    break;
            }


            foreach ($arrMembersWithData as $arrData) {
                $label = '';
                $booSkipThisValue = false;
                if (isset($arrData[$breakdownFieldFullId])) {
                    switch ($breakdownFieldType) {
                        case 'date_repeatable':
                        case 'date':
                            $oRecordDate = new DateTime($arrData[$breakdownFieldFullId]);

                            if (empty($arrPeriods)) {
                                $booInRange = true;
                            } else {
                                $booInRange = false;
                                foreach ($arrPeriods as $arrPeriod) {
                                    if ($oRecordDate >= $arrPeriod['from'] && $oRecordDate <= $arrPeriod['to']) {
                                        $booInRange = true;
                                        break;
                                    }
                                }
                            }

                            switch ($arrBreakdownFieldsFilters['operator']) {
                                case 'years':
                                    $label = $oRecordDate->format('Y');

                                    if (!$booInRange) {
                                        $booSkipThisValue = true;
                                    }
                                    break;

                                case 'quarters':
                                case 'same_quarter_last_years':
                                    $label = $oRecordDate->format('Y') . ' Q' . ceil($oRecordDate->format('n') / 3);

                                    if (!empty($arrPeriods)) {
                                        if (!$booInRange) {
                                            $booSkipThisValue = true;
                                        } elseif ($arrBreakdownFieldsFilters['operator'] === 'same_quarter_last_years') {
                                            $booSkipThisValue = ceil($oRecordDate->format('n') / 3) != $arrBreakdownFieldsFilters['starting_quarter'];
                                        }
                                    }
                                    break;

                                case 'months':
                                case 'same_month_last_years':
                                    $label = $oRecordDate->format('Y') . ' ' . $oRecordDate->format('M');

                                    if (!empty($arrPeriods)) {
                                        if (!$booInRange) {
                                            $booSkipThisValue = true;
                                        } elseif ($arrBreakdownFieldsFilters['operator'] === 'same_month_last_years') {
                                            $booSkipThisValue = $oRecordDate->format('n') != $arrBreakdownFieldsFilters['starting_month'];
                                        }
                                    }
                                    break;

                                case 'week_days':
                                    $label = $oRecordDate->format('l');
                                    break;

                                case 'month_days':
                                    $label = $oRecordDate->format('d');
                                    break;

                                default:
                                    $label = $arrData[$breakdownFieldFullId];
                                    break;
                            }
                            break;

                        default:
                            $label = $arrData[$breakdownFieldFullId];
                            break;
                    }
                } elseif (!empty($arrPeriods)) {
                    // Value wasn't set for this field and filtering was used
                    $booSkipThisValue = true;
                }

                if (!$booSkipThisValue) {
                    if (isset($arrDataGrouped[$label])) {
                        $arrDataGrouped[$label][] = $arrData['case_id'];
                    } else {
                        $arrDataGrouped[$label] = array($arrData['case_id']);
                    }
                }
            }

            if (in_array($arrBreakdownFieldsFilters['operator'], array('months', 'same_month_last_years'))) {
                // Sort Months in other way
                uksort(
                    $arrDataGrouped,
                    function ($a, $b) {
                        $dateA = DateTime::createFromFormat('Y M', empty($a) ? '1900 Jan' : $a);
                        $dateB = DateTime::createFromFormat('Y M', empty($b) ? '1900 Jan' : $b);

                        return $dateA->getTimestamp() > $dateB->getTimestamp() ? -1 : 1;
                    }
                );
            } else {
                // We should support php 5.3, that's why we use uksort
                // krsort($arrDataGrouped, SORT_NATURAL | SORT_FLAG_CASE);
                uksort(
                    $arrDataGrouped,
                    function ($a, $b) {
                        return strcasecmp($b, $a);
                    }
                );
            }
        }

        return $arrDataGrouped;
    }


    /**
     * Load analytics (grouped) data for clients
     *
     * @param array $arrMemberIds
     * @param array $arrBreakdownFieldData
     * @param array $arrBreakdownField2Data
     * @param array $arrBreakdownFieldsFilters
     * @return array
     */
    public function getAnalyticsData($arrMemberIds, $arrBreakdownFieldData, $arrBreakdownField2Data, $arrBreakdownFieldsFilters)
    {
        $arrData = array(
            'labels' => array(),
            'datasets' => array(),
        );

        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            if ($arrBreakdownFieldData['field_client_type'] == 'contact') {
                $arrMemberIds = $this->_clients->getAssignedContacts($arrMemberIds, true);
            } else {
                $arrMemberIds = $this->_clients->getCasesFromTheList(
                    $arrMemberIds,
                    $arrBreakdownFieldData['field_client_type'] == 'case' || (isset($arrBreakdownField2Data['field_client_type']) && $arrBreakdownField2Data['field_client_type'] == 'case')
                );
            }


            // Load grouped data for the first passed field
            $arrDataGrouped = $this->getGroupedAnalyticsData(
                $companyId,
                $arrMemberIds,
                $arrBreakdownFieldData['field_client_type'] . '_' . $arrBreakdownFieldData['field_unique_id'],
                $arrBreakdownFieldData['field_type'],
                $arrBreakdownFieldsFilters['breakdown_field_1_filter']
            );

            if (empty($arrBreakdownField2Data)) {
                $arrData['labels'][] = 'Total';
                foreach ($arrDataGrouped as $label => $arrIds) {
                    $label = empty($label) ? '[Not set]' : $label;

                    $arrData['datasets'][] = array(
                        'label' => $label,
                        'data' => array(count($arrIds))
                    );
                }
            } else {
                // Load grouped data for the second passed field
                $arrDataGrouped2 = $this->getGroupedAnalyticsData(
                    $companyId,
                    $arrMemberIds,
                    $arrBreakdownField2Data['field_client_type'] . '_' . $arrBreakdownField2Data['field_unique_id'],
                    $arrBreakdownField2Data['field_type'],
                    $arrBreakdownFieldsFilters['breakdown_field_2_filter']
                );

                $arrMainGrouping = array();
                foreach ($arrDataGrouped2 as $groupField2 => $arrSecondIds) {
                    $label = empty($groupField2) ? '[Not set]' : $groupField2;
                    if (!in_array($label, $arrData['labels'])) {
                        $arrData['labels'][] = $label;
                    }

                    foreach ($arrSecondIds as $secondId) {
                        foreach ($arrDataGrouped as $groupField1 => $arrFirstIds) {
                            $label1 = empty($groupField1) ? '[Not set]' : $groupField1;

                            if (!isset($arrMainGrouping[$label1][$label])) {
                                $arrMainGrouping[$label1][$label] = 0;
                            }

                            if (in_array($secondId, $arrFirstIds)) {
                                if (isset($arrMainGrouping[$label1][$label])) {
                                    ++$arrMainGrouping[$label1][$label];
                                } else {
                                    $arrMainGrouping[$label1][$label] = 1;
                                }
                            }
                        }
                    }
                }

                foreach ($arrMainGrouping as $groupField2 => $arrGroupField1Data) {
                    $arrData['datasets'][] = array(
                        'label' => $groupField2,
                        'data' => array_values($arrGroupField1Data)
                    );
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrData;
    }

    /**
     * Delete saved analytics
     *
     * @param int $analyticsId
     * @return bool true on success
     */
    public function delete($analyticsId)
    {
        try {
            $this->_db2->delete('analytics', ['analytics_id' => (int)$analyticsId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Copy default analytics to specific company
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     */
    public function createDefaultCompanyAnalytics($fromCompanyId, $toCompanyId)
    {
        $arrAllRecords = $this->getCompanyAnalytics($fromCompanyId, '');
        foreach ($arrAllRecords as $arrRecord) {
            $this->createUpdateAnalytics(
                $toCompanyId,
                0,
                $arrRecord['analytics_name'],
                $arrRecord['analytics_type'],
                $arrRecord['analytics_params']
            );
        }
    }
}
