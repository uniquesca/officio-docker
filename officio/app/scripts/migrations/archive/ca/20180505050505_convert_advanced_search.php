<?php

use Clients\Service\Clients;
use Officio\Common\Json;
use Officio\Common\Service\Country;
use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ConvertAdvancedSearch extends AbstractMigration
{
    private $caseFields;
    private $caseFieldsOptions;
    private $clientFieldsOptions;
    private $companiesCategoryOptions;

    private function getOptionIdByName($companyId, $fieldId, $clientType, $fieldOptionLabel)
    {
        /** @var Clients $clients */
        $clients = self::getService(Clients::class);

        $fieldOptionId = 0;

        if ($fieldId == 'categories') {
            if (!isset($this->companiesCategoryOptions[$companyId])) {
                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from('company_default_options')
                    ->where(['company_id' => (int)$companyId])
                    ->where(['default_option_type' => 'categories'])
                    ->execute();

                $this->companiesCategoryOptions[$companyId] = $statement->fetchAll('assoc');
            }

            $arrCategoryOptions = $this->companiesCategoryOptions[$companyId];
            foreach ($arrCategoryOptions as $arrCategoryOption) {
                if ($arrCategoryOption['default_option_name'] == $fieldOptionLabel) {
                    $fieldOptionId = $arrCategoryOption['default_option_id'];
                    break;
                }
            }
        } else {
            if ($clientType == 'case') {
                if (!isset($this->caseFieldsOptions[$companyId][$fieldId])) {
                    $this->caseFieldsOptions[$companyId][$fieldId] = $clients->getFields()->getFieldValueByCompanyFieldId($fieldId, $companyId);
                }
                $arrFieldOptions = $this->caseFieldsOptions[$companyId][$fieldId];
            } else {
                if (!isset($this->clientFieldsOptions[$companyId][$fieldId])) {
                    $this->clientFieldsOptions[$companyId][$fieldId] = $clients->getApplicantFields()->getFieldValueByCompanyFieldId($fieldId, $companyId);
                }
                $arrFieldOptions = $this->clientFieldsOptions[$companyId][$fieldId];
            }


            foreach ($arrFieldOptions as $arrFieldOption) {
                if ($arrFieldOption['value'] == $fieldOptionLabel) {
                    $fieldOptionId = isset($arrFieldOption['form_default_id']) ? $arrFieldOption['form_default_id'] : $arrFieldOption['applicant_form_default_id'];
                    break;
                }
            }
        }

        return $fieldOptionId;
    }

    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from(array('s' => 'searches'))
                ->order('company_id')
                ->execute();

            $arrSearches = $statement->fetchAll('assoc');

            /** @var Clients $oClients */
            $oClients    = self::getService(Clients::class);
            $oFieldTypes = $oClients->getFieldTypes();

            /** @var Country $oCountry */
            $oCountry = self::getService(Country::class);

            $conn = $this->getAdapter()->getConnection();
            foreach ($arrSearches as $arrSearchInfo) {
                if (!isset($this->caseFields[$arrSearchInfo['company_id']])) {
                    $this->caseFields[$arrSearchInfo['company_id']] = $oClients->getFields()->getCompanyFields($arrSearchInfo['company_id']);
                }

                $query = Json::decode($arrSearchInfo['query'], Json::TYPE_ARRAY);

                $arrNewQuery = array();

                $count            = 0;
                $booActiveClients = true;
                for ($i = 0; $i < 10; $i++) {
                    if (isset($query['srchField-' . $i])) {
                        $count++;

                        list($fieldId, $fieldType) = explode('|', $query['srchField-' . $i]);

                        $operator = isset($query['match-' . ($i + 1)]) && $query['match-' . ($i + 1)] === 'OR' ? 'or' : 'and';

                        $filter         = null;
                        $text           = null;
                        $date           = null;
                        $dateNextNum    = null;
                        $dateNextPeriod = null;
                        $dateFrom       = null;
                        $dateTo         = null;
                        $option         = null;

                        $clientType = 'case';

                        switch ($fieldId) {
                            case 'ta_total':
                            case 'ob_total':
                                $fieldType = 'special';
                                break;

                            case 'Client_file_status':
                                $fieldType        = 'checkbox';
                                $filter           = $query['txtSrchClient-' . $i] == 'Active' ? 'is_not_empty' : 'is_empty';
                                $booActiveClients = $filter === 'is_not_empty';
                                break;

                            default:
                                $fieldType = $oFieldTypes->getStringFieldTypeById($fieldType);

                                // Check if this field is from case's template
                                $booFound = false;
                                foreach ($this->caseFields[$arrSearchInfo['company_id']] as $arrFieldInfo) {
                                    if ($arrFieldInfo['company_field_id'] == $fieldId) {
                                        $booFound = true;
                                        break;
                                    }
                                }

                                if (!$booFound) {
                                    $clientType = 'individual';
                                }
                                break;
                        }

                        switch ($fieldType) {
                            case 'text':
                            case 'email':
                            case 'phone':
                            case 'memo':
                                $text = $query['txtSrchClient-' . $i];

                                switch ($query['srcTxtCondition-' . $i]) {
                                    case 'is':
                                    case 'contains':
                                        $filter = $query['srcTxtCondition-' . $i];
                                        break;

                                    case "isn't":
                                    case "is not":
                                        $filter = 'is_not';
                                        break;

                                    case "does not contain":
                                    case "doesn't contain":
                                        $filter = 'does_not_contain';
                                        break;

                                    case 'starts with':
                                        $filter = 'starts_with';
                                        break;

                                    case 'ends with':
                                        $filter = 'ends_with';
                                        break;

                                    case 'is empty':
                                        $filter = 'is_empty';
                                        $text   = null;
                                        break;

                                    case 'is not empty':
                                        $filter = 'is_not_empty';
                                        $text   = null;
                                        break;

                                    default:
                                        $filter = 'is';
                                        break;
                                }
                                break;

                            case 'agents':
                                $filter = $query['srchAgentConditions-' . $i];
                                $filter = $filter == 'is' ? $filter : 'is_not';
                                $option = $query['srchAgentList-' . $i]; // NOTE: cannot be converted
                                break;

                            case 'office':
                            case 'office_multi':
                                $fieldType = 'office_multi';
                                $filter    = $query['srchDivisionConditions-' . $i];
                                $filter    = $filter == 'is' || $filter == 'contains' ? 'is' : 'is_not';
                                $option    = $query['srchDivisionList-' . $i];
                                break;

                            case 'combo':
                                $filter = $query['srcTxtCondition-' . $i];
                                $filter = $filter == 'is' ? $filter : 'is_not';
                                $option = $this->getOptionIdByName($arrSearchInfo['company_id'], $fieldId, $clientType, $query['txtSrchClient-' . $i]);
                                break;

                            case 'assigned_to':
                                $filter = $query['srchStaffConditions-' . $i];
                                $filter = $filter == 'is' ? $filter : 'is_not';
                                $option = $query['srchStaffList-' . $i];
                                break;

                            case 'country':
                                $filter = $query['srchCountryConditions-' . $i];
                                $filter = $filter == 'is' ? $filter : 'is_not';
                                $option = $oCountry->getCountryName($query['srchCountryList-' . $i]);
                                break;

                            case 'date':
                            case 'date_repeatable':
                                switch ($query['srchDateCondition-' . $i]) {
                                    case 'is':
                                        $filter = 'is';
                                        $date   = $query['txtSrchDate-' . $i];
                                        $date   = empty($date) ? '' : date('Y-m-d', strtotime($date));
                                        break;

                                    case "is not":
                                    case "isn't":
                                        $filter = 'is_not';
                                        $date   = $query['txtSrchDate-' . $i];
                                        $date   = empty($date) ? '' : date('Y-m-d', strtotime($date));
                                        break;

                                    case 'is before':
                                        $filter = 'is_before';
                                        $date   = $query['txtSrchDate-' . $i];
                                        $date   = empty($date) ? '' : date('Y-m-d', strtotime($date));
                                        break;

                                    case 'is after':
                                        $filter = 'is_after';
                                        $date   = $query['txtSrchDate-' . $i];
                                        $date   = empty($date) ? '' : date('Y-m-d', strtotime($date));
                                        break;

                                    case 'is empty':
                                        $filter = 'is_empty';
                                        break;

                                    case 'is not empty':
                                        $filter = 'is_not_empty';
                                        break;

                                    case 'is between two dates':
                                        $filter   = 'is_between_2_dates';
                                        $dateFrom = $query['txtSrchDate-' . $i];
                                        $dateFrom = empty($dateFrom) ? '' : date('Y-m-d', strtotime($dateFrom));
                                        $dateTo   = $query['txtSrchDateTo-' . $i];
                                        $dateTo   = empty($dateTo) ? '' : date('Y-m-d', strtotime($dateTo));
                                        break;

                                    case 'is between today and a date':
                                        $filter = 'is_between_today_and_date';
                                        $date   = $query['txtSrchDate-' . $i];
                                        $date   = empty($date) ? '' : date('Y-m-d', strtotime($date));
                                        break;

                                    case 'is between a date and today':
                                        $filter = 'is_between_date_and_today';
                                        $date   = $query['txtSrchDate-' . $i];
                                        $date   = empty($date) ? '' : date('Y-m-d', strtotime($date));
                                        break;

                                    case 'is in the next':
                                        $filter      = 'is_in_the_next';
                                        $dateNextNum = $query['txtNextNum-' . $i];

                                        switch ($query['txtNextPeriod-' . $i]) {
                                            case 'DAYS':
                                                $dateNextPeriod = 'D';
                                                break;

                                            case 'WEEKS':
                                                $dateNextPeriod = 'W';
                                                break;

                                            case 'MONTHS':
                                                $dateNextPeriod = 'M';
                                                break;

                                            case 'YEARS':
                                                $dateNextPeriod = 'Y';
                                                break;

                                            default:
                                                break;
                                        }
                                        break;

                                    case 'is in the previous':
                                        $filter      = 'is_in_the_previous';
                                        $dateNextNum = $query['txtNextNum-' . $i];

                                        switch ($query['txtNextPeriod-' . $i]) {
                                            case 'DAYS':
                                                $dateNextPeriod = 'D';
                                                break;

                                            case 'WEEKS':
                                                $dateNextPeriod = 'W';
                                                break;

                                            case 'MONTHS':
                                                $dateNextPeriod = 'M';
                                                break;

                                            case 'YEARS':
                                                $dateNextPeriod = 'Y';
                                                break;

                                            default:
                                                break;
                                        }
                                        break;

                                    case 'is since the start of the year to now':
                                        $filter = 'is_since_start_of_the_year_to_now';
                                        break;

                                    case 'is from today to end of the year':
                                        $filter = 'is_from_today_to_the_end_of_year';
                                        break;

                                    case 'is in this month':
                                        $filter = 'is_in_this_month';
                                        break;

                                    case 'is in this year':
                                        $filter = 'is_in_this_year';
                                        break;

                                    default:
                                        // Can't be here
                                        break;
                                }
                                break;

                            case 'checkbox':
                            case 'password':
                            case 'number':
                            case 'radio':
                            case 'photo':
                            case 'file':
                            case 'special':
                            default:
                                // Not used, can't be here
                                break;
                        }

                        $arrNewQuery['field_type_' . $count]        = $fieldType;
                        $arrNewQuery['operator_' . $count]          = $operator;
                        $arrNewQuery['field_client_type_' . $count] = $clientType;
                        $arrNewQuery['field_' . $count]             = $fieldId;

                        if (!is_null($filter)) {
                            $arrNewQuery['filter_' . $count] = $filter;
                        }

                        if (!is_null($text)) {
                            $arrNewQuery['text_' . $count] = $text;
                        }

                        if (!is_null($dateNextNum)) {
                            $arrNewQuery['date_next_num_' . $count] = $dateNextNum;
                        }

                        if (!is_null($dateNextPeriod)) {
                            $arrNewQuery['date_next_period_' . $count] = $dateNextPeriod;
                        }

                        if (!is_null($date)) {
                            $arrNewQuery['date_' . $count] = $date;
                        }

                        if (!is_null($dateFrom)) {
                            $arrNewQuery['date_from_' . $count] = $dateFrom;
                        }

                        if (!is_null($dateTo)) {
                            $arrNewQuery['date_to_' . $count] = $dateTo;
                        }

                        if (!is_null($option)) {
                            $arrNewQuery['option_' . $count] = $option;
                        }
                    }
                }

                $columns = Json::decode($arrSearchInfo['columns'], Json::TYPE_ARRAY);

                $arrNewColumns = array();
                if (!empty($columns)) {
                    $sortCol = '';
                    foreach ($columns as $columnId) {
                        // Accounting columns have new ids...
                        if (in_array($columnId, array('total_fees', 'ta_summary', 'ta_summaryCND', 'osBalance', 'osBalanceCND'))) {
                            switch ($columnId) {
                                case 'ta_summary':
                                    $columnId = 'trust_account_summary_secondary';
                                    break;

                                case 'ta_summaryCND':
                                    $columnId = 'trust_account_summary_primary';
                                    break;

                                case 'osBalance':
                                    $columnId = 'outstanding_balance_secondary';
                                    break;

                                case 'osBalanceCND':
                                    $columnId = 'outstanding_balance_primary';
                                    break;

                                case 'total_fees':
                                default:
                                    // No changes
                                    break;
                            }
                            $realColumnId = 'accounting_' . $columnId;
                        } else {
                            $booFound = false;
                            foreach ($this->caseFields[$arrSearchInfo['company_id']] as $arrFieldInfo) {
                                if ($arrFieldInfo['company_field_id'] == $columnId) {
                                    $booFound = true;
                                    break;
                                }
                            }

                            $columnClientType = $booFound ? 'case' : 'individual';
                            $realColumnId     = $columnClientType . '_' . $columnId;
                        }

                        // Set Last/First Name as sorting column
                        if ($columnId == 'last_name' || (empty($sortCol) && $columnId == 'first_name')) {
                            $sortCol = $realColumnId;
                        }

                        $arrNewColumns[] = array(
                            'id'    => $realColumnId,
                            'width' => 150
                        );
                    }

                    $arrNewColumns = array(
                        'arrColumns'  => $arrNewColumns,
                        'arrSortInfo' => array(
                            'sort' => empty($sortCol) ? $arrNewColumns[0]['id'] : $sortCol,
                            'dir'  => 'ASC'
                        )
                    );
                }

                $arrNewQuery['max_rows_count'] = $count;
                $arrNewQuery['active-clients'] = $booActiveClients ? '1' : '0';

                $query = sprintf(
                    'UPDATE `searches` SET query = %s, columns = %s WHERE `search_id` = %d',
                    $conn->quote(Json::encode($arrNewQuery)),
                    $conn->quote(Json::encode($arrNewColumns)),
                    $arrSearchInfo['search_id']
                );

                $this->execute($query);
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}