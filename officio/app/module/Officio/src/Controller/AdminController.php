<?php

namespace Officio\Controller;
 
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * Admin Controller - bridge between superadmin's section
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AdminController extends BaseController
{
    /**
     * The default action - show the home page
     */
    public function indexAction ()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }
}