<?php

namespace SpecialOffer\Controller;

use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Country;

class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_country = $services[Country::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();
        $this->layout()->setTemplate('layout/bootstrap');

        $strError = '';
        try {
            // Before expiry section
            $view->setVariable('settings', $this->_company->getCompanyPrices($this->_company->getDefaultCompanyId(), false));
        } catch (\Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $view->setVariable('strError', $strError);

        return $view;
    }
}