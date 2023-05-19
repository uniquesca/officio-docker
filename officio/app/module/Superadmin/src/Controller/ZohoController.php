<?php

namespace Superadmin\Controller;

use Exception;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\ZohoKeys;

/**
 * Zoho Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ZohoController extends BaseController
{
    /** @var ZohoKeys */
    private $_zohoKeys;

    public function initAdditionalServices(array $services)
    {
        $this->_zohoKeys = $services[ZohoKeys::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Zoho settings');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('intShowKeysPerPage', $this->_zohoKeys->intShowKeysPerPage);

        return $view;
    }

    public function getKeysListAction()
    {
        $view = new JsonModel();
        try {
            // Get params
            $sort  = $this->findParam('sort');
            $dir   = $this->findParam('dir');
            $start = $this->findParam('start');
            $limit = $this->findParam('limit');

            $arrKeysList = $this->_zohoKeys->getZohoKeysList($sort, $dir, $start, $limit);
        } catch (Exception $e) {
            $arrKeysList = array(
                'rows'       => array(),
                'totalCount' => 0
            );
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrKeysList);
    }

    public function deleteKeysAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $arrKeys = Json::decode($this->findParam('arrKeys'), Json::TYPE_ARRAY);

            if (!$this->_zohoKeys->deleteZohoKeys($arrKeys)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function saveKeyAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $zohoKey         = trim($this->findParam('zohoKey', ''));
            $zohoKeyPrevious = trim($this->findParam('zohoKeyPrevious', ''));
            $zohoKeyStatus   = $this->findParam('zohoKeyStatus');

            if (!empty($zohoKeyPrevious) && !$this->_zohoKeys->exists($zohoKeyPrevious)) {
                $strError = $this->_tr->translate('Key was selected incorrectly.');
            }

            if (empty($strError) && empty($zohoKey)) {
                $strError = $this->_tr->translate('Key is a required field.');
            }

            if (empty($strError) && !empty($zohoKeyPrevious) && $zohoKeyPrevious != $zohoKey && $this->_zohoKeys->exists($zohoKey)) {
                $strError = $this->_tr->translate('Key must be unique.');
            }

            if (empty($strError) && empty($zohoKeyPrevious) && $this->_zohoKeys->exists($zohoKey)) {
                $strError = $this->_tr->translate('Key must be unique.');
            }

            if (empty($strError) && !in_array($zohoKeyStatus, array('enabled', 'disabled'))) {
                $strError = $this->_tr->translate('Status was set incorrectly.');
            }

            if (empty($strError)) {
                if (empty($zohoKeyPrevious)) {
                    $this->_zohoKeys->addZohoKey($zohoKey, $zohoKeyStatus);
                } else {
                    $this->_zohoKeys->updateZohoKey(
                        $zohoKeyPrevious,
                        array(
                            'zoho_key'        => $zohoKey,
                            'zoho_key_status' => $zohoKeyStatus,
                        )
                    );
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
    }
}