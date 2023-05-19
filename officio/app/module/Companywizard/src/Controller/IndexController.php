<?php

namespace Companywizard\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Country;
use Officio\Service\Roles;
use Officio\Service\Company;
use Prospects\Service\Prospects;

/**
 * Company Wizard Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{
    /** @var Prospects */
    private $_prospects;

    /** @var Company */
    private $_company;

    /** @var Country */
    protected $_country;

    /** @var Roles */
    protected $_roles;

    public function initAdditionalServices(array $services)
    {
        $this->_company      = $services[Company::class];
        $this->_country = $services[Country::class];
        $this->_prospects     = $services[Prospects::class];
        $this->_roles = $services[Roles::class];
    }
    
    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $filter = new StripTags();
        $key    = $filter->filter($this->findParam('key'));
        $step    = $filter->filter($this->findParam('step'));

        $view->setVariable('key', $key);
        $view->setVariable('settings', $this->_settings);

        $view->setVariable('defaultCountryId', $this->_country->getDefaultCountryId());
        $view->setVariable('defaultTimezone', $this->_country->getDefaultTimeZone());
        $view->setVariable('passwordHighSecurity', $this->_config['security']['password_high_secure']);

        $strError = '';
        if(empty($key) || (!empty($step) && $step == 1)) {

            $view->setVariable('step', 1);
            list($strError) = $this->_prospects->checkIsProspectKeyStillValid($key);

        } else {

            if(empty($step)){
                $view->setVariable('step', 3);
            } elseif($step == 2) {
                $view->setVariable('step', 2);
            } elseif($step == 3) {
                $view->setVariable('step', 3);
            } else {
                $strError = 'Invalid link.';
            }

            //get prospect info
            if (empty($strError)){
                list($strError, $prospectInfo) = $this->_prospects->checkIsProspectKeyStillValid($key);
            }

            if (empty($strError)) {
                $prospectInfo['country_name'] = $this->_country->getCountryNameByCountryCode($prospectInfo['country']);
                $prospectInfo['country']      = $this->_country->getCountryIdByCode($prospectInfo['country']);
                $prospectInfo['package_type'] = $this->_company->getPackages()->getSubscriptionNameById($prospectInfo['package_type']);
                $prospectInfo['payment_term'] = $this->_company->getCompanySubscriptions()->getPaymentTermNameById($prospectInfo['payment_term']);
                $prospectInfo['support'] = $this->_prospects->getProspectSupportName($prospectInfo['support']);
                $prospectInfo['sign_in_date'] = $this->_settings->formatDate($prospectInfo['sign_in_date']);

                $view->setVariable('prospect', $prospectInfo);
                $view->setVariable('arrProvinces', $this->_country->getStatesList(0, true, true));
                $view->setVariable('arrCountries', $this->_country->getCountries(true));
                $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));
            }
        }

        // Load default roles
        $view->setVariable('arrRoles', Json::encode($this->_roles->getDefaultRoles()));
        $view->setVariable('strError', $strError);
        $view->setVariable('taLabel', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
        $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));

        return $view;
    }

    public function checkProspectKeyAction() {
        $view = new JsonModel();

        try {
            $key = Json::decode($this->findParam('key'), Json::TYPE_ARRAY);

            list($strMessage, $prospectInfo) = $this->_prospects->checkIsProspectKeyStillValid($key);
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array ("success" => empty($strMessage), 'msg' => $strMessage));
    }
}
