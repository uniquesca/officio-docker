<?php

namespace Superadmin\Controller;

use Laminas\View\Model\ViewModel;
use Officio\Service\AutomatedBillingErrorCodes;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Accounts
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AccountsController extends BaseController
{

    /** @var AutomatedBillingErrorCodes */
    protected $_automatedBillingErrorCodes;

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_automatedBillingErrorCodes = $services[AutomatedBillingErrorCodes::class];
        $this->_company = $services[Company::class];
    }

    public function indexAction() {
        $view = new ViewModel();

        $strTitle = $this->_tr->translate('Accounts');
        $this->layout()->setVariable('title', $strTitle);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($strTitle);
        
        // tab: Manage PT Invoices
        
        $oInvoices = $this->_company->getCompanyInvoice();

        // Global settings
        $arrInvoicesConfig = array(
            'invoicesOnPage'     => $oInvoices->intShowInvoicesPerPage,
            'currency'           => $this->_settings->getSiteDefaultCurrency(),
            'booCollapsedFilter' => false
        );
        $view->setVariable('arrInvoicesConfig', $arrInvoicesConfig);

        $arrModes = $oInvoices->getModesOfPaymentList();
        $view->setVariable('arrModes', $arrModes);

        $arrProducts = $oInvoices->getProductsList();
        $view->setVariable('arrProducts', $arrProducts);
        
        // tab: Bad debts log
        
        $view->setVariable('countInvoicesOnPage', $oInvoices->intShowCompaniesPerPage);
        
        // tab: Automated billing log
        
        // tab: Manage PT Error codes
        $view->setVariable('countErrorsOnPage', $this->_automatedBillingErrorCodes->intShowErrorsPerPage);

        // Get ACL
        $arrAccountsAccessRights = array(
            'pt_invoices'           => $this->_acl->isAllowed('manage-invoices'),
            'bad_debts_log'         => $this->_acl->isAllowed('manage-bad-debts-log'),
            'automated_billing_log' => $this->_acl->isAllowed('automated-billing-log'),
            'manage_pt_error_codes' => $this->_acl->isAllowed('manage-pt-error-codes'),
        );

        $view->setVariable('arrAccountsAccessRights', $arrAccountsAccessRights);

        return $view;
    }
}