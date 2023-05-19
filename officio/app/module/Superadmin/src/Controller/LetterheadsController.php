<?php

namespace Superadmin\Controller;

use Exception;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Service\Letterheads;
use Officio\Common\Service\Settings;

/**
 * Letterheads Controller
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class LetterheadsController extends BaseController
{
    public const A4_MIN_WIDTH = 595; // px
    public const A4_MIN_HEIGHT = 842; // px
    public const LETTER_MIN_WIDTH = 612; // px
    public const LETTER_MIN_HEIGHT = 792; // px
    public const MAX_UPLOAD_FILE_SIZE = 5242880; // 5Mb

    /** @var Letterheads */
    protected $_letterheads;

    /** @var Files */
    protected $_files;

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_files = $services[Files::class];
        $this->_company = $services[Company::class];
        $this->_letterheads = $services[Letterheads::class];
    }

    /**
     * Index action - show Letterheads list for the company
     *
     */
    public function indexAction ()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Letterheads');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    public function getLetterheadAction()
    {
        $view = new JsonModel();
        $booSuccess = false;
        $letterhead = array();
        $strError = '';
        try {
            $letterheadId = $this->findParam('letterhead_id');
            if (!$this->_letterheads->isAllowed($letterheadId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
            if (empty($strError)) {
                $letterhead = $this->_letterheads->getLetterhead($letterheadId);
                if ($letterhead) {
                    $booSuccess = true;
                } else {
                    $strError = $this->_tr->translate('Selected letterhead does not exist');
                }
            }

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $arrResult = array('success' => $booSuccess, 'letterhead' => $letterhead, 'error' => $strError);
        return $view->setVariables($arrResult);
    }

    public function getLetterheadFileAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $letterheadId = $this->findParam('letterhead');
            $fileNumber   = $this->findParam('file');
            $booSmall     = (bool)$this->findParam('small');
            $companyId    = $this->_auth->getCurrentUserCompanyId();
            $booLocal     = $this->_company->isCompanyStorageLocationLocal($companyId);
            if (!$this->_letterheads->isAllowed($letterheadId, $companyId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
            if (empty($strError)) {
                $arrLetterheadFile = $this->_letterheads->getLetterheadFile($letterheadId, $fileNumber);
                $filePath = $this->_files->getCompanyLetterheadsPath($companyId, $booLocal) . '/' . $arrLetterheadFile['letterhead_file_id'];
                if ($booSmall) {
                    $filePath .= '_small';
                }

                if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                    return $this->downloadFile($filePath, $arrLetterheadFile['file_name'], '', false, false);
                } else {
                    $url = $this->_files->getCloud()->getFile($filePath, $arrLetterheadFile['file_name'], false, false);
                    if ($url) {
                        return $this->redirect()->toUrl($url);
                    } else {
                        return $this->fileNotFound();
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }

    public function saveLetterheadAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        $strError = '';

        try {
            //get params
            $filter = new StripTags();
            $arrParams = Settings::filterParamsArray($this->findParams(), $filter);
            $minHeight = $arrParams['type'] == 'a4' ? self::A4_MIN_HEIGHT : self::LETTER_MIN_HEIGHT;
            $minWidth = $arrParams['type'] == 'a4' ? self::A4_MIN_WIDTH : self::LETTER_MIN_WIDTH;

            $filesCount = count($_FILES);
            $companyId  = $this->_auth->getCurrentUserCompanyId();
            if ($arrParams['type_action'] == 'edit' && !$this->_letterheads->isAllowed($arrParams['letterhead_id'], $companyId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                //get files info and size
                $files = array();
                for ($i = 1; $i <= $filesCount; $i++) {

                    $id = 'letterhead-upload-file-' . $i;
                    if (!empty($_FILES[$id]['name']) && !empty($_FILES[$id]['tmp_name'])) {

                        if (!$this->_files->isImage($_FILES[$id]['type'])) {
                            if ($i == 1) {
                                $strError = $this->_tr->translate('Uploaded file for a first page is not an image.');
                            } else {
                                $strError = $this->_tr->translate('Uploaded file for subsequent pages is not an image.');
                            }
                            break;
                        }

                        if (empty($strError) && filesize($_FILES[$id]['tmp_name']) > self::MAX_UPLOAD_FILE_SIZE) {
                            if ($i == 1) {
                                $strError = $this->_tr->translate(sprintf('Uploaded file for a first page is too large. (Max %s).', Settings::formatSize(self::MAX_UPLOAD_FILE_SIZE / 1024)));
                            } else {
                                $strError = $this->_tr->translate(sprintf('Uploaded file for subsequent pages is too large. (Max %s).', Settings::formatSize(self::MAX_UPLOAD_FILE_SIZE / 1024)));
                            }
                            break;
                        }

                        $size = getimagesize($_FILES[$id]['tmp_name']);
                        if ($size && $size[0] < $minWidth || $size[1] < $minHeight) {
                            if ($i == 1) {
                                $strError = $this->_tr->translate(sprintf('Uploaded file for a first page has too small resolution. (Min: %dx%d px).', $minWidth, $minHeight));
                            } else {
                                $strError = $this->_tr->translate(sprintf('Uploaded file for subsequent pages has too small resolution. (Min: %dx%d px).', $minWidth, $minHeight));
                            }
                            break;
                        }
                    }

                    $files[$i]['tmp_name'] = $_FILES[$id]['tmp_name'];
                    $files[$i]['file_name'] = $_FILES[$id]['name'];
                    $files[$i]['size'] = Settings::formatSize($_FILES[$id]['size'] / 1024);
                    $files[$i]['margin_left'] = $arrParams['margin-left-' . $i];
                    $files[$i]['margin_right'] = $arrParams['margin-right-' . $i];
                    $files[$i]['margin_top'] = $arrParams['margin-top-' . $i];
                    $files[$i]['margin_bottom'] = $arrParams['margin-bottom-' . $i];
                    $files[$i]['number'] = $i;
                }
            }

            if (empty($strError)) {
                $memberId = $arrParams['author_id'] ?? $this->_auth->getCurrentUserId();
                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
                $strError = $this->_letterheads->saveLetterhead($arrParams, $files, $memberId, $companyId, $booLocal);
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'error'   => $strError
        );
        return $view->setVariables(['content' => Json::encode($arrResult)]);
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $booSuccess = false;
        $strError = '';
        try {
            $letterheadId = $this->findParam('letterhead_id');
            if (!$this->_letterheads->isAllowed($letterheadId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
            if (empty($strError)) {
                $companyId  = $this->_auth->getCurrentUserCompanyId();
                $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);
                $booSuccess = $this->_letterheads->deleteLetterhead($letterheadId, $companyId, $booLocal);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $view->setVariables(array('success' => $booSuccess, 'error' => $strError));
    }
}

