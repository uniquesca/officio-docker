<?php

namespace Rss\Controller;

use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Rss\Service\Rss;

/**
 * Home page RSS Index Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{
    /** @var Rss */
    protected $_rss;

    public function initAdditionalServices(array $services)
    {
        $this->_rss = $services[Rss::class];
    }

    public function getAction()
    {
        $view = new ViewModel(
            ['content' => $this->_rss->generateHtml()]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }
}
