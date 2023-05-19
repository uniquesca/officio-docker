<?php

namespace Clients\Controller;

use Clients\Service\Clients;
use Exception;
use Files\BufferedStream;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Service\GstHst;
use Officio\Common\Service\Settings;
use Clients\Service\TimeTracker;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Uniques\Php\StdLib\FileTools;

/**
 * Clients Time Tracker Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class TimeTrackerController extends BaseController
{
    /** @var TimeTracker */
    private $_tracker;

    /** @var Company */
    protected $_company;

    /** @var GstHst */
    protected $_gstHst;

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_tracker = $services[TimeTracker::class];
        $this->_gstHst  = $services[GstHst::class];
        $this->_files   = $services[Files::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    /**
     * This action is used when we load records in the grid
     */
    public function getListAction()
    {
        $strError = '';
        $list     = array(
            'items'      => array(),
            'allIds'     => array(),
            'count'      => 0,
            'totalHours' => 0,
            'totalRate'  => 0,
        );

        try {
            $clientId  = (int)$this->params()->fromPost('clientId');
            $companyId = (int)$this->params()->fromPost('companyId');

            if (($this->_auth->isCurrentUserSuperadmin() && !$companyId) || (!$this->_auth->isCurrentUserSuperadmin() && !$this->_members->hasCurrentMemberAccessToMember($clientId))) {
                $strError = $this->_tr->translate('Internal error');
            }

            $postedBy = $this->params()->fromPost('tracker-filter-owner');
            if (empty($strError) && !empty($postedBy) && !$this->_members->hasCurrentMemberAccessToMember($postedBy)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $filter  = new StripTags();
                $filters = array(
                    'client_id'           => $clientId,
                    'company_id'          => $companyId,
                    'billed'              => $filter->filter($this->params()->fromPost('tracker-filter-billed')),
                    'posted_by_member_id' => $postedBy,
                    'date_from'           => $filter->filter($this->params()->fromPost('tracker-filter-date-from')),
                    'date_to'             => $filter->filter($this->params()->fromPost('tracker-filter-date-to')),
                );

                $list = $this->_tracker->getList(
                    $filters,
                    $filter->filter($this->params()->fromPost('sort')),
                    $this->params()->fromPost('dir'),
                    $this->params()->fromPost('start', 0),
                    $this->params()->fromPost('limit', 1000)
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'msg'        => $strError,
            'items'      => $list['items'],
            'count'      => $list['count'],
            'totalHours' => $list['totalHours'],
            'totalRate'  => $list['totalRate'],
            'allIds'     => $list['allIds']
        );

        return new JsonModel($arrResult);
    }

    /**
     * This action is used when we create record from the auto popup dialog
     */
    public function createAction()
    {
        return new JsonModel($this->_tracker->addedit($this->params()->fromPost()));
    }

    /**
     * This action is used when we create record from the dialog showed by clicking on Add button (grid toolbar)
     */
    public function addAction()
    {
        return new JsonModel($this->_tracker->addedit($this->params()->fromPost()));
    }

    /**
     * This action is used when we update record from the dialog showed by clicking on Edit button (grid toolbar)
     */
    public function editAction()
    {
        return new JsonModel($this->_tracker->addedit($this->params()->fromPost()));
    }

    /**
     * This action is used when we delete record(s) from the grid - by clicking on Delete button (grid toolbar)
     */
    public function deleteAction()
    {
        $strError = '';

        try {
            $ids = Json::decode($this->params()->fromPost('track_ids'), Json::TYPE_ARRAY);

            if (!$this->_auth->isCurrentUserSuperadmin()) {
                $arrClientIds = array_unique($this->_tracker->getClientsIdsByItemsIds($ids));

                if (count($arrClientIds) !== 1 || !$this->_members->hasCurrentMemberAccessToMember($arrClientIds[0])) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            if (empty($strError)) {
                $this->_tracker->deleteItems($ids);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function markBilledAction()
    {
        $strError = '';

        try {
            $filter          = new StripTags();
            $ids             = Json::decode($this->params()->fromPost('track_ids'), Json::TYPE_ARRAY);
            $memberId        = (int)$this->params()->fromPost('client_id');
            $companyTAId     = (int)$this->params()->fromPost('ta_id');
            $amount          = (double)$this->params()->fromPost('due');
            $description     = $filter->filter($this->params()->fromPost('desc'));
            $type            = 'add-fee-due';
            $date            = date('Y-m-d H:i:s', strtotime($this->params()->fromPost('date')));
            $payment_made_by = '';
            $gst             = 0;
            $gst_province_id = 0;
            $gst_tax_label   = '';
            $notes           = '';

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('You do not have access to this Case');
            }

            if (empty($strError)) {
                $gst_province_id = (int)Json::decode($this->params()->fromPost('gst_province_id'), Json::TYPE_ARRAY);

                if (!is_numeric($gst_province_id)) {
                    $strError = $this->_tr->translate('Incorrectly selected GST');
                } elseif (!empty($gst_province_id)) {
                    $arrProvinceInfo = $this->_gstHst->getProvinceById($gst_province_id);

                    if (empty($arrProvinceInfo)) {
                        $strError = $this->_tr->translate('Incorrectly selected GST');
                    } else {
                        $gst           = $arrProvinceInfo['rate'];
                        $gst_tax_label = $arrProvinceInfo['tax_label'];
                    }
                }
            }

            if (empty($strError) && !$this->_auth->isCurrentUserSuperadmin()) {
                $arrClientIds = array_unique($this->_tracker->getClientsIdsByItemsIds($ids));

                if (count($arrClientIds) !== 1 || !$this->_members->hasCurrentMemberAccessToMember($arrClientIds[0])) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            if (empty($strError)) {
                // mark as billed
                $this->_tracker->markBilled($ids);

                $this->_clients->getAccounting()->addFee(
                    $companyTAId,
                    $memberId,
                    $amount,
                    $description,
                    $type,
                    $date,
                    $payment_made_by,
                    $gst,
                    $gst_province_id,
                    $gst_tax_label,
                    $notes
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function printAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $filter = new StripTags();

        $ids        = Json::decode($this->params()->fromPost('track_ids'), Json::TYPE_ARRAY);
        $title      = $filter->filter(Json::decode($this->params()->fromPost('title'), Json::TYPE_ARRAY));
        $sort       = $filter->filter($this->params()->fromPost('sort'));
        $sort_where = $filter->filter($this->params()->fromPost('sort_where'));
        $arrColumns = Settings::filterParamsArray(Json::decode($this->params()->fromPost('columns'), Json::TYPE_ARRAY), $filter);

        $items = $this->_tracker->getList(array('ids_list' => $ids), $sort, $sort_where, 0, 999999);

        $companyId      = $this->_auth->getCurrentUserCompanyId();
        $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
        $imgSrc         = $this->_company->getCompanyLogoData($arrCompanyInfo);

        $view->setVariable('items', $items['items']);
        $this->layout()->setVariable('title', $title);
        $view->setVariable('src', $imgSrc);
        $view->setVariable('columns', $arrColumns);

        return $view;
    }

    public function timeLogSummaryLoadAction()
    {
        $strError = '';
        $arrItems = [];
        $count    = 0;

        try {
            $postedBy = $this->params()->fromPost('tracker-filter-owner');
            if (!empty($postedBy) && !$this->_members->hasCurrentMemberAccessToMember($postedBy)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $filter  = new StripTags();
                $filters = array(
                    'client_company_id'   => $this->_auth->getCurrentUserCompanyId(),
                    'billed'              => $filter->filter($this->params()->fromPost('tracker-filter-billed')),
                    'posted_by_member_id' => $postedBy,
                    'date_from'           => $filter->filter($this->params()->fromPost('tracker-filter-date-from')),
                    'date_to'             => $filter->filter($this->params()->fromPost('tracker-filter-date-to')),
                );

                $list = $this->_tracker->getList(
                    $filters,
                    $filter->filter($this->params()->fromPost('sort')),
                    $filter->filter($this->params()->fromPost('dir')),
                    $this->params()->fromPost('start', 0),
                    $this->params()->fromPost('limit', 1000)
                );

                $arrItems = $list['items'];
                $count    = $list['count'];

                foreach ($arrItems as $key => $arrItemInfo) {
                    $arrParents = $this->_clients->getParentsForAssignedApplicants(array($arrItemInfo['track_member_id']), false, false);

                    $arrItems[$key]['track_client_id'] = $arrParents[0]['parent_member_id'];
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrItems,
            'count'   => $count
        );

        return new JsonModel($arrResult);
    }

    public function timeLogSummaryExportAction()
    {
        $strError = '';
        ini_set('memory_limit', '-1');

        try {
            $postedBy = $this->params()->fromPost('tracker-filter-owner');
            if (!empty($postedBy) && !$this->_members->hasCurrentMemberAccessToMember($postedBy)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            $fileName    = '';
            $spreadsheet = '';
            if (empty($strError)) {
                $filter    = new StripTags();
                $arrFilter = array(
                    'billed'              => $filter->filter($this->params()->fromPost('tracker-filter-billed')),
                    'posted_by_member_id' => $postedBy,
                    'date_from'           => $filter->filter($this->params()->fromPost('tracker-filter-date-from')),
                    'date_to'             => $filter->filter($this->params()->fromPost('tracker-filter-date-to')),

                    'sort' => $filter->filter($this->params()->fromPost('sort')),
                    'dir'  => $filter->filter($this->params()->fromPost('dir')),
                );

                $companyId = $this->_auth->getCurrentUserCompanyId();
                $result    = $this->_company->getCompanyExport()->export($companyId, 'time_log', 0, 0, 0, $arrFilter);
                if (is_array($result)) {
                    list($fileName, $spreadsheet) = $result;
                } else {
                    $strError = $result;
                }
            }

            if (empty($strError)) {
                $pointer        = fopen('php://output', 'wb');
                $bufferedStream = new BufferedStream(FileTools::getMimeByFileName($fileName), null, "attachment; filename=\"$fileName\"");
                $bufferedStream->setStream($pointer);

                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                fclose($pointer);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel(['content' => $strError]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

}