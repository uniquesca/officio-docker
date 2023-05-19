<?php

namespace Superadmin\Controller;

use Exception;
use Forms\Service\Forms;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\Uri\UriFactory;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Url;

/**
 * URL Checker Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class UrlCheckerController extends BaseController
{

    /** @var Url */
    private $_url;

    /** @var Forms */
    protected $_forms;

    public function initAdditionalServices(array $services)
    {
        $this->_forms = $services[Forms::class];
    }

    public function init()
    {
        $this->_url = new Url($this->_db2, $this->_log);
    }

    public function indexAction()
    {
        return new ViewModel();
    }

    public function getListAction()
    {
        $arrUrls  = array();
        $arrForms = array();

        try {
            $arrUrls  = $this->_url->getList();
            $arrForms = array_merge(array(array('form_id' => 0, 'file_name' => '-- Not Assigned --')), $this->_forms->getFormUpload()->getPdfForms(false));
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrUrls,
            'totalCount' => count($arrUrls),
            'arrForms'   => $arrForms,

        );

        return new JsonModel($arrResult);
    }

    public function saveAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $urlId             = Json::decode($this->params()->fromPost('id'), Json::TYPE_ARRAY);
            $urlAddress        = $filter->filter(Json::decode($this->params()->fromPost('url'), Json::TYPE_ARRAY));
            $urlDescription    = trim($filter->filter(Json::decode($this->params()->fromPost('url_description', ''), Json::TYPE_ARRAY)));
            $urlAssignedFormId = $filter->filter(Json::decode($this->params()->fromPost('assigned_form_id'), Json::TYPE_ARRAY));

            if (!is_numeric($urlId) || $urlId < 0 || $urlId > 100000) {
                $strError = $this->_tr->translate('Incorrect Url id');
            }

            $validator = UriFactory::factory($urlAddress);
            if (empty($strError) && !$validator->isValid()) {
                $strError = $this->_tr->translate('Incorrect Url address');
            }

            if (empty($strError) && !empty($urlAssignedFormId)) {
                $arrForms = $this->_forms->getFormUpload()->getPdfForms();

                $booFound = false;
                foreach ($arrForms as $arrFormInfo) {
                    if ($arrFormInfo['form_id'] == $urlAssignedFormId) {
                        $booFound = true;
                        break;
                    }
                }

                if (!$booFound) {
                    $strError = $this->_tr->translate('Incorrect assigned form');
                }
            }

            if (empty($strError)) {
                $strError = $this->_url->saveUrl($urlId, $urlAddress, $urlDescription, $urlAssignedFormId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function checkAction()
    {
        set_time_limit(5 * 60); // 5 minutes, no more

        $urlId    = 0;
        $urlError = '';
        $urlHash  = '';

        try {
            $arrUrl = Json::decode($this->params()->fromPost('arr_url'), Json::TYPE_ARRAY);

            $strError   = '';
            $textStatus = '';
            if (!is_array($arrUrl) || !count($arrUrl)) {
                $strError = $textStatus = 'Incorrectly selected url addresses';
            }

            if (empty($strError)) {
                // Run request to server
                $arrScanResult = $this->urlChecker($arrUrl['url']);

                $urlId      = $arrUrl['id'];
                $urlOldHash = $arrUrl['hash'];
                $urlError   = $arrScanResult['error'];
                $urlHash    = $arrScanResult['hash'];


                if (!empty($urlError)) {
                    $strColor  = 'orange';
                    $strStatus = 'Error: ' . $urlError;
                } else {
                    if ($urlOldHash != $urlHash) {
                        $strColor  = 'red';
                        $strStatus = 'Changed';
                    } else {
                        $strColor  = '#006600';
                        $strStatus = 'Ok';
                    }
                }
                $textStatus = sprintf('<div style="font-weight: bold;">%s</div>', $arrUrl['url']);
                $textStatus .= sprintf('<div style="color: %s">%s</div>', $strColor, $strStatus);
            }
        } catch (Exception $e) {
            $strError = $textStatus = $e->getMessage();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'     => empty($strError),
            'message'     => $strError,
            'url_id'      => $urlId,
            'url_error'   => $urlError,
            'url_hash'    => $urlHash,
            'text_status' => $textStatus
        );

        return new JsonModel($arrResult);
    }


    public function updateHashAction()
    {
        $strError = '';

        try {
            $arrUrls = Json::decode($this->params()->fromPost('arr_urls'), Json::TYPE_ARRAY);
            if (!is_array($arrUrls) || !count($arrUrls)) {
                $strError = 'Incorrectly selected url addresses';
            }

            if (empty($strError)) {
                foreach ($arrUrls as $arrUrlInfo) {
                    $this->_url->updateHash($arrUrlInfo['id'], $arrUrlInfo['new_hash']);
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError = '';

        try {
            $arrUrls = Json::decode($this->params()->fromPost('arr_urls'), Json::TYPE_ARRAY);

            if (!is_array($arrUrls) || !count($arrUrls)) {
                $strError = 'Incorrectly selected url addresses';
            }

            if (empty($strError)) {
                $strError = $this->_url->deleteUrls($arrUrls);
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}