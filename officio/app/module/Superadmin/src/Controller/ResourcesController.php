<?php

namespace Superadmin\Controller;

use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * Resources controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ResourcesController extends BaseController
{
    /**
     * The default action - show the home page
     */
    public function indexAction ()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Manage Resources');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }
}