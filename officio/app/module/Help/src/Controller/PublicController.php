<?php

namespace Help\Controller;

use Exception;
use Help\Service\Help;
use Laminas\View\Helper\HeadScript;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * Help Public Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class PublicController extends BaseController
{
    /** @var  Help */
    protected $_help;

    public function initAdditionalServices(array $services)
    {
        $this->_help = $services[Help::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $sectionType = $this->params()->fromQuery('type');
        $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

        $view->setVariable('faqArr', $this->_help->getFAQList($sectionType));
        $view->setVariable('sectionType', $sectionType);

        /** @var HeadScript $headScript */
        $headScript = $this->_serviceManager->get('ViewHelperManager')->get('headScript');
        $headScript->getContainer()->exchangeArray(array()); // clear headScript
        $headScript->prependFile($this->layout()->getVariable('topJsUrl') . '/help/main.js');

        $view->setVariable('hide_ask_link', true);

        return $view;
    }

    public function getCvnInfoAction()
    {
        $view = new ViewModel(
            [
                'topBaseUrl' => $this->layout()->getVariable('topBaseUrl')
            ]
        );
        $view->setTerminal(true);
        return $view;
    }

    public function searchAction()
    {
        $view = new JsonModel();

        $arrResult = array();
        try {
            $sectionType = $this->params()->fromQuery('type');
            $sectionType = in_array($sectionType, array('help', 'ilearn')) ? $sectionType : 'help';

            $query = trim($this->params()->fromQuery('query', ''));

            if ($query != '') {
                $arrResult = $this->_help->search($sectionType, $query);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrResult);
    }
}