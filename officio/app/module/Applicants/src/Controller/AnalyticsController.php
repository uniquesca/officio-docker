<?php

namespace Applicants\Controller;

use Clients\Service\Clients;
use Exception;
use Files\BufferedStream;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Clients\Service\Analytics;
use Officio\BaseController;
use Officio\Common\Service\Settings;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Applicants Analytics Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */

class AnalyticsController extends BaseController
{
    /** @var Analytics */
    private $_analytics;

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_analytics = $services[Analytics::class];
        $this->_clients = $services[Clients::class];
    }

    public function loadListAction()
    {
        $view = new JsonModel();
        $strError   = '';
        $arrRecords = array();

        try {
            $filter   = new StripTags();
            $analyticsType = $filter->filter(Json::decode($this->findParam('analytics_type'), Json::TYPE_ARRAY));
            if ($this->_analytics->hasAccessToAnalyticsType($analyticsType, 'analytics-view')) {
                $arrRecords = $this->_analytics->getCompanyAnalytics($this->_auth->getCurrentUserCompanyId(), $analyticsType);
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $arrRecords = array();
            $strError   = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrRecords = array(
            'items' => $arrRecords,
            'count' => count($arrRecords),
        );


        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrRecords['items'],
            'count'   => $arrRecords['count'],
        );

        return $view->setVariables($arrResult);
    }

    private function updateAnalytics($booAddAnalytics)
    {
        $strError    = '';
        $analyticsId = 0;

        try {
            $analyticsType = Json::decode($this->findParam('analytics_type'), Json::TYPE_ARRAY);
            $analyticsId   = $booAddAnalytics ? 0 : Json::decode($this->findParam('analytics_id'), Json::TYPE_ARRAY);

            $aclCheck = empty($analyticsId) ? 'analytics-add' : 'analytics-edit';
            if (empty($strError) && !$this->_analytics->hasAccessToAnalyticsType($analyticsType, $aclCheck)) {
                $strError = $this->_tr->translate('Insufficient access rights (type).');
            }

            if (empty($strError) && !$booAddAnalytics && !$this->_analytics->hasAccessToSavedAnalytics($analyticsId, $analyticsType)) {
                $strError = $this->_tr->translate('Insufficient access rights (id).');
            }

            $filter        = new StripTags();
            $analyticsName = $filter->filter(trim(Json::decode($this->findParam('analytics_name', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && !strlen($analyticsName)) {
                $strError = $this->_tr->translate('Name is a required field.');
            }

            $arrAnalyticsParams = Json::decode($this->findParam('analytics_params'), Json::TYPE_ARRAY);
            $arrAnalyticsParams = Settings::filterParamsArray($arrAnalyticsParams, $filter);
            if (empty($strError)) {
                list($strError, $arrAnalyticsParams) = $this->_analytics->getAnalyticsParams($analyticsType, $arrAnalyticsParams);
            }

            if (empty($strError)) {
                $analyticsId = $this->_analytics->createUpdateAnalytics(
                    $this->_auth->getCurrentUserCompanyId(),
                    $analyticsId,
                    $analyticsName,
                    $analyticsType,
                    $arrAnalyticsParams
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $analyticsId);
    }

    public function addAction()
    {
        $view = new JsonModel();
        list($strError, $analyticsId) = $this->updateAnalytics(true);

        $arrResult = array(
            'success'          => empty($strError),
            'message'          => $strError,
            'savedAnalyticsId' => $analyticsId
        );

        return $view->setVariables($arrResult);
    }

    public function editAction()
    {
        $view = new JsonModel();
        list($strError, $analyticsId) = $this->updateAnalytics(false);

        $arrResult = array(
            'success'          => empty($strError),
            'message'          => $strError,
            'savedAnalyticsId' => $analyticsId
        );

        return $view->setVariables($arrResult);
    }

    public function getAnalyticsDataAction()
    {
        $view = new JsonModel();
        $strError = '';

        $arrData = array(
            'labels'   => array(),
            'datasets' => array(),
        );

        try {
            $panelType = Json::decode($this->findParam('panel_type'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_analytics->hasAccessToAnalyticsType($panelType, 'analytics-view')) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $arrMemberIds = array();
            if (empty($strError)) {
                $booStandalone = Json::decode($this->findParam('standalone'), Json::TYPE_ARRAY);
                if ($booStandalone) {
                    $searchFor    = $this->_clients->getMemberTypeIdByName($panelType === 'contacts' ? 'contact' : 'case');
                    $arrMemberIds = $this->_clients->getMembersWhichICanAccess($searchFor);
                } else {
                    // Check filtering data + access to fields/clients
                    $arrMemberIds = Json::decode($this->findParam('ids'), Json::TYPE_ARRAY);
                    if (!is_array($arrMemberIds) || empty($arrMemberIds)) {
                        $strError = $this->_tr->translate('Incorrectly selected users.');
                    }

                    if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToMember($arrMemberIds)) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }
            }

            $arrBreakdownFieldData     = array();
            $arrBreakdownField2Data    = array();
            $arrBreakdownFieldsFilters = array();
            if (empty($strError)) {
                $filter   = new StripTags();
                $params = array_merge($this->params()->fromPost(), $this->params()->fromQuery());
                $analyticsParams = Settings::filterParamsArray($params, $filter);
                list($strError, $arrBreakdownFieldData, $arrBreakdownField2Data, $arrBreakdownFieldsFilters) = $this->_analytics->checkAnalyticsParams($panelType, $analyticsParams);
            }

            if (empty($strError)) {
                // Supported samples:
                /*
                $arrData = array(
                    'labels'   => array(2011, 2012, 2013),
                    'datasets' => array(
                        array(
                            'label' => 'Country A',
                            'data'  => array(10, 20, 30)
                        ),
                        array(
                            'label' => 'Country B',
                            'data'  => array(15, 12, 18)
                        ),
                        array(
                            'label' => 'Country C',
                            'data'  => array(11, 10, 15)
                        ),
                    )
                );

                $arrData = array(
                    'labels'   => array('Total'),
                    'datasets' => array(
                        array(
                            'label' => 'Country A',
                            'data'  => array(60)
                        ),
                        array(
                            'label' => 'Country B',
                            'data'  => array(45)
                        ),
                        array(
                            'label' => 'Country C',
                            'data'  => array(36)
                        )
                    )
                );*/

                $arrData = $this->_analytics->getAnalyticsData(
                    $arrMemberIds,
                    $arrBreakdownFieldData,
                    $arrBreakdownField2Data,
                    $arrBreakdownFieldsFilters
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'chartData' => $arrData,
            'success'   => empty($strError),
            'message'   => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function exportAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        set_time_limit(5 * 60); // 5 minutes, no more
        ini_set('memory_limit', '-1');

        // Close session for writing - so next requests can be done
        session_write_close();

        $strMessage = '';

        try {
            $filter     = new StripTags();
            $arrColumns = Settings::filterParamsArray(Json::decode($this->findParam('arrColumns'), Json::TYPE_ARRAY), $filter);
            $arrRecords = Settings::filterParamsArray(Json::decode($this->findParam('arrRecords'), Json::TYPE_ARRAY), $filter);

            if (empty($strMessage) && (empty($arrColumns) || !is_array($arrColumns))) {
                $strMessage = $this->_tr->translate('Incorrect columns.');
            }

            if (empty($strMessage) && (empty($arrRecords) || !is_array($arrRecords))) {
                $strMessage = $this->_tr->translate('Incorrect data.');
            }

            if (empty($strMessage)) {
                $title = 'Analytics';
                $fileName = "$title.xlsx";
                $spreadsheet = $this->_clients->getSearch()->exportSearchData($arrColumns, $arrRecords, $title);
                if (!$spreadsheet) {
                    $strMessage = $this->_tr->translate('Internal error.');
                } else {
                    $writer = new Xlsx($spreadsheet);

                    $disposition = "attachment; filename=\"$fileName\"";

                    $pointer = fopen('php://output', 'wb');
                    $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, $disposition);
                    $bufferedStream->setStream($pointer);

                    $writer->save('php://output');
                    fclose($pointer);

                    return $view->setVariable('content', null);
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $strMessage);

        return $view;
    }


    public function deleteAction()
    {
        $view = new JsonModel();

        $strError = '';
        try {
            $analyticsType = Json::decode($this->findParam('analytics_type'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_analytics->hasAccessToAnalyticsType($analyticsType, 'analytics-delete')) {
                $strError = $this->_tr->translate('Insufficient access rights (type).');
            }

            // Check access rights for saved search id
            $analyticsId = (int)Json::decode($this->findParam('analytics_id'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_analytics->hasAccessToSavedAnalytics($analyticsId, $analyticsType)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Delete saved search
            if (empty($strError)) {
                $this->_analytics->delete($analyticsId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
    }
}