<?php

namespace Documents\Controller;

use Exception;
use Files\Service\Files;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;

/**
 * Documents Manager Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManagerController extends BaseController
{
    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_files = $services[Files::class];
    }

    public function uploadImageAction()
    {
        $strError = '';
        $fileName = '';
        $fileUrl  = '';

        try {
            $fileId = 'upload';

            // Make sure a that file was provided
            if (empty($strError) && (empty($_FILES[$fileId]['tmp_name']) || $_FILES[$fileId]['tmp_name'] == 'none')) {
                $strError = $this->_tr->translate('No file was uploaded.');
            }

            if (empty($strError)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();

                $booIsLocal    = $this->_config['html_editor']['storage'] === 'local';
                $pathToDir = $this->_files->getHTMLEditorImagesPath($companyId, $booIsLocal);
                $fileExtension = strtolower(FileTools::getFileExtension($_FILES['upload']['name']) ?? '');
                $fileExtension = empty($fileExtension) ? '' : '.' . $fileExtension;

                // Use a random string + a date/time as the file name
                // @Note: please make sure that file name format is the same as it is used in the deleteImageAction
                $fileName = date('Y-m-d H-i-s') . ' ' . bin2hex(random_bytes(10)) . $fileExtension;

                $arrSavingResult = $this->_files->saveImage(
                    $pathToDir,
                    $fileId,
                    $fileName,
                    array(),
                    $booIsLocal,
                    null,
                    false,
                    false,
                    true
                );

                if ($arrSavingResult['error']) {
                    $strError = $arrSavingResult['result'];
                } else {
                    $fileUrl = $this->_files->getHTMLEditorImagesUrl($this->layout()->getVariable('topBaseUrl'), $companyId, $fileName);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'uploaded' => empty($strError),
            'fileName' => $fileName,

            // Used by Froala
            'link'     => $fileUrl,

            'error' => array(
                'message' => $strError
            )
        );

        return new JsonModel($arrResult);
    }

    public function deleteImageAction()
    {
        $strError = '';

        try {
            $imageUrl = Json::decode($this->params()->fromPost('img', ''), Json::TYPE_ARRAY);
            if (empty($imageUrl)) {
                $strError = $this->_tr->translate('Incorrect image url.');
            } else {
                $imageUrl = urldecode($imageUrl);
            }

            $companyId = $this->_auth->getCurrentUserCompanyId();
            $imagesUrl = $this->_files->getHTMLEditorImagesUrl($this->layout()->getVariable('topBaseUrl', ''), $companyId);

            // Make sure that path starts with the images url
            if (empty($strError)) {
                if (empty($imagesUrl)) {
                    $strError = $this->_tr->translate('Images url not set.');
                } else {
                    // If there is no protocol -> use https
                    if (strpos($imageUrl, '//') === 0) {
                        $imageUrl = 'https:' . $imageUrl;
                    }

                    if (strpos($imageUrl, $imagesUrl) !== 0) {
                        $strError = $this->_tr->translate('Unsupported image url.');
                    }
                }
            }

            // Make sure that file name is in the same format as we support/expect
            $fileName = '';
            if (empty($strError)) {
                $fileName = basename(parse_url($imageUrl, PHP_URL_PATH));

                // @Note: please make sure that file name format is the same as it is defined in the uploadImageAction
                $supportedExtensions = implode('|', Files::SUPPORTED_IMAGE_FROMATS);
                if (!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) [0-9]{2}-[0-9]{2}-[0-9]{2} [[:ascii:]]{20}\.(' . $supportedExtensions . ')$/', $fileName)) {
                    $strError = $this->_tr->translate('Incorrect image file name.');
                }
            }

            // If all checks were passed - delete this file
            if (empty($strError)) {
                $booIsLocal = $this->_config['html_editor']['storage'] === 'local';
                $pathToDir  = $this->_files->getHTMLEditorImagesPath($companyId, $booIsLocal);
                if (!$this->_files->deleteFile($pathToDir . '/' . $fileName, $booIsLocal, true)) {
                    $strError = $this->_tr->translate('Image was not deleted.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return new JsonModel($arrResult);
    }
}
