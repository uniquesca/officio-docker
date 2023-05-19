<?php

namespace Freetrial\Controller;

use Exception;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Country;
use Officio\Service\Roles;
use Officio\Service\Company;

/**
 * Free trial Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var Roles */
    protected $_roles;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_country = $services[Country::class];
        $this->_roles = $services[Roles::class];
    }

    public function indexAction() {
        $view = new ViewModel();
        $view->setTerminal(true);

        $view->setVariable('taLabel', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
        $view->setVariable('provincesList', $this->_country->getStatesList());
        $view->setVariable('settings', $this->_settings);
        $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));

        $view->setVariable('defaultCountryId', $this->_country->getDefaultCountryId());
        $view->setVariable('defaultTimezone', $this->_country->getDefaultTimeZone());
        $view->setVariable('passwordHighSecurity', $this->_config['security']['password_high_secure']);

        // Load default roles
        $view->setVariable('arrRoles', Json::encode($this->_roles->getDefaultRoles()));

        return $view;
    }
    
    public function checkKeyAction() {
        $view = new JsonModel();

        try {
            $trialKey = Json::decode($this->findParam('freetrial_key'), Json::TYPE_ARRAY);

            $strMessage = $this->_company->getCompanyTrial()->checkKeyCorrect($trialKey);
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        
        return $view->setVariables(array ("success" => empty($strMessage), 'msg' => $strMessage));
    }
}