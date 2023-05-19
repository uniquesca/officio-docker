<?php

namespace Wizard\Controller;

use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Helper\Partial;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\Roles;
use Officio\Common\Service\Settings;

/**
 * Wizard Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Roles */
    protected $_roles;

    /** @var Country */
    protected $_country;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_roles   = $services[Roles::class];
        $this->_country = $services[Country::class];
        $this->_mailer  = $services[Mailer::class];
    }

    public function indexAction()
    {
        exit('Please contact to support.');
        $view = new ViewModel();
        $view->setTerminal(true);

        $view->setVariable('package1', 'checked="checked"');
        $view->setVariable('package2', '');
        $view->setVariable('package3', '');

        $view->setVariable('defaultCountryId', $this->_country->getDefaultCountryId());
        $view->setVariable('defaultTimezone', $this->_country->getDefaultTimeZone());

        // Load packages list
        if ($this->getRequest()->isPost()) {
            $filter = new StripTags();
            if ($filter->filter($this->getRequest()->getPost('post_id')) == '9110119A_b?!') {
                $view->setVariable('step', 2);
                if ($filter->filter($this->getRequest()->getPost('post_action')) == 'pricing') {
                    $view->setVariable('post_action', 'http://' . $this->layout()->getVariable('officio_domain') . '/pricing.php');
                } else {
                    $view->setVariable('post_action', 'http://' . $this->layout()->getVariable('officio_domain') . '/signup.php');
                }

                $view->setVariable('post', '$this->getRequest()->getPost()');

                if ($filter->filter($this->getRequest()->getPost('product1Name')) != '') {
                    $view->setVariable('package1', 'checked="checked"');
                }

                if ($filter->filter($this->getRequest()->getPost('product2Name')) != '') {
                    $view->setVariable('package2', 'checked="checked"');
                }

                if ($filter->filter($this->getRequest()->getPost('product3Name')) != '') {
                    $view->setVariable('package3', 'checked="checked"');
                }
            }
        }

        // Load default roles
        $view->setVariable('arrRoles', Json::encode($this->_roles->getDefaultRoles()));
        $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));
        $view->setVariable('ta_label', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));

        return $view;
    }

    public function sendAction()
    {
        /** @var HelperPluginManager $viewHelperManager */
        $viewHelperManager = $this->_serviceManager->get('ViewHelperManager');
        /** @var Partial $partial */
        $partial = $viewHelperManager->get('partial');

        $strError = '';

        $arrEmailSettings = $this->_settings->getOfficioSupportEmail();
        $filter = new StripTags();
        switch ($filter->filter($this->findParam('action'))) {
            case 'companyInfo':
                $arrCompanyInfo = Settings::filterParamsArray(Json::decode($this->findParam('companyInfo'), Json::TYPE_ARRAY), $filter);
                $subject = $arrCompanyInfo['companyName'] . ' - Company Defined';

                $view = new ViewModel();
                $view->setTemplate('wizard/index/email-company-info.phtml');
                $msg = $partial($view);

                foreach ($arrCompanyInfo as $key => $val) {
                    if (empty($val)) {
                        $val = '&nbsp;';
                    }
                    $msg = str_replace('__' . $key . '__', nl2br($val), $msg);
                }

                $msg = str_replace('__companyCityLabel__', $this->_settings->getSiteCityLabel(), $msg);

                if (!$this->_mailer->sendEmailToSupport($subject, $msg, $arrEmailSettings['email'])) {
                    $strError = 'Email was not sent. Please contact the website support.';
                }
                break;

            case 'ccInfo':
                $arrCompanyInfo = Settings::filterParamsArray(Json::decode($this->findParam('companyInfo', ''), Json::TYPE_ARRAY), $filter);
                $arrCCInfo = Settings::filterParamsArray(Json::decode($this->findParam('ccInfo'), Json::TYPE_ARRAY), $filter);
                $subject = $arrCompanyInfo['companyName'] . ' - Credit Card';

                $view = new ViewModel();
                $view->setTemplate('wizard/index/email-cc-info.phtml');
                $msg = $partial($view);

                $msg = str_replace('__companyName__', nl2br($arrCompanyInfo['companyName']), $msg);
                foreach ($arrCCInfo as $key => $val) {
                    if (empty($val)) {
                        $val = '&nbsp;';
                    }
                    $msg = str_replace('__' . $key . '__', nl2br($val), $msg);
                }

                if (!$this->_mailer->sendEmailToSupport($subject, $msg, $arrEmailSettings['email'])) {
                    $strError = 'Email was not sent. Please contact the website support.';
                }
                break;

            default:
                $strError = 'Incorrect data';
                break;
        }

        return new JsonModel(array("success" => empty($strError), "message" => $strError));
    }

}
