<?php

namespace CmiSignup\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Country;
use Officio\Service\Roles;
use Officio\Service\Company;

/**
 * CMI signup Controller
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
        exit('Please contact to support.');
        $view = new ViewModel();
        $view->setVariables([
                                'defaultCountryId' => $this->_country->getDefaultCountryId(),
                                'defaultTimezone'  => $this->_country->getDefaultTimeZone(),
                                'taLabel'          => $this->_company->getCurrentCompanyDefaultLabel('trust_account'),
                                'package1'         => 'checked="checked"',
                                'package2'         => 'checked="checked"',
                                'package3'         => 'checked="checked"',
                                'provincesList'    => $this->_country->getStatesList(),
                                'settings'         => $this->_settings,
                                'booShowABN'       => !empty($this->_config['site_version']['check_abn_enabled']),
                                'arrRoles'         => Json::encode($this->_roles->getDefaultRoles())
                            ]);

        return $view;
    }
    
    public function checkCmiAction() {
        $view = new JsonModel();
        try {
            $filter = new StripTags();
            $cmi_id = $filter->filter(Json::decode($this->findParam('cmi_id'), Json::TYPE_ARRAY));
            $reg_id = $filter->filter(Json::decode($this->findParam('reg_id'), Json::TYPE_ARRAY));
            
            $strMessage = $this->_company->getCompanyCMI()->checkCMIPairUsed($cmi_id, $reg_id);
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        
        return $view->setVariables(array("success" => empty($strMessage), 'msg' => $strMessage));
    }
}