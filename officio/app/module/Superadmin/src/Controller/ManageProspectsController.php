<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Country;
use Officio\Service\Company;
use Prospects\Service\Prospects;

/**
 * Prospects Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageProspectsController extends BaseController
{
    /** @var Prospects */
    private $_prospects;

    /** @var Company */
    private $_company;

    /** @var Country */
    protected $_country;

    public function initAdditionalServices(array $services)
    {
        $this->_company   = $services[Company::class];
        $this->_country   = $services[Country::class];
        $this->_prospects = $services[Prospects::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Manage Prospects');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('booHasAccessToMail', $this->_acl->isAllowed('mail-view') && $this->_config['mail']['enabled']);
        $view->setVariable('booHasAccessToManageTemplates', $this->_acl->isAllowed('manage-templates'));

        $view->setVariable('intShowProspectsPerPage', $this->_prospects->intShowProspectsPerPage);

        return $view;
    }
    
    public function listAction()
    {
        $view = new JsonModel();
        $arrProspects = array();
        $countries = array();
        $arrPackages = array();
        $totalCount = 0;

        try {
            $arrPackages = $this->_company->getPackages()->getSubscriptionsList(false, true);

            // Get params
            $sort  = $this->findParam('sort');
            $dir   = $this->findParam('dir');
            $start = $this->findParam('start');
            $limit = $this->findParam('limit');

            // Load prospects list
            $arrProspectsList = $this->_prospects->getProspectsList($sort, $dir, $start, $limit);
            $arrProspects = $arrProspectsList['rows'];
            $totalCount   = $arrProspectsList['totalCount'];
            
            $prospectIds = array();
            foreach($arrProspects as $prospect) {
                $prospectIds[] = (int) $prospect['prospect_id'];
            }

            // Load invoices for these prospects
            $invoices = $this->_company->getCompanyInvoice()->getProspectsInvoices($prospectIds);
            foreach($arrProspects as &$prospect) {
                $prospect['invoices'] = array();

                foreach($invoices as $invoice) {
                    if($invoice['prospect_id'] == $prospect['prospect_id']) {
                        $prospect['invoices'][] = array(
                            'company_id' => (int) $invoice['company_id'],
                            'company_invoice_id' => (int) $invoice['company_invoice_id'],
                            'invoice_name' => 'Invoice #' . $invoice['invoice_number']
                        );
                    }
                }
            }
            unset($prospect);

            //get countries
            $oSubscriptions = $this->_company->getCompanySubscriptions();
            $countries      = $this->_country->getCountriesAsCodeKey();

            //update some field values
            foreach($arrProspects as &$prospect) {
                $prospect['country_display'] = $this->_country->getCountryNameByCountryCode($prospect['country']);
                $prospect['package_display'] = $this->_company->getPackages()->getSubscriptionNameById($prospect['package_type']);
                $prospect['payment_term_display'] = $oSubscriptions->getPaymentTermNameById($prospect['payment_term']);
                $prospect['support'] = $this->_prospects->getProspectSupportName($prospect['support']);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return array result
        $arrResult = array(
            'success'       => $booSuccess,
            'totalCount'    => $totalCount,
            'rows'          => $arrProspects,
            'countries'     => $countries,
            'packages_list' => $arrPackages
        );

        return $view->setVariables($arrResult);
    }

    public function saveAction()
    {
        $view = new JsonModel();
        $filter = new StripTags();

        try {
            $act = $filter->filter($this->findParam('act'));

            //get prospect ID
            if ($act != 'add') {
                $prospectId = $filter->filter($this->findParam('prospect_id'));
            } else {
                $prospectId = 0;
            }

            $data = array(
                'salutation'            => $filter->filter(Json::decode($this->findParam('salutation'), Json::TYPE_ARRAY)),
                'name'                  => $filter->filter(Json::decode($this->findParam('name'), Json::TYPE_ARRAY)),
                'last_name'             => $filter->filter(Json::decode($this->findParam('last_name'), Json::TYPE_ARRAY)),
                'company'               => $filter->filter(Json::decode($this->findParam('company'), Json::TYPE_ARRAY)),
                'email'                 => $filter->filter(Json::decode($this->findParam('email'), Json::TYPE_ARRAY)),
                'phone_w'               => $filter->filter(Json::decode($this->findParam('phone_w'), Json::TYPE_ARRAY)),
                'phone_m'               => $filter->filter(Json::decode($this->findParam('phone_m'), Json::TYPE_ARRAY)),
                'source'                => $filter->filter(Json::decode($this->findParam('source'), Json::TYPE_ARRAY)),
                'key'                   => $filter->filter(Json::decode($this->findParam('key'), Json::TYPE_ARRAY)),
                'key_status'            => $filter->filter(Json::decode($this->findParam('key_status'), Json::TYPE_ARRAY)),
                'address'               => $filter->filter(Json::decode($this->findParam('address'), Json::TYPE_ARRAY)),
                'city'                  => $filter->filter(Json::decode($this->findParam('city'), Json::TYPE_ARRAY)),
                'state'                 => $filter->filter(Json::decode($this->findParam('state'), Json::TYPE_ARRAY)),
                'country'               => $filter->filter(Json::decode($this->findParam('country'), Json::TYPE_ARRAY)),
                'zip'                   => $filter->filter(Json::decode($this->findParam('zip'), Json::TYPE_ARRAY)),
                'package_type'          => $filter->filter(Json::decode($this->findParam('package_type'), Json::TYPE_ARRAY)),
                'support'               => $filter->filter(Json::decode($this->findParam('support'), Json::TYPE_ARRAY)),
                'payment_term'          => $filter->filter(Json::decode($this->findParam('payment_term'), Json::TYPE_ARRAY)),
                'paymentech_profile_id' => $filter->filter($this->findParam('paymentech_profile_id')),
                'status'                => $filter->filter(Json::decode($this->findParam('status'), Json::TYPE_ARRAY)),
                'notes'                 => $filter->filter(Json::decode($this->findParam('notes'), Json::TYPE_ARRAY))
            );

            $data['package_type'] = empty($data['package_type']) ? null : $data['package_type'];

            $prospectId = $this->_prospects->createUpdateProspect($prospectId, $data);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $booSuccess = !empty($prospectId);

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function deleteAction()
    {
        $prospects     = Json::decode($this->findParam('prospects'), Json::TYPE_ARRAY);
        $removed_count = $this->_db2->delete('prospects', ['prospect_id' => $prospects]);

        return new JsonModel(array('success' => $removed_count > 0));
    }
}
