<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;

/**
 * Client Account Settings Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class TrustAccountSettingsController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
    }

    /**
     * Index action - show TA list for current company
     *
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $taLabel     = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
        $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');

        $title = $this->_tr->translate($taLabel);
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $companyId       = $this->_getCompanyId();
        $divisionGroupId = $this->_auth->isCurrentUserSuperadmin() ? 0 : $this->_auth->getCurrentUserDivisionGroupId();
        $view->setVariable('arrTA', Json::encode($this->_getParsedCompanyTA($companyId)));
        $view->setVariable('arrOffices', $this->_company->getDivisions($companyId, $divisionGroupId));

        $strAdmin = $this->_auth->isCurrentUserSuperadmin() ? 'false' : 'true';
        $view->setVariable('booAdmin', $strAdmin);

        $view->setVariable('arrCurrencies', $this->_clients->getAccounting()->getSupportedCurrencies());
        $view->setVariable('activeAdminTab', 1);
        $view->setVariable('ta_label', $taLabel);
        $view->setVariable('officeLabel', $officeLabel);

        return $view;
    }

    private function _getCompanyId()
    {
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $company_id = $this->_auth->getCurrentUserCompanyId();
        } else {
            // Superadmin
            $company_id = $this->findParam('company_id');
            if (empty($company_id)) {
                $company_id = 0;
            }
        }

        return $company_id;
    }

    private function _canEditTA($company_id, $ta_id)
    {
        $booCan = false;

        $select = (new Select())
            ->from('company_ta')
            ->columns(['company_ta_id'])
            ->where(['company_id' => $company_id]);

        $arrTa = $this->_db2->fetchCol($select);

        if (is_array($arrTa) && in_array($ta_id, $arrTa)) {
            $booCan = true;
        }

        return $booCan;
    }

    private function _getParsedCompanyTA($companyId)
    {
        $arrResultTA = array();
        $arrTA       = $this->_clients->getAccounting()->getCompanyTA($companyId);
        if (!empty($arrTA)) {
            $oCompanyTADivisions = $this->_company->getCompanyTADivisions();
            foreach ($arrTA as $TAInfo) {
                $arrResultTA[] = array(
                    'ta_id'                   => (int)$TAInfo['company_ta_id'],
                    'ta_name'                 => $TAInfo['name'],
                    'ta_detailed_description' => $TAInfo['detailed_description'],
                    'ta_currency'             => $TAInfo['currency'],
                    'ta_currency_label'       => $TAInfo['currencyLabel'],
                    'ta_view_month'           => (int)$TAInfo['view_transactions_months'],
                    'ta_balance'              => empty($TAInfo['opening_balance']) ? 0 : $TAInfo['opening_balance'],
                    'ta_status'               => (int)$TAInfo['status'],
                    'allow_new_bank_id'       => (int)$TAInfo['allow_new_bank_id'],
                    'ta_can_change_currency'  => ($TAInfo['imported_recs_count'] == 0) ? 'true' : 'false',
                    'ta_last_reconcile'       => $TAInfo['last_reconcile'],
                    'ta_last_reconcile_iccrc' => $TAInfo['last_reconcile_iccrc'],
                    'ta_recon_dates'          => $this->_clients->getAccounting()->getTrustAccount()->getCompanyTAReconciliationRecordsDates($TAInfo['company_ta_id'], 'general'),
                    'ta_recon_dates_iccrc'    => $this->_clients->getAccounting()->getTrustAccount()->getCompanyTAReconciliationRecordsDates($TAInfo['company_ta_id'], 'iccrc'),
                    'ta_divisions'            => $oCompanyTADivisions->getCompanyTaDivisions($TAInfo['company_ta_id'])
                );
            }
        }

        return $arrResultTA;
    }

    public function settingsAction()
    {
        $view = new ViewModel();

        $taLabel     = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
        $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');

        $title = $this->_tr->translate($taLabel . ' Transaction Settings');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $companyId       = $this->_getCompanyId();
        $divisionGroupId = $this->_auth->isCurrentUserSuperadmin() ? 0 : $this->_auth->getCurrentUserDivisionGroupId();
        $view->setVariable('arrTA', Json::encode($this->_getParsedCompanyTA($companyId)));
        $view->setVariable('arrOffices', $this->_company->getDivisions($companyId, $divisionGroupId));

        $strAdmin = $this->_auth->isCurrentUserSuperadmin() ? 'false' : 'true';
        $view->setVariable('booAdmin', $strAdmin);

        $view->setVariable('arrCurrencies', $this->_clients->getAccounting()->getSupportedCurrencies());

        $view->setVariable('activeAdminTab', 0);
        $view->setVariable('ta_label', $taLabel);
        $view->setVariable('officeLabel', $officeLabel);

        return $view;
    }

    /**
     * Add/edit TA (can be array of TAs)
     *
     */
    public function manageAction()
    {
        $view        = new JsonModel();
        $strMessage  = '';
        $arrResultTA = array();

        try {
            $filter = new StripTags();
            $TAInfo = Settings::filterParamsArray(Json::decode($this->findParam('changes'), Json::TYPE_ARRAY), $filter);

            if (!is_array($TAInfo) || empty($TAInfo)) {
                $strMessage = $this->_tr->translate('Please make changes and try again');
            }

            if (empty($strMessage)) {
                $company_id = $this->_getCompanyId();

                $arrCurrencies = $this->_clients->getAccounting()->getSupportedCurrencies();
                $arrKeys       = array_keys($arrCurrencies);
                if (!in_array($TAInfo['ta_currency'], $arrKeys)) {
                    $TAInfo['ta_currency'] = 'usd';
                }

                $arrUpdate = array(
                    'name'                     => addslashes($TAInfo['ta_name'] ?? ''),
                    'detailed_description'     => addslashes($TAInfo['ta_detailed_description'] ?? ''),
                    'currency'                 => $TAInfo['ta_currency'],
                    'view_transactions_months' => (int)$TAInfo['ta_view_month'],
                    'status'                   => (int)$TAInfo['ta_status'],
                    'allow_new_bank_id'        => (int)$TAInfo['ta_allow'],
                    'last_reconcile'           => $TAInfo['ta_last_reconcile'] ? $TAInfo['ta_last_reconcile'] . date('-t', strtotime($TAInfo['ta_last_reconcile'] . '-01')) : null,
                    'last_reconcile_iccrc'     => $TAInfo['ta_last_reconcile_iccrc'] ? $TAInfo['ta_last_reconcile_iccrc'] . date('-t', strtotime($TAInfo['ta_last_reconcile_iccrc'] . '-01')) : null,
                );

                if (!empty($TAInfo['ta_id'])) {
                    // Check if user has access to this TA
                    if (!$this->_canEditTA($company_id, $TAInfo ['ta_id'])) {
                        $strMessage = $this->_tr->translate('Insufficient access rights');
                    }

                    if (empty ($strMessage)) {
                        // Check if it is possible to change the currency
                        $booCanChangeCurrency = $this->_clients->getAccounting()->canDeleteOrChangeCurrency($TAInfo ['ta_id']);

                        $arrTAInfo = $this->_clients->getAccounting()->getCompanyTAbyId($TAInfo ['ta_id']);
                        if (!empty($arrTAInfo)) {
                            $oldCurrency = $arrTAInfo['currency'];

                            // Currency can be changed only if no any transactions were imported
                            if (!$booCanChangeCurrency && $arrUpdate ['currency'] != $oldCurrency) {
                                $strMessage = $this->_tr->translate('Cannot change currency because there are imported records in ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account') . '.');
                            } elseif (!$booCanChangeCurrency) {
                                unset ($arrUpdate ['currency']);
                            }
                        }
                    }

                    if (empty ($strMessage)) {
                        $this->_db2->update(
                            'company_ta',
                            $arrUpdate,
                            [
                                'company_id'    => $company_id,
                                'company_ta_id' => $TAInfo ['ta_id']
                            ]
                        );

                        $this->_clients->getAccounting()->updateTrustAccountRecordsBalance($TAInfo['ta_id'], false, $TAInfo['ta_balance']);

                        // for both ta_last_reconcile & ta_last_reconcile_iccrc delete all records in reconciliation_log table after this date. Also delete PDF files. If date==null => delete all records and PDFs
                        $ids_to_delete = $this->_clients->getAccounting()->getTrustAccount()->getCompanyTAReconciliationRecordsAfterDates($TAInfo['ta_id'], $arrUpdate['last_reconcile'], $arrUpdate['last_reconcile_iccrc']);

                        $this->_clients->getAccounting()->getTrustAccount()->deleteCompanyTAReconciliationRecords($ids_to_delete);
                    }
                } else {
                    $arrUpdate['company_id']           = $company_id;
                    $arrUpdate['create_date']          = date('Y-m-d');
                    $arrUpdate['last_reconcile']       = '0000-00-00';
                    $arrUpdate['last_reconcile_iccrc'] = '0000-00-00';

                    $TAInfo ['ta_id'] = $this->_db2->insert('company_ta', $arrUpdate);

                    $this->_clients->getAccounting()->createStartBalance($TAInfo['ta_id'], $TAInfo['ta_balance']);
                }

                // Update company T/A offices
                if (empty ($strMessage)) {
                    $this->_company->getCompanyTADivisions()->updateCompanyTaDivisions($TAInfo ['ta_id'], $TAInfo['ta_divisions']);
                }

                $arrResultTA = $this->_getParsedCompanyTA($company_id);
            }
        } catch (Exception $e) {
            $strMessage = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'         => !empty($strMessage),
            'error_message' => $strMessage,
            'arrTA'         => $arrResultTA
        );
        return $view->setVariables($arrResult);
    }

    public function getSettingsAction()
    {
        $view     = new JsonModel();
        $strError = '';
        $arrInfo  = array();

        try {
            $companyTAId = Json::decode($this->findParam('company_ta_id'), Json::TYPE_ARRAY);

            $companyId = $this->_getCompanyId();
            if (!$this->_canEditTA($companyId, $companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrAllCompanyTA = $this->_getParsedCompanyTA($companyId);
                foreach ($arrAllCompanyTA as $arrCompanyTAInfo) {
                    if ($arrCompanyTAInfo['ta_id'] == $companyTAId) {
                        $arrInfo = $arrCompanyTAInfo;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'arrInfo' => $arrInfo
        );
        return $view->setVariables($arrResult);
    }

    /**
     * Delete TA, but before check if user has access to that
     *
     */
    public function deleteAction()
    {
        $view        = new JsonModel();
        $strMessage  = '';
        $arrResultTA = array();

        try {
            $company_id = $this->_getCompanyId();

            $arrDeleteTAs = Json::decode($this->findParam('changes'), Json::TYPE_ARRAY);
            if (!is_array($arrDeleteTAs) || empty($arrDeleteTAs)) {
                $strMessage = $this->_tr->translate('Incorrect data.');
            }

            if (empty($strMessage)) {
                $arrTADelete = array();
                $taLabel     = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
                foreach ($arrDeleteTAs as $TAInfo) {
                    $companyTAId = $TAInfo['ta_id'];
                    if (empty($strMessage) && !$this->_canEditTA($company_id, $companyTAId)) {
                        $strMessage = $this->_tr->translate('Insufficient access rights');
                        break;
                    }

                    if (empty($strMessage) && $this->_clients->getAccounting()->hasTrustAccountAssignedTransactions($companyTAId)) {
                        $strMessage = $this->_tr->translate('Cannot delete the ' . $taLabel . ' because it has imported transactions and some of the transactions are already assigned to cases.');
                        break;
                    }

                    if (empty($strMessage) && $this->_clients->getAccounting()->isCompanyTAAssigned($companyTAId)) {
                        $strMessage = $this->_tr->translate('This ' . $taLabel . ' is assigned to case(s). Please assign the Clients to a different ' . $taLabel . ' first then delete the ' . $taLabel . '.');
                        break;
                    }

                    $arrTADelete[] = $companyTAId;
                }

                if (empty($strMessage) && !empty($arrTADelete)) {
                    // Delete all related records from all related tables
                    foreach ($arrTADelete as $companyTAId) {
                        $arrTransactionsIds = $this->_clients->getAccounting()->getTrustAccountIdByCompanyTAId($companyTAId);
                        if (!empty($arrTransactionsIds)) {
                            $this->_db2->delete('u_log', ['trust_account_id' => $arrTransactionsIds]);
                        }
                    }


                    $arrClearTables = array(
                        'members_ta',
                        'u_import_transactions',
                        'u_assigned_deposits',
                        'u_assigned_withdrawals',
                        'u_invoice_payments',
                        'u_invoice',
                        'u_payment',
                        'u_trust_account',
                        'company_ta'
                    );

                    // Delete company T/A
                    $strWhere = 'company_ta_id';
                    foreach ($arrClearTables as $tableName) {
                        $strRunWhere = (new Where())
                            ->in($strWhere, $arrTADelete);

                        $this->_db2->delete($tableName, [$strRunWhere]);
                    }
                }

                $arrResultTA = $this->_getParsedCompanyTA($company_id);
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('error' => !empty($strMessage), 'error_message' => $strMessage, 'arrTA' => $arrResultTA));
    }


    public function getTypeOptionsAction()
    {
        $view            = new JsonModel();
        $strError        = '';
        $arrTransactions = array();

        $booWithoutOther = Json::decode($this->findParam('withoutOther'), Json::TYPE_ARRAY);
        $type            = Json::decode($this->findParam('type'), Json::TYPE_ARRAY);
        if (!in_array($type, array('deposit', 'withdrawal', 'destination'))) {
            $strError = $this->_tr->translate('Incorrectly selected Option');
        }

        if (empty($strError)) {
            $arrTransactions = $this->_clients->getAccounting()->getTrustAccount()->getTypeOptions($type, (bool)$booWithoutOther);
        }

        return $view->setVariables(array('success' => empty($strError), 'error' => $strError, 'rows' => $arrTransactions, 'totalCount' => count($arrTransactions)));
    }

    public function deleteTypeOptionAction()
    {
        $view      = new JsonModel();
        $strError  = '';
        $option_id = $this->findParam('changes');

        $type = Json::decode($this->findParam('type'), Json::TYPE_ARRAY);
        if (!in_array($type, array('deposit', 'withdrawal', 'destination'))) {
            $strError = $this->_tr->translate('Incorrectly selected Option');
        }

        if (empty($strError)) {
            $success = $this->_clients->getAccounting()->getTrustAccount()->deleteTypeOption($type, (int)$option_id);
            if (!$success) {
                $strError = $this->_tr->translate('Cannot delete option');
            }
        }

        return $view->setVariables(array('success' => empty($strError)));
    }

    public function manageTypeOptionAction()
    {
        $view     = new JsonModel();
        $strError = '';
        try {
            $changes = Json::decode($this->findParam('changes'), Json::TYPE_ARRAY);

            $type = Json::decode($this->findParam('type'), Json::TYPE_ARRAY);
            if (!in_array($type, array('deposit', 'withdrawal', 'destination'))) {
                $strError = $this->_tr->translate('Incorrectly selected Option');
            }

            if (empty($strError)) {
                $success = $this->_clients->getAccounting()->getTrustAccount()->saveTypeOption($type, $changes);
                if (!$success) {
                    $strError = $this->_tr->translate('Cannot update option');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strError)));
    }
}
