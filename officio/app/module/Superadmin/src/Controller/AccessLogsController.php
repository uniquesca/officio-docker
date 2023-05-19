<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Clients\Service\Members;
use Exception;
use Files\BufferedStream;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Officio\Common\Service\AccessLogs;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Access Logs Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AccessLogsController extends BaseController
{

    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_accessLogs = $services[AccessLogs::class];
        $this->_clients    = $services[Clients::class];
        $this->_company    = $services[Company::class];
    }

    /**
     * Index action - show Access Logs list for the company
     *
     */
    public function indexAction()
    {
        $arrSettings = [];

        try {
            $title = $this->_tr->translate('Events Log');
            $this->layout()->setVariable('title', $title);
            $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

            $arrUsers      = [];
            $arrMemberIds  = $this->_company->getCompanyMembersIds($this->_getCompanyId(), 'admin_and_user');
            $arrSavedUsers = $this->_clients->getMembersInfo($arrMemberIds, false);
            foreach ($arrSavedUsers as $arrSavedUserInfo) {
                $arrUsers[] = [
                    'filter_user_id'  => $arrSavedUserInfo[0],
                    'filter_username' => trim($arrSavedUserInfo[1] ?? ''),
                ];
            }

            $arrSettings = array(
                'logs_per_page'       => AccessLogs::$logsPerPage,
                'view'                => $this->_acl->isAllowed('access-logs-view'),
                'export'              => $this->_acl->isAllowed('access-logs-view'),
                // @TODO: Temporary disallow events removing, even if it is allowed in the role
                'delete'              => false, // $this->_acl->isAllowed('access-logs-delete'),
                'show_company_filter' => $this->_auth->isCurrentUserSuperadmin(),
                'arrLogTypes'         => $this->_accessLogs->getLogTypes(false),
                'arrLogUsers'         => $arrUsers,
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new ViewModel(['arrSettings' => $arrSettings]);
    }

    /**
     * Load current logged-in user company id
     * For superadmin company id can be passed from params
     *
     * @return int company id
     */
    private function _getCompanyId()
    {
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $companyId = $this->_auth->getCurrentUserCompanyId();
        } else {
            // Superadmin
            $companyId = (int)Json::decode($this->params()->fromPost('filter_company'), Json::TYPE_ARRAY);
            if (empty($companyId)) {
                $companyId = 0;
            }
        }

        return $companyId;
    }

    /**
     * Load list of saved Access Log records from DB
     */
    public function listAction()
    {
        set_time_limit(5 * 60); // I said you have 5 minutes, no more
        ini_set('memory_limit', '-1'); // But you can use as many resources, as you can

        // Close session for writing - so next requests can be done
        session_write_close();

        $type     = '';
        $strError = '';

        try {
            $filter    = new StripTags();
            $sort      = $filter->filter($this->params()->fromPost('sort'));
            $dir       = $filter->filter($this->params()->fromPost('dir'));
            $start     = (int)$this->params()->fromPost('start');
            $type      = $this->params()->fromPost('type');
            $limit     = $type === 'export_to_csv' ? 0 : AccessLogs::$logsPerPage;
            $companyId = $this->_getCompanyId();

            $arrFilterData = array(
                'filter_date_by'         => $filter->filter(Json::decode($this->params()->fromPost('filter_date_by'), Json::TYPE_ARRAY)),
                'filter_date_from'       => $filter->filter(Json::decode($this->params()->fromPost('filter_date_from'), Json::TYPE_ARRAY)),
                'filter_company'         => $companyId,
                'filter_date_to'         => $filter->filter(Json::decode($this->params()->fromPost('filter_date_to'), Json::TYPE_ARRAY)),
                'filter_mode_of_payment' => $filter->filter(Json::decode($this->params()->fromPost('filter_mode_of_payment'), Json::TYPE_ARRAY)),
                'filter_product'         => $filter->filter(Json::decode($this->params()->fromPost('filter_product'), Json::TYPE_ARRAY)),
                'filter_type'            => $filter->filter(Json::decode($this->params()->fromPost('filter_type'), Json::TYPE_ARRAY)),
                'filter_user'            => $filter->filter(Json::decode($this->params()->fromPost('filter_user'), Json::TYPE_ARRAY)),
                'filter_case'            => $filter->filter(Json::decode($this->params()->fromPost('filter_case'), Json::TYPE_ARRAY)),
            );

            $arrMemberIds = $this->_company->getCompanyMembersIds($companyId, 'admin_and_user');
            if (empty($companyId) || (!empty($arrFilterData['filter_user']) && !in_array($arrFilterData['filter_user'], $arrMemberIds))) {
                $arrFilterData['filter_user'] = 0;
            }

            if (empty($companyId) || (!empty($arrFilterData['filter_case']) && (!$this->_members->hasCurrentMemberAccessToMember($arrFilterData['filter_case']) || !$this->_clients->isAlowedClient($arrFilterData['filter_case'])))) {
                $arrFilterData['filter_case'] = 0;
            }

            $arrResult = $this->_accessLogs->getLogs($arrFilterData, $sort, $dir, $start, $limit);

            $arrLogs    = array();
            $totalCount = $arrResult['totalCount'];
            foreach ($arrResult['rows'] as $arrSavedLogInfo) {
                // {1} - created by (by default Officio)
                // {2} - applied to
                $createdByName = 'Officio';
                if (!empty($arrSavedLogInfo['byFirstName']) || !empty($arrSavedLogInfo['byLastName'])) {
                    $arrNameInfo = $this->_clients::generateMemberName(
                        array(
                            'fName' => $arrSavedLogInfo['byFirstName'],
                            'lName' => $arrSavedLogInfo['byLastName']
                        )
                    );

                    $createdByName = trim($arrNameInfo['full_name'] ?? '');
                }

                $appliedToName = '';
                $clientName    = '';
                if (!empty($arrSavedLogInfo['toUserType'])) {
                    if (in_array($arrSavedLogInfo['toUserType'], Members::getMemberType('case'))) {
                        list($clientName,) = $this->_clients->getCaseAndClientName($arrSavedLogInfo['log_action_applied_to']);
                        $appliedToName = $clientName;
                    } else {
                        $arrNameInfo = $this->_clients::generateMemberName(
                            array(
                                'fName' => $arrSavedLogInfo['toFirstName'],
                                'lName' => $arrSavedLogInfo['toLastName']
                            )
                        );

                        $appliedToName = trim($arrNameInfo['full_name'] ?? '');
                    }
                }

                $description = $arrSavedLogInfo['log_description'];
                if ($type == 'export_to_csv') {
                    $description = str_replace('{1}', $createdByName, $description);
                    $description = str_replace('{2}', $appliedToName, $description);
                } else {
                    $description = str_replace('{1}', '<b>' . $createdByName . '</b>', $description);
                    $description = str_replace('{2}', '<b>' . $appliedToName . '</b>', $description);
                }

                $arrLogs[] = array(
                    'log_id'          => $arrSavedLogInfo['log_id'],
                    'log_user'        => $createdByName,
                    'log_client'      => $clientName,
                    'log_description' => trim($description ?? ''),
                    'log_created_on'  => $arrSavedLogInfo['log_created_on'],
                    // Do not show the IP by default
                    'log_ip'          => '' // $arrSavedLogInfo['log_ip'],
                );
            }
        } catch (Exception $e) {
            $arrLogs    = array();
            $totalCount = 0;
            $strError   = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrLogs,
            'totalCount' => $totalCount
        );

        if ($type == 'export_to_csv') {
            try {
                if (empty($strError)) {
                    $arrRows = [];

                    $arrColumns = [
                        [
                            'id'   => 'log_user',
                            'name' => $this->_tr->translate('User')
                        ],
                        [
                            'id'   => 'log_description',
                            'name' => $this->_tr->translate('Event Description')
                        ],
                        [
                            'id'   => 'log_client',
                            'name' => $this->_tr->translate('Client/Case')
                        ],
                        [
                            'id'   => 'log_created_on',
                            'name' => $this->_tr->translate('Date')
                        ],
                    ];

                    $arrColumnsRow = array();
                    foreach ($arrColumns as $arrColumnInfo) {
                        $arrColumnsRow[] = $arrColumnInfo['name'];
                    }
                    $arrRows[] = $arrColumnsRow;

                    foreach ($arrResult['rows'] as $arrRow) {
                        $arrCSVRow = array();
                        foreach ($arrColumns as $col => $arrColumnInfo) {
                            $arrCSVRow[$col] = !empty($arrRow[$arrColumnInfo['id']]) ? $arrRow[$arrColumnInfo['id']] : '';
                        }
                        $arrRows[] = $arrCSVRow;
                    }

                    $strFileName    = $this->_tr->translate('events log') . ' ' . date('Y-m-d H:i:s');
                    $disposition    = "attachment; filename=\"$strFileName.csv\"";
                    $pointer        = fopen('php://output', 'wb');
                    $bufferedStream = new BufferedStream('text/csv', null, $disposition);
                    $bufferedStream->setStream($pointer);
                    foreach ($arrRows as $row) {
                        fputcsv($pointer, $row);
                    }
                    $result = $bufferedStream;
                }
            } catch (Exception $e) {
                $strError = $this->_tr->translate('Internal error. Please try again later');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }

            if (!empty($strError) || empty($result)) {
                $view = new ViewModel();
                $view->setTemplate('layout/plain');
                $view->setTerminal(true);
                $view->setVariable('content', $strError);
                $result = $view;
            }
        } else {
            $result = new JsonModel($arrResult);
        }

        return $result;
    }

    /**
     * Delete Access Logs, but before check if user has access to that
     *
     */
    public function deleteAction()
    {
        $strError = '';

        try {
            // @TODO: temporary disabled
            $strError = $this->_tr->translate('Temporary disabled.');

            $arrIds = Json::decode($this->params()->fromPost('ids'), Json::TYPE_ARRAY);
            if (!is_array($arrIds) || !count($arrIds)) {
                $strError = $this->_tr->translate('Please select log record(s) and try again.');
            }

            if (empty($strError) && !$this->_accessLogs->deleteLogRecords($this->_getCompanyId(), $arrIds)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => !empty($strError) ? $strError : $this->_tr->translate('Done!')
        );
        return new JsonModel($arrResult);
    }
}