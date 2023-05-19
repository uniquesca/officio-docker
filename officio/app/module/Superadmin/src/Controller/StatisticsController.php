<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Settings;
use Officio\Service\Statistics;

/**
 * Accounts
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class StatisticsController extends BaseController
{
    /** @var Statistics $_statistic */
    protected $_statistic;

    public function initAdditionalServices(array $services)
    {
        $this->_statistic = $services[Statistics::class];
    }

    public function indexAction() {
        $view = new ViewModel();
        $strTitle = $this->_tr->translate('Statistics');
        $this->layout()->setVariable('title', $strTitle);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($strTitle);

        $view->setVariable('tableSize', sprintf("%.2fMb", $this->_statistic->getDatabaseSize()));
        $view->setVariable('databaseSize', sprintf("%.2fMb", $this->_statistic->getDatabaseSize(false)));

        return $view;
    }

    public function getAction()
    {
        try {
            $filter = new StripTags();
            $date   = $this->_settings->formatJsonDate($filter->filter(Json::decode($this->params()->fromPost('date'), Json::TYPE_ARRAY)));
            $type   = $filter->filter(Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY));

            $date_format = 'Y-m-d H:i:s';
            if (Settings::isValidDateFormat($date, $date_format)) {
                $loadForDate = date('Y-m-d', strtotime($date));
            } else {
                // Use today
                $loadForDate = date('Y-m-d');
            }

            $arrAllRecords = $this->_statistic->getRecords($loadForDate, $type);

            $arrRecords = array();
            foreach ($arrAllRecords as $arrRecordInfo) {
                $arrRecords[] = array(
                    'time' => $arrRecordInfo['name'] . ':00',
                    'hits' => (int)$arrRecordInfo['hits'],
                );
            }
        } catch (Exception $e) {
            $arrRecords = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($arrRecords);
    }

    public function deleteAction()
    {
        $view = new JsonModel();

        $strMessage = '';

        try {
            $filter = new StripTags();

            $date = $this->_settings->formatJsonDate($filter->filter(Json::decode($this->findParam('date'), Json::TYPE_ARRAY)));

            $this->_statistic->deleteRecords($date);
        } catch (Exception $e) {
            $strMessage = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strMessage), 'message' => $strMessage));
    }
}