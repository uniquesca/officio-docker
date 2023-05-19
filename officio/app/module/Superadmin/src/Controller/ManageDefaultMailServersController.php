<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Email\ServerSuggestions;

/**
 * Manage Mail Server Suggestions Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ManageDefaultMailServersController extends BaseController
{
    /** @var ServerSuggestions */
    private $_mailSuggestions;

    public function initAdditionalServices(array $services)
    {
        $this->_mailSuggestions = $services[ServerSuggestions::class];
    }

    public function indexAction()
    {
        return new ViewModel();
    }

    public function getListAction()
    {
        $view  = new JsonModel();
        $start = Json::decode($this->findParam('start'), Json::TYPE_ARRAY);
        $limit = Json::decode($this->findParam('limit'), Json::TYPE_ARRAY);
        $sort  = $this->findParam('sort');
        $dir   = $this->findParam('dir');

        list($totalRecords, $arrRows,) = $this->_mailSuggestions->getServerSuggestions(
            null,
            null,
            $this->findParam('start', $start),
            $this->findParam('limit', $limit),
            $sort,
            $dir
        );

        return $view->setVariables(array(
                                       'results' => $arrRows,
                                       'count'   => $totalRecords
                                   ));
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $booSuccess = false;

        try {
            $ids = Json::decode($this->findParam('ids'), Json::TYPE_ARRAY);

            if (!is_array($ids)) {
                $ids = array($ids);
            }


            $booSuccess = $this->_mailSuggestions->deleteDefaultMailServers($ids);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function editAction()
    {
        $view         = new JsonModel();
        $booSuccess   = false;
        $booCreateNew = false;

        try {
            $filter = new StripTags();
            $id   = (int)$this->findParam('id');
            $name = $filter->filter($this->findParam('name'));
            $type = $this->findParam('type');
            $host = $filter->filter($this->findParam('host'));
            $port = (int)$this->findParam('port');
            $ssl  = $this->findParam('ssl');

            $strError = '';
            if (empty($strError) && empty($name)) {
                $strError = $this->_tr->translate('Incorrect Name');
            }

            if (empty($strError) && empty($host)) {
                $strError = $this->_tr->translate('Incorrect Host');
            }

            if (empty($strError) && !in_array($type, array('pop3', 'imap', 'smtp'))) {
                $strError = $this->_tr->translate('Incorrect Type');
            }

            if (empty($strError)) {
                $arrData = array(
                    'name' => $name,
                    'type' => $type,
                    'host' => $host,
                    'port' => $port,
                    'ssl'  => $ssl
                );

                if ($id) {
                    $arrData['id'] = $id;
                }

                $booSuccess   = $this->_mailSuggestions->updateDefaultMailServer($id, $arrData);
                $booCreateNew = empty($id) && $booSuccess;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array(
                                       'success'      => $booSuccess,
                                       'booCreateNew' => $booCreateNew
                                   ));
    }
}
