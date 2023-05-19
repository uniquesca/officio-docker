<?php

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Officio\Common\Json;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\GstHst;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use Officio\Common\SubServiceInterface;
use Officio\Common\SubServiceOwner;
use Officio\Templates\SystemTemplates;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use TCPDF;
use Templates\Service\Templates;
use Laminas\Db\Sql\Expression;

class Accounting extends SubServiceOwner implements SubServiceInterface
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_parent;

    /** @var GstHst */
    protected $_gstHst;

    /** @var TrustAccount */
    protected $_trustAccount;

    /** @var Files */
    protected $_files;

    /** @var Pdf */
    protected $_pdf;

    /** @var SystemTriggers */
    protected $_systemTriggers;

    /** @var Documents */
    protected $_documents;

    /** @var Encryption */
    protected $_encryption;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    // Used to identify transaction which identify T/A balance
    // Must be updated in showGrid.js too
    public $startBalanceTransactionId = 'OFFICIO_START_BALANCE';
    public $missingTransactionId = 'OFFICIO_BANK_TRANSFER_DIFFERENTIAL';

    public function initAdditionalServices(array $services)
    {
        $this->_company         = $services[Company::class];
        $this->_gstHst          = $services[GstHst::class];
        $this->_files           = $services[Files::class];
        $this->_pdf             = $services[Pdf::class];
        $this->_systemTriggers  = $services[SystemTriggers::class];
        $this->_documents       = $services[Documents::class];
        $this->_encryption      = $services[Encryption::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
    }


    public function init()
    {
        $this->_systemTemplates->getEventManager()->attach(SystemTemplates::EVENT_GET_AVAILABLE_FIELDS, [$this, 'getSystemTemplateFields']);
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * @return TrustAccount
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getTrustAccount()
    {
        if (is_null($this->_trustAccount)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_trustAccount = $this->_serviceContainer->build(TrustAccount::class, ['parent' => $this]);
            } else {
                $this->_trustAccount = $this->_serviceContainer->get(TrustAccount::class);
                $this->_trustAccount->setParent($this);
            }
        }

        return $this->_trustAccount;
    }


    /**
     * Load Accounting access settings for the current user
     *
     * @return array
     */
    public function getAccountingAccessRights()
    {
        try {
            $booAuthorizedAgentsManagementEnabled = $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled();

            $arrAccessRights = array(
                'fees_and_disbursements' => array(
                    'show_table'              => true,
                    'new_fees'                => $this->_acl->isAllowed('clients-accounting-ft-add-fees-due'),
                    'generate_invoice'        => $this->_acl->isAllowed('clients-accounting-ft-generate-invoice'),
                    'generate_receipt'        => $this->_acl->isAllowed('clients-accounting-ft-generate-receipt'),
                    'error_correction'        => $this->_acl->isAllowed('clients-accounting-ft-error-correction'),
                    'allow_manage_ps_records' => !$booAuthorizedAgentsManagementEnabled,
                    'pay_by_cc'               => $booAuthorizedAgentsManagementEnabled,
                ),

                'invoices' => array(
                    'show_table'            => true,
                    'pay_now'               => $this->_acl->isAllowed('clients-accounting-ft-add-fees-received'),
                    'trust_account_summary' => !$booAuthorizedAgentsManagementEnabled,
                ),

                'general' => array(
                    'can_add_ta'       => $this->canCurrentMemberAddEditTA(),
                    'can_assign_ta'    => !$this->_auth->isCurrentUserClient(),
                    'can_edit'         => $this->_acl->isAllowed('trust-account-edit-view'),
                    'import'           => $this->_acl->isAllowed('trust-account-import-view'),
                    'history'          => $this->_acl->isAllowed('trust-account-history-view'),
                    'change_currency'  => $this->_acl->isAllowed('clients-accounting-change-currency'),
                    'email_accounting' => $this->_acl->isAllowed('clients-accounting-email-accounting'),
                    'reports'          => $this->_acl->isAllowed('clients-accounting-reports'),
                    'print'            => $this->_acl->isAllowed('clients-accounting-print'),
                ),
            );
        } catch (Exception $e) {
            $arrAccessRights = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrAccessRights;
    }


    /**
     * Load information about specific company T/A
     *
     * @param int $companyTAId
     * @return array
     */
    public function getTAInfo($companyTAId)
    {
        $select = (new Select())
            ->from(array('ta' => 'company_ta'))
            ->where(['ta.company_ta_id' => (int)$companyTAId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load company T/A list for specific company
     *
     * @param int $companyId
     * @param bool $booIdOnly
     * @return array
     */
    public function getCompanyTA($companyId, $booIdOnly = false)
    {
        $select = (new Select())
            ->from(array('ta' => 'company_ta'))
            ->columns([$booIdOnly ? 'company_ta_id' : Select::SQL_STAR])
            ->join(array('i' => 'u_import_transactions'), 'i.company_ta_id = ta.company_ta_id', ['imported_recs_count' => new Expression('COUNT(i.company_ta_id)')], Select::JOIN_LEFT)
            ->where(['ta.company_id' => (int)$companyId])
            ->group('ta.company_ta_id')
            ->order(array('ta.company_ta_id', 'ta.name'));

        if ($booIdOnly) {
            $arrResult = $this->_db2->fetchCol($select);
        } else {
            $arrTA     = $this->_db2->fetchAll($select);
            $arrResult = array();
            if (is_array($arrTA) && !empty($arrTA)) {
                foreach ($arrTA as $TAInfo) {
                    $TAInfo['currencyLabel'] = self::getCurrencyLabel($TAInfo['currency']);

                    $arrStartBalance           = $this->getStartBalanceInfo($TAInfo['company_ta_id']);
                    $TAInfo['opening_balance'] = $arrStartBalance['deposit'] ?? 0;

                    $arrResult[] = $TAInfo;
                }
            }
        }

        return $arrResult;
    }

    /**
     * Load list of company T/A which current user has access to
     * @return array
     */
    public function getCompanyTAIdsWithAccess()
    {
        $arrCompanyTAIdsWithAccess = array();

        try {
            if (!$this->_auth->isCurrentUserAdmin() && $this->_auth->isCurrentUserDivision()) {
                $arrDivisions              = $this->_auth->getCurrentUserDivisions();
                $arrCompanyTAIdsWithAccess = $this->_company->getCompanyTADivisions()->getCompanyTAIdsByDivisions($arrDivisions);
            } else {
                $companyId                 = $this->_auth->getCurrentUserCompanyId();
                $arrCompanyTAIdsWithAccess = $this->getCompanyTA($companyId, true);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $arrCompanyTAIdsWithAccess;
    }

    /**
     * Load members list which are assigned to specific company T/A
     *
     * @param int $companyTAId
     * @return array
     */
    public function getTAMembers($companyTAId)
    {
        $select = (new Select())
            ->from(array('mta' => 'members_ta'))
            ->columns(['member_id'])
            ->where(['mta.company_ta_id' => (int)$companyTAId])
            ->order('mta.order ASC');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load list of supported currencies
     * if company id is provided - currencies for this company will be loaded only (from all T/A)
     * @param int|null $companyId
     * @return array
     */
    public function getSupportedCurrencies($companyId = null)
    {
        // All currencies list (codes only)
        $arrCurrencies = array(
            'cad',
            'usd',
            'aed',
            'afn',
            'all',
            'amd',
            'ang',
            'aoa',
            'ars',
            'aud',
            'awg',
            'azn',
            'bam',
            'bbd',
            'bdt',
            'bgn',
            'bhd',
            'bif',
            'bmd',
            'bnd',
            'bob',
            'brl',
            'bsd',
            'btn',
            'bwp',
            'byr',
            'bzd',
            'cdf',
            'chf',
            'clp',
            'cny',
            'cop',
            'crc',
            'cup',
            'cve',
            'cyp',
            'czk',
            'djf',
            'dkk',
            'dop',
            'dzd',
            'eek',
            'egp',
            'ern',
            'etb',
            'eur',
            'fjd',
            'fkp',
            'gbp',
            'gel',
            'ggp',
            'ghs',
            'gip',
            'gmd',
            'gnf',
            'gtq',
            'gyd',
            'hkd',
            'hnl',
            'hrk',
            'htg',
            'huf',
            'idr',
            'ils',
            'imp',
            'inr',
            'iqd',
            'irr',
            'isk',
            'jep',
            'jmd',
            'jod',
            'jpy',
            'kes',
            'kgs',
            'khr',
            'kmf',
            'kpw',
            'krw',
            'kwd',
            'kyd',
            'kzt',
            'lak',
            'lbp',
            'lkr',
            'lrd',
            'lsl',
            'ltl',
            'lvl',
            'lyd',
            'mad',
            'mdl',
            'mga',
            'mkd',
            'mmk',
            'mnt',
            'mop',
            'mro',
            'mtl',
            'mur',
            'mvr',
            'mw',
            'mxn',
            'myr',
            'mzn',
            'nad',
            'ngn',
            'nio',
            'nok',
            'npr',
            'nzd',
            'omr',
            'pab',
            'pen',
            'pgk',
            'php',
            'pkr',
            'pln',
            'pyg',
            'qar',
            'ron',
            'rsd',
            'rub',
            'rwf',
            'sar',
            'sbd',
            'scr',
            'sdg',
            'sek',
            'sgd',
            'shp',
            'sll',
            'sos',
            'spl',
            'srd',
            'std',
            'svc',
            'syp',
            'szl',
            'thb',
            'tjs',
            'tmm',
            'tnd',
            'top',
            'try',
            'ttd',
            'tvd',
            'twd',
            'tzs',
            'uah',
            'ugx',
            'uyu',
            'uzs',
            'veb',
            'vef',
            'vnd',
            'vuv',
            'wst',
            'xaf',
            'xag',
            'xau',
            'xcd',
            'xdr',
            'xof',
            'xpd',
            'xpf',
            'xpt',
            'yer',
            'zar',
            'zmk',
            'zwd'
        );

        if (!is_null($companyId)) {
            // Load T/A currencies for specific company
            $arrCurrencies   = array();
            $arrCompanyTAIds = $this->getCompanyTA($companyId, true);
            if (is_array($arrCompanyTAIds) && !empty($arrCompanyTAIds)) {
                $arrCurrencies = $this->getCompanyTACurrency($arrCompanyTAIds);
            }
        }

        $arrResult = array();
        if (is_array($arrCurrencies) && !empty($arrCurrencies)) {
            // Get labels for these currencies
            foreach ($arrCurrencies as $currency) {
                $arrResult[$currency] = self::getCurrencyLabel($currency);
            }
        }
        return $arrResult;
    }

    /**
     * Load currency sign by its string id
     *
     * @param $currencyId
     * @return string
     */
    public static function getCurrencySign($currencyId)
    {
        return in_array(strtolower($currencyId ?? ''), array('cad', 'usd', 'aud')) ? '$' : '';
    }

    /**
     * Load currency label (includes sign if needed) by currency id
     *
     * @param $currencyId
     * @param bool $booWithCurrencySign
     * @return string
     */
    public static function getCurrencyLabel($currencyId, $booWithCurrencySign = true)
    {
        $currencyId = strtoupper($currencyId ?? '');
        switch ($currencyId) {
            case 'AUD':
                $currencyLabel = 'AUD';
                $currencySign  = '$';
                break;

            case 'USD':
                $currencyLabel = 'US';
                $currencySign  = '$';
                break;

            case 'CAD':
                $currencyLabel = 'CDN';
                $currencySign  = '$';
                break;

            default:
                $currencyLabel = $currencyId;
                $currencySign  = '';
                break;
        }

        return $booWithCurrencySign ? $currencyLabel . $currencySign : $currencyLabel;
    }

    /**
     * Load currency by specific company T/A
     *
     * @param $companyTAId
     * @return string
     */
    public function getCurrency($companyTAId)
    {
        $arrInfo = $this->getTAInfo($companyTAId);
        return is_array($arrInfo) && array_key_exists('currency', $arrInfo) ? $arrInfo['currency'] : '';
    }


    /**
     * Check if Company T/A is assigned at last to one client
     *
     * @param int $companyTAId
     * @return bool true if company is assigned, otherwise false
     */
    public function isCompanyTAAssigned($companyTAId)
    {
        $select = (new Select())
            ->from('members_ta')
            ->columns(array('members_count' => new Expression('COUNT(*)')))
            ->where(['company_ta_id' => $companyTAId]);

        return $this->_db2->fetchOne($select) > 0;
    }

    /**
     * Check if company T/A has assigned transactions
     *
     * @param int $companyTAId
     * @param null|array $arrTransactionsIds
     * @return bool true if there are assigned transactions
     */
    public function hasTrustAccountAssignedTransactions($companyTAId, $arrTransactionsIds = null)
    {
        if (empty($arrTransactionsIds)) {
            // get all TA records
            $select = (new Select())
                ->from('u_trust_account')
                ->columns(array('trust_account_id'))
                ->where(['company_ta_id' => (int)$companyTAId]);

            $arrTransactionsIds = $this->_db2->fetchCol($select);
        }

        if (!count($arrTransactionsIds)) {
            return false;
        }

        $select = (new Select())
            ->from('u_assigned_deposits')
            ->columns(array('deposits_count' => new Expression('COUNT(*)')))
            ->where(['trust_account_id' => $arrTransactionsIds]);

        $depositsCount = $this->_db2->fetchOne($select);

        $select = (new Select())
            ->from('u_assigned_withdrawals')
            ->columns(array('withdrawals_count' => new Expression('COUNT(*)')))
            ->where(['trust_account_id' => $arrTransactionsIds]);

        $withdrawalsCount = $this->_db2->fetchOne($select);

        $select = (new Select())
            ->from('u_payment')
            ->columns(array('payments_count' => new Expression('COUNT(*)')))
            ->where(['trust_account_id' => $arrTransactionsIds]);

        $paymentsCount = $this->_db2->fetchOne($select);

        return $depositsCount > 0 || $withdrawalsCount > 0 || $paymentsCount > 0;
    }

    /**
     * Check if Company T/A can be deleted
     *
     * @param int $companyTAId
     * @param string $date
     * @param string $ba before or after the date
     * @return array(error code or 0 if T/A can be deleted, reconcile_date, transaction_ids_to_delete)
     */
    public function canDeleteTARecordsBeforeAfterDate($companyTAId, $date, $ba)
    {
        $arrTAInfo = $this->getTAInfo($companyTAId);

        // Get maximum reconciliation date
        $arrTimes = array();
        if (!Settings::isDateEmpty($arrTAInfo['last_reconcile'])) {
            $arrTimes[] = strtotime($arrTAInfo['last_reconcile']);
        }

        if (!Settings::isDateEmpty($arrTAInfo['last_reconcile_iccrc'])) {
            $arrTimes[] = strtotime($arrTAInfo['last_reconcile_iccrc']);
        }

        $reconcileDateTime = count($arrTimes) ? max(array_filter($arrTimes)) : 0;

        if ($ba == 'after') {
            if (!empty($reconcileDateTime) && $reconcileDateTime > strtotime($date)) {
                return array(1, $reconcileDateTime, array());
            }

            // Load all related transactions
            $select = (new Select())
                ->from('u_trust_account')
                ->columns(array('trust_account_id'))
                ->where(
                    [
                        (new Where())
                            ->equalTo('company_ta_id', $companyTAId)
                            ->greaterThanOrEqualTo('date_from_bank', $date)
                    ]
                );
        } else {
            if (!empty($reconcileDateTime)) {
                return array(1, $reconcileDateTime, array());
            }

            // Load all related transactions
            $select = (new Select())
                ->from('u_trust_account')
                ->columns(array('trust_account_id'))
                ->where(
                    [
                        (new Where())
                            ->equalTo('company_ta_id', $companyTAId)
                            ->lessThanOrEqualTo('date_from_bank', $date)
                    ]
                );
        }

        $arrTransactionsIds = $this->_db2->fetchCol($select);

        if (!empty($arrTransactionsIds) && $this->hasTrustAccountAssignedTransactions($companyTAId, $arrTransactionsIds)) {
            return array(2, $reconcileDateTime, $arrTransactionsIds);
        }

        return array(0, $reconcileDateTime, $arrTransactionsIds);
    }

    /**
     * Check if records in Company T/A can be deleted
     *
     * @param int $companyTAId
     * @param array $arrTransactionsIds
     * @return array(error code or 0 if T/A can be deleted, reconcile_date)
     */
    public function canDeleteTARecords($companyTAId, $arrTransactionsIds)
    {
        $arrTAInfo = $this->getTAInfo($companyTAId);
        $startDate = '';

        $reconcileDate   = 0;
        $arrDatesToCheck = [];
        if (isset($arrTAInfo['last_reconcile']) && !Settings::isDateEmpty($arrTAInfo['last_reconcile'])) {
            $arrDatesToCheck[] = strtotime($arrTAInfo['last_reconcile']);
        }

        if (isset($arrTAInfo['last_reconcile_iccrc']) && !Settings::isDateEmpty($arrTAInfo['last_reconcile_iccrc'])) {
            $arrDatesToCheck[] = strtotime($arrTAInfo['last_reconcile_iccrc']);
        }

        if (!empty($arrDatesToCheck)) {
            $reconcileDate = max($arrDatesToCheck);
        }

        // Load all related transactions
        $select = (new Select())
            ->from('u_trust_account')
            ->where(
                [
                    'company_ta_id'    => (int)$companyTAId,
                    'trust_account_id' => $arrTransactionsIds
                ]
            )
            ->order('date_from_bank');

        $arrTransactions = $this->_db2->fetchAll($select);

        // We need to be sure that passed record ids are for this T/A
        $arrRealTransactionsIds = array();
        foreach ($arrTransactions as $transaction) {
            $arrRealTransactionsIds[] = $transaction['trust_account_id'];
            if (!empty($reconcileDate) && $reconcileDate > strtotime($transaction['date_from_bank'])) {
                return array(1, $reconcileDate, '', array());
            }
            if (empty($startDate)) {
                $startDate = $transaction['date_from_bank'];
            }
        }

        if (!empty($arrTransactionsIds) && $this->hasTrustAccountAssignedTransactions($companyTAId, $arrTransactionsIds)) {
            return array(2, $reconcileDate, '', array());
        }

        return array(0, $reconcileDate, $startDate, $arrRealTransactionsIds);
    }

    /**
     * Check if currency can be changed for specific company T/A
     *
     * @param $companyTAId
     * @return bool true if currency can be changed
     */
    public function canDeleteOrChangeCurrency($companyTAId)
    {
        $select = (new Select())
            ->from('u_import_transactions')
            ->columns(array('import_count' => new Expression('COUNT(*)')))
            ->where(['company_ta_id' => (int)$companyTAId]);

        $countImport = $this->_db2->fetchOne($select);

        return empty($countImport);
    }

    /**
     * Check if current user has access TA tab
     *
     * @return bool true if user has access, otherwise false
     */
    public function canCurrentMemberAddEditTA()
    {
        return $this->_acl->isAllowed('trust-account-settings-view');
    }

    /**
     * Check if current user can access payment by payment info
     * @param array $arrPaymentInfo
     *
     * @return bool true if user can access, otherwise false
     */
    public function canCurrentMemberAccessPayment($arrPaymentInfo)
    {
        $booCan = false;

        if (!empty($arrPaymentInfo) && is_array($arrPaymentInfo) && array_key_exists('company_ta_id', $arrPaymentInfo)) {
            $booCan = $this->_parent->hasCurrentMemberAccessToTA($arrPaymentInfo['company_ta_id']);
        }

        return $booCan;
    }

    /**
     * Format float price in readable format
     *
     * @param $floatPrice
     * @param string $currencyId
     * @param bool $booShowZeroValue
     * @param bool $booShowAfterDecimals
     * @return string
     */
    public static function formatPrice($floatPrice, $currencyId = '', $booShowZeroValue = true, $booShowAfterDecimals = true)
    {
        $floatPrice = floatval($floatPrice);
        if (empty($floatPrice) && !$booShowZeroValue) {
            return '';
        }

        if (!empty($currencyId)) {
            $sign      = self::getCurrencySign($currencyId);
            $strFormat = $booShowAfterDecimals ? "%01.2f" : "%01.0f";
            if ($floatPrice < 0) {
                return '-' . $sign . sprintf($strFormat, abs($floatPrice));
            } else {
                return $sign . sprintf($strFormat, $floatPrice);
            }
        } else {
            return sprintf('%01.2f', $floatPrice);
        }
    }

    /**
     * Load paged info from specific T/A for specific client
     *
     * @param $memberId
     * @param $companyTAId
     * @param $start
     * @param $limit
     * @return array
     */
    public function getClientsTrustAccountInfoPaged($memberId, $companyTAId, $start, $limit)
    {
        $arr = $this->getClientsTrustAccountInfo($memberId, $companyTAId);

        // Apply paging
        $result = array();
        for ($i = $start; $i < $start + $limit; $i++) {
            if (count($arr) > $i) {
                $result[] = $arr[$i];
            }
        }

        return array(
            'rows'                  => $result,
            'totalCount'            => count($arr),
            'available_total'       => $this->getTrustAccountSubTotal($memberId, $companyTAId, false),
            'deposits_not_verified' => $this->getNotVerifiedDepositsSum($memberId, $companyTAId, false)
        );
    }

    /**
     * Load all info from specific T/A for specific client
     *
     * @param $memberId
     * @param $companyTAId
     * @param $booPrint
     * @return array
     */
    public function getClientsTrustAccountInfo($memberId, $companyTAId, $booPrint = false)
    {
        try {
            $arrResult = array();

            $taLabel           = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            $booCanEditClient  = $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) && !$booPrint;
            $companyTACurrency = $this->getCompanyTACurrency($companyTAId);

            // Load assigned deposits to this client
            $select = (new Select())
                ->from(array('a' => 'u_assigned_deposits'))
                ->join(array('m' => 'members'), 'm.member_id = a.author_id', array('fName', 'lName'), Select::JOIN_LEFT)
                ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = a.trust_account_id', ['date_from_bank'], Select::JOIN_LEFT)
                ->where(
                    [
                        'a.member_id'     => (int)$memberId,
                        'a.company_ta_id' => (int)$companyTAId
                    ]
                );

            $arrAssignedDeposits = $this->_db2->fetchAll($select);

            foreach ($arrAssignedDeposits as $arrDepositInfo) {
                if (empty($arrDepositInfo ['trust_account_id'])) {
                    // Not Cleared Deposit
                    $description = empty($arrDepositInfo['description']) ? $this->_tr->translate('Pending for clearing in ' . $taLabel) : $arrDepositInfo['description'];
                    $comments    = $this->_tr->translate('Deposit Not Verified');
                    $date        = $arrDepositInfo ['date_of_event'];
                } else {
                    // Cleared Deposit
                    $description = $arrDepositInfo['deposit'] < 0 ? $this->_tr->translate('Refund from ' . $taLabel) : $this->_tr->translate('Deposit in ' . $taLabel);
                    $comments    = $this->_tr->translate('Deposit Verified');
                    $date        = Settings::isDateEmpty($arrDepositInfo ['date_from_bank']) ? '' : $arrDepositInfo ['date_from_bank'];
                }

                $receiptNumber = isset($arrDepositInfo['receipt_number']) && !empty($arrDepositInfo['receipt_number']) ? $arrDepositInfo['receipt_number'] : '';

                if (!$booPrint && !empty($arrDepositInfo["template_id"]) && !empty($arrDepositInfo["member_id"])) {
                    $receiptNumber = '<a href="#" onclick="downloadAssignedDepositReceipt(' . $arrDepositInfo["member_id"] . ',' . $arrDepositInfo["template_id"] . '); return false;">' . $arrDepositInfo['receipt_number'] . '</a>';
                }

                $arrResult[] = array(
                    'id'               => 'deposit-' . $arrDepositInfo['deposit_id'],
                    'real_id'          => $arrDepositInfo['deposit_id'],
                    'type'             => 'deposit',
                    'can_edit_client'  => $booCanEditClient,
                    'date'             => $this->_settings->formatDate($date),
                    'time'             => strtotime($date),
                    'description'      => $description,
                    'receipt_number'   => $receiptNumber,
                    'deposit'          => static::formatPrice($arrDepositInfo ['deposit']),
                    'withdrawal'       => static::formatPrice(0),
                    'trust_account_id' => $arrDepositInfo ['trust_account_id'],
                    'status'           => empty($arrDepositInfo['notes']) ? $comments : $comments . ' - ' . $arrDepositInfo['notes']
                );
            }


            // 2. Invoices payments (assigned and not)
            $select = (new Select())
                ->from(array('ip' => 'u_invoice_payments'))
                ->join(array('i' => 'u_invoice'), 'ip.invoice_id = i.invoice_id', ['date_of_creation', 'notes', 'invoice_num'])
                ->join(array('w' => 'u_assigned_withdrawals'), 'w.invoice_payment_id = ip.invoice_payment_id', ['withdrawal_id', 'trust_account_id', 'withdrawal', 'withdrawal_notes' => 'notes', 'assigned_date' => 'date_of_event'], Join::JOIN_LEFT)
                ->where(['i.member_id ' => (int)$memberId])
                ->where([
                    (new Where())
                        ->nest()
                        ->nest()
                        ->equalTo('ip.company_ta_id', (int)$companyTAId)
                        ->and
                        ->isNull('ip.transfer_from_company_ta_id')
                        ->unnest()
                        ->or
                        ->equalTo('ip.transfer_from_company_ta_id', (int)$companyTAId)
                        ->unnest()
                ]);

            $arrInvoicesPayments = $this->_db2->fetchAll($select);

            foreach ($arrInvoicesPayments as $arrInvoicesPayment) {
                $notes = empty($arrInvoicesPayment['withdrawal_notes']) ? $arrInvoicesPayment['notes'] : $arrInvoicesPayment['withdrawal_notes'];
                $notes = empty($notes) ? '' : ' - ' . $notes;

                $status = sprintf(
                    'Invoice Payment %s- %s%s',
                    empty($arrInvoicesPayment['invoice_num']) ? '' : (is_numeric($arrInvoicesPayment['invoice_num']) ? '#' : '') . $arrInvoicesPayment['invoice_num'] . ' ',
                    empty($arrInvoicesPayment ['assigned_date']) ? $this->_tr->translate('Not Cleared') : $this->_tr->translate('Cleared'),
                    $notes
                );

                if (empty($arrInvoicesPayment ['assigned_date'])) {
                    if (!empty($arrInvoicesPayment['transfer_from_company_ta_id']) && $arrInvoicesPayment['transfer_from_company_ta_id'] == $companyTAId) {
                        $withdrawal = $arrInvoicesPayment ['transfer_from_amount'];
                    } else {
                        $withdrawal = $arrInvoicesPayment ['invoice_payment_amount'];
                    }
                } else {
                    if (!empty($arrInvoicesPayment['transfer_from_company_ta_id'])) {
                        $otherTACurrency = $this->getCompanyTACurrency($arrInvoicesPayment['transfer_from_company_ta_id']);

                        // Show 'equivalent of' only if currencies are different
                        if ($companyTACurrency != $otherTACurrency) {
                            $status .= '<br/>' .
                                sprintf(
                                    $this->_tr->translate('(equivalent of %s %s)'),
                                    $arrInvoicesPayment['transfer_from_amount'],
                                    self::getCurrencyLabel($otherTACurrency)
                                );
                        }
                    }

                    $withdrawal = $arrInvoicesPayment ['withdrawal'];
                }

                $arrResult[] = array(
                    'id'               => 'invoice-payment-' . $arrInvoicesPayment['invoice_payment_id'],
                    'real_id'          => $arrInvoicesPayment['invoice_payment_id'],
                    'type'             => 'invoice-payment',
                    'can_edit_client'  => $booCanEditClient,
                    'date'             => $this->_settings->formatDate($arrInvoicesPayment ['invoice_payment_date']),
                    'time'             => strtotime($arrInvoicesPayment ['invoice_payment_date']),
                    'date_of_creation' => $arrInvoicesPayment ['date_of_creation'],
                    'description'      => $this->_tr->translate('Transferred from ') . $taLabel,
                    'deposit'          => static::formatPrice(0),
                    'withdrawal'       => static::formatPrice($withdrawal),
                    'trust_account_id' => $arrInvoicesPayment ['trust_account_id'],
                    'status'           => $status
                );
            }


            // Load assigned withdrawals to this client
            $select = (new Select())
                ->from(array('a' => 'u_assigned_withdrawals'))
                ->join(array('m' => 'members'), 'm.member_id = a.author_id', array('fName', 'lName'), Select::JOIN_LEFT)
                ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = a.trust_account_id', ['date_from_bank'], Select::JOIN_LEFT)
                ->where(
                    [
                        (new Where())
                            ->nest()
                            ->equalTo('a.company_ta_id', (int)$companyTAId)
                            ->equalTo('a.member_id', (int)$memberId)
                            ->unnest()
                            ->or
                            ->nest()
                            ->equalTo('a.company_ta_id', (int)$companyTAId)
                            ->equalTo('a.returned_payment_member_id', (int)$memberId)
                            ->unnest()
                    ]
                );

            $arrWithdrawals = $this->_db2->fetchAll($select);

            foreach ($arrWithdrawals as $arrWithdrawalInfo) {
                $notes = empty($arrWithdrawalInfo['notes']) ? '' : ' - ' . $arrWithdrawalInfo['notes'];

                $date = $arrWithdrawalInfo['date_of_event'];
                if (!empty($arrWithdrawalInfo ['trust_account_id']) && !Settings::isDateEmpty($arrWithdrawalInfo['date_from_bank'])) {
                    $date = $arrWithdrawalInfo['date_from_bank'];
                }

                if (!empty($arrWithdrawalInfo['member_id'])) {
                    if (empty($arrWithdrawalInfo ['trust_account_id'])) {
                        // Not Cleared Withdrawal
                        $description = empty($arrWithdrawalInfo['description']) ? $this->_tr->translate('Pending for clearing in ' . $taLabel) : $arrWithdrawalInfo['description'];
                        $linkTitle   = $this->_tr->translate('Withdrawal Not Verified');
                    } else {
                        // Cleared Withdrawal
                        $description = $arrWithdrawalInfo['withdrawal'] < 0 ? $this->_tr->translate('Refund from ' . $taLabel) : $this->_tr->translate('Transferred from ' . $taLabel);
                        $linkTitle   = $this->_tr->translate('Withdrawal Verified');
                    }

                    $comments = $linkTitle . $notes;
                    if ($booCanEditClient) {
                        $comments = sprintf(
                            "<a onclick='showWithdrawalDetails(%d,%d,%d); return false;' href='#' title='%s'>%s</a>",
                            $arrWithdrawalInfo['withdrawal_id'],
                            $memberId,
                            $companyTAId,
                            str_replace("'", "′", $comments),
                            $comments
                        );
                    }
                } else {
                    $description = $this->_tr->translate('Returned Payment');

                    $comments = $description . $notes;
                    if ($booCanEditClient) {
                        $comments = sprintf(
                            "<a onclick='showReturnedPaymentDetails(%d,%d,%d); return false;' href='#' title='%s'>%s</a>",
                            $arrWithdrawalInfo['withdrawal_id'],
                            $memberId,
                            $companyTAId,
                            str_replace("'", "′", $comments),
                            $comments
                        );
                    }
                }

                $arrResult[] = array(
                    'id'               => 'withdrawal-' . $arrWithdrawalInfo['withdrawal_id'],
                    'real_id'          => $arrWithdrawalInfo['withdrawal_id'],
                    'type'             => 'withdrawal',
                    'can_edit_client'  => $booCanEditClient,
                    'date'             => $this->_settings->formatDate($date),
                    'time'             => strtotime($date),
                    'description'      => $description,
                    'deposit'          => static::formatPrice(0),
                    'withdrawal'       => static::formatPrice($arrWithdrawalInfo['withdrawal']),
                    'trust_account_id' => $arrWithdrawalInfo['trust_account_id'],
                    'status'           => $comments
                );
            }

            // Sort records based on time and id
            $id = $time = array();
            foreach ($arrResult as $key => $row) {
                $time[$key] = $row['time'];
                $id[$key]   = $row['id'];
            }

            if (!empty ($arrResult) && !empty ($time) && !empty ($id)) {
                array_multisort($time, SORT_ASC, $id, SORT_ASC, $arrResult);
            }
        } catch (Exception $e) {
            $arrResult = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Load all not cleared deposits for specific company T/A and specific client
     *
     * @param $companyTaId
     * @param int|array $memberId
     * @param string|bool $startDate
     * @param string|bool $endDate
     * @param bool $booGroup
     * @return array
     */
    public function getNotClearedDeposits($companyTaId, $memberId = 0, $startDate = false, $endDate = false, $booGroup = false)
    {
        $select = (new Select())
            ->from(array('a' => 'u_assigned_deposits'))
            ->where(
                [
                    (new Where())
                        ->isNull('a.trust_account_id')
                        ->equalTo('a.company_ta_id', $companyTaId)
                ]
            );

        if ($startDate) {
            $select->where->greaterThan('a.date_of_event', $startDate);
        }

        if ($endDate) {
            $select->where->lessThanOrEqualTo('a.date_of_event', $endDate);
        }

        if ($memberId) {
            if (!is_array($memberId)) {
                $arrMemberIds = array($memberId);
            } else {
                $arrMemberIds = $memberId;
            }

            $select->where(['a.member_id' => $arrMemberIds]);
        }

        $arrResult = $this->_db2->fetchAll($select);

        if ($booGroup) {
            $arrNotClearedDeposits = array();
            foreach ($arrResult as $r) {
                $arrNotClearedDeposits[$r['member_id']][] = $r;
            }
            $arrResult = $arrNotClearedDeposits;
        }

        return $arrResult;
    }

    /**
     * Calculate total of not cleared deposits
     *
     * @param int $companyTaId
     * @param int|bool $memberId
     * @param string|bool $startDate
     * @param string|bool $endDate
     * @return float
     */
    public function calculateNotClearedDepositsTotal($companyTaId, $memberId = false, $startDate = false, $endDate = false)
    {
        $arrNotClearedDeposits = $this->getNotClearedDeposits($companyTaId, $memberId, $startDate, $endDate);

        $total = 0;
        foreach ($arrNotClearedDeposits as $ncd) {
            $total += $ncd['deposit'];
        }

        return $total;
    }

    /**
     * Load currency id for specific company T/A
     * (or array of ids if array was passed)
     *
     * @param int|array $companyTAId
     * @return array|string
     */
    public function getCompanyTACurrency($companyTAId)
    {
        $companyTAId = empty($companyTAId) ? 0 : $companyTAId;

        $select = (new Select())
            ->from(array('ta' => 'company_ta'))
            ->columns(['currency'])
            ->where(['ta.company_ta_id' => $companyTAId]);

        return is_array($companyTAId) ? $this->_db2->fetchCol($select) : $this->_db2->fetchOne($select);
    }

    /**
     * Load T/A info by transaction id
     *
     * @param int $trustAccountId
     * @return array
     */
    public function getCompanyTARecordByTrustAccountId($trustAccountId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->where([(new Where())->equalTo('ta.trust_account_id', $trustAccountId)]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load company T/A id by imported transaction id
     *
     * @param int $trustAccountId
     * @return int
     */
    public function getCompanyTAIdByTrustAccountId($trustAccountId)
    {
        $arrRecord = $this->getCompanyTARecordByTrustAccountId($trustAccountId);

        return $arrRecord['company_ta_id'] ?? '';
    }

    /**
     * Load ids of all imported transactions for specific company T/A
     *
     * @param int $companyTAId
     * @return array
     */
    public function getTrustAccountIdByCompanyTAId($companyTAId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(['trust_account_id'])
            ->where(['ta.company_ta_id' => (int)$companyTAId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load company T/A info by provided id
     *
     * @param int $companyTAId
     * @param bool $booIdOnly - true to return id only (can be used to check if id is correct)
     * @return array|int
     */
    public function getCompanyTAbyId($companyTAId, $booIdOnly = false)
    {
        $select = (new Select())
            ->from(array('ta' => 'company_ta'))
            ->columns([$booIdOnly ? 'company_ta_id' : Select::SQL_STAR])
            ->where(['ta.company_ta_id' => (int)$companyTAId])
            ->order('ta.name');

        return $booIdOnly ? $this->_db2->fetchOne($select) : $this->_db2->fetchRow($select);
    }

    /**
     * Load assigned company T/A id(s) for specific client
     *
     * @param int $memberId
     * @param bool $booIdOnly - true to load ids only
     * @return array
     */
    public function getMemberCompanyTA($memberId, $booIdOnly = false)
    {
        return $this->getCompanyTA($this->_company->getMemberCompanyId($memberId), $booIdOnly);
    }

    /**
     * Check if company T/A can be changed or deleted for specific client
     *
     * @param int $memberId
     * @param int $companyTaId
     * @return bool
     */
    public function canDeleteOrChangeTA($memberId, $companyTaId)
    {
        $arrTrustAccountSummary = $this->getClientsTrustAccountInfo($memberId, $companyTaId);

        return (count($arrTrustAccountSummary) == 0);
    }

    /**
     * Assign specific company T/A to specific client
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param int $order
     */
    public function assignMemberTA($memberId, $companyTAId, $order = 0)
    {
        $this->_db2->insert(
            'members_ta',
            [
                'member_id'     => (int)$memberId,
                'company_ta_id' => (int)$companyTAId,
                'order'         => (int)$order
            ]
        );
    }

    /**
     * Load all assigned company T/A for specific client
     * @param int $memberId
     * @param bool $booIdOnly - true if load only ids
     * @return array
     */
    public function getMemberTA($memberId, $booIdOnly = false)
    {
        $arrSelectFrom = $booIdOnly ? array('company_ta_id') : array('company_ta_id', 'order');
        $arrSelectJoin = $booIdOnly ? [] : array('company_id', 'name', 'currency', 'view_transactions_months');

        $select = (new Select())
            ->from(array('ma' => 'members_ta'))
            ->columns($arrSelectFrom)
            ->join(array('ta' => 'company_ta'), 'ta.company_ta_id = ma.company_ta_id', $arrSelectJoin, Select::JOIN_LEFT)
            ->where(['ma.member_id' => (int)$memberId])
            ->order('ma.order');

        return $booIdOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load all assigned company T/A for specific clients
     *
     * @param array $arrMemberIds
     * @param bool $booCalculateOB - true if OB needs to be calculated
     * @return array
     */
    public function getMembersTA($arrMemberIds, $booCalculateOB = false)
    {
        $arrMembersTa = array();
        if (!empty($arrMemberIds)) {
            $select = (new Select())
                ->from(array('ma' => 'members_ta'))
                ->columns(array('company_ta_id', 'order', 'member_id', 'outstanding_balance', 'sub_total', 'sub_total_cleared'))
                ->join(array('ta' => 'company_ta'), 'ta.company_ta_id = ma.company_ta_id', array('company_id', 'name', 'currency', 'view_transactions_months'), Select::JOIN_LEFT)
                ->where(['ma.member_id' => $arrMemberIds]);

            $arrMembersTa = $this->_db2->fetchAll($select);

            $arrResult = array();
            foreach ($arrMembersTa as $arrMemberTaInfo) {
                $arrMemberTaInfo['currencyLabel'] = self::getCurrencyLabel($arrMemberTaInfo['currency']);

                if ($booCalculateOB) {
                    $arrMemberTaInfo['outstanding_balance'] = $this->calculateOutstandingBalance($arrMemberTaInfo['member_id'], $arrMemberTaInfo['company_ta_id']);
                }

                $arrResult[$arrMemberTaInfo['member_id']][$arrMemberTaInfo['company_ta_id']] = $arrMemberTaInfo;
            }
            $arrMembersTa = $arrResult;
        }

        return $arrMembersTa;
    }

    /**
     * Get assigned T/A list for specific member
     *
     * @param int $memberId
     * @param int $companyId
     * @return array
     */
    public function getMemberTAList($memberId, $companyId)
    {
        $arrMemberAssignedTA = array();

        try {
            $arrMemberTA  = $this->getMemberTA($memberId);
            $arrCompanyTA = $this->getCompanyTA($companyId);

            // Assign this T/A automatically for this client
            // if there are no assigned T/A and company has only one T/A
            if (count($arrMemberTA) == 0 && count($arrCompanyTA) == 1) {
                $this->assignMemberTA($memberId, $arrCompanyTA[0]['company_ta_id']);
                $arrMemberTA = $this->getMemberTA($memberId);
            }

            if (is_array($arrMemberTA) && count($arrMemberTA)) {
                foreach ($arrMemberTA as $memberTAInfo) {
                    $arrMemberAssignedTA[] = array(
                        'id'            => $memberTAInfo['company_ta_id'],
                        'name'          => $memberTAInfo['name'],
                        'currency'      => $memberTAInfo['currency'],
                        'currency_name' => self::getCurrencyLabel($memberTAInfo['currency'])
                    );
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrMemberAssignedTA;
    }

    /**
     * Load all information (related to company T/A) for specific client
     *
     * @param int $memberId
     * @param int $companyId
     * @return array
     */
    public function getMemberAccounting($memberId, $companyId)
    {
        $arrResult = array();

        // Default values
        $arrResult['settings'] = $this->getAccountingSettings($companyId);

        // Load T/A list for current member
        $arrMemberTA  = $this->getMemberTA($memberId);
        $arrCompanyTA = $this->getMemberCompanyTA($memberId);

        $arrMemberCompanyTA[] = array(0, 'Not used for this case', '', '');
        if (is_array($arrCompanyTA) && !empty($arrCompanyTA)) {
            foreach ($arrCompanyTA as $TAInfo) {
                $currency = self::getCurrencyLabel($TAInfo['currency']);
                $taName   = "$TAInfo[name] - $currency";
                $taId     = $TAInfo['company_ta_id'];

                $arrMemberCompanyTA[] = array($taId, $taName, $TAInfo['currency'], $currency);
            }
        }
        $arrResult['arrCompanyTA'] = $arrMemberCompanyTA;


        // Assign this T/A automatically for this client
        // if there are no assigned T/A and company has only one T/A
        if (count($arrMemberTA) == 0 && count($arrCompanyTA) == 1) {
            $this->assignMemberTA($memberId, $arrCompanyTA[0]['company_ta_id']);
            $arrMemberTA = $this->getMemberTA($memberId);
        }


        // Get available currencies
        $arrCurrencies          = array();
        $arrSupportedCurrencies = $this->getSupportedCurrencies($companyId);
        if (is_array($arrSupportedCurrencies) && !empty($arrSupportedCurrencies)) {
            foreach ($arrSupportedCurrencies as $currencyId => $currencyLabel) {
                $arrCurrencies[] = array($currencyId, $currencyLabel);
            }
        }
        $arrResult['currencies'] = $arrCurrencies;


        // Get assigned Member T/A and related info
        $primaryTAId         = 0;
        $primaryCurrency     = '';
        $secondaryTAId       = 0;
        $arrMemberAssignedTA = array();
        $switchTAMode        = [];
        if (is_array($arrMemberTA) && !empty($arrMemberTA)) {
            foreach ($arrMemberTA as $memberTAInfo) {
                $taId = $memberTAInfo['company_ta_id'];

                if ($memberTAInfo['order'] == 0) {
                    $primaryTAId     = $taId;
                    $primaryCurrency = $memberTAInfo['currency'];
                } elseif ($memberTAInfo['order'] == 1) {
                    $secondaryTAId = $taId;
                }

                $arrMemberAssignedTA[] = array(
                    'id'            => $taId,
                    'name'          => $memberTAInfo['name'],
                    'currency'      => $memberTAInfo['currency'],
                    'currency_name' => self::getCurrencyLabel($memberTAInfo['currency'])
                );

                $switchTAMode[] = [
                    'ta_id'      => $taId,
                    'can_change' => $this->canDeleteOrChangeTA($memberId, $taId)
                ];
            }
        }

        $arrResult['switchTAMode']    = $switchTAMode;
        $arrResult['arrMemberTA']     = $arrMemberAssignedTA;
        $arrResult['primaryTAId']     = $primaryTAId;
        $arrResult['primaryCurrency'] = $primaryCurrency;
        $arrResult['secondaryTAId']   = $secondaryTAId;

        return $arrResult;
    }

    /**
     * Default Settings for Accounting tabs
     *
     * @return array
     */
    public function getAccountingSettings($companyId)
    {
        $arrSettings = array();

        $arrCompanyDetails                  = $this->_company->getCompanyDetailsInfo($companyId);
        $arrInvoiceNumberSettings           = Json::decode($arrCompanyDetails['invoice_number_settings'], Json::TYPE_ARRAY);
        $arrSettings['invoiceNumberFormat'] = $arrInvoiceNumberSettings['format'];
        $arrSettings['arrSavedPayments']    = $this->getSavedPaymentsList(true);
        $arrSettings['arrProvinces']        = $this->_gstHst->getTaxesList();
        $arrSettings['recordsOnPage']       = 500;
        $arrSettings['taRecordsOnPage']     = 500;
        $arrSettings['scheduleHelpMessage'] = $this->_config['site_version']['retainer_schedule_help_message'];

        $arrSettings['invoicePaymentOperatingAccountLabel']       = self::getInvoicePaymentOperatingAccountOption();
        $arrSettings['arrInvoicePaymentSpecialAdjustmentOptions'] = $this->getInvoicePaymentSpecialAdjustmentOptions();

        // Get available currencies
        $arrCurrencies          = array();
        $arrSupportedCurrencies = $this->getSupportedCurrencies($companyId);
        if (is_array($arrSupportedCurrencies) && !empty($arrSupportedCurrencies)) {
            foreach ($arrSupportedCurrencies as $currencyId => $currencyLabel) {
                $arrCurrencies[] = array($currencyId, $currencyLabel);
            }
        }
        $arrSettings['arrCurrencies'] = $arrCurrencies;

        // A list of T/A available for the company
        $arrCompanyTAs = $this->getCompanyTA($companyId);

        $arrMemberCompanyTA[] = array(0, 'Not used for this case', '', '');
        if (is_array($arrCompanyTAs) && !empty($arrCompanyTAs)) {
            foreach ($arrCompanyTAs as $arrCompanyTAInfo) {
                $currency = self::getCurrencyLabel($arrCompanyTAInfo['currency']);

                $arrMemberCompanyTA[] = array($arrCompanyTAInfo['company_ta_id'], "$arrCompanyTAInfo[name] - $currency", $arrCompanyTAInfo['currency'], $currency);
            }
        }
        $arrSettings['arrCompanyTA'] = $arrMemberCompanyTA;

        return $arrSettings;
    }

    /**
     * Calculate assigned deposits assigned to a specific client and company T/A
     *
     * @param array|int $memberId
     * @param int $companyTaId
     * @param bool|string $startDate
     * @param bool|string $endDate
     * @param bool $booCleared
     * @return float
     */
    public function getClientAssignedDeposits($memberId, $companyTaId, $startDate = false, $endDate = false, $booCleared = false)
    {
        if (empty($memberId) || empty($companyTaId)) {
            return 0;
        }

        $select = (new Select())
            ->quantifier(Select::QUANTIFIER_DISTINCT)
            ->from(array('a' => 'u_assigned_deposits'))
            ->columns(['sum' => new Expression('SUM(a.deposit)')])
            ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = a.trust_account_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'a.company_ta_id' => (int)$companyTaId,
                    'a.member_id'     => $memberId
                ]
            );

        if ($booCleared) {
            $select->where->isNotNull('a.trust_account_id');
        }

        if ($startDate) {
            $select->where->greaterThan('date_from_bank', $startDate);
        }
        if ($endDate) {
            $select->where->lessThanOrEqualTo('date_from_bank', $endDate);
        }

        return (float)$this->_db2->fetchOne($select);
    }


    /**
     * Calculate sub total for specific client and company T/A
     *
     * @param array|int $memberId
     * @param int $companyTaId
     * @param bool|string $startDate
     * @param bool|string $endDate
     * @param bool $booCleared
     * @return float|int
     */
    public function calculateTrustAccountSubTotal($memberId, $companyTaId, $startDate = false, $endDate = false, $booCleared = false)
    {
        if (empty($memberId) || empty($companyTaId)) {
            return 0;
        }

        $memberId = is_array($memberId) ? $memberId : array($memberId);

        // Calculate deposits sum
        $deposit = $this->getClientAssignedDeposits($memberId, $companyTaId, $startDate, $endDate, $booCleared);

        // Get assigned withdrawals
        $arrMembersInvoicePaymentIds = $this->getMembersInvoicePayments($memberId, $companyTaId, true);
        $arrMembersInvoicePaymentIds = empty($arrMembersInvoicePaymentIds) ? array(0) : $arrMembersInvoicePaymentIds;

        // Calculate withdrawals sum
        $select = (new Select())
            ->quantifier(Select::QUANTIFIER_DISTINCT)
            ->from(array('a' => 'u_assigned_withdrawals'))
            ->columns(['sum' => new Expression('SUM(a.withdrawal)')])
            ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = a.trust_account_id', [], Select::JOIN_LEFT)
            ->where([
                (new Where())
                    ->nest()
                    ->nest()
                    ->nest()
                    ->isNull('a.returned_payment_member_id')
                    ->isNull('a.special_transaction')
                    ->isNull('a.special_transaction_id')
                    ->unnest()
                    ->in('a.invoice_payment_id', $arrMembersInvoicePaymentIds)
                    ->unnest()
                    ->or
                    ->in('returned_payment_member_id', $memberId)
                    ->or
                    ->in('a.member_id', $memberId)
                    ->unnest()
                    ->equalTo('a.company_ta_id', (int)$companyTaId)
            ]);

        if ($booCleared) {
            $select->where([(new Where())->isNotNull('a.trust_account_id')]);
        }

        if ($startDate) {
            $select->where([(new Where())->greaterThan('date_from_bank', $startDate)]);
        }
        if ($endDate) {
            $select->where([(new Where())->lessThanOrEqualTo('date_from_bank', $endDate)]);
        }

        $withdrawal = (float)$this->_db2->fetchOne($select);

        // Calculate unassigned invoice payments sum
        $unassignedInvoicePaymentsAmount = $this->calculateUnassignedInvoicePaymentsAmount($memberId, $companyTaId, $startDate, $endDate);
        $unassignedInvoicePaymentsAmount = empty($unassignedInvoicePaymentsAmount) ? 0 : $unassignedInvoicePaymentsAmount;

        return ($deposit - $withdrawal - $unassignedInvoicePaymentsAmount);
    }

    /**
     * Create start balance record for specific company T/A
     *
     * @param $companyTaId
     * @param $balance
     * @param string $date
     */
    public function createStartBalance($companyTaId, $balance, $date = '')
    {
        $this->_db2->insert(
            'u_trust_account',
            [
                'company_ta_id'  => (int)$companyTaId,
                'import_id'      => null,
                'fit'            => null,
                'date_from_bank' => empty($date) ? date('c') : $date,
                'description'    => 'Starting Balance',
                'deposit'        => (double)$balance,
                'withdrawal'     => 0,
                'balance'        => 0,
                'purpose'        => $this->startBalanceTransactionId
            ]
        );
    }

    /**
     * Delete balance record if exists
     *
     * @param int $companyTAId
     */
    public function deleteStartRecord($companyTAId)
    {
        $this->_db2->delete(
            'u_trust_account',
            [
                'company_ta_id' => (int)$companyTAId,
                'purpose'       => $this->startBalanceTransactionId
            ]
        );
    }


    /**
     * Update start balance record for specific company T/A
     *
     * @param int $companyTaId
     * @param int $balance
     * @param string $date
     */
    public function updateStartBalance($companyTaId, $balance = null, $date = null)
    {
        $arrUpdate = array();
        if (!is_null($balance)) {
            $arrUpdate['deposit'] = (double)$balance;
        }

        if (!is_null($date)) {
            $arrUpdate['date_from_bank'] = empty($date) ? date('c') : $date;
        }

        if (count($arrUpdate)) {
            $this->_db2->update(
                'u_trust_account',
                $arrUpdate,
                [
                    'company_ta_id' => $companyTaId,
                    'purpose'       => $this->startBalanceTransactionId
                ]
            );
        }
    }

    /**
     * Load information about first transaction for specific T/A
     * (except of the starting balance)
     *
     * @param $companyTaId
     * @return array
     */
    public function getFirstTransactionInfo($companyTaId)
    {
        $select = (new Select())
            ->from('u_trust_account')
            ->where(
                [
                    (new Where())
                        ->equalTo('company_ta_id', (int)$companyTaId)
                        ->notEqualTo('purpose', $this->startBalanceTransactionId)
                ]
            )
            ->order('date_from_bank');

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load start balance transaction saved info for specific T/A
     *
     * @param $companyTaId
     * @return array
     */
    public function getStartBalanceInfo($companyTaId)
    {
        $select = (new Select())
            ->from('u_trust_account')
            ->where(
                [
                    'company_ta_id' => (int)$companyTaId,
                    'purpose'       => $this->startBalanceTransactionId
                ]
            );

        return $this->_db2->fetchRow($select);
    }

    /**
     * Update balance for records of the company T/A
     *
     * @param int $companyTaId
     * @param ?string $startDate
     * @param double $openingBalance
     */
    public function updateTrustAccountRecordsBalance($companyTaId, $startDate = null, $openingBalance = null)
    {
        $arrOpeningBalanceInfo = $this->getStartBalanceInfo($companyTaId);
        if (is_null($openingBalance)) {
            if (empty($arrOpeningBalanceInfo['trust_account_id'])) {
                $openingBalance = 0.00;
            } else {
                $openingBalance = (double)$arrOpeningBalanceInfo['deposit'];
            }
        }

        // We need to be sure that start balance record exists in DB
        if (empty($arrOpeningBalanceInfo['trust_account_id'])) {
            // Load date of the first transaction
            $arrFirstRecord   = $this->getFirstTransactionInfo($companyTaId);
            $startBalanceDate = !empty($arrFirstRecord) ? date('c', strtotime($arrFirstRecord['date_from_bank']) - 60 * 60 * 24) : '';

            $this->createStartBalance($companyTaId, $openingBalance, $startBalanceDate);
        }

        $select = (new Select())
            ->from('u_trust_account')
            ->where(['company_ta_id' => (int)$companyTaId])
            ->order('date_from_bank');

        $transactions = $this->_db2->fetchAll($select);

        $balance = 0.00;
        if (!is_null($startDate)) {
            foreach ($transactions as $transaction) {
                if (strtotime($transaction['date_from_bank']) < strtotime($startDate)) {
                    if ($transaction['purpose'] == $this->startBalanceTransactionId) {
                        $balance = $transaction['deposit'];
                    } else {
                        $balance = $transaction['balance'] + $transaction['deposit'] - $transaction['withdrawal'];
                    }
                }
            }
        }

        $deposit    = 0.00;
        $withdrawal = 0.00;

        foreach ($transactions as $transaction) {
            if (is_null($startDate) || strtotime($transaction['date_from_bank']) >= strtotime($startDate)) {
                $balance = $balance + $deposit - $withdrawal;

                if ($transaction['purpose'] == $this->startBalanceTransactionId) {
                    $this->updateStartBalance($companyTaId, $openingBalance);

                    $transaction['deposit'] = $openingBalance;
                } else {
                    $this->_db2->update(
                        'u_trust_account',
                        ['balance' => $balance],
                        [
                            'company_ta_id'    => $companyTaId,
                            'trust_account_id' => (int)$transaction['trust_account_id']
                        ]
                    );
                }

                $deposit    = (double)$transaction['deposit'];
                $withdrawal = (double)$transaction['withdrawal'];
            }
        }
    }

    /**
     * Load T/A subtotal for the whole company or for a specific client
     *
     * @param int|bool $memberId
     * @param int|bool $companyTAId
     * @param bool $booFormat
     * @param bool $booCleared
     * @param int $companyId
     * @return array|int
     */
    public function getTrustAccountSubTotal($memberId = false, $companyTAId = false, $booFormat = true, $booCleared = false, $companyId = null)
    {
        $variable  = $booCleared ? 'sub_total_cleared' : 'sub_total';
        $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        $select = (new Select())
            ->from(array('mta' => 'members_ta'))
            ->columns(array($variable, 'member_id', 'company_ta_id'))
            ->join(array('cta' => 'company_ta'), 'cta.company_ta_id = mta.company_ta_id', array('currency'), Select::JOIN_LEFT)
            ->where(['cta.company_id' => (int)$companyId]);

        if ($memberId) {
            $select->where(['mta.member_id' => (int)$memberId]);
        }

        if ($companyTAId) {
            $select->where(['mta.company_ta_id' => (int)$companyTAId]);
        }

        $subTotalArr = $this->_db2->fetchAll($select);

        if ($booFormat) {
            foreach ($subTotalArr as $key => $mta) {
                $subTotalArr[$key][$variable] = static::formatPrice($mta[$variable], $mta['currency']);
            }
        }

        //group
        if ($memberId && $companyTAId) {
            return $subTotalArr[0][$variable] ?? 0;
        } else {
            $subTotal = array();
            $currency = $this->_settings->getSiteDefaultCurrency();
            foreach ($subTotalArr as $st) {
                $subTotal[$st['company_ta_id']][$currency]        = ($st['currency'] == $currency);
                $subTotal[$st['company_ta_id']][$st['member_id']] = $st[$variable];
            }
            return $subTotal;
        }
    }

    /**
     * Load cleared subtotal for specific client and company T/A
     *
     * @param bool $memberId
     * @param bool $companyTAId
     * @param bool $format
     * @param null $companyId
     * @return array|int
     */
    public function getTrustAccountSubTotalCleared($memberId = false, $companyTAId = false, $format = true, $companyId = null)
    {
        return $this->getTrustAccountSubTotal($memberId, $companyTAId, $format, true, $companyId);
    }

    /**
     * Load not verified sum of deposits for specific client and company T/A
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param bool $booFormat
     * @param bool $startDate
     * @param bool $endDate
     * @return float
     */
    public function getNotVerifiedDepositsSum($memberId, $companyTAId, $booFormat = true, $startDate = false, $endDate = false)
    {
        $sumOfNotClearedDeposits = $this->calculateNotClearedDepositsTotal($companyTAId, $memberId, $startDate, $endDate);
        if ($booFormat) {
            $currency                = $this->getCompanyTACurrency($companyTAId);
            $sumOfNotClearedDeposits = static::formatPrice($sumOfNotClearedDeposits, $currency);
        }

        return $sumOfNotClearedDeposits;
    }

    /**
     * Load not verified deposits for specific clients
     *
     * @param array $arrMemberIds
     * @param bool $startDate
     * @param bool $endDate
     * @return array
     */
    public function getNotVerifiedDepositsForClientBalancesReport($arrMemberIds, $startDate = false, $endDate = false)
    {
        $select = (new Select())
            ->from(array('a' => 'u_assigned_deposits'))
            ->where([(new Where())->isNull('a.trust_account_id')])
            ->where(['a.member_id' => $arrMemberIds]);

        if ($startDate) {
            $select->where->greaterThan('a.date_of_event', $startDate);
        }

        if ($endDate) {
            $select->where->lessThanOrEqualTo('a.date_of_event', $endDate);
        }

        $arrNotVerifiedDeposits = $this->_db2->fetchAll($select);

        $arrResult = array();
        foreach ($arrNotVerifiedDeposits as $arrNotVerifiedDeposit) {
            $arrResult[$arrNotVerifiedDeposit['member_id']][$arrNotVerifiedDeposit['company_ta_id']][] = $arrNotVerifiedDeposit;
        }
        return $arrResult;
    }

    /**
     * Update cleared and not cleared subtotals for a specific client(s) and company T/A
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param bool $booCleared
     * @param float|int $amount
     * @return void
     */
    public function updateTrustAccountCalculatedSubTotal($memberId, $companyTAId, $booCleared, $amount)
    {
        $variable = $booCleared ? 'sub_total_cleared' : 'sub_total';

        $this->_db2->update(
            'members_ta',
            [$variable => $amount],
            [
                'member_id' => (int)$memberId,
                'company_ta_id' => (int)$companyTAId
            ]
        );
    }

    /**
     * Update cleared and not cleared subtotals for a specific client(s) and company T/A
     *
     * @param int $memberId
     * @param int $companyTAId
     * @return bool
     */
    public function updateTrustAccountSubTotal($memberId, $companyTAId)
    {
        try {
            $this->updateTrustAccountCalculatedSubTotal($memberId, $companyTAId, true, $this->calculateTrustAccountSubTotal($memberId, $companyTAId, false, false, true));
            $this->updateTrustAccountCalculatedSubTotal($memberId, $companyTAId, false, $this->calculateTrustAccountSubTotal($memberId, $companyTAId));

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Calculate sum of all paid deposits
     *
     * @param int $caseId
     * @return float|int
     */
    public function calculateDepositsTotalFeesPaidByMemberId($caseId)
    {
        $companyTAId = $this->getClientPrimaryCompanyTaId($caseId);
        if (!empty($companyTAId)) {
            $select = (new Select())
                ->from(array('p' => 'u_payment'))
                ->columns(['sum' => new Expression('SUM(deposit)')])
                ->where(
                    [
                        (new Where())->isNotNull('p.transaction_id'),
                        'p.member_id'     => (int)$caseId,
                        'p.company_ta_id' => (int)$companyTAId
                    ]
                );

            $sum = (float)$this->_db2->fetchOne($select);
        } else {
            $sum = 0;
        }

        return $sum;
    }

    /**
     * Calculate Outstanding balance (what we show as Total Due Now) for a specific client and company T/A
     *
     * @param int $memberId
     * @param int $companyTaId
     * @param bool $booFormat
     * @return float
     */
    public function calculateOutstandingBalance($memberId, $companyTaId, $booFormat = false)
    {
        $arrFeesResult = $this->getClientAccountingFeesList($memberId, $companyTaId);
        $totalDue      = $arrFeesResult['total_due'];

        if ($booFormat) {
            $arrCompanyTAInfo = $this->getCompanyTAbyId($companyTaId);
            $totalFormatted   = static::formatPrice($totalDue, $arrCompanyTAInfo['currency']);
            if ($totalDue < 0) {
                $totalDue = '<span style="color:#FF0000;">' . $totalFormatted . '</span>';
            } else {
                $totalDue = $totalFormatted;
            }
        }

        return $totalDue;
    }

    /**
     * Update outstanding balance for specific client and company T/A
     *
     * @param $memberId
     * @param $companyTAId
     * @return bool true if updated successfully
     */
    public function updateOutstandingBalance($memberId, $companyTAId)
    {
        $updatedCount = $this->_db2->update(
            'members_ta',
            [
                'outstanding_balance' => $this->calculateOutstandingBalance($memberId, $companyTAId)
            ],
            [
                'member_id'     => $memberId,
                'company_ta_id' => $companyTAId
            ]
        );

        return $updatedCount == 1;
    }

    /**
     * Calculate unassigned deposits for specific company T/A
     * @param int $companyTaId
     * @param string|bool $startDate
     * @param string|bool $endDate
     * @return array
     */
    public function calculateUnassignedDeposits($companyTaId, $startDate = false, $endDate = false)
    {
        $subSelect = (new Select())
            ->from(['d' => 'u_assigned_deposits'])
            ->columns(['trust_account_id'])
            ->join(['ta' => 'u_trust_account'], 'ta.trust_account_id = d.trust_account_id', [], Select::JOIN_LEFT)
            ->where(['ta.company_ta_id' => $companyTaId]);

        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(['date_from_bank', 'description', 'deposit'])
            ->where(
                [
                    'ta.company_ta_id' => $companyTaId,
                    (new Where())->greaterThan('ta.deposit', 0),
                    (new Where())->notIn('ta.trust_account_id', $subSelect)
                ]
            );

        if ($startDate) {
            $select->where->greaterThan('ta.date_from_bank', $startDate);
        }

        if ($endDate) {
            $select->where->lessThanOrEqualTo('ta.date_from_bank', $endDate);
        }

        return $this->_db2->fetchAll($select);
    }

    /**
     * Reset all balances for a specific client and company T/A
     *
     * @param int $memberId
     * @param int $companyTAId
     * @return int
     */
    public function resetMemberTABalances($memberId, $companyTAId)
    {
        return $this->_db2->update(
            'members_ta',
            [
                'outstanding_balance' => 0,
                'sub_total'           => 0,
                'sub_total_cleared'   => 0
            ],
            [
                'member_id'     => $memberId,
                'company_ta_id' => $companyTAId
            ]
        );
    }

    /**
     * Load unassigned invoice payments list for a specific company T/A, period of time or cases
     *
     * @param int $companyTaId
     * @param bool $startDate
     * @param bool $endDate
     * @param array $arrMemberIds
     * @return array
     */
    public function getUnassignedInvoicePayments($companyTaId, $startDate = false, $endDate = false, $arrMemberIds = array())
    {
        // Get the list of assigned invoice payments
        $select = (new Select())
            ->from(array('w' => 'u_assigned_withdrawals'))
            ->columns(['invoice_payment_id'])
            ->where(
                [
                    (new Where())
                        ->isNotNull('w.invoice_payment_id')
                        ->equalTo('w.company_ta_id', (int)$companyTaId)
                ]
            );

        $arrAssignedInvoicePayments = $this->_db2->fetchCol($select);

        $select = (new Select())
            ->from(array('ip' => 'u_invoice_payments'))
            ->join(array('i' => 'u_invoice'), 'ip.invoice_id = i.invoice_id', ['invoice_num', 'member_id'])
            ->where(
                [
                    (new Where())
                        ->nest()
                        ->equalTo('ip.company_ta_id', (int)$companyTaId)
                        ->or
                        ->equalTo('ip.transfer_from_company_ta_id', (int)$companyTaId)
                        ->unnest()
                ]
            );

        if ($startDate) {
            $select->where->greaterThan('ip.invoice_payment_date', $startDate);
        }

        if ($endDate) {
            $select->where->lessThanOrEqualTo('ip.invoice_payment_date', $endDate);
        }

        if ($arrMemberIds) {
            $select->where(['i.member_id' => $arrMemberIds]);
        }

        // If there are some assigned invoice payments - skip them
        if (is_array($arrAssignedInvoicePayments) && !empty($arrAssignedInvoicePayments)) {
            $select->where->notIn('ip.invoice_payment_id', $arrAssignedInvoicePayments);
        }

        $arrResult = $this->_db2->fetchAll($select);

        $arrUnassignedInvoicePayments = array();
        foreach ($arrResult as $r) {
            $arrRecord = [
                'member_id'                   => $r['member_id'],
                'transfer_from_company_ta_id' => $r['transfer_from_company_ta_id'],
                'transfer_from_amount'        => $r['transfer_from_amount'],
                'invoice_payment_amount'      => $r['invoice_payment_amount'],
                'invoice_num'                 => $r['invoice_num'],
                'invoice_payment_date'        => $r['invoice_payment_date'],
                'invoice_payment_cheque_num'  => $r['invoice_payment_cheque_num']
            ];

            if ($arrMemberIds) {
                $arrUnassignedInvoicePayments[$r['member_id']][] = $arrRecord;
            } else {
                $arrUnassignedInvoicePayments[] = $arrRecord;
            }
        }

        return $arrUnassignedInvoicePayments;
    }

    /**
     * Load list of invoice payments for members for a specific company T/A
     *
     * @param array $arrMembersIds
     * @param int $companyTaId
     * @param bool $booIgnorePaymentsFromAnotherTA
     * @return array
     */
    public function getMembersInvoicePayments($arrMembersIds, $companyTaId, $booIgnorePaymentsFromAnotherTA = false)
    {
        $arrInvoicePaymentIds = array();

        if (!empty($arrMembersIds)) {
            $select = (new Select())
                ->from(array('p' => 'u_invoice_payments'))
                ->columns(['invoice_payment_id'])
                ->join(array('i' => 'u_invoice'), 'p.invoice_id = i.invoice_id', [])
                ->where([
                    'i.member_id' => $arrMembersIds
                ]);

            if ($booIgnorePaymentsFromAnotherTA) {
                $select->where(
                    [
                        (new Where())
                            ->nest()
                            ->isNull('p.transfer_from_company_ta_id')
                            ->and
                            ->equalTo('i.company_ta_id', (int)$companyTaId)
                            ->unnest()
                            ->or
                            ->equalTo('p.transfer_from_company_ta_id', (int)$companyTaId)
                    ]
                );
            } else {
                $select->where(['i.company_ta_id' => (int)$companyTaId]);
            }

            $arrInvoicePaymentIds = $this->_db2->fetchCol($select);
        }

        return $arrInvoicePaymentIds;
    }

    /**
     * Load list of payments for a specific invoice
     *
     * @param int $invoiceId
     * @return array
     */
    public function getInvoicePayments($invoiceId)
    {
        $arrInvoicePayments = array();

        if (!empty($invoiceId)) {
            $select = (new Select())
                ->from(array('p' => 'u_invoice_payments'))
                ->join(array('w' => 'u_assigned_withdrawals'), 'w.invoice_payment_id = p.invoice_payment_id', [], Select::JOIN_LEFT)
                ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = w.trust_account_id', ['ta_date_from_bank' => 'date_from_bank'], Select::JOIN_LEFT)
                ->where(['p.invoice_id' => (int)$invoiceId])
                ->order(array('invoice_payment_date', 'invoice_payment_id'));

            $arrInvoicePayments = $this->_db2->fetchAll($select);

            foreach ($arrInvoicePayments as $key => $arrInvoicePaymentInfo) {
                if (!empty($arrInvoicePaymentInfo['ta_date_from_bank'])) {
                    $arrInvoicePayments[$key]['invoice_payment_date'] = $arrInvoicePaymentInfo['ta_date_from_bank'];
                }

                unset($arrInvoicePayments[$key]['ta_date_from_bank']);
            }
        }

        return $arrInvoicePayments;
    }

    /**
     * Create/assign payment to a specific invoice
     *
     * @param array $arrInvoicePaymentInfo
     */
    public function createInvoicePayments($arrInvoicePaymentInfo)
    {
        $this->_db2->insert('u_invoice_payments', $arrInvoicePaymentInfo);
    }

    /**
     * Get invoice payment's info
     *
     * @param int $invoicePaymentId
     * @return array
     */
    public function getInvoicePaymentInfo($invoicePaymentId)
    {
        $arrInvoicePaymentInfo = array();
        if (!empty($invoicePaymentId)) {
            $select = (new Select())
                ->from(array('p' => 'u_invoice_payments'))
                ->join(array('i' => 'u_invoice'), 'p.invoice_id = i.invoice_id')
                ->where(['p.invoice_payment_id' => (int)$invoicePaymentId]);

            $arrInvoicePaymentInfo = $this->_db2->fetchRow($select);
        }

        return $arrInvoicePaymentInfo;
    }

    /**
     * Check if the current user has access to the invoice
     *
     * @param int $invoiceId
     * @return bool true if current user has access
     */
    public function hasAccessToInvoice($invoiceId)
    {
        $booHasAccess = false;

        if (!empty($invoiceId)) {
            $arrInvoiceInfo = $this->getInvoiceInfo($invoiceId);
            if (isset($arrInvoiceInfo['company_ta_id']) && $this->_parent->hasCurrentMemberAccessToTA($arrInvoiceInfo['company_ta_id'])) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }

    /**
     * Check if the current user has access to the invoice payment
     *
     * @param int $invoicePaymentId
     * @return bool true if current user has access
     */
    public function hasAccessToInvoicePayment($invoicePaymentId)
    {
        $booHasAccess = false;

        $arrInvoicePaymentInfo = $this->getInvoicePaymentInfo($invoicePaymentId);
        if (isset($arrInvoicePaymentInfo['company_ta_id']) && $this->_parent->hasCurrentMemberAccessToTA($arrInvoicePaymentInfo['company_ta_id'])) {
            $booHasAccess = true;
        }

        return $booHasAccess;
    }

    /**
     * Delete invoice's payment
     *
     * @param int $invoicePaymentId
     */
    public function deleteInvoicePayment($invoicePaymentId)
    {
        $this->_db2->delete('u_invoice_payments', ['invoice_payment_id' => (int)$invoicePaymentId]);
    }

    /**
     * Load list of not fully paid invoices for a specific client + T/A
     *
     * @param int $memberId
     * @return array
     */
    public function getUnpaidInvoices($memberId)
    {
        $arrUnpaidInvoices = array();

        $select = (new Select())
            ->from(array('i' => 'u_invoice'))
            ->where(['i.member_id' => $memberId]);

        $arrInvoices = $this->_db2->fetchAll($select);

        if (!empty($arrInvoices)) {
            foreach ($arrInvoices as $arrInvoiceInfo) {
                $outstandingAmount = $this->getInvoiceOutstandingAmount($arrInvoiceInfo['invoice_id']);
                if ($outstandingAmount > 0) {
                    $arrUnpaidInvoices[] = array(
                        'invoice_id'         => $arrInvoiceInfo['invoice_id'],
                        'invoice_number'     => $arrInvoiceInfo['invoice_num'],
                        'invoice_amount'     => $arrInvoiceInfo['amount'],
                        'invoice_amount_due' => $outstandingAmount,
                        'invoice_date'       => $arrInvoiceInfo['date_of_invoice'],
                    );
                }
            }
        }

        return $arrUnpaidInvoices;
    }

    /**
     * Get invoice's cheque number from created/assigned payments (the last one)
     *
     * @param $invoiceId
     * @return mixed|string
     */
    public function getInvoiceLastPaymentChequeNumber($invoiceId)
    {
        $chequeNumber = '';
        $arrPayments  = $this->getInvoicePayments($invoiceId);
        if (!empty($arrPayments)) {
            $chequeNumber = $arrPayments[0]['invoice_payment_cheque_num'];
        }

        return $chequeNumber;
    }

    /**
     * Get outstanding amount for a specific invoice (the difference between the invoice's amount and a sum of all assigned payments)
     * @param int $invoiceId
     * @return float
     */
    public function getInvoiceOutstandingAmount($invoiceId)
    {
        $outstandingBalance = 0;
        if (!empty($invoiceId)) {
            $arrPayments = $this->getInvoicePayments($invoiceId);

            $invoicePaymentsAmount = 0;
            foreach ($arrPayments as $arrInvoicePaymentInfo) {
                $invoicePaymentsAmount += $arrInvoicePaymentInfo['invoice_payment_amount'];
            }

            $arrInvoiceInfo = $this->getInvoiceInfo($invoiceId);

            $outstandingBalance = $arrInvoiceInfo['amount'] - $invoicePaymentsAmount;
        }

        return round($outstandingBalance, 2);
    }

    /**
     * Invoice can be assigned to several fees in the FT table, load these records
     *
     * @param int $invoiceId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getFeesAssignedToInvoice($invoiceId, $booIdsOnly = false)
    {
        $arrAssignedRecords = array();

        if (!empty($invoiceId)) {
            $select = (new Select())
                ->from(array('p' => 'u_payment'))
                ->columns([$booIdsOnly ? 'payment_id' : Select::SQL_STAR])
                ->where(['p.invoice_id' => (int)$invoiceId]);


            if ($booIdsOnly) {
                $arrAssignedRecords = $this->_db2->fetchCol($select);
            } else {
                $arrTransactions = $this->_db2->fetchAll($select);
                foreach ($arrTransactions as $arrTransactionInfo) {
                    $fee = round((double)$arrTransactionInfo['withdrawal'], 2);
                    $gst = 0;
                    if (!empty($arrTransactionInfo['gst_province_id'])) {
                        $arrProvinceInfo = $this->_gstHst->getProvinceById($arrTransactionInfo['gst_province_id']);

                        if (!empty($arrProvinceInfo)) {
                            $taxRate = $arrProvinceInfo['rate'];
                            $taxType = $arrProvinceInfo['tax_type'];

                            $arrCalculatedGst = $this->_gstHst->calculateGstAndSubtotal($taxType, $taxRate, $arrTransactionInfo['withdrawal']);

                            $fee = $arrCalculatedGst['subtotal'];
                            $gst = $arrCalculatedGst['gst'];
                        }
                    }


                    $arrAssignedRecords[] = array(
                        'transaction_description' => $arrTransactionInfo['description'],
                        'transaction_amount'      => round($fee + $gst, 2),
                        'transaction_date'        => $arrTransactionInfo['date_of_event'],
                    );
                }
            }
        }

        return $arrAssignedRecords;
    }

    /**
     * Calculate unassigned invoice payments amount for a specific client(s) and company T/A
     *
     * @param int|array $memberId
     * @param int $companyTaId
     * @param string|bool $startDate
     * @param string|bool $endDate
     * @return float
     */
    public function calculateUnassignedInvoicePaymentsAmount($memberId, $companyTaId, $startDate = false, $endDate = false)
    {
        $sum      = 0;
        $memberId = is_array($memberId) ? $memberId : array($memberId);

        // Get the list of assigned invoice payments
        $select = (new Select())
            ->from(array('w' => 'u_assigned_withdrawals'))
            ->columns(['invoice_payment_id'])
            ->where(
                [
                    (new Where())
                        ->isNotNull('w.invoice_payment_id')
                        ->equalTo('w.company_ta_id', (int)$companyTaId)
                ]
            );

        $arrAssignedInvoicePayments = $this->_db2->fetchCol($select);

        $select = (new Select())
            ->from(array('ip' => 'u_invoice_payments'))
            ->join(array('i' => 'u_invoice'), 'ip.invoice_id = i.invoice_id', [])
            ->where(
                [
                    (new Where())
                        ->in('i.member_id', $memberId)
                        ->nest()
                        ->nest()
                        ->equalTo('ip.company_ta_id', (int)$companyTaId)
                        ->and
                        ->isNull('ip.transfer_from_company_ta_id')
                        ->unnest()
                        ->or
                        ->equalTo('ip.transfer_from_company_ta_id', (int)$companyTaId)
                        ->unnest()
                ]
            );

        // If there are some assigned invoice payments - skip them
        if (!empty($arrAssignedInvoicePayments)) {
            $arrAssignedInvoicePayments = array_map('intval', $arrAssignedInvoicePayments);
            $select->where->notIn('ip.invoice_payment_id', $arrAssignedInvoicePayments);
        }

        if ($startDate) {
            $select->where->greaterThan('ip.invoice_payment_date', $startDate);
        }

        if ($endDate) {
            $select->where->lessThanOrEqualTo('ip.invoice_payment_date', $endDate);
        }

        $arrUnassignedInvoicePayments = $this->_db2->fetchAll($select);

        // Calculate the sum
        if (is_array($arrUnassignedInvoicePayments) && !empty($arrUnassignedInvoicePayments)) {
            foreach ($arrUnassignedInvoicePayments as $invoicePaymentInfo) {
                if (!empty($invoicePaymentInfo['transfer_from_company_ta_id'])) {
                    if ($invoicePaymentInfo['transfer_from_company_ta_id'] == $companyTaId) {
                        $sum += (float)$invoicePaymentInfo['invoice_payment_amount'];
                    }
                } else {
                    $sum += (float)$invoicePaymentInfo['invoice_payment_amount'];
                }
            }
        }

        return $sum;
    }

    /**
     * Calculate T/A balance for specific company T/A
     *
     * @param int $companyTAId
     * @param string|bool $startDate
     * @param string|bool $endDate
     * @return string
     */
    public function calculateTABalance($companyTAId, $startDate = false, $endDate = false)
    {
        $select = (new Select())
            ->from('u_trust_account')
            ->columns(['ta_balance' => new Expression('IFNULL(SUM(deposit), 0) - IFNULL(SUM(withdrawal), 0)')])
            ->where(['company_ta_id' => (int)$companyTAId]);

        if ($startDate) {
            $select->where->greaterThan('date_from_bank', $startDate);
        }

        if ($endDate) {
            $select->where->lessThanOrEqualTo('date_from_bank', $endDate);
        }

        return $this->_db2->fetchOne($select);
    }

    public function getWithdrawalInfo($withdrawalId)
    {
        $select = (new Select())
            ->from(array('w' => 'u_assigned_withdrawals'))
            ->where(['w.withdrawal_id' => $withdrawalId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load information about specific invoice
     *
     * @param int $invoiceId
     * @return array
     */
    public function getInvoiceInfo($invoiceId)
    {
        $select = (new Select())
            ->from(array('i' => 'u_invoice'))
            ->where(['i.invoice_id' => (int)$invoiceId]);

        $arrInvoiceInfo = $this->_db2->fetchRow($select);

        if (!empty($arrInvoiceInfo)) {
            $arrInvoiceInfo['trust_account_id'] = array();

            $arrInvoiceAssignedTransactions = $this->getAssignedTransactionsByInvoiceId($invoiceId);
            foreach ($arrInvoiceAssignedTransactions as $arrInvoiceAssignedTransactionInfo) {
                $arrInvoiceInfo['trust_account_id'][] = $arrInvoiceAssignedTransactionInfo['trust_account_id'];
            }
        }

        return $arrInvoiceInfo;
    }

    /**
     * Load assigned member id by specific invoice
     *
     * @param int $invoiceId
     * @return int
     */
    public function getMemberIdByInvoiceId($invoiceId)
    {
        $invoiceInfo = $this->getInvoiceInfo($invoiceId);
        return $invoiceInfo['member_id'] ?? 0;
    }

    /**
     * Get transaction info by linked invoice id
     *
     * @param int $invoiceId
     * @return array
     */
    public function getAssignedTransactionsByInvoiceId($invoiceId)
    {
        $select = (new Select())
            ->from(array('w' => 'u_assigned_withdrawals'))
            ->join(array('ip' => 'u_invoice_payments'), 'ip.invoice_payment_id = w.invoice_payment_id', [])
            ->join(array('ta' => 'u_trust_account'), 'w.trust_account_id = ta.trust_account_id', Select::SQL_STAR, Select::JOIN_LEFT)
            ->where(['ip.invoice_id' => (int)$invoiceId])
            ->group('ip.invoice_id');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of not cleared deposits for a specific T/A (T/A records that were not assigned to any clients)
     *
     * @param $companyTaId
     * @return array
     */
    public function getTrustAccountNotAssignedWithdrawals($companyTaId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(array('trust_account_id', 'date_from_bank', 'description', 'withdrawal'))
            ->join(array('w' => 'u_assigned_withdrawals'), 'w.trust_account_id = ta.trust_account_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('ta.company_ta_id', (int)$companyTaId)
                        ->greaterThan('ta.withdrawal', 0)
                        ->isNull('w.returned_payment_member_id')
                        ->isNull('w.special_transaction')
                        ->isNull('w.special_transaction_id')
                        ->isNull('w.withdrawal_id')
                ]
            )
            ->order('ta.date_from_bank');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of not cleared deposits for a specific T/A (T/A records that were not assigned to any clients)
     *
     * @param $companyTaId
     * @return array
     */
    public function getTrustAccountNotClearedDeposits($companyTaId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(array('trust_account_id', 'date_from_bank', 'description', 'deposit'))
            ->join(array('d' => 'u_assigned_deposits'), 'd.trust_account_id = ta.trust_account_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('ta.company_ta_id', (int)$companyTaId)
                        ->greaterThan('ta.deposit', 0)
                        ->isNull('d.deposit_id')
                ]
            )
            ->order('ta.date_from_bank');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load not cleared list of deposits for specific company T/A
     *
     * @param int $companyTaId
     * @return array
     */
    public function getCompanyNotClearedDepositsList($companyTaId)
    {
        $select = (new Select())
            ->from(array('a' => 'u_assigned_deposits'))
            ->join(array('c' => 'company_ta'), 'a.company_ta_id = c.company_ta_id', 'currency', Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('a.company_ta_id', (int)$companyTaId)
                        ->isNull('trust_account_id')
                ]
            );

        $arrNotClearedDeposits = $this->_db2->fetchAll($select);

        $arrResult = array();
        if (is_array($arrNotClearedDeposits)) {
            foreach ($arrNotClearedDeposits as $arrDepositInfo) {
                $depositName = empty($arrDepositInfo['description']) ? $this->_tr->translate('Pending Deposit') : $arrDepositInfo['description'];
                $arrResult[] = array(
                    'memberId'     => $arrDepositInfo['member_id'],
                    'depositId'    => $arrDepositInfo['deposit_id'],
                    'depositName'  => $depositName,
                    'depositValue' => static::formatPrice($arrDepositInfo ['deposit'], $arrDepositInfo['currency']),
                    'depositDate'  => $this->_settings->formatDate($arrDepositInfo['date_of_event'])
                );
            }
        }

        return $arrResult;
    }

    /**
     * Get detailed information about deposit by deposit id and client id
     *
     * @param int $depositId
     * @param int $memberId
     * @return array
     */
    public function getDeposit($depositId, $memberId)
    {
        $arrResult = array();

        try {
            $select = (new Select())
                ->from(array('a' => 'u_assigned_deposits'))
                ->join(array('m' => 'members'), 'm.member_id = a.author_id', array('fName', 'lName'), Select::JOIN_LEFT)
                ->join(array('ta' => 'u_trust_account'), 'a.trust_account_id = ta.trust_account_id', array('date_from_bank', 'ta_description' => 'description', 'ta_notes' => 'notes'), Select::JOIN_LEFT)
                ->join(array('i' => 'u_import_transactions'), 'ta.import_id = i.import_transaction_id', array('imported_by' => 'author_id'), Select::JOIN_LEFT)
                ->where(['a.deposit_id' => (int)$depositId]);

            if (!empty($memberId)) {
                $select->where->equalTo('a.member_id', (int)$memberId);
            }

            $arrDepositInfo = $this->_db2->fetchRow($select);

            if (is_array($arrDepositInfo) && count($arrDepositInfo)) {
                $arrDepositInfo = $this->_parent::generateMemberName($arrDepositInfo);
                if (!empty($arrDepositInfo['imported_by'])) {
                    $authorInfo = $this->_parent->getMemberInfo($arrDepositInfo['imported_by']);
                } else {
                    $authorInfo = array('full_name' => '');
                }

                $arrCreatedBy = $this->_parent->getMemberInfo($arrDepositInfo['author_id']);

                $currency    = $this->getCompanyTACurrency($arrDepositInfo['company_ta_id']);
                $description = $arrDepositInfo['ta_description'] . (empty($arrDepositInfo['ta_notes']) ? '' : ' - ' . $arrDepositInfo['ta_notes']);

                $dateFormatFull         = $this->_settings->variable_get('dateFormatFull');
                $dateFormatFullWithTime = $dateFormatFull . ' H:i:s';

                $arrResult = array(
                    'deposit_id'          => $arrDepositInfo['deposit_id'],
                    'deposit_description' => $arrDepositInfo['description'],
                    'status_cleared'      => !empty($arrDepositInfo['trust_account_id']),
                    'created_by'          => $arrCreatedBy['full_name'],
                    'author'              => $authorInfo['full_name'],
                    'amount'              => $arrDepositInfo['deposit'],
                    'currency'            => $currency,
                    'date_from_bank'      => Settings::isDateEmpty($arrDepositInfo['date_from_bank']) ? '' : $this->_settings->reformatDate($arrDepositInfo['date_from_bank'], Settings::DATE_UNIX, $dateFormatFull),
                    'notes'               => $arrDepositInfo['notes'],
                    'description'         => $description,
                    'assigned_by'         => $arrDepositInfo['full_name'],
                    'assigned_on'         => Settings::isDateEmpty($arrDepositInfo['date_of_event']) ? '' : $this->_settings->reformatDate($arrDepositInfo['date_of_event'], Settings::DATETIME_UNIX, $dateFormatFullWithTime),
                    'receipt_number'      => $arrDepositInfo['receipt_number'],
                    'template_id'         => $arrDepositInfo['template_id'],
                    'member_id'           => $arrDepositInfo['member_id']
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Load detailed information about returned payment by withdrawal and client ids
     *
     * @param int $withdrawalId
     * @param int $memberId
     * @return array
     */
    public function getReturnedPayment($withdrawalId, $memberId)
    {
        $select = (new Select())
            ->from(array('a' => 'u_assigned_withdrawals'))
            ->join(array('m' => 'members'), 'm.member_id = a.author_id', array('fName', 'lName'), Select::JOIN_LEFT)
            ->join(array('ta' => 'u_trust_account'), 'a.trust_account_id = ta.trust_account_id', array('company_ta_id', 'date_from_bank', 'ta_description' => 'description', 'ta_notes' => 'notes'), Select::JOIN_LEFT)
            ->join(array('i' => 'u_import_transactions'), 'ta.import_id = i.import_transaction_id', array('imported_by' => 'author_id'), Select::JOIN_LEFT)
            ->where(
                [
                    'a.returned_payment_member_id' => (int)$memberId,
                    'a.withdrawal_id'              => (int)$withdrawalId
                ]
            );

        $arrWithdrawalInfo = $this->_db2->fetchRow($select);

        $arrWithdrawalInfo = $this->_parent::generateMemberName($arrWithdrawalInfo);
        $authorInfo        = $this->_parent->getMemberInfo($arrWithdrawalInfo['imported_by']);

        $currency    = $this->getCompanyTACurrency($arrWithdrawalInfo['company_ta_id']);
        $description = $arrWithdrawalInfo['ta_description'] . (empty($arrWithdrawalInfo['ta_notes']) ? '' : ' - ' . $arrWithdrawalInfo['ta_notes']);

        return array(
            'description'    => 'Returned Payment',
            'author'         => $authorInfo['full_name'],
            'amount'         => $arrWithdrawalInfo['withdrawal'],
            'currency'       => $currency,
            'date_from_bank' => $arrWithdrawalInfo['date_from_bank'],
            'notes'          => $arrWithdrawalInfo['notes'],
            'ta_description' => $description,
            'ta_assigned_by' => $arrWithdrawalInfo['full_name'],
            'ta_assigned_on' => $arrWithdrawalInfo['date_of_event']
        );
    }

    public function getWithdrawal($withdrawalId, $memberId)
    {
        $select = (new Select())
            ->from(array('a' => 'u_assigned_withdrawals'))
            ->join(array('m' => 'members'), 'm.member_id = a.author_id', array('fName', 'lName'), Select::JOIN_LEFT)
            ->join(array('ta' => 'u_trust_account'), 'a.trust_account_id = ta.trust_account_id', array('company_ta_id', 'date_from_bank', 'ta_description' => 'description', 'ta_notes' => 'notes'), Select::JOIN_LEFT)
            ->join(array('i' => 'u_import_transactions'), 'ta.import_id = i.import_transaction_id', array('imported_by' => 'author_id'), Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('a.withdrawal_id', (int)$withdrawalId)
                        ->nest()
                        ->equalTo('a.member_id', (int)$memberId)
                        ->or
                        ->equalTo('a.returned_payment_member_id', (int)$memberId)
                        ->unnest()
                ]
            );

        $arrWithdrawalInfo = $this->_db2->fetchRow($select);

        if (is_array($arrWithdrawalInfo) && count($arrWithdrawalInfo)) {
            $arrWithdrawalInfo = $this->_parent::generateMemberName($arrWithdrawalInfo);
            $authorInfo        = empty($arrWithdrawalInfo['imported_by']) ? [] : $this->_parent->getMemberInfo($arrWithdrawalInfo['imported_by']);
            $currency          = $this->getCompanyTACurrency($arrWithdrawalInfo['company_ta_id']);
            $description       = $arrWithdrawalInfo['ta_description'] . (empty($arrWithdrawalInfo['ta_notes']) ? '' : ' - ' . $arrWithdrawalInfo['ta_notes']);

            $dateFormatFull         = $this->_settings->variable_get('dateFormatFull');
            $dateFormatFullWithTime = $dateFormatFull . ' H:i:s';

            $arrWithdrawalInfo = array(
                'description'    => empty($arrWithdrawalInfo['member_id']) ? 'Returned Payment' : 'Withdrawal Verified',
                'author'         => $authorInfo['full_name'] ?? '',
                'amount'         => $arrWithdrawalInfo['withdrawal'],
                'currency'       => $currency,
                'date_from_bank' => Settings::isDateEmpty($arrWithdrawalInfo['date_from_bank']) ? '' : $this->_settings->reformatDate($arrWithdrawalInfo['date_from_bank'], Settings::DATE_UNIX, $dateFormatFull),
                'notes'          => $arrWithdrawalInfo['notes'],
                'ta_description' => $description,
                'ta_assigned_by' => $arrWithdrawalInfo['full_name'],
                'ta_assigned_on' => Settings::isDateEmpty($arrWithdrawalInfo['date_of_event']) ? '' : $this->_settings->reformatDate($arrWithdrawalInfo['date_of_event'], Settings::DATETIME_UNIX, $dateFormatFullWithTime),
            );
        } else {
            $arrWithdrawalInfo = array();
        }

        return $arrWithdrawalInfo;
    }

    /**
     * Update notes for specific transaction type
     *
     * @param int $id
     * @param string $notes
     * @param string $type one of: 'invoice', 'invoice_recipient_notes', 'deposit', 'withdrawal', 'payment'
     * @return bool
     */
    public function updateNotes($id, $notes, $type)
    {
        $booSuccess = false;

        try {
            $tableName = '';
            $arrWhere  = [];
            $arrWhat   = ['notes' => $notes];

            switch ($type) {
                case 'invoice':
                    $tableName = 'u_invoice';
                    $arrWhere  = ['invoice_id' => $id];
                    break;

                case 'invoice_recipient_notes':
                    $tableName = 'u_invoice';
                    $arrWhat   = ['invoice_recipient_notes' => $notes];
                    $arrWhere  = ['invoice_id' => $id];
                    break;

                case 'deposit':
                    $tableName = 'u_assigned_deposits';
                    $arrWhere  = ['deposit_id' => $id];
                    break;

                case 'withdrawal':
                    $tableName = 'u_assigned_withdrawals';
                    $arrWhere  = ['withdrawal_id' => $id];
                    break;

                case 'payment':
                    $tableName = 'u_payment';
                    $arrWhere  = ['payment_id' => $id];
                    break;

                case 'ps':
                    $tableName = 'u_payment_schedule';
                    $arrWhere  = ['payment_schedule_id' => $id];
                    break;

                default:
                    break;
            }

            if (!empty($tableName)) {
                $this->_db2->update(
                    $tableName,
                    $arrWhat,
                    $arrWhere
                );

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Reverse specific transaction
     *
     * @param array $arrPaymentInfo
     * @param string $paymentType
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function reverseTransaction($arrPaymentInfo, $paymentType)
    {
        $booRefreshTA = false;
        $booRefreshPS = false;
        $strError     = '';

        try {
            // By default, we don't need to delete this record
            $booDeleteEntry    = false;
            $booUpdateBalances = true;

            if ($paymentType == 'ps') {
                $booUpdateBalances = false;

                $this->_db2->delete(
                    'u_payment_schedule',
                    [
                        'payment_schedule_id' => (int)$arrPaymentInfo['payment_schedule_id'],
                        'status'              => 0
                    ]
                );
            } elseif ($paymentType != 'invoice' && $paymentType != 'receipt') { // Fee Due
                $booDeleteEntry = true;

                // Remove record from PS table too
                if (array_key_exists('payment_schedule_id', $arrPaymentInfo) && !empty($arrPaymentInfo['payment_schedule_id'])) {
                    $this->_db2->delete('u_payment_schedule', ['payment_schedule_id' => $arrPaymentInfo['payment_schedule_id']]);
                    $booRefreshPS = true;
                }
            } else { // Fee Received

                // From non-client AC source
                if (empty($arrPaymentInfo['trust_account_id'])) {
                    $booDeleteEntry = true;
                } else {
                    $arrTrustAccountIds = is_array($arrPaymentInfo['trust_account_id']) ? $arrPaymentInfo['trust_account_id'] : array($arrPaymentInfo['trust_account_id']);

                    // Make sure that ALL transactions can be deleted
                    foreach ($arrTrustAccountIds as $trustAccountId) {
                        // Get info about assigned transaction
                        $arrTrustAccountInfo = $this->getTrustAccount()->getTransactionInfo($trustAccountId);

                        // Check if we can unassign this transaction
                        $booCanUnAssign = $this->getTrustAccount()->canUnassignTransaction($arrTrustAccountInfo['company_ta_id'], $arrTrustAccountInfo['date_from_bank']);

                        if (!$booCanUnAssign) {
                            $strError = $this->_tr->translate('This transaction cannot be corrected, as it is already reconciled.');
                            break;
                        }
                    }

                    if (empty($strError)) {
                        foreach ($arrTrustAccountIds as $trustAccountId) {
                            // UnAssign transaction
                            $this->getTrustAccount()->unassignTransaction($trustAccountId);

                            // Refresh T/A section
                            $booRefreshTA = true;

                            // And delete from DB
                            $booDeleteEntry = true;
                        }
                    }
                }
            }

            if ($booDeleteEntry) {
                // Remove this entry from FT table
                if ($paymentType == 'invoice' || $paymentType == 'receipt') {
                    $this->deleteInvoice($arrPaymentInfo['member_id'], $arrPaymentInfo['invoice_id']);
                } elseif ($paymentType == 'payment') {
                    $this->deleteInvoice($arrPaymentInfo['member_id'], $arrPaymentInfo['invoice_id']);

                    $this->deletePayment($arrPaymentInfo['payment_id']);
                } else {
                    $this->_db2->delete('u_assigned_withdrawals', ['withdrawal_id' => $arrPaymentInfo['withdrawal_id']]);
                }
            }

            if (empty($strError) && $booUpdateBalances) {
                // All is okay? Update balance
                $this->updateOutstandingBalance($arrPaymentInfo['member_id'], $arrPaymentInfo['company_ta_id']);

                //update sub total
                $this->updateTrustAccountSubTotal($arrPaymentInfo['member_id'], $arrPaymentInfo['company_ta_id']);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('message' => $strError, 'booRefreshTA' => $booRefreshTA, 'booRefreshPS' => $booRefreshPS);
    }

    /**
     * Delete invoice for a specific case
     *
     * @param int $memberId
     * @param int $invoiceId
     */
    public function deleteInvoice($memberId, $invoiceId)
    {
        if (!empty($invoiceId)) {
            $this->_db2->delete('u_invoice', ['invoice_id' => (int)$invoiceId]);

            $this->_db2->update(
                'u_payment',
                ['invoice_id' => null],
                ['invoice_id' => (int)$invoiceId]
            );

            $arrMemberInfo              = $this->_parent->getMemberInfo($memberId);
            $booLocal                   = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);
            $invoiceDocumentsFolderPath = $this->_files->getClientInvoiceDocumentsFolder($memberId, $arrMemberInfo['company_id'], $booLocal);
            $invoiceDocumentsFilePath   = $invoiceDocumentsFolderPath . '/' . $invoiceId . '.pdf';
            $this->_files->deleteFile($invoiceDocumentsFilePath, $booLocal);
        }
    }


    /**
     * Load list of records for PS table for specific client
     *
     * @param int $memberId
     * @param bool $booLetterTemplate
     * @return array
     */
    public function getPaymentScheduleList($memberId, $booLetterTemplate = false)
    {
        $paymentInfo = $this->getClientsPaymentScheduleInfo($memberId);

        // Prepare provinces list with taxes
        $arrProvinces = $this->_gstHst->getProvincesList();

        $arr         = array();
        $booIsClient = $this->_auth->isCurrentUserClient();
        $booCanEdit  = $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId);
        foreach ($paymentInfo as $row) {
            $name = $row['name'];

            // Show if gst was used
            $gst = '';
            if ($row['gst'] > 0) {
                if (array_key_exists($row['gst_province_id'], $arrProvinces)) {
                    $arrCurrentProvince = $arrProvinces[$row['gst_province_id']];

                    $taxLabel = array_key_exists('gst_tax_label', $row) ? (empty($row['gst_tax_label']) ? 'GST' : $row['gst_tax_label']) : $arrCurrentProvince['tax_label'];

                    switch ($this->_config['site_version']['version']) {
                        case 'australia':
                            $gst = $taxLabel;
                            break;

                        default:
                            if ($row['status'] == 0) {
                                $arrToFormat = $arrCurrentProvince;
                            } else {
                                $arrToFormat = array(
                                    'province'  => $arrCurrentProvince['province'],
                                    'tax_label' => $taxLabel,
                                    'rate'      => $row['gst'],
                                    'tax_type'  => $row['gst_type'],
                                    'is_system' => 'N',
                                );
                            }
                            $gst = $this->_gstHst->formatGSTLabel($arrToFormat);
                            break;
                    }

                    switch ($row['gst_type']) {
                        case 'included':
                            $gst = $this->_config['site_version']['version'] == 'australia' ? "(inclusive of $gst)" : "($gst)";
                            break;

                        case 'excluded':
                        default:
                            $gst = $this->_config['site_version']['version'] == 'australia' ? "(plus $gst)" : "(+$gst)";
                            break;
                    }
                } else {
                    $gst = '(+GST)';
                }

                if ($booLetterTemplate) {
                    $name .= $gst;
                }
            }

            $arr[] = array(
                'id'       => $row['id'],
                'name'     => $name,
                'gst'      => $gst,
                'amount'   => $row['amount'],
                'due_on'   => $row['due_on'],
                'status'   => $row['status'],
                'can_edit' => !$booIsClient && $booCanEdit
            );
        }

        return $arr;
    }

    /**
     * Create/update record in PS table
     *
     * @param $mode
     * @param $memberId
     * @param $companyTAId
     * @param $paymentScheduleId
     * @param $amount
     * @param $description
     * @param $basedType
     * @param $based
     * @param $date
     * @param $gst
     * @param $gstProvinceId
     * @param $gstTaxLabel
     * @param bool $templateId
     */
    public function savePayment($mode, $memberId, $companyTAId, $paymentScheduleId, $amount, $description, $basedType, $based, $date, $gst, $gstProvinceId, $gstTaxLabel, $templateId = false)
    {
        // Simple check for gst
        $gst = !is_numeric($gst) || $gst < 0 ? 0 : $gst;

        $arrData = array(
            'description'     => $description,
            'amount'          => (double)$amount,
            'gst'             => (double)$gst,
            'gst_province_id' => (int)$gstProvinceId,
            'gst_tax_label'   => $gstTaxLabel
        );


        switch ($basedType) {
            case  'date':
                $arrData['based_on_date']               = $date;
                $arrData['based_on_profile_date_field'] = null;
                $arrData['based_on_account']            = null;
                break;

            case  'profile_date':
                $arrData['based_on_date']               = null;
                $arrData['based_on_profile_date_field'] = (int)$based;
                $arrData['based_on_account']            = null;
                break;

            case  'file_status':
            default:
                $arrData['based_on_date']               = null;
                $arrData['based_on_profile_date_field'] = null;
                $arrData['based_on_account']            = (int)$based;
                break;
        }

        if ($templateId) {
            $arrData['template_id'] = (int)$templateId;
        }

        if ($mode == 'add') {
            $arrData['member_id']     = (int)$memberId;
            $arrData['company_ta_id'] = empty($companyTAId) ? null : (int)$companyTAId;
            $arrData['status']        = 0;

            $this->_db2->insert('u_payment_schedule', $arrData);
        } else {
            // Skip gst update for old 'Federal' records
            if ($gstProvinceId == '-1') {
                unset($arrData['gst'], $arrData['gst_province_id'], $arrData['gst_tax_label']);
            }

            $this->updatePSRecordInfo($paymentScheduleId, $memberId, $arrData);
        }

        switch ($basedType) {
            case 'date':
                // Trigger is based on specific date
                $this->_systemTriggers->triggerPaymentScheduleDateIsDue($memberId);
                break;

            case 'profile_date':
                // Trigger is based on client's date field
                $this->_systemTriggers->triggerProfileDateFieldChanged($memberId);
                break;

            default:
                // Trigger is based on client's file status
                $this->_systemTriggers->triggerPaymentAdded($memberId, $based);
                break;
        }
    }

    /**
     * Update PS record's info
     *
     * @param int $paymentScheduleId
     * @param int $memberId
     * @param array $arrData
     * @return bool
     */
    public function updatePSRecordInfo($paymentScheduleId, $memberId, $arrData)
    {
        try {
            $this->_db2->update(
                'u_payment_schedule',
                $arrData,
                [
                    'payment_schedule_id' => (int)$paymentScheduleId,
                    'member_id'           => $memberId
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create an invoice record
     *
     * @param array $arrInvoiceInfo
     * @return int
     */
    public function saveInvoice($arrInvoiceInfo)
    {
        $invoiceId = 0;

        try {
            // Can be empty, when run from the cron
            $authorId  = $this->_auth->getCurrentUserId();
            $companyId = $this->_company->getMemberCompanyId($arrInvoiceInfo['member_id']);

            $arrToInsert = array(
                'type'                    => 'invoice',
                'member_id'               => (int)$arrInvoiceInfo['member_id'],
                'company_ta_id'           => (int)$arrInvoiceInfo['transfer_to_ta_id'],
                'author_id'               => empty($authorId) ? null : $authorId,
                'invoice_num'             => $this->_company->generateInvoiceNumberFromFormat($companyId, $arrInvoiceInfo['invoice_num']),
                'date_of_invoice'         => $arrInvoiceInfo['date'],
                'date_of_creation'        => date('Y-m-d H:i:s'),
                'fee'                     => (double)$arrInvoiceInfo['fee'],
                'tax'                     => (double)$arrInvoiceInfo['tax'],
                'amount'                  => (double)$arrInvoiceInfo['amount'],
                'received'                => 'N',
                'invoice_recipient_notes' => empty($arrInvoiceInfo['invoice_recipient_notes']) ? null : $arrInvoiceInfo['invoice_recipient_notes']
            );

            $invoiceId = $this->_db2->insert('u_invoice', $arrToInsert, 0);

            // Save to company's settings this used invoice number
            $this->_company->updateCompanyInvoiceNumberStartFrom($companyId, $arrInvoiceInfo['invoice_num']);
            $this->updatePaymentInfo($arrInvoiceInfo['arrPayments'], array('invoice_id' => $invoiceId));

            //update outstanding balance
            $this->updateOutstandingBalance($arrInvoiceInfo['member_id'], $arrInvoiceInfo['transfer_to_ta_id']);

            //update sub total
            $this->updateTrustAccountSubTotal($arrInvoiceInfo['member_id'], $arrInvoiceInfo['transfer_to_ta_id']);

            $this->_company->updateLastField(false, 'last_accounting_subtab_updated');
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $invoiceId;
    }

    /**
     * Update invoice details
     *
     * @param int $invoiceId
     * @param array $arrInvoiceInfo
     * @return bool
     */
    public function updateInvoice($invoiceId, $arrInvoiceInfo)
    {
        $booSuccess = false;

        try {
            if (!empty($arrInvoiceInfo)) {
                $this->_db2->update(
                    'u_invoice',
                    $arrInvoiceInfo,
                    [
                        'invoice_id' => $invoiceId
                    ]
                );

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load PS record info
     *
     * @param int $paymentScheduleId
     * @return array
     */
    public function getPaymentScheduleInfo($paymentScheduleId)
    {
        $arrPaymentScheduleInfo = array();
        if (!empty($paymentScheduleId)) {
            $select = (new Select())
                ->from(array('s' => 'u_payment_schedule'))
                ->where(['s.payment_schedule_id' => (int)$paymentScheduleId]);

            $arrPaymentScheduleInfo = $this->_db2->fetchRow($select);

            if (isset($arrPaymentScheduleInfo['payment_schedule_id'])) {
                $basedOn = '';
                if (!empty($arrPaymentScheduleInfo ['based_on_account'])) {
                    $basedOn = 'file_status';
                } elseif (!empty($arrPaymentScheduleInfo ['based_on_profile_date_field'])) {
                    $basedOn = 'profile_date';
                } elseif (!empty ($arrPaymentScheduleInfo ['based_on_date'])) {
                    $basedOn = 'date';
                }

                if (!empty($basedOn)) {
                    $arrPaymentScheduleInfo['type'] = $basedOn;
                }
            }
        }

        return $arrPaymentScheduleInfo;
    }

    /**
     * Check if current member has access to assigned withdrawal record
     *
     * @param int $assignedWithdrawalId
     * @return bool
     */
    public function hasAccessToAssignedWithdrawal($assignedWithdrawalId)
    {
        $booHasAccess = false;
        if (!empty($assignedWithdrawalId) && is_numeric($assignedWithdrawalId)) {
            $select = (new Select())
                ->from('u_assigned_withdrawals')
                ->where(['withdrawal_id' => (int)$assignedWithdrawalId]);

            $arrDepositInfo = $this->_db2->fetchRow($select);

            if (isset($arrDepositInfo['company_ta_id']) && $this->_parent->hasCurrentMemberAccessToTA($arrDepositInfo['company_ta_id'])) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }

    /**
     * Check if current member has access to assigned deposit record
     *
     * @param int $assignedDepositId
     * @return bool
     */
    public function hasAccessToAssignedDeposit($assignedDepositId)
    {
        $booHasAccess = false;
        if (!empty($assignedDepositId) && is_numeric($assignedDepositId)) {
            $select = (new Select())
                ->from('u_assigned_deposits')
                ->where(['deposit_id' => (int)$assignedDepositId]);

            $arrDepositInfo = $this->_db2->fetchRow($select);

            if (isset($arrDepositInfo['company_ta_id']) && $this->_parent->hasCurrentMemberAccessToTA($arrDepositInfo['company_ta_id'])) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }

    /**
     * Check if current member has access to PS record
     *
     * @param int $paymentScheduleId
     * @return bool
     */
    public function hasAccessToPaymentSchedule($paymentScheduleId)
    {
        $booHasAccess = false;
        if (!empty($paymentScheduleId) && is_numeric($paymentScheduleId)) {
            $arrPaymentInfo = $this->getPaymentScheduleInfo($paymentScheduleId);

            if (isset($arrPaymentInfo['member_id']) && $this->_parent->hasCurrentMemberAccessToMember($arrPaymentInfo['member_id'])) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }

    /**
     * Load PS records for specific client
     *
     * @param int $memberId
     * @param int $companyTAId
     * @return array
     */
    private function getClientsPaymentScheduleInfo($memberId, $companyTAId = 0)
    {
        $arrPaymentScheduleInfo = array();

        $select = (new Select())
            ->from(array('s' => 'u_payment_schedule'))
            ->join(array('hst' => 'hst_companies'), 's.gst_province_id = hst.province_id', array('tax_type', 'is_system'), Select::JOIN_LEFT)
            ->where(['s.member_id' => (int)$memberId])
            ->order('s.payment_schedule_id ASC');

        // If no T/A was provided - that means we want to load records for the main/primary T/A of the client
        $primaryTAId = $this->getClientPrimaryCompanyTaId($memberId);
        if (empty($companyTAId)) {
            $companyTAId = $primaryTAId;
        }

        // Load unassigned PS records for the primary T/A only
        if ($primaryTAId == $companyTAId) {
            $select->where
                ->nest()
                ->equalTo('s.company_ta_id', (int)$companyTAId)
                ->or
                ->isNull('s.company_ta_id')
                ->unnest();
        } else {
            $select->where->equalTo('s.company_ta_id', (int)$companyTAId);
        }

        $arrPSRecords = $this->_db2->fetchAll($select);

        if (count($arrPSRecords)) {
            $arrStatuses = $this->_parent->getFields()->getClientCategories();


            foreach ($arrPSRecords as $arrResult) {
                $dueOn   = '';
                $basedOn = '';
                if (!empty($arrResult ['based_on_account'])) {
                    // Based on case status field
                    foreach ($arrStatuses as $statusInfo) {
                        if (is_array($statusInfo) && array_key_exists('cOptionId', $statusInfo) && $arrResult ['based_on_account'] == $statusInfo ['cOptionId']) {
                            $dueOn   = $statusInfo ['cName'];
                            $basedOn = 'file_status';
                            break;
                        }
                    }
                } elseif (!empty($arrResult ['based_on_profile_date_field'])) {
                    // Based on date field from profile
                    foreach ($arrStatuses as $statusInfo) {
                        if ($arrResult ['based_on_profile_date_field'] == $statusInfo ['cFieldId']) {
                            $dueOn   = $statusInfo ['cName'];
                            $basedOn = 'profile_date';
                            break;
                        }
                    }
                } elseif (!empty ($arrResult ['based_on_date'])) {
                    // Based on specific date
                    $dueOn   = $this->_settings->formatDate($arrResult ['based_on_date']);
                    $basedOn = 'date';
                }

                $arrPaymentScheduleInfo [] = array(
                    'id'              => $arrResult ['payment_schedule_id'],
                    'name'            => $arrResult ['description'],
                    'notes'           => $arrResult ['notes'],
                    'amount'          => static::formatPrice($arrResult ['amount']),
                    'based_on'        => $basedOn,
                    'due_on'          => $dueOn,
                    'due_on_ymd'      => $arrResult ['based_on_date'],
                    'status'          => $arrResult['status'],
                    'gst'             => $arrResult['gst'],
                    'gst_province_id' => $arrResult['gst_province_id'],
                    'gst_tax_label'   => $arrResult['gst_tax_label'],
                    'gst_type'        => $arrResult['tax_type'],
                    'gst_is_system'   => $arrResult['is_system'],
                    'tax_type'        => $arrResult['tax_type'],
                );
            }
        }

        return $arrPaymentScheduleInfo;
    }

    /**
     * Create several records to PS table in batch
     *
     * @param $amount
     * @param $description
     * @param $paymentsCount
     * @param $start
     * @param $period
     * @param $memberId
     * @param $companyTAId
     * @param $gst
     * @param $gstProvinceId
     * @param $gstTaxLabel
     * @return bool true on success
     */
    public function addWizard($amount, $description, $paymentsCount, $start, $period, $memberId, $companyTAId, $gst, $gstProvinceId, $gstTaxLabel)
    {
        try {
            for ($i = 0; $i < $paymentsCount; $i++) {
                $basedDate = $this->calculatePaymentRecurringDate($start, $period, $i);
                if (empty($basedDate)) {
                    continue;
                }

                $this->savePayment(
                    'add',
                    $memberId,
                    $companyTAId,
                    0,
                    $amount,
                    $description . ' #' . ($i + 1),
                    0,
                    'date',
                    $basedDate,
                    $gst,
                    $gstProvinceId,
                    $gstTaxLabel
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Calculate recurring payment date based on the start date, frequency and number
     *
     * @param string $start
     * @param int $period
     * @param int $i
     * @return string
     */
    public function calculatePaymentRecurringDate($start, $period, $i)
    {
        $time = strtotime($start . ' +' . ($period * $i) . ' month');
        return empty($time) ? '' : date(Settings::DATE_UNIX, $time);
    }


    /**
     * Load transaction records for specific client and company T/A
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param $start
     * @param $limit
     * @param bool $booLoadWithdrawals
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getClientsTransactionsInfoPaged($memberId, $companyTAId, $start, $limit, $booLoadWithdrawals = true)
    {
        $arrTransactions = $this->getClientsTransactionsInfo($memberId, $companyTAId, false, false, false, $booLoadWithdrawals);

        $amountOutstanding = 0;
        $amountPaid        = 0;
        foreach ($arrTransactions as $arrTransaction) {
            if ($arrTransaction['fees_received'] > 0) {
                $amountPaid += $arrTransaction['fees_received'];
            } else {
                $amountOutstanding += $arrTransaction['fees_due'];
            }
        }

        $result = array();
        for ($i = $start; $i < $start + $limit; $i++) {
            if (count($arrTransactions) > $i) {
                $result[] = $arrTransactions[$i];
            }
        }

        return array(
            'rows'              => $result,
            'totalCount'        => count($arrTransactions),
            'amountOutstanding' => $amountOutstanding,
            'amountPaid'        => $amountPaid,
            'balance'           => $amountOutstanding - $amountPaid,
        );
    }

    /**
     * Load descriptions used in payments for specific user
     *
     * @param int $authorId
     * @return array
     */
    public function getPaymentDescriptions($authorId)
    {
        $select = (new Select())
            ->from('u_payment')
            ->columns(['description'])
            ->where(['author_id' => (int)$authorId])
            ->group('description');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load additional info (amount and GST) for payment
     *
     * @param array $arrPaymentInfo
     * @return array
     */
    public function getPaymentCorrectAmountAndGst($arrPaymentInfo)
    {
        $arrGstInfo                     = $this->_gstHst->calculateGstAndSubtotal($arrPaymentInfo['tax_type'], $arrPaymentInfo ['gst'], $arrPaymentInfo['deposit']);
        $arrPaymentInfo['deposit']      = $arrGstInfo['subtotal'];
        $arrPaymentInfo['received_gst'] = $arrGstInfo['gst'];

        $arrGstInfo                   = $this->_gstHst->calculateGstAndSubtotal($arrPaymentInfo['tax_type'], $arrPaymentInfo ['gst'], $arrPaymentInfo['withdrawal']);
        $arrPaymentInfo['withdrawal'] = $arrGstInfo['subtotal'];
        $arrPaymentInfo['due_gst']    = $arrGstInfo['gst'];

        return $arrPaymentInfo;
    }

    /**
     * Load client's payments records for a specific T/A
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param string $startDate
     * @param string $endDate
     * @param bool $booLoadOnlyWithdrawals
     * @return array
     */
    public function getClientPayments($memberId, $companyTAId, $startDate = '', $endDate = '', $booLoadOnlyWithdrawals = false)
    {
        $arrPayments = array();
        if (!empty($memberId) && !empty($companyTAId)) {
            $select = (new Select())
                ->from(array('p' => 'u_payment'))
                ->join(array('hst' => 'hst_companies'), 'p.gst_province_id = hst.province_id', array('tax_type', 'is_system'), Select::JOIN_LEFT)
                ->join(array('i' => 'u_invoice'), 'p.invoice_id = i.invoice_id', array('invoice_num', 'invoice_amount' => 'amount', 'invoice_fee' => 'fee', 'invoice_tax' => 'tax', 'date_of_invoice'), Select::JOIN_LEFT)
                ->where(
                    [
                        'p.member_id'     => (int)$memberId,
                        'p.company_ta_id' => (int)$companyTAId
                    ]
                )
                ->order('p.date_of_event');

            if ($booLoadOnlyWithdrawals) {
                $select->where->greaterThan('p.withdrawal', 0);
            }

            if ($startDate) {
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $select->where->greaterThanOrEqualTo('p.date_of_event', $startDate);
            }

            if ($endDate) {
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $select->where->lessThanOrEqualTo('p.date_of_event', $endDate);
            }

            $arrPayments = $this->_db2->fetchAll($select);
        }

        return $arrPayments;
    }

    /**
     * Load client's transactions list formatted
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param bool|string $startDate
     * @param bool|string $endDate
     * @param bool $booLetterTemplate
     * @param bool $booLoadWithdrawals
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getClientsTransactionsInfo($memberId, $companyTAId, $startDate = false, $endDate = false, $booLetterTemplate = false, $booLoadWithdrawals = true)
    {
        $arrResult = array();

        $arrProvinces = $this->_gstHst->getProvincesList();

        // Load processed payments
        $arrInvoicePayments = $this->getClientPayments($memberId, $companyTAId, $startDate, $endDate);

        $currency = $this->getCompanyTACurrency($companyTAId);
        foreach ($arrInvoicePayments as $arrPaymentInfo) {
            // Show if gst was used
            if ($arrPaymentInfo['gst'] > 0) {
                if (array_key_exists($arrPaymentInfo['gst_province_id'], $arrProvinces)) {
                    $taxLabel = array_key_exists('gst_tax_label', $arrPaymentInfo) ?
                        (empty($arrPaymentInfo['gst_tax_label']) ? 'GST' : $arrPaymentInfo['gst_tax_label']) :
                        $arrProvinces[$arrPaymentInfo['gst_province_id']]['tax_label'];
                    switch ($this->_config['site_version']['version']) {
                        case 'australia':
                            $gst = $taxLabel;
                            break;

                        default:
                            $arrToFormat = array(
                                'province'  => '',
                                'tax_label' => $taxLabel,
                                'rate'      => $arrPaymentInfo['gst'],
                                'tax_type'  => $arrPaymentInfo['tax_type'],
                                'is_system' => 'N', // We need this to show a correct label
                            );

                            $gst = $this->_gstHst->formatGSTLabel($arrToFormat);
                            break;
                    }
                } else {
                    $gst = 'GST';
                }

                $arrPaymentInfo['description_without_gst'] = $arrPaymentInfo['description'];
                $arrPaymentInfo['description_gst']         = $gst;
                if (!$booLetterTemplate) {
                    $arrPaymentInfo['description'] = $arrPaymentInfo['description'] . "<div style='color:#666666; padding-top: 5px;'>$gst</div>";
                } else {
                    $arrPaymentInfo['description'] = $arrPaymentInfo['description'] . '\n' . $gst;
                }
            }

            $status = $arrPaymentInfo['notes'];

            $arrPaymentInfo = $this->getPaymentCorrectAmountAndGst($arrPaymentInfo);

            // Generate the tooltip for the invoice link
            $arrInvoiceClearedDetails = [];
            if (!empty($arrPaymentInfo['invoice_id'])) {
                // For cleared invoices - get details
                $arrAssignedTransactions = $this->getAssignedTransactionsByInvoiceId($arrPaymentInfo['invoice_id']);
                foreach ($arrAssignedTransactions as $arrAssignedTransactionInfo) {
                    if (isset($arrAssignedTransactionInfo['trust_account_id']) && !empty($arrAssignedTransactionInfo['trust_account_id'])) {
                        $notes    = empty($arrAssignedTransactionInfo['notes']) ? '' : ' - ' . $arrAssignedTransactionInfo['notes'];
                        $comments = sprintf(
                            "%s%s",
                            $this->_tr->translate('Details'),
                            $notes
                        );

                        $arrInvoiceClearedDetails[] = $comments;
                    }
                }
            }

            $outstandingAmount = $this->getInvoiceOutstandingAmount($arrPaymentInfo['invoice_id']);
            $outstandingAmount = max(0, $outstandingAmount);

            $arrResult[] = array(
                'id'                         => 'payment-' . $arrPaymentInfo['payment_id'],
                'real_id'                    => $arrPaymentInfo['payment_id'],
                'date'                       => $this->_settings->formatDate($arrPaymentInfo['date_of_event']),
                'short_date'                 => $this->_settings->formatDate($arrPaymentInfo['date_of_event']),
                'time'                       => strtotime($arrPaymentInfo['date_of_event']),
                'description'                => $arrPaymentInfo['description'],
                'description_gst'            => $arrPaymentInfo['description_gst'] ?? '',
                'description_without_gst'    => $arrPaymentInfo['description_without_gst'] ?? '',
                'fees_received'              => static::formatPrice($arrPaymentInfo['deposit'], '', false),
                'fees_due'                   => static::formatPrice($arrPaymentInfo['withdrawal'], '', false),
                'due_gst'                    => static::formatPrice($arrPaymentInfo['due_gst'], '', false),
                'received_gst'               => static::formatPrice($arrPaymentInfo['received_gst'], '', false),
                'transfer_from_amount'       => '',
                'type'                       => 'payment',
                'status'                     => $status,
                'destination'                => '',
                'invoice_id'                 => $arrPaymentInfo['invoice_id'],
                'invoice_num'                => $arrPaymentInfo['invoice_num'],
                'invoice_amount'             => $arrPaymentInfo['invoice_amount'],
                'invoice_outstanding_amount' => $outstandingAmount,
                'invoice_cleared_details'    => $arrInvoiceClearedDetails,
                'invoice_date'               => $arrPaymentInfo['date_of_invoice'],
                'can_edit'                   => $arrPaymentInfo['company_agent'] == 'N',
            );
        }

        // Load invoice payments
        $select = (new Select())
            ->from(array('ip' => 'u_invoice_payments'))
            ->join(array('i' => 'u_invoice'), 'ip.invoice_id = i.invoice_id', array('invoice_description' => 'description', 'date_of_creation', 'invoice_num', 'notes'))
            ->join(array('w' => 'u_assigned_withdrawals'), 'w.invoice_payment_id = ip.invoice_payment_id', array('withdrawal_id', 'withdrawal', 'withdrawal_notes' => 'notes', 'assigned_date' => 'date_of_event', 'destination_account_id', 'destination_account_other', 'trust_account_id'), Join::JOIN_LEFT)
            ->where([
                (new Where())
                    ->equalTo('i.member_id', $memberId)
                    ->nest()
                    ->equalTo('ip.company_ta_id', (int)$companyTAId)
                    ->or
                    ->equalTo('ip.transfer_from_company_ta_id', (int)$companyTAId)
                    ->or
                    ->nest()
                    ->isNull('ip.company_ta_id')
                    ->isNull('ip.transfer_from_company_ta_id')
                    ->isNotNull('ip.company_ta_other')
                    ->equalTo('i.company_ta_id', (int)$companyTAId)
                    ->unnest()
                    ->unnest()
            ]);

        if ($startDate) {
            $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
            $select->where([(new Where())->greaterThanOrEqualTo('ip.invoice_payment_date', $startDate)]);
        }

        if ($endDate) {
            $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
            $select->where([(new Where())->lessThanOrEqualTo('ip.invoice_payment_date', $endDate)]);
        }

        $arrInvoicePayments = $this->_db2->fetchAll($select);

        foreach ($arrInvoicePayments as $invoicePaymentInfo) {
            if (!empty($invoicePaymentInfo['withdrawal_notes'])) {
                $notes  = $invoicePaymentInfo['withdrawal_notes'];
                $cheque = $invoicePaymentInfo['invoice_payment_cheque_num'];
            } elseif ($invoicePaymentInfo['invoice_num'] == 'Statement') {
                $notes  = $invoicePaymentInfo['invoice_payment_cheque_num'];
                $cheque = '';
            } else {
                $notes  = $invoicePaymentInfo['notes'];
                $cheque = $invoicePaymentInfo['invoice_payment_cheque_num'];
            }

            if (empty($notes)) {
                $notes = '';
            } elseif (!$booLetterTemplate) {
                $notes = "<div style='padding-top: 5px;'>$notes</div>";
            } else {
                $notes = "\n" . $notes;
            }


            if (empty($invoicePaymentInfo['company_ta_id']) && empty($invoicePaymentInfo['transfer_from_company_ta_id']) && empty($invoicePaymentInfo['withdrawal_id']) && !empty($invoicePaymentInfo['company_ta_other'])) {
                $status = sprintf(
                    "Invoice Payment %s %s",
                    empty($invoicePaymentInfo['invoice_num']) ? '' : (is_numeric($invoicePaymentInfo['invoice_num']) ? '#' : '') . $invoicePaymentInfo['invoice_num'],
                    empty($cheque) ? '' : $cheque
                );
            } else {
                $status = sprintf(
                    "Invoice Payment %s- %s %s",
                    empty($invoicePaymentInfo['invoice_num']) ? '' : (is_numeric($invoicePaymentInfo['invoice_num']) ? '#' : '') . $invoicePaymentInfo['invoice_num'] . ' ',
                    empty($invoicePaymentInfo['assigned_date']) ? $this->_tr->translate('Not Cleared') : $this->_tr->translate('Cleared'),
                    empty($cheque) ? '' : $cheque
                );

                // From other T/A
                if (!empty($invoicePaymentInfo['transfer_from_company_ta_id'])) {
                    $otherTACurrency = $this->getCompanyTACurrency($invoicePaymentInfo['transfer_from_company_ta_id']);

                    // Show 'equivalent of' only if currencies are different
                    if ($currency != $otherTACurrency) {
                        $status = trim($status);
                        $status .= "\n(equivalent of " . $invoicePaymentInfo['transfer_from_amount'] . ' ' . self::getCurrencyLabel($otherTACurrency) . ')';
                    }
                }
            }

            $destination = '';
            if ($invoicePaymentInfo['destination_account_id'] != null && ((int)$invoicePaymentInfo['destination_account_id']) >= 0) {
                $destination = $this->getTrustAccount()->getDestinationType($invoicePaymentInfo['destination_account_id'], $invoicePaymentInfo['destination_account_other']);
            }


            if (!empty($invoicePaymentInfo['invoice_description'])) {
                $description = $invoicePaymentInfo['invoice_description'];
            } else {
                $description = 'Transferred from ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            }

            $arrResult[] = array(
                'id'                   => 'invoice-payment-' . $invoicePaymentInfo['invoice_payment_id'],
                'real_id'              => $invoicePaymentInfo['invoice_payment_id'],
                'invoice_num'          => $invoicePaymentInfo['invoice_num'],
                'date'                 => $this->_settings->formatDate($invoicePaymentInfo['invoice_payment_date']),
                'time'                 => strtotime($invoicePaymentInfo['invoice_payment_date']),
                'description'          => $description . $notes,
                'fees_due'             => '',
                'fees_received'        => static::formatPrice($invoicePaymentInfo['invoice_payment_amount']),
                'transfer_from_amount' => static::formatPrice($invoicePaymentInfo['transfer_from_amount']),
                'fee'                  => '',
                'tax'                  => '',
                'type'                 => 'invoice-payment',
                'status'               => trim($status),
                'due_gst'              => '',
                'received_gst'         => '',
                'destination'          => $destination,
            );
        }

        // Load withdrawals
        if ($booLoadWithdrawals) {
            $select = (new Select())
                ->from(array('a' => 'u_assigned_withdrawals'))
                ->join(array('m' => 'members'), 'm.member_id = a.author_id', array('fName', 'lName'), Select::JOIN_LEFT)
                ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = a.trust_account_id', ['date_from_bank'], Select::JOIN_LEFT)
                ->where(
                    [
                        'a.company_ta_id'      => $companyTAId,
                        'a.member_id'          => $memberId,
                        'a.invoice_payment_id' => null
                    ]
                );

            if ($startDate) {
                $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
                $select->where([(new Where())->greaterThanOrEqualTo('a.date_of_event', $startDate)]);
            }

            if ($endDate) {
                $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
                $select->where([(new Where())->lessThanOrEqualTo('a.date_of_event', $endDate)]);
            }

            $p_result = $this->_db2->fetchAll($select);

            if (is_array($p_result) && !empty($p_result)) {
                $taLabel = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
                foreach ($p_result as $arrWithdrawals) {
                    $date = $arrWithdrawals['date_of_event'];
                    if (!empty($arrWithdrawals['trust_account_id']) && !Settings::isDateEmpty($arrWithdrawals['date_from_bank'])) {
                        $date = $arrWithdrawals['date_from_bank'];
                    }

                    $notes = empty($arrWithdrawals['notes']) ? '' : ' - ' . $arrWithdrawals['notes'];
                    if (empty($arrWithdrawals['trust_account_id'])) {
                        // Not Cleared Withdrawal
                        $description = empty($arrWithdrawals['description']) ? $this->_tr->translate('Pending for clearing in ' . $taLabel) : $arrWithdrawals['description'];
                        $linkTitle   = $this->_tr->translate('Withdrawal Not Verified');
                    } else {
                        // Cleared Withdrawal
                        $description = $arrWithdrawals['withdrawal'] < 0 ? $this->_tr->translate('Refund from ' . $taLabel) : $this->_tr->translate('Transferred from ' . $taLabel);
                        $linkTitle   = $this->_tr->translate('Withdrawal Verified');
                    }

                    $comments = sprintf(
                        '%s%s',
                        $linkTitle,
                        $notes
                    );

                    $destination = $arrWithdrawals['destination_account_id'];
                    if (empty($destination)) {
                        $destination = 'Other: ' . $arrWithdrawals['destination_account_other'];
                    } elseif ($destination < 0) {
                        $destination = '';
                    }

                    $arrResult[] = array(
                        'id'            => 'withdrawal-' . $arrWithdrawals['withdrawal_id'],
                        'real_id'       => $arrWithdrawals['withdrawal_id'],
                        'date'          => $this->_settings->formatDate($date),
                        'time'          => strtotime($date),
                        'description'   => $description,
                        'fees_received' => static::formatPrice($arrWithdrawals['withdrawal']),
                        'fees_due'      => '',
                        'type'          => 'withdrawal',
                        'status'        => $comments,
                        'due_gst'       => '',
                        'received_gst'  => '',
                        'destination'   => $destination
                    );
                }
            }
        }

        // Sort all info based on time + id
        $id   = array();
        $time = array();
        foreach ($arrResult as $key => $row) {
            $time[$key] = $row['time'];
            $id[$key]   = $row['id'];
        }

        if (!empty($arrResult) && !empty($time) && !empty($id)) {
            array_multisort($time, SORT_ASC, $id, SORT_ASC, $arrResult);
        }

        return $arrResult;
    }

    /**
     * A special label / identificator that identifies if invoice payment was paid via the Operating account
     * This is what we'll show and save for the invoice payment
     *
     * @return string
     */
    public static function getInvoicePaymentOperatingAccountOption()
    {
        return 'Operating account';
    }

    /**
     * A list of possible options for the "Special adjustment" combo
     * In the future we can have a different list for each company
     *
     * @return string[]
     */
    public function getInvoicePaymentSpecialAdjustmentOptions()
    {
        return [
            'Discount',
            'Referral',
            'Non-payment'
        ];
    }

    /**
     * Generate a label from the invoice payment record
     *
     * @param array $arrInvoicePaymentInfo
     * @param string $currency
     * @param string $invoiceNumber
     * @param string $strType
     * @return string
     */
    public function getInvoicePaymentLabel($arrInvoicePaymentInfo, $currency, $invoiceNumber = '', $strType = 'general')
    {
        $amount = static::formatPrice($arrInvoicePaymentInfo['invoice_payment_amount'], $currency);

        if (empty($arrInvoicePaymentInfo['company_ta_id'])) {
            if ($arrInvoicePaymentInfo['company_ta_other'] == self::getInvoicePaymentOperatingAccountOption()) {
                if ($strType == 'invoice') {
                    $format = $this->_tr->translate('%date% - From: %from%%cheque%');
                } elseif ($strType == 'pdf') {
                    $format = $this->_tr->translate('%from%%cheque%');
                } else {
                    $format = $this->_tr->translate('Paid %amount% on %date%%invoice_num%, from: %from%%cheque%');
                }

                $from = self::getInvoicePaymentOperatingAccountOption();
            } else {
                if ($strType == 'invoice') {
                    $format = $this->_tr->translate('%date% - From: %from%%description%');
                } elseif ($strType == 'pdf') {
                    $format = $this->_tr->translate('%from%%description%');
                } else {
                    $format = $this->_tr->translate('%from% %amount% on %date%%invoice_num%%description%');
                }

                $from = $arrInvoicePaymentInfo['company_ta_other'];
            }
        } else {
            if ($strType == 'invoice') {
                $format = $this->_tr->translate('%date% - From: %from%%cheque%');
            } elseif ($strType == 'pdf') {
                $format = $this->_tr->translate('%from%%cheque%');
            } else {
                $format = $this->_tr->translate('Paid %amount% on %date%%invoice_num%, from: %from%%cheque%');
            }

            if (!empty($arrInvoicePaymentInfo['transfer_from_company_ta_id'])) {
                $arrTransferFromTA = $this->getCompanyTAbyId($arrInvoicePaymentInfo['transfer_from_company_ta_id']);

                $from = $arrTransferFromTA['name'];

                // Show 'equivalent of' only if currencies are different
                if ($arrTransferFromTA['currency'] != $this->getCompanyTACurrency($arrInvoicePaymentInfo['company_ta_id'])) {
                    $amount .= ' (' . $this->_tr->translate('equivalent of ') . $arrInvoicePaymentInfo['transfer_from_amount'] . ' ' . self::getCurrencyLabel($arrTransferFromTA['currency']) . ')';
                }
            } else {
                $arrTransferFromTA = $this->getCompanyTAbyId($arrInvoicePaymentInfo['company_ta_id']);

                $from = $arrTransferFromTA['name'];
            }
        }

        return $this->_settings->_sprintf(
            $format,
            array(
                'amount'      => $amount,
                'date'        => $this->_settings->formatDate($arrInvoicePaymentInfo['invoice_payment_date']),
                'from'        => $from,
                'cheque'      => empty($arrInvoicePaymentInfo['invoice_payment_cheque_num']) ? '' : $this->_tr->translate(', cheque #: ') . $arrInvoicePaymentInfo['invoice_payment_cheque_num'],
                'description' => empty($arrInvoicePaymentInfo['invoice_payment_cheque_num']) ? '' : ' ' . $arrInvoicePaymentInfo['invoice_payment_cheque_num'],
                'invoice_num' => empty($invoiceNumber) ? '' : (is_numeric($invoiceNumber) ? $this->_tr->translate(' Invoice#') : '') . ' ' . $invoiceNumber,
            )
        );
    }

    /**
     * Create new record in payments table
     *
     * @param int $companyTAId
     * @param int $memberId
     * @param float $amount
     * @param string $description
     * @param $type
     * @param $date
     * @param int $paymentMadeBy
     * @param $gst
     * @param $gstProvinceId
     * @param $gstTaxLabel
     * @param $notes
     * @param null $authorId
     * @param bool $booCompanyAgent
     * @param string $transactionId
     * @return int payment id
     */
    public function addFee($companyTAId, $memberId, $amount, $description, $type, $date, $paymentMadeBy, $gst, $gstProvinceId, $gstTaxLabel, $notes, $authorId = null, $booCompanyAgent = false, $transactionId = '')
    {
        try {
            $arrInsert = array(
                'member_id'       => $memberId,
                'company_ta_id'   => $companyTAId,
                'transaction_id'  => empty($transactionId) ? null : $transactionId,
                'description'     => $description,
                'payment_made_by' => $paymentMadeBy,
                'date_of_event'   => $date,
                'gst'             => $gst,
                'gst_province_id' => $gstProvinceId,
                'gst_tax_label'   => $gstTaxLabel,
                'notes'           => $notes
            );

            if ($type == 'add-fee-due') {
                $arrInsert['deposit']    = 0;
                $arrInsert['withdrawal'] = $amount;
            } else {
                $arrInsert['withdrawal'] = 0;
                $arrInsert['deposit']    = $amount;
            }

            if (!empty($authorId)) {
                $arrInsert['author_id'] = $authorId;
            }

            if ($booCompanyAgent) {
                $arrInsert['company_agent'] = 'Y';
            }

            $paymentId = $this->_db2->insert('u_payment', $arrInsert, 0);

            //update member outstanding balance
            $this->updateOutstandingBalance($memberId, $companyTAId);
        } catch (Exception $e) {
            $paymentId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $paymentId;
    }

    /**
     * Update Fee's details
     *
     * @param int $paymentId
     * @param int $companyTAId
     * @param int $memberId
     * @param float $amount
     * @param string $description
     * @param string $date
     * @param float $gst
     * @param int $gstProvinceId
     * @param string $gstTaxLabel
     * @return bool true on success
     */
    public function updateFee($paymentId, $companyTAId, $memberId, $amount, $description, $date, $gst, $gstProvinceId, $gstTaxLabel)
    {
        try {
            $arrToUpdate = array(
                'member_id'       => $memberId,
                'company_ta_id'   => $companyTAId,
                'description'     => $description,
                'withdrawal'      => $amount,
                'date_of_event'   => $date,
                'gst'             => $gst,
                'gst_province_id' => $gstProvinceId,
                'gst_tax_label'   => $gstTaxLabel,
            );

            $this->_db2->update('u_payment', $arrToUpdate, ['payment_id' => $paymentId]);

            //update member outstanding balance
            $this->updateOutstandingBalance($memberId, $companyTAId);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load last reconcile date for specific company T/A
     *
     * @param int $companyTAId
     * @param bool $booLastReconcileOnly
     * @param string $reconcileType
     * @return string
     */
    public function getLastReconcileDate($companyTAId, $booLastReconcileOnly = false, $reconcileType = null)
    {
        $arrTaInfo = $this->getCompanyTAbyId($companyTAId);

        if (is_null($reconcileType)) {
            $generalTimestamp = Settings::isDateEmpty($arrTaInfo['last_reconcile']) ? 0 : strtotime($arrTaInfo['last_reconcile']);
            $ICCRCTimestamp   = Settings::isDateEmpty($arrTaInfo['last_reconcile_iccrc']) ? 0 : strtotime($arrTaInfo['last_reconcile_iccrc']);

            $strDate = $generalTimestamp > $ICCRCTimestamp ? $arrTaInfo['last_reconcile'] : $arrTaInfo['last_reconcile_iccrc'];
        } else {
            $strDate = $reconcileType == 'general' ? $arrTaInfo['last_reconcile'] : $arrTaInfo['last_reconcile_iccrc'];
        }

        if (!$booLastReconcileOnly) {
            $strDate = Settings::isDateEmpty($strDate) ? $arrTaInfo['create_date'] : $strDate;
        }

        return $strDate;
    }

    public function getAssignedWithdrawalsByTransactionId($tid)
    {
        $select = (new Select())
            ->from('u_assigned_withdrawals')
            ->where(['trust_account_id' => (int)$tid]);

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load client ids by specific transaction
     *
     * @param int $tid
     * @return array
     */
    public function findMemberIdsByTransactionId($tid)
    {
        $arrAssignedWithdrawals = $this->getAssignedWithdrawalsByTransactionId($tid);

        $members = array();
        foreach ($arrAssignedWithdrawals as $arrAssignedWithdrawalInfo) {
            $members[] = $arrAssignedWithdrawalInfo['member_id'];
        }

        if (empty($members)) {
            $select = (new Select())
                ->from('u_assigned_withdrawals')
                ->columns(['returned_payment_member_id'])
                ->where(
                    [
                        (new Where())
                            ->isNotNull('returned_payment_member_id')
                            ->equalTo('trust_account_id', (int)$tid)
                    ]
                );

            $members = $this->_db2->fetchCol($select);
        }
        if (empty($members)) {
            $select = (new Select())
                ->from(array('w' => 'u_assigned_withdrawals'))
                ->columns(['member_id'])
                ->join(array('ip' => 'u_invoice_payments'), 'ip.invoice_payment_id = w.invoice_payment_id', [])
                ->join(array('i' => 'u_invoice'), 'i.invoice_id = ip.invoice_id', ['member_id'])
                ->where(['w.trust_account_id' => (int)$tid]);

            $members = $this->_db2->fetchCol($select);
        }

        if (empty($members)) {
            $members = $this->getDepositsByTARecordId($tid);
        }

        return $members;
    }

    /**
     * Load assigned deposit records for a specific trust account record id
     *
     * @param int $tid
     * @param bool $booMemberIdsOnly
     * @return array
     */
    public function getDepositsByTARecordId($tid, $booMemberIdsOnly = true)
    {
        $select = (new Select())
            ->from('u_assigned_deposits')
            ->columns([$booMemberIdsOnly ? 'member_id' : Select::SQL_STAR])
            ->where(['trust_account_id' => (int)$tid]);

        return $booMemberIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load max invoice number from company's settings
     *
     * @param int $companyTAId
     * @return int
     */
    public function getMaxInvoiceNumber($companyTAId)
    {
        $arrCompanyTAInfo         = $this->getTAInfo($companyTAId);
        $arrInvoiceNumberSettings = $this->_company->getCompanyInvoiceNumberSettings($arrCompanyTAInfo['company_id']);

        return empty($arrInvoiceNumberSettings['start_from']) ? 1 : $arrInvoiceNumberSettings['start_from'];
    }

    /**
     * Load last invoice info, if id provided - use that invoice
     *
     * @param int $memberId
     * @param int $invoiceId
     * @return array
     */
    public function getLastMemberInvoice($memberId, $invoiceId)
    {
        $arrLastInvoice = array();

        try {
            $arrWhere = [
                'i.member_id' => (int)$memberId
            ];

            if (!empty($invoiceId)) {
                $arrWhere['i.invoice_id'] = (int)$invoiceId;
            }

            $select = (new Select())
                ->from(array('i' => 'u_invoice'))
                ->join(array('ta' => 'company_ta'), 'ta.company_ta_id = i.company_ta_id', array('currency', 'name'), Select::JOIN_LEFT)
                ->where($arrWhere)
                ->order(array('i.date_of_creation DESC'))
                ->limit(1);

            $arrLastInvoice = $this->_db2->fetchRow($select);

            if (!empty($arrLastInvoice)) {
                $arrLastInvoice['cheque_num'] = $this->getInvoiceLastPaymentChequeNumber($arrLastInvoice['invoice_id']);
                $arrLastInvoice['total']      = $arrLastInvoice['amount'];
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrLastInvoice;
    }

    /**
     * Load last payment info for specific case
     *
     * @param $memberId
     * @return array
     */
    public function getLastMemberPayment($memberId)
    {
        $select = (new Select())
            ->from(array('p' => 'u_payment'))
            ->where(['p.member_id' => (int)$memberId])
            ->order(array('p.payment_id DESC'))
            ->limit(1);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load list of payments assigned to PS records
     *
     * @param array $arrPSRecords
     * @return array
     */
    public function getPaymentsByPSRecords($arrPSRecords)
    {
        $select = (new Select())
            ->from(array('p' => 'u_payment'))
            ->columns(['payment_id'])
            ->where(['p.payment_schedule_id' => $arrPSRecords]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load calculated balance for the first/last transaction
     *
     * @param int $companyTAId
     * @param bool $booFirst
     * @param bool $booExceptOpeningBalance
     * @return float
     */
    public function getBalanceForImport($companyTAId, $booFirst = true, $booExceptOpeningBalance = true)
    {
        $arrOrder = $booFirst ? array('date_from_bank ASC', 'trust_account_id ASC') : array('date_from_bank DESC', 'trust_account_id DESC');

        $select = (new Select())
            ->from('u_trust_account')
            ->columns(array('deposit', 'withdrawal', 'balance'))
            ->where(['company_ta_id' => (int)$companyTAId])
            ->order($arrOrder)
            ->limit(1);

        if ($booExceptOpeningBalance) {
            $select->where([(new Where())->notEqualTo('purpose', $this->startBalanceTransactionId)]);
        }

        $arrResult = $this->_db2->fetchRow($select);

        if (empty($arrResult)) {
            return 0;
        } else {
            return $booFirst ? $arrResult['balance'] : $arrResult['deposit'] - $arrResult['withdrawal'] + $arrResult['balance'];
        }
    }

    /**
     * Load prepared information for specific company T/A
     *
     * @param $companyTAId
     * @return array|int
     */
    public function getImportSummaryInfo($companyTAId)
    {
        $select = (new Select())
            ->from('u_trust_account')
            ->columns([
                'min_dt_start' => new Expression('MIN(date_from_bank)'),
                'max_dt_end'   => new Expression('MAX(date_from_bank)')
            ])
            ->where(['company_ta_id' => (int)$companyTAId]);

        $arrResult = $this->_db2->fetchRow($select);

        $arrTaInfo = $this->getCompanyTAbyId($companyTAId);

        return $arrResult + $arrTaInfo;
    }

    /**
     * Check if Officio start balance was assigned in specific company T/A
     *
     * @param int $companyTAId
     * @return bool
     */
    public function isStartBalanceAssigned($companyTAId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(['count' => new Expression('COUNT(d.trust_account_id)')])
            ->join(array('d' => 'u_assigned_deposits'), 'ta.trust_account_id = d.trust_account_id', Select::SQL_STAR, Select::JOIN_LEFT)
            ->where(
                [
                    'ta.company_ta_id' => (int)$companyTAId,
                    'ta.purpose'       => $this->startBalanceTransactionId
                ]
            )
            ->group(array('d.trust_account_id'));

        return $this->_db2->fetchOne($select) > 0;
    }

    /**
     * Check if Officio start balance exists for specific company T/A
     *
     * @param int $companyTAId
     * @return int empty if not exists
     */
    public function startBalanceRecordExists($companyTAId)
    {
        $arrStartBalance = $this->getStartBalanceInfo($companyTAId);
        return $arrStartBalance['trust_account_id'] ?? 0;
    }

    /**
     * Check if any record except start balance exists for specific company T/A
     *
     * @param int $companyTAId
     * @return int empty if not exists
     */
    public function hasTrustAccountTransactions($companyTAId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(['trust_account_id'])
            ->where(
                [
                    (new Where())
                        ->notEqualTo('ta.purpose', $this->startBalanceTransactionId)
                        ->equalTo('ta.company_ta_id', (int)$companyTAId)
                ]
            );

        $a = $this->_db2->fetchCol($select);

        return count($a);
    }

    /**
     * Load detailed info for specific payment
     *
     * @param int $paymentId
     * @return array
     */
    public function getPaymentInfo($paymentId)
    {
        //get payment info
        $select = (new Select())
            ->from(array('p' => 'u_payment'))
            ->join(array('hst' => 'hst_companies'), 'p.gst_province_id = hst.province_id', array('tax_type', 'is_system'), Select::JOIN_LEFT)
            ->where(['p.payment_id' => (int)$paymentId]);

        $payment = $this->_db2->fetchRow($select);

        // Return empty array if current user cannot access to this client
        if (!isset($payment['member_id']) || !$this->_parent->hasCurrentMemberAccessToMember($payment['member_id'])) {
            return array();
        }

        //get formatted date
        $payment = $this->getPaymentCorrectAmountAndGst($payment);

        $payment['date_formatted'] = $this->_settings->formatDate($payment['date_of_event']);

        $currency = $this->getCurrency($payment['company_ta_id']);

        //get amount and gst
        if (empty($payment['invoice_number'])) {
            $amount = $payment['withdrawal'] == '0' ? $payment['deposit'] : $payment['withdrawal'];

            $payment['isFee']  = true;
            $payment['amount'] = static::formatPrice($amount, $currency);

            //gst
            $payment['gst_formatted'] = static::formatPrice($amount * $payment['gst'] / 100, $currency, false);

            if (empty($payment['gst_tax_label'])) {
                $payment['gst_label'] = 'GST';
            } else {
                $arrToFormat = array(
                    'province'  => '',
                    'tax_label' => $payment['gst_tax_label'],
                    'rate'      => $payment['gst'],
                    'tax_type'  => $payment['tax_type'],
                    'is_system' => $payment['is_system'],
                );

                $payment['gst_label'] = $this->_gstHst->formatGSTLabel($arrToFormat);
            }
        } else {
            $payment['isFee']  = false;
            $payment['amount'] = static::formatPrice($payment['deposit'], $currency);
        }

        return $payment;
    }

    public function getSavedPaymentsList($booWithNew)
    {
        $select = (new Select())
            ->from('u_payment_templates')
            ->where(['company_id' => $this->_auth->getCurrentUserCompanyId()])
            ->order('name');

        $arrPayments = $this->_db2->fetchAll($select);

        foreach ($arrPayments as &$payment) {
            $payment['payments'] = json_decode($payment['payments'], true);
        }

        if ($booWithNew) {
            $newItem = array(
                'saved_payment_template_id' => 0,
                'name'                      => 'New Template',
                'created_date'              => '',
                'payments'                  => array()
            );

            $arrPayments = array_merge(array($newItem), $arrPayments);
        }

        return $arrPayments;
    }

    /**
     * Create/update payment template record
     *
     * @param mixed $paymentTemplateId
     * @param string $name
     * @param array $arrPayments
     * @return bool|int - false on error
     */
    public function savePaymentTemplate($paymentTemplateId, $name, $arrPayments)
    {
        try {
            $paymentsToInsert = array();
            foreach ($arrPayments as $payment) {
                $paymentsToInsert[] = array(
                    'amount'      => (double)$payment['amount'],
                    'type'        => $payment['type'],
                    'tax_id'      => (int)$payment['tax_id'],
                    'description' => $payment['description'],
                    'due_on_id'   => (int)$payment['due_on_id'],
                    'due_date'    => $payment['due_date']
                );
            }

            $insertArr = array(
                'name'     => $name,
                'payments' => json_encode($paymentsToInsert)
            );

            if (empty($paymentTemplateId)) {
                $insertArr['created_date'] = date('Y-m-d');
                $insertArr['company_id']   = $this->_auth->getCurrentUserCompanyId();

                $paymentTemplateId = $this->_db2->insert('u_payment_templates', $insertArr, false);
            } else {
                $this->_db2->update('u_payment_templates', $insertArr, ['saved_payment_template_id' => $paymentTemplateId]);
            }
        } catch (Exception $e) {
            $paymentTemplateId = false;
        }

        return $paymentTemplateId;
    }

    /**
     * Remove payment template by id
     *
     * @param int $paymentTemplateId
     */
    public function removePaymentTemplate($paymentTemplateId)
    {
        $this->_db2->delete(
            'u_payment_templates',
            [
                'saved_payment_template_id' => $paymentTemplateId,
                'company_id'                => $this->_auth->getCurrentUserCompanyId()
            ]
        );
    }

    public function hasAccessToPaymentTemplate($paymentTemplateId)
    {
        $booHasAccess = false;

        if ($this->_auth->isCurrentUserSuperadmin()) {
            $booHasAccess = true;
        } else {
            $select = (new Select())
                ->from('u_payment_templates')
                ->where(
                    [
                        'saved_payment_template_id' => (int)$paymentTemplateId,
                        'company_id'                => $this->_auth->getCurrentUserCompanyId()
                    ]
                );

            $arrPayments = $this->_db2->fetchAll($select);

            if (count($arrPayments)) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }

    /**
     * Collect Client Balances data and generate a report (in the Excel or Pdf format)
     *
     * @param string $type - 'excel' or 'pdf'
     * @param string $fileName
     * @param string $title
     * @param string $start_date - start date of the limitations in report
     * @param string $end_date - end date of the limitations in report
     * @param int|null $companyId - for which company we need load info
     * @return Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function generateClientBalancesReport($type, $fileName, $title, $start_date, $end_date, $companyId = null)
    {
        $arrClientsList = !is_null($companyId) ? $this->_parent->getAllClientsList($companyId) : $this->_parent->getClientsList();
        $arrParentsList = $this->_parent->getCasesListWithParents($arrClientsList);
        $companyId      = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
        $company        = $this->_company->getCompanyInfo($companyId);
        $taLabel        = $this->_company->getCurrentCompanyDefaultLabel('trust_account');

        $arrClientIds                  = array_column($arrClientsList, 'member_id');
        $arrMembersTa                  = $this->getMembersTA($arrClientIds, true);
        $arrClientsNotVerifiedDeposits = $this->getNotVerifiedDepositsForClientBalancesReport($arrClientIds);

        $arrReportInfo = array();
        foreach ($arrClientsList as $client) {
            $booIsAssigned     = false;
            $caseAndClientName = $client['full_name_with_file_num'];
            foreach ($arrParentsList as $arrParentInfo) {
                if ($client['member_id'] == $arrParentInfo['clientId']) {
                    $booIsAssigned     = true;
                    $caseAndClientName = $arrParentInfo['clientFullName'];
                    break;
                }
            }

            // Don't show a Case record if it is not assigned to the client
            if (!$booIsAssigned) {
                continue;
            }

            if (!isset($arrMembersTa[$client['member_id']])) {
                // There are no assigned T/A to this client
                continue;
            }

            $arrCaseTA = $arrMembersTa[$client['member_id']];

            // Default values
            $arrRowInfo = array(
                'client'                           => $caseAndClientName,
                'primary_currency_not_verified'    => '',
                'primary_currency_available'       => '',
                'primary_currency_outst_balance'   => '',
                'secondary_currency_not_verified'  => '',
                'secondary_currency_available'     => '',
                'secondary_currency_outst_balance' => ''
            );


            // Collect required data
            $booPrimaryCurrencyTAEnabled   = false;
            $booSecondaryCurrencyTAEnabled = false;
            foreach ($arrCaseTA as $ta) {
                $currency = self::getCurrencyLabel($ta['currency']);
                if (empty($ta['order'])) {
                    if (!$booPrimaryCurrencyTAEnabled) {
                        // First time
                        $booPrimaryCurrencyTAEnabled = true;

                        if (empty($start_date) || empty($end_date)) {
                            $arrRowInfo['primary_currency_available'] = $arrMembersTa[$client['member_id']][$ta['company_ta_id']]['sub_total'] . ' ' . $currency;
                        } else {
                            $arrRowInfo['primary_currency_available'] = $this->calculateTrustAccountSubTotal($client['member_id'], $ta['company_ta_id'], $start_date, $end_date) . ' ' . $currency;
                        }

                        $notVerifiedDepositsSum = 0;

                        if (isset($arrClientsNotVerifiedDeposits[$client['member_id']][$ta['company_ta_id']])) {
                            $arrNotVerifiedDeposits = $arrClientsNotVerifiedDeposits[$client['member_id']][$ta['company_ta_id']];

                            foreach ($arrNotVerifiedDeposits as $notVerifiedDeposit) {
                                $notVerifiedDepositsSum += $notVerifiedDeposit['deposit'];
                            }
                        }

                        $arrRowInfo['primary_currency_not_verified']  = $notVerifiedDepositsSum . ' ' . $currency;
                        $arrRowInfo['primary_currency_outst_balance'] = $arrMembersTa[$client['member_id']][$ta['company_ta_id']]['outstanding_balance'] . ' ' . $currency;
                    }
                } elseif (!$booSecondaryCurrencyTAEnabled) {
                    $booSecondaryCurrencyTAEnabled = true;

                    if (empty($start_date) || empty($end_date)) {
                        $arrRowInfo['secondary_currency_available'] = $arrMembersTa[$client['member_id']][$ta['company_ta_id']]['sub_total'] . ' ' . $currency;
                    } else {
                        $arrRowInfo['secondary_currency_available'] = $this->calculateTrustAccountSubTotal($client['member_id'], $ta['company_ta_id'], $start_date, $end_date) . ' ' . $currency;
                    }

                    $notVerifiedDepositsSum = 0;

                    if (isset($arrClientsNotVerifiedDeposits[$client['member_id']][$ta['company_ta_id']])) {
                        $arrNotVerifiedDeposits = $arrClientsNotVerifiedDeposits[$client['member_id']][$ta['company_ta_id']];

                        foreach ($arrNotVerifiedDeposits as $notVerifiedDeposit) {
                            $notVerifiedDepositsSum += $notVerifiedDeposit['deposit'];
                        }
                    }

                    $arrRowInfo['secondary_currency_not_verified']  = $notVerifiedDepositsSum . ' ' . $currency;
                    $arrRowInfo['secondary_currency_outst_balance'] = $arrMembersTa[$client['member_id']][$ta['company_ta_id']]['outstanding_balance'] . ' ' . $currency;
                }

                if ($booPrimaryCurrencyTAEnabled && $booSecondaryCurrencyTAEnabled) {
                    break;
                }
            }

            $arrReportInfo[] = $arrRowInfo;
        }

        if ($type == 'excel') {
            $result = $this->createClientsBalancesExcelReport($arrReportInfo, $title, $company['companyName'], $taLabel);
        } else {
            $this->_pdf->createClientsBalancesReport($fileName, $arrReportInfo, $title, $company['companyName'], $taLabel);

            $result = null;
        }

        return $result;
    }

    /**
     * Generate Excel report and output it in browser
     *
     * @param array $arrReportInfo - array with report info which will be parsed
     * @param string $title - worksheet name
     * @param string $company - company name will be shown at the top in header
     * @param string $taLabel - T/A label
     * @return Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    private function createClientsBalancesExcelReport($arrReportInfo, $title, $company, $taLabel)
    {
        // Turn off warnings - issue when generate xls file
        error_reporting(E_ERROR);

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');


        $worksheetName = $this->_files::checkPhpExcelFileName($title);
        $worksheetName = empty($worksheetName) ? 'Export Result' : $worksheetName;

        $abc     = array('A');
        $current = 'A';
        while ($current != 'ZZZ') {
            $abc[] = ++$current;
        }

        // Creating an object
        $objPHPExcel = new Spreadsheet();

        // Set properties
        $objPHPExcel->getProperties()->setTitle($worksheetName);
        $objPHPExcel->getProperties()->setSubject($worksheetName);

        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();

        // column sizes
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);

        // all cells styles
        $bottom_right_cell = 'G' . (count($arrReportInfo) + 6);

        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setName('Arial');
        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setSize(10);

        // info styles
        $sheet->getStyle('A1')->getFont()->setBold(true);

        // header styles
        $sheet->getStyle('B6:E6')->getAlignment()->setWrapText(true); // wrap!
        $sheet->getStyle('A5:G6')->getFont()->setBold(true);
        $sheet->getStyle('A5:G6')->getFill()->applyFromArray(
            [
                'fillType'   => Fill::FILL_SOLID,
                'rotation'   => 0,
                'startColor' => [
                    'rgb' => 'DBDBDB'
                ],
                'endColor'   => [
                    'argb' => 'DBDBDB'
                ]
            ]
        );

        $sheet->getStyle('A5:G6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // data styles
        $sheet->getStyle('B7:' . $bottom_right_cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // output info
        $sheet->setCellValue('A1', $worksheetName);
        $sheet->setCellValue('A2', $company);
        $sheet->setCellValue('A3', 'Report Date ' . date('M d, Y'));

        // output headers
        $sheet->mergeCells('B5:E5'); // colspan
        $sheet->mergeCells('F5:G5'); // colspan

        $sheet->setCellValue('A5', 'Client');
        $sheet->setCellValue('B5', $taLabel . ' Summary');
        $sheet->setCellValue('F5', 'Outstanding Balance');
        $sheet->setCellValue('B6', 'Deposits not verified (Secondary ' . $taLabel . ')');
        $sheet->setCellValue('C6', 'Available Total (Secondary ' . $taLabel . ')');
        $sheet->setCellValue('D6', 'Deposits not verified (Primary ' . $taLabel . ')');
        $sheet->setCellValue('E6', 'Available Total (Primary ' . $taLabel . ')');

        // output data
        $arrColumns = array(
            'secondary_currency_not_verified',
            'secondary_currency_available',
            'primary_currency_not_verified',
            'primary_currency_available',
            'secondary_currency_outst_balance',
            'primary_currency_outst_balance'
        );

        foreach ($arrReportInfo as $key_row => $arrRow) {
            $sheet->setCellValue('A' . ($key_row + 7), $arrRow['client']);

            foreach ($arrColumns as $key_col => $f) {
                if ($arrRow[$f]) {
                    // get currency format for Excel. Example: ###0.00__\U\S\$
                    $array                     = explode(" ", $arrRow[$f]);
                    $currency_format           = end($array);
                    $currency_format_for_excel = '###0.00__';

                    $iMax = strlen($currency_format);
                    for ($i = 0; $i < $iMax; $i++) {
                        $currency_format_for_excel .= '\\' . $currency_format[$i];
                    }

                    $cell_id = $abc[$key_col + 1] . ($key_row + 7);
                    $sheet->setCellValue($cell_id, current(explode(' ', $arrRow[$f])));
                    $sheet->getStyle($cell_id)->getNumberFormat()->setFormatCode($currency_format_for_excel);
                }
            }
        }

        // Rename sheet
        $sheet->setTitle($worksheetName);

        return $objPHPExcel;
    }

    /**
     * Generate client transactions report
     *
     * @param string $format - pdf or excel, if pdf -> we generate the pdf and output it immediately
     * @param string $fileName
     * @param string $title
     * @param string $report
     * @param string $currency
     * @param bool|string $from
     * @param bool|string $to
     * @param null|int $companyId
     * @return Spreadsheet
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function generateClientTransactionsReport($format, $fileName, $title, $report, $currency, $from, $to, $companyId = null)
    {
        $arrReportInfo   = array();
        $arrClientsNames = array();

        // Get clients info
        $booCheckCurrency = is_null($companyId);

        $arrClientsList = !is_null($companyId) ? $this->_parent->getAllClientsList($companyId) : $this->_parent->getClientsList();
        $arrParentsList = $this->_parent->getCasesListWithParents($arrClientsList);
        $companyId      = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
        $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);

        $arrClientIds = array();
        foreach ($arrClientsList as $client) {
            $arrClientIds[] = $client['member_id'];
        }

        $arrMembersTa = $this->getMembersTA($arrClientIds);

        foreach ($arrClientsList as $client) {
            $booIsAssigned     = false;
            $caseAndClientName = $client['full_name_with_file_num'];
            foreach ($arrParentsList as $arrParentInfo) {
                if ($client['member_id'] == $arrParentInfo['clientId']) {
                    $booIsAssigned     = true;
                    $caseAndClientName = $arrParentInfo['clientFullName'];
                    break;
                }
            }

            // Don't show a Case record if he is not assigned to clients
            if (!$booIsAssigned) {
                continue;
            }

            //get T/A list
            if (!isset($arrMembersTa[$client['member_id']])) {
                // There are no assigned T/A to this client
                continue;
            } else {
                $taList = $arrMembersTa[$client['member_id']];
            }

            //get client transactions list
            $arrClientTransactions = array();
            foreach ($taList as $ta) {
                if ($booCheckCurrency && $ta['currency'] != $currency) {
                    continue;
                }

                $transactionsList = $this->getClientsTransactionsInfo($client['member_id'], $ta['company_ta_id'], $from, $to);
                if (!empty($transactionsList)) {
                    //filter
                    foreach ($transactionsList as $key => $transaction) {
                        if (($report == 'transaction-fees-received' && empty($transaction['fees_received'])) ||
                            ($report == 'transaction-fees-due' && empty($transaction['fees_due']))) {
                            unset($transactionsList[$key]);
                        }
                    }

                    if (!empty($transactionsList)) {
                        $arrClientTransactions[] = array(
                            'name'         => $ta['name'],
                            'currency'     => $ta['currency'],
                            'transactions' => $transactionsList
                        );
                    }
                }
            }

            if (!empty($arrClientTransactions)) {
                $arrReportInfo[] = array(
                    'client' => $caseAndClientName,
                    'ta'     => $arrClientTransactions
                );

                $arrClientsNames[] = $caseAndClientName;
            }
        }

        // Sort result by clients' names
        array_multisort($arrClientsNames, SORT_ASC, $arrReportInfo);

        if ($format == 'pdf') {
            $this->_pdf->createClientsTransactionsReport(
                $fileName,
                $arrReportInfo,
                $report,
                $title,
                $arrCompanyInfo['companyName'],
                'Report Date ' . $this->_settings->formatDate(date('Y-m-d'))
            );

            // Cannot be here
            $result = null;
        } else {
            $result = $this->createClientsTransactionsExcelReport(
                $title,
                $arrReportInfo,
                $report,
                $arrCompanyInfo['companyName'],
                $from,
                $to
            );
        }

        return $result;
    }

    /**
     * Generate clients transactions report (in excel format)
     *
     * @param $title
     * @param $arrReportInfo
     * @param $report
     * @param $company
     * @param $from
     * @param $to
     * @return Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function createClientsTransactionsExcelReport($title, $arrReportInfo, $report, $company, $from, $to)
    {
        // Turn off warnings - issue when generate xls file
        error_reporting(E_ERROR);

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        $booShowFeeReceived = in_array($report, array('transaction-all', 'transaction-fees-received'));
        $booShowFeeDue      = in_array($report, array('transaction-all', 'transaction-fees-due'));

        $worksheetName = $this->_files::checkPhpExcelFileName($title);
        $worksheetName = empty($worksheetName) ? 'Export Result' : $worksheetName;

        $abc     = array('A');
        $current = 'A';
        while ($current != 'ZZZ') {
            $abc[] = ++$current;
        }

        // Creating an object
        $objPHPExcel = new Spreadsheet();

        // Set properties
        $objPHPExcel->getProperties()->setTitle($worksheetName);
        $objPHPExcel->getProperties()->setSubject($worksheetName);

        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();

        // column sizes
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(50);
        $sheet->getColumnDimension('E')->setWidth(12);
        if ($booShowFeeReceived && $booShowFeeDue) {
            $sheet->getColumnDimension('F')->setWidth(12);
        }
        $sheet->getColumnDimension($abc[4 + (int)$booShowFeeReceived + (int)$booShowFeeDue])->setWidth(34);
        $sheet->getColumnDimension($abc[5 + (int)$booShowFeeReceived + (int)$booShowFeeDue])->setWidth(12);
        if ($booShowFeeDue) {
            $sheet->getColumnDimension($abc[6 + (int)$booShowFeeReceived + (int)$booShowFeeDue])->setWidth(12);
            $sheet->getColumnDimension($abc[7 + (int)$booShowFeeReceived + (int)$booShowFeeDue])->setWidth(12);
        }
        if ($booShowFeeReceived && $booShowFeeDue) {
            $sheet->getColumnDimension($abc[8 + (int)$booShowFeeReceived + (int)$booShowFeeDue])->setWidth(12);
            $sheet->getColumnDimension($abc[9 + (int)$booShowFeeReceived + (int)$booShowFeeDue])->setWidth(12);
        }

        // info styles
        $sheet->getStyle('A1')->getFont()->setBold(true);

        if (!empty($from) || !empty($to)) {
            $sheet->getRowDimension(1)->setRowHeight(25);
        }

        // output info
        $sheet->setCellValue('A1', $title);
        $sheet->setCellValue('A2', $company);
        $sheet->setCellValue('A3', 'Report Date ' . date('M d, Y'));

        // output headers
        $numbers_columns = array();

        $sheet->setCellValue('A5', 'Case');
        $sheet->setCellValue('B5', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
        $sheet->setCellValue('C5', 'Date');
        $sheet->setCellValue('D5', 'Description');
        if ($booShowFeeReceived) {
            $sheet->setCellValue('E5', 'Fee Received');
            $numbers_columns[] = 'E';
        }
        if ($booShowFeeDue) {
            $sheet->setCellValue($abc[4 + (int)$booShowFeeReceived] . '5', 'Fee Due');
            $numbers_columns[] = $abc[4 + (int)$booShowFeeReceived];
        }
        $sheet->setCellValue($abc[4 + (int)$booShowFeeReceived + (int)$booShowFeeDue] . '5', 'Comments');
        if ($booShowFeeReceived) {
            $sheet->setCellValue($abc[6 + (int)$booShowFeeDue] . '5', 'Total Fees Received');
            $numbers_columns[] = $abc[6 + (int)$booShowFeeDue];
        }
        if ($booShowFeeDue) {
            $sheet->setCellValue($abc[6 + 2 * (int)$booShowFeeReceived] . '5', 'Total Fees Due');
            $numbers_columns[] = $abc[6 + 2 * (int)$booShowFeeReceived];
        }
        if ($booShowFeeDue && $booShowFeeReceived) {
            $sheet->setCellValue('J5', 'Total Outstanding');
            $numbers_columns[] = 'J';
        }
        if ($booShowFeeDue) {
            $sheet->setCellValue($abc[7 + 3 * (int)$booShowFeeReceived] . '5', 'Total Net Fees Due');
            $sheet->setCellValue($abc[8 + 3 * (int)$booShowFeeReceived] . '5', 'Total Net Taxes Due');
            $numbers_columns[] = $abc[7 + 3 * (int)$booShowFeeReceived];
            $numbers_columns[] = $abc[8 + 3 * (int)$booShowFeeReceived];
        }

        // output data
        $currentRow = 6;

        $super_total_cols = array();
        foreach ($arrReportInfo as $arrClients) {
            $sheet->setCellValue('A' . $currentRow++, '');
            $sheet->setCellValue('A' . $currentRow, $arrClients['client']);

            foreach ($arrClients['ta'] as $arrTA) {
                $sheet->setCellValue('B' . $currentRow, $arrTA['name'] . "\n(" . self::getCurrencyLabel($arrTA['currency']) . ')');

                $received_total = $due_total = $gst_due_total = 0;
                foreach ($arrTA['transactions'] as $transaction) {
                    if (isset($transaction['description_without_gst'])) {
                        $description = strip_tags(str_replace('<br />', "\n", $transaction['description_without_gst']));
                    } else {
                        $description = strip_tags(str_replace('<br />', "\n", $transaction['description'] ?? ''));
                    }
                    $description .= empty($transaction['destination']) ? '' : "\nDestination: " . $transaction['destination'];

                    $status   = strip_tags(str_replace('<br />', "\n", $transaction['status'] ?? ''));
                    $received = $transaction['fees_received'];
                    $due      = $transaction['fees_due'];

                    $sheet->setCellValue('C' . $currentRow, $transaction['date']);
                    $sheet->setCellValue('D' . $currentRow, $description);

                    if ($booShowFeeReceived && $received) {
                        $sheet->setCellValue('E' . $currentRow, $received);
                    }

                    if ($booShowFeeDue && $due) {
                        $sheet->setCellValue($abc[4 + (int)$booShowFeeReceived] . $currentRow, $due);
                    }

                    $sheet->setCellValue($abc[4 + (int)$booShowFeeReceived + (int)$booShowFeeDue] . $currentRow, $status);

                    ++$currentRow;

                    if ($transaction['received_gst'] || $transaction['due_gst']) {
                        $sheet->setCellValue('D' . $currentRow, $transaction['description_gst']);

                        if ($booShowFeeReceived && $transaction['received_gst']) {
                            $sheet->setCellValue('E' . $currentRow, $transaction['received_gst']);
                        }

                        if ($booShowFeeDue && $transaction['due_gst']) {
                            $sheet->setCellValue($abc[4 + (int)$booShowFeeReceived] . $currentRow, $transaction['due_gst']);
                            $gst_due_total += floatval($transaction['due_gst']);
                        }

                        ++$currentRow;
                    }

                    $received_total += floatval($transaction['fees_received']) + floatval($transaction['received_gst']);
                    $due_total      += floatval($transaction['fees_due']) + floatval($transaction['due_gst']);
                }

                // totals
                $sheet->setCellValue('A' . $currentRow, 'Total');

                if ($booShowFeeReceived) {
                    $col = 6 + (int)$booShowFeeDue;
                    $sheet->setCellValue($abc[$col] . $currentRow, static::formatPrice($received_total));
                    $super_total_cols[] = $col;
                }

                if ($booShowFeeDue) {
                    $col = 6 + 2 * (int)$booShowFeeReceived;
                    $sheet->setCellValue($abc[$col] . $currentRow, static::formatPrice($due_total));
                    $super_total_cols[] = $col;
                }

                if ($booShowFeeReceived && $booShowFeeDue) {
                    $col = 9;
                    $sheet->setCellValue($abc[$col] . $currentRow, static::formatPrice($due_total - $received_total));
                    $super_total_cols[] = $col;
                }

                if ($booShowFeeDue) {
                    $col = 7 + 3 * (int)$booShowFeeReceived;
                    $sheet->setCellValue($abc[$col] . $currentRow, static::formatPrice($due_total - $gst_due_total));
                    $super_total_cols[] = $col;

                    $col = 8 + 3 * (int)$booShowFeeReceived;
                    $sheet->setCellValue($abc[$col] . $currentRow, static::formatPrice($gst_due_total));
                    $super_total_cols[] = $col;
                }

                $currentRow += 3;
            }
        }

        // output super-totals
        $last_row         = $currentRow;
        $last_col         = 'A';
        $super_total_cols = array_unique($super_total_cols);
        sort($super_total_cols);
        foreach (array_unique($super_total_cols) as $col) {
            $sheet->setCellValue($abc[$col] . $currentRow, '=SUM(' . $abc[$col] . '7:' . $abc[$col] . ($currentRow - 1) . ')');
            $last_col = $abc[$col];
        }

        $bottom_right_cell = $last_col . $last_row;

        // all cells styles
        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setName('Arial');
        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setSize(10);

        // header styles
        $headers_interval = 'A5:' . $last_col . '5';

        $sheet->getStyle($headers_interval)->getAlignment()->setWrapText(true); // wrap!
        $sheet->getStyle($headers_interval)->getFont()->setBold(true);
        $sheet->getStyle($headers_interval)->getFill()->applyFromArray(
            [
                'fillType'   => Fill::FILL_SOLID,
                'rotation'   => 0,
                'startColor' => [
                    'rgb' => 'DBDBDB'
                ],
                'endColor'   => [
                    'argb' => 'DBDBDB'
                ]
            ]
        );

        $sheet->getStyle($headers_interval)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headers_interval)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // client and T/A cols styles
        if (count($super_total_cols) > 0) {
            $sheet->getStyle('A7:B' . $last_row)->getFont()->setBold(true);

            // numbers cols styles
            foreach ($numbers_columns as $c) {
                $sheet->getStyle($c . '7:' . $c . $last_row)->getNumberFormat()->setFormatCode('###0.00');
            }

            // totals cols styles
            $sheet->getStyle(
                $abc[current($super_total_cols)] . '7:' . $abc[end($super_total_cols)] . $last_row
            )->getFont()->setBold(true);
        }

        // Rename sheet
        $sheet->setTitle($worksheetName);

        return $objPHPExcel;
    }

    /**
     * Render invoice template
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param int $invoiceId
     * @param int $templateId
     * @param array $arrFeesIds
     * @param bool $booPdf
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getInvoiceRenderedTemplate($memberId, $companyTAId, $invoiceId, $templateId, $arrFeesIds, $booPdf)
    {
        $strHtmlTemplate = '';
        $arrInvoiceInfo  = [];
        $arrFees         = [];
        $arrPayments     = [];
        $feesAmount      = '';
        $feesTax         = '';
        $feesTotal       = '';
        $paymentsTotal   = '';
        $outstanding     = '';
        $invoicePaid     = false;

        if (empty($invoiceId)) {
            $booReadonly = false;
            $arrResult   = $this->getNewInvoiceDetails($companyTAId, $memberId, [], $arrFeesIds);
            $strError    = $arrResult['message'];

            if (empty($strError)) {
                $arrFees     = $arrResult['fees'];
                $arrPayments = $arrResult['payments'];

                $arrInvoiceInfo = [
                    'invoice_id'              => 0,
                    'invoice_num'             => $arrResult['invoice_num'],
                    'fee'                     => $arrResult['fee'],
                    'tax'                     => $arrResult['tax'],
                    'total'                   => $arrResult['total'],
                    'currency'                => $arrResult['currency'],
                    'currency_label'          => static::getCurrencyLabel($arrResult['currency'], false),
                    'currency_sign'           => static::getCurrencySign($arrResult['currency']),
                    'invoice_recipient_notes' => '',
                ];

                $feesAmount    = static::formatPrice($arrInvoiceInfo['fee'], $arrInvoiceInfo['currency']);
                $feesTax       = static::formatPrice($arrInvoiceInfo['tax'], $arrInvoiceInfo['currency']);
                $feesTotal     = static::formatPrice($arrInvoiceInfo['total'], $arrInvoiceInfo['currency']);
                $paymentsTotal = static::formatPrice($arrResult['payments_total'], $arrInvoiceInfo['currency']);
                $outstanding   = static::formatPrice($arrResult['outstanding'], $arrInvoiceInfo['currency']);
                $invoicePaid   = $arrResult['outstanding'] <= 0;
            }
        } else {
            $booReadonly = true;
            $invoiceInfo = $this->getInvoiceInfo($invoiceId);
            $memberId    = $invoiceInfo['member_id'];
            $memberInfo  = $this->_parent->getMemberInfo($memberId);
            $clientInfo  = $this->_parent->generateClientName($memberInfo);

            $arrInvoiceInfo = array(
                'member_id'               => $memberId,
                'member_name'             => $clientInfo['full_name'],
                'author_id'               => $invoiceInfo['author_id'],
                'invoice_num'             => $invoiceInfo['invoice_num'],
                'fee'                     => $invoiceInfo['fee'] ?: '',
                'tax'                     => $invoiceInfo['tax'] ?: '',
                'amount'                  => $invoiceInfo['amount'],
                'currency'                => $this->getCompanyTACurrency($invoiceInfo['company_ta_id']),
                'notes'                   => $invoiceInfo['notes'],
                'invoice_recipient_notes' => $invoiceInfo['invoice_recipient_notes'],
                'date_of_invoice'         => $invoiceInfo['date_of_invoice'],
                'date_of_creation'        => $invoiceInfo['date_of_creation']
            );

            $arrInvoiceInfo['invoice_id']     = $invoiceId;
            $arrInvoiceInfo['total']          = floatval($arrInvoiceInfo['fee']) + floatval($arrInvoiceInfo['tax']);
            $arrInvoiceInfo['date_formatted'] = $this->_settings->formatDate($arrInvoiceInfo['date_of_invoice']);
            $arrInvoiceInfo['currency_label'] = static::getCurrencyLabel($arrInvoiceInfo['currency'], false);
            $arrInvoiceInfo['currency_sign']  = static::getCurrencySign($arrInvoiceInfo['currency']);

            // Load additional info about the invoice's payments and fees
            $arrInvoiceSavedPayments = $this->getInvoicePayments($invoiceId);

            $arrPaymentIds     = $this->getFeesAssignedToInvoice($invoiceId, true);
            $arrInvoiceDetails = $this->getInvoiceGroupedDetails($companyTAId, $arrPaymentIds, $arrInvoiceSavedPayments);
            $strError          = $arrInvoiceDetails['strError'];

            if (empty($strError)) {
                $arrFees       = $arrInvoiceDetails['arrInvoiceFees'];
                $arrPayments   = $arrInvoiceDetails['arrInvoicePayments'];
                $paymentsTotal = static::formatPrice($arrInvoiceDetails['payments_total'], $arrInvoiceInfo['currency']);
                $outstanding   = static::formatPrice($arrInvoiceDetails['outstanding'], $arrInvoiceInfo['currency']);
                $invoicePaid   = $arrInvoiceDetails['outstanding'] <= 0;

                if (!empty($arrInvoiceDetails['total'])) {
                    $feesAmount = static::formatPrice($arrInvoiceDetails['fee'], $arrInvoiceInfo['currency']);
                    $feesTax    = static::formatPrice($arrInvoiceDetails['tax'], $arrInvoiceInfo['currency']);
                    $feesTotal  = static::formatPrice($arrInvoiceDetails['total'], $arrInvoiceInfo['currency']);
                }
            }
        }

        if (empty($strError)) {
            $companyId      = $this->_auth->getCurrentUserCompanyId();
            $arrSettings    = $this->getAccountingSettings($companyId);
            $arrCaseInfo    = $this->_parent->getClientAndCaseReadableInfo($memberId);
            $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
            $imgSrc         = $this->_company->getCompanyLogoData($arrCompanyInfo);

            $arrCaseParents     = $this->_parent->getParentsForAssignedApplicants([$memberId]);
            $parentId           = $arrCaseParents[$memberId]['parent_member_id'];
            $parentUserTypeName = $arrCaseParents[$memberId]['member_type_name'];

            list($arrFields,) = $this->_parent->getAllApplicantFieldsData($parentId, $this->_parent->getMemberTypeIdByName($parentUserTypeName), true);
            $arrFields = array_map(function ($val) {
                return $val[0];
            }, $arrFields);

            $arrInvoiceNumberSettings             = $this->_company->getCompanyInvoiceNumberSettings($companyId);
            $arrInvoiceInfo['invoice_tax_number'] = $arrInvoiceNumberSettings['tax_number'];
            $arrInvoiceInfo['invoice_disclaimer'] = $arrInvoiceNumberSettings['disclaimer'];

            $arrParams = [
                'profileInfo'    => $arrFields,
                'isPdf'          => $booPdf,
                'readOnly'       => $booReadonly,
                'settings'       => $arrSettings,
                'caseInfo'       => $arrCaseInfo,
                'companyInfo'    => $arrCompanyInfo,
                'invoiceInfo'    => $arrInvoiceInfo,
                'fees'           => $arrFees,
                'fees_amount'    => $feesAmount,
                'fees_tax'       => $feesTax,
                'fees_total'     => $feesTotal,
                'payments'       => $arrPayments,
                'payments_total' => $paymentsTotal,
                'outstanding'    => $outstanding,
                'invoicePaid'    => $invoicePaid,
                'imgSrc'         => $imgSrc,
            ];

            $viewModel = new ViewModel($arrParams);
            $viewModel->setTerminal(true);
            $viewModel->setTemplate('clients/accounting/invoice_template_' . $templateId . '.phtml');

            /** @var PhpRenderer $renderer */
            $renderer        = $this->_serviceContainer->get(PhpRenderer::class);
            $strHtmlTemplate = $renderer->render($viewModel);
        }

        return [$strError, $strHtmlTemplate];
    }

    /**
     * Generate a pdf invoice file
     *
     * @param $arrInvoiceInfo
     * @param $strHtml
     * @param bool $booCopyToCorrespondence
     * @return array
     */
    public function createInvoicePdf($arrInvoiceInfo, $strHtml, $booCopyToCorrespondence)
    {
        $fileId   = '';
        $fileSize = '';
        $strError = '';

        try {
            $fileName = trim('Invoice ' . $arrInvoiceInfo['invoice_num']) . '.pdf';

            // Save in the temp pdf file
            $tmpPdfFile = tempnam($this->_config['directory']['tmp'], 'invoice');

            $this->_pdf->htmlToPdf(
                $strHtml,
                $tmpPdfFile,
                'F',
                array(
                    'header_title' => null,

                    // Disabled header string
                    //'header_string' => 'Printed on ' . $this->_settings->formatDate(date('Y-m-d')),
                    //'setHeaderFont' => array('helvetica', '', 8)
                ),
                true
            );

            if (!file_exists($tmpPdfFile)) {
                $strError = $this->_tr->translate('Internal error.');
            } else {
                $fileId   = $this->_encryption->encode($tmpPdfFile . '#' . $arrInvoiceInfo['member_id']);
                $fileSize = Settings::formatSize(filesize($tmpPdfFile) / 1024);

                if ($booCopyToCorrespondence) {
                    $companyId            = $this->_auth->getCurrentUserCompanyId();
                    $booLocal             = $this->_company->isCompanyStorageLocationLocal($companyId);
                    $correspondenceFolder = $this->_files->getClientCorrespondenceFTPFolder($companyId, $arrInvoiceInfo['member_id'], $booLocal);
                    $pdfPath              = $this->_files->generateFileName($correspondenceFolder . '/' . $fileName, $booLocal);

                    if ($booLocal) {
                        $booSuccess = rename($tmpPdfFile, $pdfPath);
                    } else {
                        $booSuccess = $this->_files->getCloud()->uploadFile($tmpPdfFile, $pdfPath);
                    }

                    if ($booSuccess) {
                        // Required info to attach this file in the email dialog
                        $fileId = $this->_encryption->encode($pdfPath . '#' . $arrInvoiceInfo['member_id']);
                    } else {
                        $strError = $this->_tr->translate('Internal error [pdf file moving].');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'error'     => $strError,
            'file_id'   => $fileId, // File path is in a such format: path/to/file#client_id
            'file_size' => $fileSize,
            'filename'  => $fileName
        );
    }

    /**
     * Get invoice pdf file
     *
     * @param int $memberId
     * @param int $invoiceId
     * @param string $invoicePath if not empty - the invoice was already generated in the temp directory
     * @return FileInfo|string error string on error
     */
    public function getInvoicePdf($memberId, $invoiceId, $invoicePath)
    {
        try {
            if (!$this->_parent->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->hasAccessToInvoice($invoiceId)) {
                $strError = $this->_tr->translate('Incorrect params.');
            }

            if (empty($strError) && !empty($invoicePath)) {
                $invoicePath = $this->_encryption->decode($invoicePath);

                $attachMemberId = 0;
                // File path is in a such format: path/to/file#client_id
                if (preg_match('/(.*)#(\d+)/', $invoicePath, $regs)) {
                    $invoicePath    = $regs[1];
                    $attachMemberId = $regs[2];
                }

                if (empty($attachMemberId) || $attachMemberId != $memberId || !file_exists($invoicePath)) {
                    $strError = $this->_tr->translate('Incorrect path to the invoice.');
                }
            }

            $arrInvoiceInfo = [];
            if (empty($strError)) {
                $arrInvoiceInfo = $this->getInvoiceInfo($invoiceId);

                if (empty($arrInvoiceInfo) || $arrInvoiceInfo['member_id'] != $memberId) {
                    $strError = $this->_tr->translate('Incorrect params.');
                }
            }

            if (empty($strError)) {
                if (empty($invoicePath)) {
                    $booLocal    = $this->_auth->isCurrentUserCompanyStorageLocal();
                    $companyId   = $this->_auth->getCurrentUserCompanyId();
                    $invoicePath = $this->_files->getClientInvoiceDocumentsFolder($memberId, $companyId, $booLocal) . '/' . $invoiceId . '.pdf';
                    $booExists   = $booLocal ? file_exists($invoicePath) : $this->_files->getCloud()->checkObjectExists($invoicePath);
                    if (!$booExists) {
                        $strError = $this->_tr->translate('File does not exist.');
                    }
                } else {
                    // This is a temp file
                    $booLocal = true;
                }

                if (empty($strError)) {
                    $fileName = trim('Invoice ' . $arrInvoiceInfo['invoice_num']) . '.pdf';
                    return new FileInfo($fileName, $invoicePath, $booLocal);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Create Transactions in DB
     *
     * @param array $arrPSRecords of Payment Schedule records
     * @return bool true on success
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function insertFinancialTransactions($arrPSRecords)
    {
        try {
            if (!empty($arrPSRecords)) {
                // Load GST/HST info
                $arrTaxes = $this->_gstHst->getProvincesTaxes();

                $arrIds = array();
                foreach ($arrPSRecords as $ps) {
                    // Use current GST/HST value
                    if (array_key_exists($ps['gst_province_id'], $arrTaxes)) {
                        $gst = $arrTaxes[$ps['gst_province_id']];
                    } else {
                        $gst = $ps['gst'];
                    }

                    // Insert new payment
                    $arrInsert = array(
                        'payment_schedule_id' => $ps['payment_schedule_id'],
                        'member_id'           => $ps['member_id'],
                        'company_ta_id'       => $ps['company_ta_id'],
                        'withdrawal'          => $ps['amount'],
                        'deposit'             => 0,
                        'description'         => $ps['description'],
                        'notes'               => $ps['notes'],
                        'date_of_event'       => $ps['payment_date_of_event'],
                        'gst'                 => $gst,
                        'gst_province_id'     => $ps['gst_province_id'],
                        'gst_tax_label'       => $ps['gst_tax_label']
                    );

                    $paymentId = $this->_db2->insert('u_payment', $arrInsert, 0);

                    if (isset($ps['template_id']) && !empty($ps['template_id'])) {
                        $invoiceNumber  = $this->getMaxInvoiceNumber($ps['company_ta_id']);
                        $arrPaymentInfo = $this->getPaymentInfo($paymentId);
                        $fee            = $arrPaymentInfo['withdrawal'];
                        $tax            = $arrPaymentInfo['due_gst'];

                        $arrInvoiceInfo = array(
                            'member_id'         => $ps['member_id'],
                            'transfer_to_ta_id' => $ps['company_ta_id'],
                            'invoice_num'       => $invoiceNumber,
                            'amount'            => $ps['amount'],
                            'fee'               => $fee,
                            'tax'               => $tax,
                            'description'       => $ps['description'],
                            'arrPayments'       => array($paymentId),
                            'date'              => date('Y-m-d H:i:s')
                        );

                        $invoiceId = $this->saveInvoice($arrInvoiceInfo);

                        // Don't try to generate the template if invoice wasn't created
                        if (!empty($invoiceId)) {
                            $filename                   = $invoiceId . '.docx';
                            $companyId                  = $this->_company->getMemberCompanyId($arrInvoiceInfo['member_id']);
                            $invoiceDocumentsFolderPath = $this->_files->getClientInvoiceDocumentsFolder($arrInvoiceInfo['member_id'], $companyId, $this->_company->isCompanyStorageLocationLocal($companyId));

                            if ($invoiceDocumentsFolderPath == 'root') {
                                $memberId                   = $this->_auth->getCurrentUserId();
                                $memberCompanyId            = $this->_auth->getCurrentUserCompanyId();
                                $memberType                 = $this->_parent->getMemberTypeByMemberId($memberId);
                                $booIsClient                = $this->_parent->isMemberClient($memberType);
                                $booIsLocal                 = $this->_company->isCompanyStorageLocationLocal($memberCompanyId);
                                $invoiceDocumentsFolderPath = $this->_files->getMemberFolder($memberCompanyId, $memberId, $booIsClient, $booIsLocal);
                            }

                            /** @var Templates $templates */
                            $templates = $this->_serviceContainer->get(Templates::class);
                            $templates->createLetterFromLetterTemplate($ps['template_id'], $arrInvoiceInfo['member_id'], $invoiceDocumentsFolderPath, $filename, $arrInvoiceInfo['arrPayments']);
                        }
                    }

                    //update outstanding balance
                    $this->updateOutstandingBalance($ps['member_id'], $ps['company_ta_id']);

                    $arrIds[] = $ps['payment_schedule_id'];
                }

                ## 3. MARK PAYMENT AS COMPLETE
                $this->_db2->update('u_payment_schedule', ['status' => 1], ['payment_schedule_id' => $arrIds]);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booSuccess = false;
        }

        return $booSuccess;
    }

    /**
     * Get information about company agent payment
     *
     * @param $clientId
     * @return array
     */
    public function getCompanyAgentSystemAccessPayment($clientId)
    {
        $select = (new Select())
            ->from(array('p' => 'u_payment'))
            ->where(
                [
                    (new Where())
                        ->equalTo('p.member_id', (int)$clientId)
                        ->greaterThanOrEqualTo('p.deposit', (double)$this->_settings->variable_get('price_dm_system_access_fee'))
                        ->isNotNull('p.transaction_id')
                        ->equalTo('p.company_agent', 'Y')
                ]
            );

        return $this->_db2->fetchRow($select);
    }

    /**
     * Check if passed CC info is valid
     *
     * @param int $companyId
     * @param int $clientId
     * @param string $customerName
     * @param string $creditCardNum
     * @param string $creditCardExpDate
     * @param string $creditCardCVN
     * @param bool $booCheckCompanyPTProfile
     * @return string error message, empty if all is valid
     */
    public function checkCCInfo($companyId, $clientId, $customerName, $creditCardNum, $creditCardExpDate, $creditCardCVN, $booCheckCompanyPTProfile = true)
    {
        $strMessage = '';

        // Check company id
        if (empty($strMessage) && (!is_numeric($companyId) || empty($companyId))) {
            $strMessage = $this->_tr->translate('Incorrectly selected company');
        }

        if (empty($strMessage) && !$this->_parent->hasCurrentMemberAccessToCompany($companyId)) {
            $strMessage = $this->_tr->translate('Insufficient access rights');
        }

        if (empty($strMessage) && !empty($clientId) && !$this->_parent->hasCurrentMemberAccessToMember($clientId)) {
            $strMessage = $this->_tr->translate('Insufficient access rights');
        }

        // Check customer name
        if (empty($strMessage) && empty($customerName)) {
            $strMessage = $this->_tr->translate('Incorrect name');
        }

        // Check Credit Card Number
        if (empty($strMessage) && !is_numeric($creditCardNum)) {
            $strMessage = $this->_tr->translate('Incorrect credit card number');
        }

        // Check expiration date
        $booCorrectExpiration = false;
        if (empty($strMessage) && strpos($creditCardExpDate, '/') !== false) {
            list($month, $year) = explode('/', $creditCardExpDate);
            if (is_numeric($month) && is_numeric($year) && ($month >= 1 && $month <= 12) && ($year >= 1 && $year <= 32767) && checkdate($month, 1, $year) && mktime(0, 0, 0, $month, 1, $year) > time()) {
                // Correct date and it is in the future
                $booCorrectExpiration = true;
            }
        }

        if (empty($strMessage) && !$booCorrectExpiration) {
            $strMessage = $this->_tr->translate('Incorrect credit card expiration date');
        }

        if (empty($strMessage) && !is_numeric($creditCardCVN) && $this->_config['site_version']['version'] == 'australia') {
            $strMessage = $this->_tr->translate('Please enter correct CVN.');
        }

        if (empty($strMessage) && $booCheckCompanyPTProfile && !$this->_config['payment']['enabled']) {
            // Payment is not enabled
            $strMessage = $this->_tr->translate('Communication with PT is turned off. Please turn it on in config file and try again.');
        }

        return $strMessage;
    }

    /**
     * Get a primary T/A id for a specific client
     *
     * @param int $clientId
     * @return string
     */
    public function getClientPrimaryCompanyTaId($clientId)
    {
        $select = (new Select())
            ->from('members_ta')
            ->columns(['company_ta_id'])
            ->where(
                [
                    'member_id' => (int)$clientId,
                    'order'     => 0
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    /**
     * Get a secondary T/A id for a specific client
     *
     * @param int $clientId
     * @return string
     */
    public function getClientSecondaryCompanyTaId($clientId)
    {
        $select = (new Select())
            ->from('members_ta')
            ->columns(['company_ta_id'])
            ->where(
                [
                    'member_id' => $clientId,
                    'order'     => 1
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    /**
     * Update specific payment info
     *
     * @param int|array $paymentId
     * @param array $arrPaymentInfo
     * @return bool true on success
     */
    public function updatePaymentInfo($paymentId, $arrPaymentInfo)
    {
        $booSuccess = false;

        try {
            $paymentId = (array)$paymentId;
            if (!empty($paymentId) && !empty($arrPaymentInfo)) {
                $this->_db2->update('u_payment', $arrPaymentInfo, ['payment_id' => $paymentId]);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete specific payment
     *
     * @param int $paymentId
     * @return bool true on success
     */
    public function deletePayment($paymentId)
    {
        $booSuccess = false;

        try {
            if (!empty($paymentId) && is_numeric($paymentId)) {
                $this->_db2->delete('u_payment', ['payment_id' => $paymentId]);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if all requirements were met and case can be submitted to the gov
     *
     * @param int $companyId
     * @param int $caseId
     * @param int $applicantId
     * @param null $caseTemplateId
     * @return array of (string error, empty on success; option selected in the "investment type" field)
     */
    public function checkAllRequirementsWereMetForSubmitting($companyId, $caseId, $applicantId = 0, $caseTemplateId = null)
    {
        $strError                       = '';
        $investmentTypeFieldValueOption = '';

        try {
            if (empty($applicantId)) {
                // Get the parent of the case
                $arrParents  = $this->_parent->getParentsForAssignedApplicants(array($caseId));
                $applicantId = $arrParents[$caseId]['parent_member_id'] ?? 0;
            }

            $arrEmptyFieldNames = array();

            if (!empty($applicantId)) {
                $memberTypeId = $this->_parent->getMemberTypeByMemberId($applicantId);

                $arrApplicantInfo   = $this->_parent->getClientInfoOnly($applicantId);
                $arrApplicantFields = $this->_parent->getApplicantFields()->getAllGroupsAndFields($companyId, $memberTypeId, $arrApplicantInfo['applicant_type_id'], true);

                foreach ($arrApplicantFields['blocks'] as $arrBlockInfo) {
                    foreach ($arrBlockInfo['block_groups'] as $arrGroupInfo) {
                        foreach ($arrGroupInfo['group_fields'] as $arrFieldInfo) {
                            if ($arrFieldInfo['required_for_submission'] == 'Y') {
                                if ($arrFieldInfo['contact_block'] == 'Y') {
                                    $clientId = $this->_parent->getAssignedContact($applicantId, $arrFieldInfo['group_id']);
                                } else {
                                    $clientId = $applicantId;
                                }

                                $fieldValue = $this->_parent->getApplicantFields()->getFieldDataValue($clientId, $arrFieldInfo['applicant_field_id']);
                                if (empty($fieldValue)) {
                                    $arrEmptyFieldNames[] = $this->_parent->getApplicantFields()->getFieldName($arrFieldInfo['applicant_field_id']);
                                }
                            }
                        }
                    }
                }
            }

            if (empty($caseTemplateId)) {
                $arrClientInfo  = $this->_parent->getClientInfo($caseId);
                $caseTemplateId = $arrClientInfo['client_type_id'];
            }

            $arrRequiredForSubmissionCaseFieldIds = $this->_parent->getFields()->getCompanyRequiredForSubmissionFields($companyId, $caseTemplateId);

            if (is_array($arrRequiredForSubmissionCaseFieldIds) && !empty($arrRequiredForSubmissionCaseFieldIds)) {
                foreach ($arrRequiredForSubmissionCaseFieldIds as $fieldId) {
                    $arrFieldInfo = $this->_parent->getFields()->getFieldInfoById($fieldId);

                    if ($arrFieldInfo['company_field_id'] == 'real_estate_project' && $this->_config['site_version']['validation']['check_investment_type']) {
                        $arrInvestmentTypeId      = $this->_parent->getFields()->getCompanyFieldIdByUniqueFieldId('cbiu_investment_type', $companyId);
                        $arrInvestmentTypeOptions = $this->_parent->getFields()->getFieldsOptions(array($arrInvestmentTypeId));

                        foreach ($arrInvestmentTypeOptions as $arrInvestmentTypeOptionInfo) {
                            if ($arrInvestmentTypeOptionInfo['value'] == 'Government Fund') {
                                $governmentFundOptionId = $arrInvestmentTypeOptionInfo['form_default_id'];
                                $investmentTypeValue    = $this->_parent->getFields()->getFieldDataValue($arrInvestmentTypeId, $caseId);
                                if ($investmentTypeValue == $governmentFundOptionId) {
                                    continue 2;
                                }
                            }
                        }
                    }

                    $fieldType = $this->_parent->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']);

                    if ($fieldType == 'authorized_agents') {
                        continue;
                    }

                    $fieldValue = $this->_parent->getFields()->getFieldDataValue($fieldId, $caseId);
                    if (empty($fieldValue)) {
                        $arrEmptyFieldNames[] = $arrFieldInfo['label'];
                    }
                }
            }

            if (!empty($arrEmptyFieldNames)) {
                $strEmptyFieldNames = '';
                foreach ($arrEmptyFieldNames as $fieldName) {
                    $strEmptyFieldNames .= '<br>' . $fieldName;
                }
                $strError .= $this->_tr->translate('The following fields are required for submission. Please fill them out and try again.' . $strEmptyFieldNames);
            }

            $investmentTypeFieldId = '';
            if (empty($strError)) {
                $investmentTypeFieldId = $this->_parent->getFields()->getCompanyFieldIdByUniqueFieldId('cbiu_investment_type', $companyId);

                if (empty($investmentTypeFieldId)) {
                    $strError = $this->_tr->translate('There is no field "Investment Type" defined in the "Case Details" tab.');
                }
            }

            $investmentTypeFieldValue = '';
            if (empty($strError)) {
                $investmentTypeFieldValue = $this->_parent->getFields()->getFieldDataValue($investmentTypeFieldId, $caseId);

                if (empty($investmentTypeFieldValue)) {
                    $strError = $this->_tr->translate('Please fill "Investment Type" field in the "Case Details" tab.');
                }
            }

            $arrInvestmentTypeFieldValueOptions = array('Government Fund', 'Real Estate');

            if (empty($strError)) {
                $investmentTypeFieldValueDetails = $this->_parent->getFields()->getDefaultFieldOptionDetails($investmentTypeFieldValue);

                if (!isset($investmentTypeFieldValueDetails['value']) ||
                    !in_array($investmentTypeFieldValueDetails['value'], $arrInvestmentTypeFieldValueOptions) ||
                    empty($investmentTypeFieldValueDetails['value'])) {
                    $strError = $this->_tr->translate('Incorrectly selected value for the "Investment Type" field in the "Case Details" tab.');
                } else {
                    $investmentTypeFieldValueOption = $investmentTypeFieldValueDetails['value'];
                }
            }

            if (empty($strError)) {
                $arrIncorrectVariables = $this->_settings->getSystemVariables()->getIncorrectPaymentVariables();
                if (count($arrIncorrectVariables)) {
                    $strError = $this->_tr->translate('Such variables must be set:<br>' . implode('<br>', $arrIncorrectVariables));
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $investmentTypeFieldValueOption);
    }

    /**
     * Regenerate Company Agent Payments on "Investment Type" field update or change Dependents list
     *
     * @param int $companyId
     * @param int $caseId
     * @param null $caseTemplateId
     * @return void
     */
    public function regenerateCompanyAgentPayments($companyId, $caseId, $caseTemplateId = null)
    {
        if (!$this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
            return;
        }

        $companyTAId = $this->getClientPrimaryCompanyTaId($caseId);

        if (empty($companyTAId)) {
            return;
        }

        $authorId = $this->_auth->getCurrentUserId();
        if (!$authorId) {
            $authorId = $this->_company->getCompanyAdminId($companyId);
        }

        $this->generateCompanyAgentPayments($companyId, $caseId, $companyTAId, $authorId, true, $caseTemplateId);
    }


    /**
     * Load/calculate agent payments (fees) in relation to the site settings (DM or Antigua) and dependents settings
     *
     * @param array $arrAssignedFamilyMembers
     * @param string $investmentTypeFieldValueOption "Government Fund" or "Real Estate"
     * @return array
     */
    private function getPreparedCompanyAgentPayments($arrAssignedFamilyMembers, $investmentTypeFieldValueOption)
    {
        $arrFeesDue = array();

        switch ($this->_config['site_version']['submission_fees_type']) {
            case 'antigua':
                // Calculate family members count including Main Applicant
                $countAssignedFamilyMembers = count($arrAssignedFamilyMembers) + 1;

                /**
                 * The order of the fees must be:
                 * 1. Processing fee
                 * 2. Contribution
                 * 3. Due Diligence fee
                 * ....list all the DD fees for all family members here line by line
                 * 4. Passport fees
                 * ... list all the Passport fees for all family members here line by line
                 * 5. System Access Fee
                 */
                switch ($investmentTypeFieldValueOption) {
                    case 'Government Fund':
                        if ($countAssignedFamilyMembers == 1) {
                            // Processing fee (Main applicant)
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_processing_fee') . ' Main Applicant',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_national_development_fund_fee_main_clients')
                            );

                            // Contribution (Main applicant)
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_development_fund_contribution') . ' Main Applicant',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_national_development_fund_contribution_main_clients')
                            );
                        } elseif ($countAssignedFamilyMembers <= 4) {
                            // Processing fee Family of up to 4
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_processing_fee') . ' Family of up to 4',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_national_development_fund_fee_up_to_4_persons')
                            );

                            // Contribution Family of up to 4
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_development_fund_contribution') . ' Family of up to 4',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_national_development_fund_contribution_up_to_4_persons')
                            );
                        } elseif ($countAssignedFamilyMembers >= 5) {
                            // Processing fee for 4 and for Each Additional Dependent
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_processing_fee') . ' Family of 5+',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_national_development_fund_fee_up_to_4_persons') + (float)$this->_settings->variable_get(
                                        'price_antigua_national_development_fund_fee_additional_dependent'
                                    ) * ($countAssignedFamilyMembers - 4)
                            );

                            // Contribution Family of 5+
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_development_fund_contribution') . '  Family of 5+',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_national_development_fund_contribution_more_than_4_persons')
                            );
                        }
                        break;

                    case 'Real Estate':
                    default:
                        if ($countAssignedFamilyMembers == 1) {
                            // Processing fee (Main applicant)
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_processing_fee') . ' Main Applicant',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_real_estate_fee_main_clients')
                            );

                            // Contribution (Main applicant)
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_real_estate_contribution') . ' Main Applicant',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_real_estate_contribution_main_clients')
                            );
                        } elseif ($countAssignedFamilyMembers <= 4) {
                            // Processing fee Family of up to 4
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_processing_fee') . ' Family of up to 4',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_real_estate_fee_up_to_4_persons')
                            );

                            // Contribution Family of up to 4
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_real_estate_contribution') . ' Family of up to 4',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_real_estate_contribution_up_to_4_persons')
                            );
                        } elseif ($countAssignedFamilyMembers >= 5) {
                            // Processing fee Each Additional Dependent
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_processing_fee') . ' Family of 5+',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_real_estate_fee_up_to_4_persons') + (float)$this->_settings->variable_get(
                                        'price_antigua_real_estate_fee_additional_dependent'
                                    ) * ($countAssignedFamilyMembers - 4)
                            );

                            // Contribution Family of 5+
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_real_estate_contribution') . '  Family of 5+',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_real_estate_contribution_more_than_4_persons')
                            );
                        }
                        break;
                }

                $arrFeesDue[] = array(
                    'description' => $this->_settings->variable_get('description_antigua_due_diligence_fee') . ' Main Applicant',
                    'amount'      => (float)$this->_settings->variable_get('price_antigua_due_diligence_fee_main_clients')
                );

                foreach ($arrAssignedFamilyMembers as $fm) {
                    switch ($fm['relationship']) {
                        case 'spouse':
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_due_diligence_fee') . ' Spouse',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_due_diligence_fee_spouse')
                            );
                            break;

                        case 'parent':
                            $arrFeesDue[] = array(
                                'description' => $this->_settings->variable_get('description_antigua_due_diligence_fee') . ' Parent',
                                'amount'      => (float)$this->_settings->variable_get('price_antigua_due_diligence_fee_dependent_parent_over_65')
                            );
                            break;

                        case 'child':
                        case 'sibling':
                        case 'other':
                        default:
                            if (strtotime($fm['DOB']) <= strtotime('-18 years')) {
                                // 18 and over
                                $arrFeesDue[] = array(
                                    'description' => $this->_settings->variable_get('description_antigua_due_diligence_fee') . ' ' . ucfirst($fm['relationship']),
                                    'amount'      => (float)$this->_settings->variable_get('price_antigua_due_diligence_fee_dependent_18_and_over')
                                );
                            } elseif (strtotime($fm['DOB']) >= strtotime('-12 years')) {
                                // from 12 to 17
                                $arrFeesDue[] = array(
                                    'description' => $this->_settings->variable_get('description_antigua_due_diligence_fee') . ' ' . ucfirst($fm['relationship']),
                                    'amount'      => (float)$this->_settings->variable_get('price_antigua_due_diligence_fee_dependent_12_to_17')
                                );
                            }
                            break;
                    }
                }

                $arrFeesDue[] = array(
                    'description' => $this->_settings->variable_get('description_antigua_passport_fee') . ' Main Applicant',
                    'amount'      => (float)$this->_settings->variable_get('price_antigua_passport_fee')
                );

                foreach ($arrAssignedFamilyMembers as $fm) {
                    if (empty($fm['DOB'])) {
                        continue;
                    }

                    $arrFeesDue[] = array(
                        'description' => $this->_settings->variable_get('description_antigua_passport_fee') . ' ' . ucfirst($fm['relationship']),
                        'amount'      => (float)$this->_settings->variable_get('price_antigua_passport_fee')
                    );
                }


                $arrFeesDue[] = array(
                    'description' => $this->_settings->variable_get('description_antigua_system_access_fee'),
                    'amount'      => (float)$this->_settings->variable_get('price_antigua_system_access_fee')
                );
                break;

            case 'dominica':
            default:
                $arrFeesDue[] = array(
                    'description' => $this->_settings->variable_get('description_dm_system_access_fee'),
                    'amount'      => (float)$this->_settings->variable_get('price_dm_system_access_fee')
                );

                // Don't show/use the "Processing Fee" if it was set empty in settings
                $processingFeeAmount = (float)$this->_settings->variable_get('price_dm_processing_fee');
                if (!empty($processingFeeAmount)) {
                    $arrFeesDue[] = array(
                        'description' => $this->_settings->variable_get('description_dm_processing_fee'),
                        'amount'      => $processingFeeAmount
                    );
                }

                $arrFeesDue[] = array(
                    'description' => $this->_settings->variable_get('description_dm_due_diligence_fee') . ' Main Applicant',
                    'amount'      => (float)$this->_settings->variable_get('price_dm_due_diligence_fee_main_clients')
                );

                $countAssignedFamilyMembers    = 0;
                $realEstateAdditionalFee       = $governmentFundAdditionalFee = 0.00;
                $booHasSpouse                  = $booHasChildren = $booHasOtherDependents = false;
                $countFamilyMembers            = 0;
                $countChildrenAgedUnder18Years = 0;

                if (is_array($arrAssignedFamilyMembers) && count($arrAssignedFamilyMembers)) {
                    $dueDiligenceChildrenFee = $dueDiligenceOtherDependentsFee = 0.00;

                    foreach ($arrAssignedFamilyMembers as $fm) {
                        if (empty($fm['DOB'])) {
                            continue;
                        }
                        $countAssignedFamilyMembers++;

                        switch ($fm['relationship']) {
                            case 'spouse':
                                $booHasSpouse = true;
                                $countFamilyMembers++;

                                $arrFeesDue[] = array(
                                    'description' => $this->_settings->variable_get('description_dm_due_diligence_fee') . ' Spouse',
                                    'amount'      => (float)$this->_settings->variable_get('price_dm_due_diligence_fee_spouse')
                                );
                                break;

                            case 'child':
                                $booHasChildren = true;
                                if (strtotime($fm['DOB']) <= strtotime('-16 years')) {
                                    $dueDiligenceChildrenFee += (float)$this->_settings->variable_get('price_dm_due_diligence_fee_dependent');
                                }

                                if (strtotime($fm['DOB']) > strtotime('-18 years')) {
                                    $countFamilyMembers++;
                                    $countChildrenAgedUnder18Years++;

                                    if ($countFamilyMembers > 5) {
                                        $realEstateAdditionalFee += (float)$this->_settings->variable_get('price_dm_real_estate_fee_additional_dependent_under_18_years');
                                    }
                                } else {
                                    $realEstateAdditionalFee     += (float)$this->_settings->variable_get('price_dm_real_estate_fee_additional_dependent_over_18_years');
                                    $governmentFundAdditionalFee += (float)$this->_settings->variable_get('price_dm_government_fund_fee_additional_dependent');
                                }
                                break;

                            case 'parent':
                            case 'sibling':
                            case 'other':
                            default:
                                $booHasOtherDependents = true;
                                if (strtotime($fm['DOB']) <= strtotime('-16 years')) {
                                    $dueDiligenceOtherDependentsFee += (float)$this->_settings->variable_get('price_dm_due_diligence_fee_dependent');
                                }
                                if (strtotime($fm['DOB']) <= strtotime('-18 years')) {
                                    $realEstateAdditionalFee += (float)$this->_settings->variable_get('price_dm_real_estate_fee_additional_dependent_over_18_years');
                                } else {
                                    $realEstateAdditionalFee += (float)$this->_settings->variable_get('price_dm_real_estate_fee_additional_dependent_under_18_years');
                                }

                                $governmentFundAdditionalFee += (float)$this->_settings->variable_get('price_dm_government_fund_fee_additional_dependent');
                                break;
                        }
                    }

                    if ($booHasChildren && $dueDiligenceChildrenFee > 0.00) {
                        $arrFeesDue[] = array(
                            'description' => $this->_settings->variable_get('description_dm_due_diligence_fee') . ' Child 16+',
                            'amount'      => $dueDiligenceChildrenFee
                        );
                    }

                    if ($booHasOtherDependents && $dueDiligenceOtherDependentsFee > 0.00) {
                        $arrFeesDue[] = array(
                            'description' => $this->_settings->variable_get('description_dm_due_diligence_fee') . ' Other Dependents',
                            'amount'      => $dueDiligenceOtherDependentsFee
                        );
                    }
                }

                if ($investmentTypeFieldValueOption == 'Government Fund') {
                    $governmentFundFee = (float)$this->_settings->variable_get('price_dm_government_fund_fee_single_clients');

                    if ($countAssignedFamilyMembers > 0) {
                        if ($booHasSpouse) {
                            if ($countChildrenAgedUnder18Years) {
                                if ($countChildrenAgedUnder18Years <= 2) {
                                    $governmentFundFee = (float)$this->_settings->variable_get('price_dm_government_fund_fee_up_to_4_persons');
                                } else {
                                    $governmentFundFee = (float)$this->_settings->variable_get('price_dm_government_fund_fee_up_to_5_persons');
                                }
                            } else {
                                $governmentFundFee = (float)$this->_settings->variable_get('price_dm_government_fund_fee_main_and_spouse');
                            }
                        } elseif ($countChildrenAgedUnder18Years) {
                            if ($countChildrenAgedUnder18Years <= 3) {
                                $governmentFundFee = (float)$this->_settings->variable_get('price_dm_government_fund_fee_up_to_4_persons');
                            } else {
                                $governmentFundFee = (float)$this->_settings->variable_get('price_dm_government_fund_fee_up_to_5_persons');
                            }
                        }

                        if ($governmentFundAdditionalFee) {
                            $governmentFundFee += $governmentFundAdditionalFee;
                        }
                    }

                    $arrFeesDue[] = array(
                        'description' => $this->_settings->variable_get('description_dm_government_fund_fee'),
                        'amount' => $governmentFundFee
                    );
                } elseif ($investmentTypeFieldValueOption == 'Real Estate') {
                    $realEstateFee = (float)$this->_settings->variable_get('price_dm_real_estate_fee_single_clients');

                    if ($countAssignedFamilyMembers > 0) {
                        if ($countFamilyMembers > 0) {
                            if ($countFamilyMembers <= 3) {
                                $realEstateFee = (float)$this->_settings->variable_get('price_dm_real_estate_fee_up_to_4_persons');
                            } else {
                                $realEstateFee = (float)$this->_settings->variable_get('price_dm_real_estate_fee_up_to_6_persons');
                            }
                        }
                        if ($realEstateAdditionalFee) {
                            $realEstateFee += $realEstateAdditionalFee;
                        }
                    }

                    $arrFeesDue[] = array(
                        'description' => $this->_settings->variable_get('description_dm_real_estate_fee'),
                        'amount' => $realEstateFee
                    );
                }

                // Certificate of Naturalisation, count main applicant too
                $certificateOfNaturalizationFee = (float)$this->_settings->variable_get('price_dm_certificate_of_naturalization_fee');

                $arrFeesDue[] = array(
                    'description' => $this->_settings->variable_get('description_dm_certificate_of_naturalization_fee'),
                    'amount'      => $certificateOfNaturalizationFee * ($countAssignedFamilyMembers + 1)
                );
                break;
        }

        return $arrFeesDue;
    }


    /**
     * Create Company Agent Fees Due
     *
     * @param int $companyId
     * @param int $caseId
     * @param int $companyTAId
     * @param int $authorId
     * @param bool $booDelete
     * @param null $caseTemplateId
     * @return array of (string error, empty on success; array of created payments ids)
     */
    public function generateCompanyAgentPayments($companyId, $caseId, $companyTAId, $authorId, $booDelete = false, $caseTemplateId = null)
    {
        $arrPaymentIds = array();

        try {
            list($strError, $investmentTypeFieldValueOption) = $this->checkAllRequirementsWereMetForSubmitting($companyId, $caseId, 0, $caseTemplateId);

            if (empty($strError) && $booDelete) {
                $this->_db2->delete(
                    'u_payment',
                    [
                        'company_ta_id' => (int)$companyTAId,
                        'member_id'     => (int)$caseId,
                        (new Where())->greaterThan('withdrawal', 0.00),
                        'company_agent' => 'Y'
                    ]
                );
            }

            if (empty($strError)) {
                // Get filled family members
                $arrAssignedFamilyMembers = $this->_parent->getFields()->getDependents(array($caseId), false);
                $arrAssignedFamilyMembers = is_array($arrAssignedFamilyMembers) ? $arrAssignedFamilyMembers : array();

                // Remove records that don't have DOB set
                foreach ($arrAssignedFamilyMembers as $key => $arrDependent) {
                    if (!isset($arrDependent['DOB']) || Settings::isDateEmpty($arrDependent['DOB'])) {
                        unset($arrAssignedFamilyMembers[$key]);
                    }
                }

                // Calculate and ge list of the required payments
                $arrFeesDue = $this->getPreparedCompanyAgentPayments($arrAssignedFamilyMembers, $investmentTypeFieldValueOption);

                if (empty($arrFeesDue)) {
                    $strError = $this->_tr->translate('Nothing to add.');
                }

                if (empty($strError)) {
                    foreach ($arrFeesDue as $fee) {
                        $paymentId = $this->addFee(
                            $companyTAId,
                            $caseId,
                            $fee['amount'],
                            $fee['description'],
                            'add-fee-due',
                            date('c'),
                            '',
                            0.00,
                            0,
                            '',
                            null,
                            $authorId,
                            true
                        );

                        if (!empty($paymentId)) {
                            $arrPaymentIds[] = $paymentId;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $arrPaymentIds);
    }


    protected function printTCPDF($logo_url, $logo_width, $memberId, $htmlTable, $destination = 'I')
    {
        global $l;
        $l = array();
        $logo_width = empty($logo_width) ? 0 : $logo_width;

        // Client + case name
        $clientAndCaseName = $this->_parent->generateClientAndCaseName($memberId);

        $title = $this->_tr->translate('Accounting Summary');

        // PAGE META DESCRIPTORS --------------------------------------

        $l['a_meta_charset']  = 'UTF-8';
        $l['a_meta_dir']      = 'ltr';
        $l['a_meta_language'] = 'en';

        // TRANSLATIONS --------------------------------------
        $l['w_page'] = 'page';

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);


        // set document information
        $pdf->setCreator('');
        $pdf->setAuthor($clientAndCaseName);
        $pdf->setTitle($title);
        $pdf->setSubject($title);
        $pdf->setKeywords('PDF, ' . $title);
        $pdf_header_title  = $title;
        $pdf_header_string = $clientAndCaseName;

        // Note that $logo_url is a temp file, but TCPDF tries to identify the type of the file by extension
        // So, either we'll provide the "correct" file or don't provide it at all
        // Maybe TCPDF sources will be fixed in the future
        $logo_url   = '';
        $logo_width = 0;

        // set default header data
        $pdf->setHeaderData($logo_url, $logo_width, $pdf_header_title, $pdf_header_string);

        // set header and footer fonts
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        $pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        //set margins
        $pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->setHeaderMargin(5);
        $pdf->setFooterMargin(5);

        //set auto page breaks
        $pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        //set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        //set some language-dependent strings
        $pdf->setLanguageArray($l);

        // ---------------------------------------------------------

        // set font
        $pdf->setFont('dejavusans', '', 10);

        // add a page
        $pdf->AddPage();


        // output the HTML content
        $pdf->writeHTML($htmlTable, true, 0, true, 0);

        // reset pointer to the last page
        $pdf->lastPage();

        $filename = $this->_files->convertToFilename('accounting_' . $clientAndCaseName . ' (' . date('Y-m-d H-i-s') . ').pdf');
        $pdf->Output($destination == 'I' ? $filename : getcwd() . '/' . $this->_config['directory']['pdf_temp'] . DIRECTORY_SEPARATOR . $filename, $destination);

        return $filename;
    }

    /**
     * Generate pdf file from all sections (client's Accounting tab)
     *
     * @param int $memberId
     * @param string $destination
     * @return array|string
     */
    public function printClientAccounting($memberId, $destination)
    {
        $arrResult = '';

        try {
            // Load T/A list for current member
            $arrMemberTA  = $this->getMemberTA($memberId);
            $arrCompanyTA = $this->getMemberCompanyTA($memberId);
            $taLabel      = $this->_company->getCurrentCompanyDefaultLabel('trust_account');

            $logo_url   = '';
            $logo_width = '';
            if (is_array($arrMemberTA) && !empty($arrMemberTA)) {
                $arrSettings = $this->getAccountingAccessRights();

                $htmlTable = '';

                // Default dimensions
                $date_width        = 80;
                $description_width = 295;
                $money_width_1     = 90;
                $status_width      = 180;

                // Fees & Disbursements Table(s)
                $booShowCurrency = count($arrMemberTA) > 1;
                if ($arrSettings['fees_and_disbursements']['show_table']) {
                    foreach ($arrMemberTA as $memberTAInfo) {
                        $taId     = $memberTAInfo['company_ta_id'];
                        $currency = $this->getCompanyTACurrency($taId);

                        $arrFeesResult = $this->getClientAccountingFeesList($memberId, $taId);
                        $arrFees       = $arrFeesResult['rows'];
                        $total         = $arrFeesResult['total'] + $arrFeesResult['total_gst'];
                        $totalDue      = $arrFeesResult['total_due'];

                        $taCurrency = self::getCurrencyLabel($memberTAInfo['currency']);
                        $htmlTable .= '<h2>' . $this->_tr->translate('Fees &amp; Disbursements') . ' (' . $taCurrency . ')</h2>';
                        $htmlTable .= '<table border="1" cellspacing="0" cellpadding="2">';
                        $htmlTable .= '<tr bgcolor="#cccccc" align="center">' .
                            '<th width="' . $date_width . '">' . $this->_tr->translate('Due Date') . '</th>' .
                            '<th width="' . $description_width . '" align="left" >' . $this->_tr->translate('Description') . '</th>' .
                            '<th width="' . $money_width_1 . '">' . $this->_tr->translate('Amount') . '</th>' .
                            '<th width="' . $status_width . '" align="left">' . $this->_tr->translate('Status') . '</th>' .
                            '</tr>';

                        if (count($arrFees)) {
                            foreach ($arrFees as $arrFeeInfo) {
                                $date = strip_tags($arrFeeInfo['fee_due_date'] ?? '');

                                $description = $arrFeeInfo['fee_description'];
                                if (!empty($arrFeeInfo['fee_gst'])) {
                                    $description .= '<br />' . $arrFeeInfo['fee_description_gst'];
                                }

                                $amount = static::formatPrice($arrFeeInfo['fee_amount'], $currency);
                                if ((double)$arrFeeInfo['fee_gst'] > 0) {
                                    $amount .= '<br />' . static::formatPrice($arrFeeInfo['fee_gst'], $currency);
                                }

                                $strStatus = '';
                                if ($arrFeeInfo['type'] == 'payment') {
                                    if (empty($arrFeeInfo['invoice_id'])) {
                                        $strStatus = '<small style="color: red">' . $this->_tr->translate('DUE') . '</small>';
                                    } else {
                                        if ($arrFeeInfo['invoice_num'] == 'Statement') {
                                            $strStatus = $arrFeeInfo ['invoice_num'];
                                            if ($booShowCurrency) {
                                                $strStatus .= ' (' . $taCurrency . ')';
                                            }
                                        } else {
                                            $strStatus = sprintf(
                                                $this->_tr->translate('Invoiced (#%s)'),
                                                $arrFeeInfo['invoice_num']
                                            );
                                        }

                                        $strStatus = '<small>' . $strStatus . '</small>';
                                    }
                                }

                                if (!empty($arrFeeInfo['fee_notes'])) {
                                    if (!empty($strStatus)) {
                                        $strStatus .= '<br />';
                                    }
                                    $strStatus .= '<small>' . $this->_tr->translate('Notes: ') . $arrFeeInfo['fee_notes'] . '</small>';
                                }

                                $htmlTable .= '<tr>' .
                                    '<td width="' . $date_width . '"><small>' . $date . '</small></td>' .
                                    '<td width="' . $description_width . '"><small>' . $description . '</small></td>' .
                                    '<td width="' . $money_width_1 . '" align="right"><small>' . $amount . '</small></td>' .
                                    '<td width="' . $status_width . '">' . $strStatus . '</td>' .
                                    '</tr>';
                            }
                        } else {
                            $htmlTable .= '<tr><td colspan="4"><small>' . $this->_tr->translate('No entries found.') . '</small></td></tr>';
                        }

                        // Totals
                        $htmlTable .= '<tr>' .
                            '<td colspan="4">' .
                            $this->_tr->translate('Total Due: ') . $this->addThousands(static::formatPrice($totalDue, $currency)) .
                            '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
                            $this->_tr->translate('Total: ') . $this->addThousands(static::formatPrice($total, $currency)) .
                            '</td></tr></table><br>';
                    }
                }


                // Invoices table
                if ($arrSettings['invoices']['show_table']) {
                    $arrClientInvoices = $this->getClientInvoices($memberId, 0, 0, true);

                    $htmlTable .= '<h2> </h2><h2>' . $this->_tr->translate('Invoices') . '</h2>';
                    $htmlTable .= '<table border="1" cellspacing="0" cellpadding="2" >';
                    $htmlTable .= '<tr bgcolor="#cccccc" align="center">' .
                        '<th width="' . $date_width . '">' . $this->_tr->translate('Date') . '</th>' .
                        '<th width="' . $money_width_1 . '" align="left">' . $this->_tr->translate('Invoice #') . '</th>' .
                        '<th width="' . $money_width_1 . '">' . $this->_tr->translate('Amount') . '</th>' .
                        '<th width="' . ($status_width - 10) . '">' . $this->_tr->translate('Status') . '</th>' .
                        '<th width="' . ($status_width + 35) . '" align="left">' . $this->_tr->translate('Notes') . '</th>' .
                        '</tr>';

                    if (count($arrClientInvoices['rows'])) {
                        foreach ($arrClientInvoices['rows'] as $invoiceInfo) {
                            $currencyLabel = $booShowCurrency ? self::getCurrencyLabel($invoiceInfo['invoice_currency'], false) : '';
                            if ($invoiceInfo['invoice_outstanding_amount'] <= 0) {
                                $strStatus = $this->_tr->translate('Paid');
                            } else {
                                $strStatus = $this->_tr->translate('Outstanding') . ' ' . $currencyLabel . self::formatPrice($invoiceInfo['invoice_outstanding_amount'], $invoiceInfo['invoice_currency']);
                            }

                            $invoiceNum = $invoiceInfo['invoice_num'];
                            if ($invoiceInfo['invoice_num'] == 'Statement' && $booShowCurrency) {
                                $invoiceNum .= ' (' . $currencyLabel . ')';
                            }

                            $arrFeesAndPayments = $invoiceInfo['invoice_assigned_fees_and_payments'];

                            $color = empty($arrFeesAndPayments) ? 'black' : 'white';

                            $htmlTable .= '<tr>' .
                                '<td width="' . $date_width . '" style="border-bottom-color:' . $color . '; font-weight: bold"><small>' . strip_tags($this->_settings->formatDate($invoiceInfo['invoice_date'] ?? '')) . '</small></td>' .
                                '<td width="' . $money_width_1 . '" style="border-bottom-color:' . $color . '; font-weight: bold"><small>' . strip_tags($invoiceNum) . '</small></td>' .
                                '<td width="' . $money_width_1 . '" align="right" style="border-bottom-color:' . $color . '; font-weight: bold"><small>' . strip_tags($currencyLabel . static::formatPrice($invoiceInfo['invoice_amount'], $invoiceInfo['invoice_currency'])) . '</small></td>' .
                                '<td width="' . ($status_width - 10) . '" style="border-bottom-color:' . $color . '; font-weight: bold"><small>' . strip_tags($strStatus) . '</small></td>' .
                                '<td width="' . ($status_width + 35) . '" style="border-bottom-color:' . $color . ';"><small>' . nl2br(strip_tags($invoiceInfo['invoice_notes'] ?? '')) . '</small></td>' .
                                '</tr>';

                            if (!empty($arrFeesAndPayments)) {
                                $htmlTable .= '<tr><td colspan="5"><table>';
                                $htmlTable .= '<tr bgcolor="#F1F2F1" align="center">' .
                                    '<th width="' . $date_width . '"><small>' . $this->_tr->translate('Date') . '</small></th>' .
                                    '<th width="' . ($status_width + 190) . '" align="left"><small>' . $this->_tr->translate('Description of Invoice Fees and Payments') . '</small></th>' .
                                    '<th width="' . $money_width_1 . '" align="right"><small>' . $this->_tr->translate('Fee Due') . '</small></th>' .
                                    '<th width="' . $money_width_1 . '" align="right"><small>' . $this->_tr->translate('Payment') . '</small></th>' .
                                    '</tr>';

                                foreach ($arrFeesAndPayments as $arrFeeOrPaymentInfo) {
                                    $htmlTable .= '<tr>' .
                                        '<td width="' . $date_width . '"><small>' . strip_tags($this->_settings->formatDate($arrFeeOrPaymentInfo['time'])) . '</small></td>' .
                                        '<td width="' . ($status_width + 190) . '"><small>' . strip_tags($arrFeeOrPaymentInfo['description']) . '</small></td>' .
                                        '<td width="' . $money_width_1 . '" align="right"><small>' . (isset($arrFeeOrPaymentInfo['fee_due']) ? strip_tags($arrFeeOrPaymentInfo['fee_due']) : '') . '</small></td>' .
                                        '<td width="' . $money_width_1 . '" align="right"><small>' . (isset($arrFeeOrPaymentInfo['payment']) ? strip_tags($arrFeeOrPaymentInfo['payment']) : '') . '</small></td>' .
                                        '</tr>';
                                }

                                $htmlTable .= '</table></td></tr>';
                            }
                        }
                    } else {
                        $htmlTable .= '<tr><td colspan="5"><small>' . $this->_tr->translate('No entries found.') . '</small></td></tr>';
                    }

                    // Outstanding balances
                    $htmlTable .= '<tr><td colspan="5">';
                    foreach ($arrMemberTA as $i => $memberTAInfo) {
                        $currencyLabel = $booShowCurrency ? self::getCurrencyLabel($memberTAInfo['currency'], false) : '';

                        foreach ($arrClientInvoices['arrTADetails'] as $arrOutstandingDetails) {
                            if ($arrOutstandingDetails['company_ta_id'] == $memberTAInfo['company_ta_id']) {
                                $htmlTable .= $this->_tr->translate('Outstanding Balance: ') . $currencyLabel . static::formatPrice($arrOutstandingDetails['outstanding_balance'], $memberTAInfo['currency']);

                                if ($i != count($arrMemberTA) - 1) {
                                    $htmlTable .= '<br />';
                                }
                            }
                        }
                    }
                    $htmlTable .= '</td></tr>';
                    $htmlTable .= '</table><br />';
                }

                // Client Account Summary Table(s)
                if ($arrSettings['invoices']['trust_account_summary']) {
                    foreach ($arrMemberTA as $memberTAInfo) {
                        $currencyName      = self::getCurrencyLabel($memberTAInfo['currency']);
                        $taId              = $memberTAInfo['company_ta_id'];
                        $currency          = $this->getCompanyTACurrency($taId);
                        $ClientSummaryList = $this->getClientsTrustAccountInfo($memberId, $taId, true);

                        $htmlTable .= '<h2> </h2><h2>' . $taLabel . ' Summary (' . $memberTAInfo['name'] . ' - ' . $currencyName . ')</h2>';
                        $htmlTable .= '<table border="1" cellspacing="0" cellpadding="2" >';
                        $htmlTable .= '<tr bgcolor="#cccccc" align="center">' .
                            '<th width="' . $date_width . '">Date</th>' .
                            '<th width="' . ($description_width - 30) . '" align="left">Description</th>' .
                            '<th width="120" align="left"  >Receipt number</th>' .
                            '<th width="' . $money_width_1 . '">Deposit</th>' .
                            '<th width="' . $money_width_1 . '">Withdrawal</th>' .
                            '</tr>';

                        if (count($ClientSummaryList)) {
                            foreach ($ClientSummaryList as $ClientSummary) {
                                $ClientSummary['receipt_number'] = $ClientSummary['receipt_number'] ?? '';

                                $htmlTable .= '<tr><td width="' . $date_width . '"><small>' . strip_tags($ClientSummary['date'] ?? '') . '</small></td>' .
                                    '<td  width="' . ($description_width - 30) . '"><small>' . $ClientSummary['description'] . '</small></td>' .
                                    '<td  width="120"><small>' . $ClientSummary['receipt_number'] . '</small></td>' .
                                    '<td width="' . $money_width_1 . '" align="right"><small>';
                                if ($ClientSummary['deposit'] != '0.00') {
                                    $htmlTable .= static::formatPrice($ClientSummary['deposit'], $currency);
                                }
                                $htmlTable .= '</small></td><td width="' . $money_width_1 . '" align="right"><small>';
                                if ($ClientSummary['withdrawal'] != '0.00') {
                                    $htmlTable .= static::formatPrice($ClientSummary['withdrawal'], $currency);
                                }
                                $htmlTable .= '</small></td></tr>';
                            }
                        } else {
                            $htmlTable .= '<tr><td colspan="5"><small>' . $this->_tr->translate('No entries found.') . '</small></td></tr>';
                        }

                        // Subtotal
                        $htmlTable .= '<tr><td colspan="5">' .
                            'Available Total: ' . $this->getTrustAccountSubTotalCleared($memberId, $taId) .
                            '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
                            'Deposits Not Verified: ' . $this->getNotVerifiedDepositsSum($memberId, $taId) .
                            '</td></tr></table><br />';
                    }
                }

                $companyId   = $this->_company->getMemberCompanyId($memberId);
                $companyInfo = $this->_company->getCompanyInfo($companyId);

                if ($companyInfo['companyLogo'] != '') {
                    $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

                    $logo_url   = $this->_files->getCompanyLogoPath($companyId, $booLocal);
                    $logo_width = '15';

                    if (!$booLocal && $this->_files->getCloud()->checkObjectExists($logo_url)) {
                        $logo_url = $this->_files->getCloud()->downloadFileContent($logo_url);
                    }

                    if (!file_exists($logo_url)) {
                        $logo_url   = '';
                        $logo_width = '';
                    }
                }
            } elseif (!is_array($arrCompanyTA) || empty($arrCompanyTA)) {
                $htmlTable = '<span>There are no created ' . $taLabel . '(s) for this company</span>';
            } else {
                $htmlTable = '<span>Please assign ' . $taLabel . ' to this Case</span>';
            }


            $filename = $this->printTCPDF($logo_url, $logo_width, $memberId, $htmlTable, $destination);

            if ($destination != 'I') {
                $path = $this->_config['directory']['pdf_temp'] . DIRECTORY_SEPARATOR . $filename;

                $arrResult = array(
                    'filename'       => $filename,
                    'check_filename' => $this->_encryption->encode($filename . '#' . $memberId),
                    'size'           => Settings::formatSize(filesize($path) / 1024),
                    'path'           => $this->_encryption->encode($path)
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Add thousands comma to number string (e.g. for currency)
     *
     * @param string $strAmount
     * @return string
     */
    public function addThousands($strAmount)
    {
        $result = '';
        try {
            $sign        = trim($strAmount ?? '', '0123456789.'); //sign + currency symbol
            $number      = str_replace($sign ?? '', '', $strAmount ?? ''); // only number
            $strAfterDot = substr(strstr($strAmount ?? '', '.'), 1, strlen($strAmount)); // str after dot

            $result = $sign . number_format((float)$number, strlen($strAfterDot));
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $result;
    }

    /**
     * Load the list of invoices (with payments) for a specific client
     *
     * @param int $memberId
     * @param int $start
     * @param int $limit
     * @param bool $booForPdf
     * @return array
     */
    public function getClientInvoices($memberId, $start, $limit, $booForPdf = false)
    {
        $arrResult                  = array();
        $arrTADetails               = array();
        $totalRecords               = 0;
        $booShowMoreInvoicesMessage = false;

        try {
            $select = (new Select())
                ->from(array('i' => 'u_invoice'))
                ->join(array('ta' => 'company_ta'), 'ta.company_ta_id = i.company_ta_id', array('currency'), Join::JOIN_LEFT)
                ->where(['i.member_id' => $memberId])
                ->order('date_of_invoice ASC');

            if (!empty($limit)) {
                $select->limit((int)$limit);
                $select->offset((int)$start);
            }

            $arrInvoices  = $this->_db2->fetchAll($select);
            $totalRecords = $this->_db2->fetchResultsCount($select);

            $primaryTAId          = 0;
            $booUniteTotalDetails = false;

            $arrMemberTA = $this->getMemberTA($memberId);
            if (count($arrMemberTA) == 2) {
                // Check if both T/A currencies are the same, if yes - unite total payments + total adjustments
                if ($arrMemberTA[0]['currency'] == $arrMemberTA[1]['currency']) {
                    $primaryTAId = $this->getClientPrimaryCompanyTaId($memberId);

                    $booUniteTotalDetails = true;
                }
            }

            $arrInvoicesAmountGroupedByTA                    = [];
            $arrInvoicePaymentsFromOtherTAGroupedByTA        = [];
            $arrInvoicePaymentsAmountsFromOtherTAGroupedByTA = [];
            $arrInvoiceAdjustmentPaymentsGroupedByTA         = [];
            $arrInvoiceAdjustmentPaymentsAmountsGroupedByTA  = [];

            $booCanEditClient = !$this->_auth->isCurrentUserClient() && $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId);
            foreach ($arrInvoices as $invoiceInfo) {
                // For cleared invoices - get details
                $booCanUnAssignInvoice             = true;
                $arrInvoiceClearedDetails          = array();
                $arrInvoicePaymentsCannotBeDeleted = array();

                $arrAssignedTransactions = $this->getAssignedTransactionsByInvoiceId($invoiceInfo['invoice_id']);
                foreach ($arrAssignedTransactions as $arrAssignedTransactionInfo) {
                    if (!empty($arrAssignedTransactionInfo['trust_account_id'])) {
                        $notes = empty($arrAssignedTransactionInfo['notes']) ? '' : ' - ' . $arrAssignedTransactionInfo['notes'];
                        if ($booCanEditClient) {
                            $comments = sprintf(
                                "<a onclick='showWithdrawalDetails(%d,%d,%d); return false;' href='#' title='Click to check details about this withdrawal'>%s%s</a>",
                                $arrAssignedTransactionInfo['withdrawal_id'],
                                $memberId,
                                $invoiceInfo['company_ta_id'],
                                $this->_tr->translate('Details'),
                                $notes
                            );
                        } else {
                            $comments = sprintf(
                                "%s%s",
                                $this->_tr->translate('Details'),
                                $notes
                            );
                        }

                        $arrInvoiceClearedDetails[] = $comments;

                        $booCanUnAssignPayment = $this->getTrustAccount()->canUnassignTransaction($arrAssignedTransactionInfo['company_ta_id'], $arrAssignedTransactionInfo['date_from_bank']);
                        if ($booCanUnAssignInvoice) {
                            // If we cannot unassign for at least one record - don't allow to delete the invoice
                            $booCanUnAssignInvoice = $booCanUnAssignPayment;
                        }

                        if (!$booCanUnAssignPayment) {
                            $arrInvoicePaymentsCannotBeDeleted[] = $arrAssignedTransactionInfo['invoice_payment_id'];
                        }
                    }
                }

                $invoiceHistory          = '';
                $invoicePayments         = array();
                $arrInvoiceSavedPayments = $this->getInvoicePayments($invoiceInfo['invoice_id']);
                if (!empty($arrInvoiceSavedPayments)) {
                    foreach ($arrInvoiceSavedPayments as $arrInvoicePaymentInfo) {
                        $invoicePaymentRecord = array(
                            'id'             => $arrInvoicePaymentInfo['invoice_payment_id'],
                            'description'    => $this->getInvoicePaymentLabel($arrInvoicePaymentInfo, $invoiceInfo['currency'], '', 'pdf'),
                            'amount'         => static::formatPrice($arrInvoicePaymentInfo['invoice_payment_amount'], $invoiceInfo['currency']),
                            'time'           => strtotime($arrInvoicePaymentInfo['invoice_payment_date'] . ' 23:59:59'),
                            'date'           => $this->_settings->formatDate($arrInvoicePaymentInfo['invoice_payment_date']),
                            'can_be_deleted' => $booCanEditClient && !in_array($arrInvoicePaymentInfo['invoice_payment_id'], $arrInvoicePaymentsCannotBeDeleted)
                        );

                        $invoicePayments[] = $invoicePaymentRecord;

                        $invoiceHistory .= $this->getInvoicePaymentLabel($arrInvoicePaymentInfo, $invoiceInfo['currency']) . '<br>';

                        if (empty($arrInvoicePaymentInfo['company_ta_id'])) {
                            $companyTAId = $booUniteTotalDetails ? $primaryTAId : $invoiceInfo['company_ta_id'];
                            if ($arrInvoicePaymentInfo['company_ta_other'] == self::getInvoicePaymentOperatingAccountOption()) {
                                if (!isset($arrInvoicePaymentsAmountsFromOtherTAGroupedByTA[$companyTAId])) {
                                    $arrInvoicePaymentsAmountsFromOtherTAGroupedByTA[$companyTAId] = 0;
                                }

                                $arrInvoicePaymentsAmountsFromOtherTAGroupedByTA[$companyTAId] += floatval($arrInvoicePaymentInfo['invoice_payment_amount']);

                                $arrInvoicePaymentsFromOtherTAGroupedByTA[$companyTAId][] = $this->getInvoicePaymentLabel($arrInvoicePaymentInfo, $invoiceInfo['currency'], $invoiceInfo['invoice_num']);
                            } else {
                                if (!isset($arrInvoiceAdjustmentPaymentsAmountsGroupedByTA[$companyTAId])) {
                                    $arrInvoiceAdjustmentPaymentsAmountsGroupedByTA[$companyTAId] = 0;
                                }

                                $arrInvoiceAdjustmentPaymentsAmountsGroupedByTA[$companyTAId] += floatval($arrInvoicePaymentInfo['invoice_payment_amount']);

                                $arrInvoiceAdjustmentPaymentsGroupedByTA[$companyTAId][] = $this->getInvoicePaymentLabel($arrInvoicePaymentInfo, $invoiceInfo['currency'], $invoiceInfo['invoice_num']);
                            }
                        }
                    }
                }

                $invoiceOutstandingBalance = $this->getInvoiceOutstandingAmount($invoiceInfo['invoice_id']);

                $arrFeesAndPayments   = [];
                $arrInvoiceLinkedFees = $this->getFeesAssignedToInvoice($invoiceInfo['invoice_id']);
                foreach ($arrInvoiceLinkedFees as $arrFeeInfo) {
                    $arrFeesAndPayments[] = [
                        'time'        => strtotime($arrFeeInfo['transaction_date']),
                        'description' => $arrFeeInfo['transaction_description'],
                        'fee_due'     => self::formatPrice($arrFeeInfo['transaction_amount'], $invoiceInfo['currency']),
                    ];
                }

                foreach ($invoicePayments as $arrInvoicePayment) {
                    $arrFeesAndPayments[] = [
                        'time'        => $arrInvoicePayment['time'],
                        'description' => $arrInvoicePayment['description'],
                        'payment'     => $arrInvoicePayment['amount'],
                    ];
                }

                $order = [];
                foreach ($arrFeesAndPayments as $key => $row) {
                    $order[$key] = $row['time'];
                }
                array_multisort($order, SORT_ASC, $arrFeesAndPayments);

                if (!$booForPdf) {
                    $strFeesAndPayments =
                        '<tr>' .
                        '<th>' . $this->_tr->translate('Date') . '</th>' .
                        '<th>' . $this->_tr->translate('Description of Invoice Fees and Payments') . '</th>' .
                        '<th>' . $this->_tr->translate('Fee Due') . '</th>' .
                        '<th>' . $this->_tr->translate('Payment') . '</th>' .
                        '</tr>';

                    foreach ($arrFeesAndPayments as $arrFeeOrPaymentInfo) {
                        $strFeesAndPayments .=
                            '<tr>' .
                            '<td>' . $this->_settings->formatDate($arrFeeOrPaymentInfo['time']) . '</td>' .
                            '<td>' . $arrFeeOrPaymentInfo['description'] . '</td>' .
                            '<td>' . ($arrFeeOrPaymentInfo['fee_due'] ?? '') . '</td>' .
                            '<td>' . ($arrFeeOrPaymentInfo['payment'] ?? '') . '</td>' .
                            '</tr>';
                    }
                    $arrFeesAndPayments = '<table class="invoice-info">' . $strFeesAndPayments . '</table>';
                }

                $arrInvoiceOutputInfo = array(
                    'invoice_id'                         => $invoiceInfo['invoice_id'],
                    'invoice_company_ta_id'              => $invoiceInfo['company_ta_id'],
                    'invoice_num'                        => $invoiceInfo['invoice_num'],
                    'invoice_date'                       => $invoiceInfo ['date_of_invoice'],
                    'invoice_fee'                        => $invoiceInfo['fee'],
                    'invoice_tax'                        => $invoiceInfo['tax'],
                    'invoice_amount'                     => $invoiceInfo['amount'],
                    'invoice_outstanding_amount'         => $invoiceOutstandingBalance,
                    'invoice_history'                    => $invoiceHistory,
                    'invoice_payments'                   => $invoicePayments,
                    'invoice_payments_amount'            => $invoiceInfo['amount'] - max(0, $invoiceOutstandingBalance),
                    'invoice_cleared_details'            => $arrInvoiceClearedDetails,
                    'invoice_currency'                   => $invoiceInfo['currency'],
                    'invoice_notes'                      => $invoiceInfo['notes'],
                    'invoice_details'                    => $invoiceInfo['description'],
                    'invoice_can_be_deleted'             => $booCanEditClient && $booCanUnAssignInvoice,
                    'invoice_is_assigned_to_fee'         => !empty($arrInvoiceLinkedFees),
                    'invoice_assigned_fees_and_payments' => $arrFeesAndPayments,
                );

                $arrResult[] = $arrInvoiceOutputInfo;

                // Calculate total of invoices - grouped by the T/A
                if (empty($arrInvoiceLinkedFees)) {
                    if (!isset($arrInvoicesAmountGroupedByTA[$invoiceInfo['company_ta_id']])) {
                        $arrInvoicesAmountGroupedByTA[$invoiceInfo['company_ta_id']] = 0;
                    }
                    $arrInvoicesAmountGroupedByTA[$invoiceInfo['company_ta_id']] += floatval($invoiceInfo['amount']);
                }
            }

            foreach ($arrMemberTA as $i => $arrMemberTAInfo) {
                $companyTAId = $arrMemberTAInfo['company_ta_id'];

                // Recalculate the subtotals + OBs for the case + the T/A.
                // This is needed because we need to be sure that balances are correct.
                $this->updateTrustAccountSubTotal($memberId, $arrMemberTAInfo['company_ta_id']);
                $this->updateOutstandingBalance($memberId, $arrMemberTAInfo['company_ta_id']);

                $arrTADetails[] = array(
                    'company_ta_id'                => $companyTAId,
                    'available_total'              => $this->getTrustAccountSubTotal($memberId, $companyTAId, false),
                    'outstanding_balance'          => $this->calculateOutstandingBalance($memberId, $companyTAId),
                    'payments_in_other_ta_total'   => $arrInvoicePaymentsAmountsFromOtherTAGroupedByTA[$companyTAId] ?? 0,
                    'payments_in_other_ta_details' => isset($arrInvoicePaymentsFromOtherTAGroupedByTA[$companyTAId]) ? implode('<br>', $arrInvoicePaymentsFromOtherTAGroupedByTA[$companyTAId]) : '',
                    'adjustment_payments_total'    => $arrInvoiceAdjustmentPaymentsAmountsGroupedByTA[$companyTAId] ?? 0,
                    'adjustment_payments_details'  => isset($arrInvoiceAdjustmentPaymentsGroupedByTA[$companyTAId]) ? implode('<br>', $arrInvoiceAdjustmentPaymentsGroupedByTA[$companyTAId]) : '',
                    'total_payments_received'      => $this->getClientAssignedDeposits($memberId, $companyTAId),
                    'deposits_not_verified'        => $this->getNotVerifiedDepositsSum($memberId, $companyTAId, false),
                    'show_total_payments_block'    => empty($i) || !$booUniteTotalDetails
                );

                // Check if total of invoices is more than total of fees due -> show a message
                // Group/calculate this info by a T/A
                if (!$booShowMoreInvoicesMessage) {
                    $invoicesAmountSum = $arrInvoicesAmountGroupedByTA[$companyTAId] ?? 0;

                    if ($invoicesAmountSum > 0) {
                        $feesAmountSum    = 0;
                        $arrAvailableFees = $this->getFeesAvailableToAssignToInvoice($memberId, $companyTAId);
                        foreach ($arrAvailableFees as $arrAvailableFeeInfo) {
                            $feesAmountSum += floatval($arrAvailableFeeInfo['fee_amount']) + floatval($arrAvailableFeeInfo['fee_gst']);
                        }

                        if ($this->_settings->floatCompare($feesAmountSum, $invoicesAmountSum, '<', 2)) {
                            $booShowMoreInvoicesMessage = true;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return array(
            'rows'                       => $arrResult,
            'totalCount'                 => $totalRecords,
            'arrTADetails'               => $arrTADetails,
            'booShowMoreInvoicesMessage' => $booShowMoreInvoicesMessage,
        );
    }

    /**
     * Get tax label for a payment/ps record
     *
     * @param array $arrProvinces
     * @param array $arrPaymentInfo
     * @return string
     */
    public function getPaymentTaxLabel($arrProvinces, $arrPaymentInfo)
    {
        $gstLabel = '';
        if ($arrPaymentInfo['gst'] > 0) {
            if (array_key_exists($arrPaymentInfo['gst_province_id'], $arrProvinces)) {
                if (array_key_exists('gst_tax_label', $arrPaymentInfo)) {
                    $taxLabel = empty($arrPaymentInfo['gst_tax_label']) ? 'GST' : $arrPaymentInfo['gst_tax_label'];
                } else {
                    $taxLabel = $arrProvinces[$arrPaymentInfo['gst_province_id']]['tax_label'];
                }
                switch ($this->_config['site_version']['version']) {
                    case 'australia':
                        $gstLabel = $taxLabel;
                        break;

                    default:
                        $arrToFormat = array(
                            'province'  => '',
                            'tax_label' => $taxLabel,
                            'rate'      => $arrPaymentInfo['gst'],
                            'tax_type'  => $arrPaymentInfo['tax_type'],
                            'is_system' => 'N', // We need this to show a correct label
                        );

                        $gstLabel = $this->_gstHst->formatGSTLabel($arrToFormat);
                        break;
                }
            } else {
                $gstLabel = 'GST';
            }
        }

        return $gstLabel;
    }

    /**
     * Load a list of records that will be shown in the Fees & Disbursements grid
     * (payment records + not due PS records for the client's primary T/A)
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param string $sort
     * @param string $dir
     * @return array
     */
    public function getClientAccountingFeesList($memberId, $companyTAId = null, $sort = '', $dir = '')
    {
        $arrResult                = array();
        $total                    = 0;
        $totalGst                 = 0;
        $totalDue                 = 0;
        $totalDueGst              = 0;
        $unassignedInvoicesAmount = 0;

        $booCorrectData = !empty($memberId);
        if ($booCorrectData && is_null($companyTAId)) {
            // Get the primary T/A of the client
            $companyTAId    = $this->getClientPrimaryCompanyTaId($memberId);
            $booCorrectData = !empty($companyTAId);
        }

        if ($booCorrectData) {
            $arrProvinces = $this->_gstHst->getProvincesList();
            $booIsClient  = $this->_auth->isCurrentUserClient();
            $booCanEdit   = $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId);

            $arrPSRecords = $this->getClientsPaymentScheduleInfo($memberId, $companyTAId);
            foreach ($arrPSRecords as $arrPSRecordInfo) {
                // Don't show processed records
                if (!empty($arrPSRecordInfo['status'])) {
                    continue;
                }

                $arrGstInfo = $this->_gstHst->calculateGstAndSubtotal($arrPSRecordInfo['tax_type'], $arrPSRecordInfo ['gst'], $arrPSRecordInfo['amount']);

                $arrResult[] = array(
                    'fee_id'              => 'ps-' . $arrPSRecordInfo['id'],
                    'real_id'             => $arrPSRecordInfo['id'],
                    'fee_due_date'        => $arrPSRecordInfo['due_on'],
                    'fee_due_date_ymd'    => $arrPSRecordInfo['due_on_ymd'],
                    'fee_due_timestamp'   => $arrPSRecordInfo['based_on'] == 'date' ? strtotime($arrPSRecordInfo ['due_on']) : 100500000000, // To show such records at the bottom of the list
                    'fee_description'     => $arrPSRecordInfo['name'],
                    'fee_description_gst' => $this->getPaymentTaxLabel($arrProvinces, $arrPSRecordInfo),
                    'fee_amount'          => static::formatPrice($arrGstInfo['subtotal'], '', false),
                    'fee_gst_province_id' => (int)$arrPSRecordInfo ['gst_province_id'],
                    'fee_gst'             => static::formatPrice($arrGstInfo['gst'], '', false),
                    'fee_note'            => $arrPSRecordInfo['notes'],
                    'fee_status'          => '',
                    'type'                => 'ps',
                    'invoice_id'          => 0,
                    'invoice_num'         => '',
                    'can_edit'            => !$booIsClient && $booCanEdit
                );

                $total    += $arrGstInfo['subtotal'];
                $totalGst += $arrGstInfo['gst'];
            }


            // Load processed payments
            $arrPayments = $this->getClientPayments($memberId, $companyTAId, '', '', true);

            $unassignedInvoicesRunningAmount = 0;
            $arrUnassignedInvoices           = $this->getClientUnassignedInvoices($memberId, $companyTAId);
            foreach ($arrUnassignedInvoices as $arrUnassignedInvoiceInfo) {
                $arrInvoicePayments = $this->getInvoicePayments($arrUnassignedInvoiceInfo['invoice_id']);
                foreach ($arrInvoicePayments as $arrInvoicePayment) {
                    $unassignedInvoicesAmount += floatval($arrInvoicePayment['invoice_payment_amount']);
                }

                $unassignedInvoicesRunningAmount += floatval($arrUnassignedInvoiceInfo['amount']);
            }


            $arrUsedInvoices = [];
            foreach ($arrPayments as $arrPaymentInfo) {
                $arrPaymentInfo = $this->getPaymentCorrectAmountAndGst($arrPaymentInfo);

                $feeStatus = empty($arrPaymentInfo['invoice_id']) ? 'due' : 'assigned';
                $feeAmount = floatval($arrPaymentInfo['withdrawal']) + floatval($arrPaymentInfo['due_gst']);
                if ($feeStatus == 'due' && $unassignedInvoicesRunningAmount >= 0) {
                    if ($unassignedInvoicesRunningAmount - $feeAmount >= 0) {
                        $feeStatus = 'due_can_be_linked';

                        $unassignedInvoicesRunningAmount -= $feeAmount;
                    }
                }

                $arrResult[] = array(
                    'fee_id'              => 'payment-' . $arrPaymentInfo['payment_id'],
                    'real_id'             => $arrPaymentInfo['payment_id'],
                    'fee_due_date'        => $this->_settings->formatDate($arrPaymentInfo['date_of_event']),
                    'fee_due_date_ymd'    => $arrPaymentInfo['date_of_event'],
                    'fee_due_timestamp'   => strtotime($arrPaymentInfo['date_of_event']),
                    'fee_description'     => $arrPaymentInfo['description'],
                    'fee_description_gst' => $this->getPaymentTaxLabel($arrProvinces, $arrPaymentInfo),
                    'fee_amount'          => static::formatPrice($arrPaymentInfo['withdrawal'], '', false),
                    'fee_gst_province_id' => (int)$arrPaymentInfo ['gst_province_id'],
                    'fee_gst'             => static::formatPrice($arrPaymentInfo['due_gst'], '', false),
                    'fee_note'            => $arrPaymentInfo['notes'],
                    'fee_status'          => $feeStatus,
                    'type'                => 'payment',
                    'invoice_id'          => $arrPaymentInfo['invoice_id'],
                    'invoice_num'         => $arrPaymentInfo['invoice_num'],
                    'can_edit'            => $arrPaymentInfo['company_agent'] == 'N',
                );

                if ($feeStatus == 'due' || $feeStatus == 'due_can_be_linked') {
                    $totalDue    += $arrPaymentInfo['withdrawal'];
                    $totalDueGst += $arrPaymentInfo['due_gst'];
                } elseif (!in_array($arrPaymentInfo['invoice_id'], $arrUsedInvoices)) {
                    // Don't try to calc for the same invoice (because 1 invoice can be assigned to several fees)
                    $totalDue    += $arrPaymentInfo['invoice_fee'];
                    $totalDueGst += $arrPaymentInfo['invoice_tax'];

                    $arrInvoicePayments = $this->getInvoicePayments($arrPaymentInfo['invoice_id']);
                    foreach ($arrInvoicePayments as $arrInvoicePayment) {
                        $totalDue -= $arrInvoicePayment['invoice_payment_amount'];
                    }

                    $arrUsedInvoices[] = $arrPaymentInfo['invoice_id'];
                }

                $total    += $arrPaymentInfo['withdrawal'];
                $totalGst += $arrPaymentInfo['due_gst'];
            }

            if (!empty($arrResult)) {
                $dir = empty($dir) ? 'ASC' : $dir;

                $arrKeys = array_keys($arrResult[0]);
                $sort    = empty($sort) || !in_array($sort, $arrKeys) ? 'fee_due_timestamp' : $sort;

                $arrGrouped = array();
                foreach ($arrResult as $key => $row) {
                    $arrGrouped[$key] = $row[$sort];
                }
                array_multisort($arrGrouped, strtoupper($dir) == 'ASC' ? SORT_ASC : SORT_DESC, $arrResult);
            }
        }

        return array(
            'rows'                       => $arrResult,
            'total'                      => round($total, 2),
            'total_gst'                  => round($totalGst, 2),
            'total_due'                  => round($totalDue + $totalDueGst - $unassignedInvoicesAmount, 2),
            'unassigned_invoices_amount' => round($unassignedInvoicesAmount, 2)
        );
    }

    /**
     * Load unassigned invoices
     *
     * @param int $memberId
     * @param int $companyTAId
     * @return array
     */
    public function getClientUnassignedInvoices($memberId, $companyTAId)
    {
        $select = (new Select())
            ->from(array('i' => 'u_invoice'))
            ->join(array('p' => 'u_payment'), 'p.invoice_id = i.invoice_id', [], Join::JOIN_LEFT)
            ->where([
                (new Where())
                    ->isNull('p.payment_id')
                    ->equalTo('i.member_id', $memberId)
                    ->equalTo('i.company_ta_id', $companyTAId)
            ]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Get the list of due (not assigned to invoice(s) yet) fees or (if passed invoice id) also fees assigned to the invoice
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param int|null $invoiceId
     * @return array
     */
    public function getFeesAvailableToAssignToInvoice($memberId, $companyTAId, $invoiceId = null)
    {
        $arrFees = array();

        $arrFeesResult = $this->getClientAccountingFeesList($memberId, $companyTAId);
        foreach ($arrFeesResult['rows'] as $arrLoadedRecordInfo) {
            if ($arrLoadedRecordInfo['type'] == 'payment' && ($arrLoadedRecordInfo['fee_status'] != 'assigned' || (!empty($invoiceId) && $arrLoadedRecordInfo['invoice_id'] == $invoiceId))) {
                unset($arrLoadedRecordInfo['invoice_id'], $arrLoadedRecordInfo['invoice_num'], $arrLoadedRecordInfo['type'], $arrLoadedRecordInfo['can_edit']);

                $arrFees[] = $arrLoadedRecordInfo;
            }
        }

        return $arrFees;
    }

    /**
     * Load detailed info about a future invoice
     *
     * @param int $companyTAId
     * @param array $arrFeesIds
     * @param array $arrInvoiceSavedPayments
     * @return array
     */
    public function getInvoiceGroupedDetails($companyTAId, $arrFeesIds, $arrInvoiceSavedPayments)
    {
        $strError           = '';
        $arrInvoiceFees     = array();
        $arrInvoicePayments = array();
        $paymentsTotal      = 0;
        $total              = 0;
        $outstanding        = 0;
        $fee                = 0;
        $tax                = 0;
        $currency           = '';

        try {
            $currency = $this->getCompanyTACurrency($companyTAId);
            foreach ($arrFeesIds as $feeId) {
                $arrFeeInfo = $this->getPaymentInfo($feeId);
                if (empty($arrFeeInfo)) {
                    $strError = $this->_tr->translate('Insufficient access rights for this payment');
                    break;
                }

                $fee += floatval($arrFeeInfo['withdrawal']);
                $tax += floatval($arrFeeInfo['due_gst']);

                $arrInvoiceFees[] = $arrFeeInfo;
            }


            if (!empty($arrInvoiceSavedPayments)) {
                foreach ($arrInvoiceSavedPayments as $arrInvoicePaymentInfo) {
                    $paymentsTotal += floatval($arrInvoicePaymentInfo['invoice_payment_amount']);

                    $arrInvoicePaymentInfo['invoice_payment_description']      = $this->getInvoicePaymentLabel($arrInvoicePaymentInfo, $currency, '', 'invoice');
                    $arrInvoicePaymentInfo['invoice_payment_amount_formatted'] = static::formatPrice($arrInvoicePaymentInfo['invoice_payment_amount'], $currency);

                    $arrInvoicePayments[$arrInvoicePaymentInfo['invoice_payment_id']] = $arrInvoicePaymentInfo;
                }
            }

            $total       = $fee + $tax;
            $outstanding = $total - $paymentsTotal;
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return array(
            'strError'           => $strError,
            'arrInvoiceFees'     => $arrInvoiceFees,
            'arrInvoicePayments' => $arrInvoicePayments,
            'payments_total'     => $paymentsTotal,
            'total'              => $total,
            'outstanding'        => $outstanding,
            'fee'                => $fee,
            'tax'                => $tax,
            'currency'           => $currency,
        );
    }

    /**
     * Add/Change/Delete client's T/A
     *
     * create -> empty $oldCompanyTAId and new $newCompanyTAId T/A is passed
     * update -> $oldCompanyTAId and new $newCompanyTAId are passed
     * delete -> not empty $oldCompanyTAId and $newCompanyTAId is empty
     *
     * @param int $memberId
     * @param int $oldCompanyTAId
     * @param int $newCompanyTAId
     * @param bool $booPrimary
     */
    public function changeMemberTA($memberId, $oldCompanyTAId, $newCompanyTAId, $booPrimary)
    {
        if (empty($oldCompanyTAId)) {
            // This is a new one
            if (!empty($newCompanyTAId)) {
                $this->assignMemberTA($memberId, $newCompanyTAId, $booPrimary ? 0 : 1);
            }
        } else {
            $this->_db2->delete(
                'members_ta',
                [
                    'member_id'     => $memberId,
                    'company_ta_id' => $oldCompanyTAId,
                ]
            );

            // Clear previously created records
            $this->_db2->delete('u_payment', ['member_id' => $memberId, 'company_ta_id' => $oldCompanyTAId]);
            $this->_db2->delete('u_invoice', ['member_id' => $memberId, 'company_ta_id' => $oldCompanyTAId]);

            if ($booPrimary) {
                $this->_db2->delete('u_payment_schedule', ['member_id' => $memberId]);
            }

            if (!empty($newCompanyTAId)) {
                // Changed, so add a new one
                $this->assignMemberTA($memberId, $newCompanyTAId, $booPrimary ? 0 : 1);
            }
        }
    }

    /**
     * Prepare the information for invoice generation
     *
     * @param int $companyTAId
     * @param int $memberId
     * @param array $arrPSRecordsIds
     * @param array $arrFeesIds
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getNewInvoiceDetails($companyTAId, $memberId, $arrPSRecordsIds, $arrFeesIds)
    {
        $strError               = '';
        $iNumber                = 1;
        $fee                    = 0;
        $tax                    = 0;
        $total                  = 0;
        $paymentsTotal          = 0;
        $outstanding            = 0;
        $currency               = '';
        $arrInvoiceAssignedFees = array();
        $arrInvoicePayments     = array();

        try {
            if (empty($strError) && empty($arrFeesIds) && empty($arrPSRecordsIds)) {
                $strError = $this->_tr->translate('Please select a payment.');
            }

            if (empty($strError) && !empty($arrPSRecordsIds)) {
                $arrPSRecords = array();
                foreach ($arrPSRecordsIds as $paymentScheduleId) {
                    $arrPSInfo = $this->getPaymentScheduleInfo($paymentScheduleId);

                    // Check if current user can access to this payment record (by assigned client id)
                    if (!isset($arrPSInfo['member_id']) || $arrPSInfo['member_id'] != $memberId) {
                        $strError = $this->_tr->translate('Insufficient access rights for this Payment Schedule');
                        break;
                    }

                    // PS records are not related to the specific T/A, so force to use the provided one
                    $arrPSInfo['company_ta_id'] = $companyTAId;

                    // Set payment record's date to now
                    $arrPSInfo['payment_date_of_event'] = date('Y-m-d H:i:s');

                    $arrPSRecords[] = $arrPSInfo;
                }

                if (empty($strError)) {
                    // Set the "Due date" to now, clear other possible DUE values
                    foreach ($arrPSRecordsIds as $paymentScheduleId) {
                        $arrData = array(
                            'based_on_date'               => date('Y-m-d H:i:s'),
                            'based_on_profile_date_field' => null,
                            'based_on_account'            => null,
                        );

                        $this->updatePSRecordInfo($paymentScheduleId, $memberId, $arrData);
                    }

                    if ($this->insertFinancialTransactions($arrPSRecords)) {
                        $arrGeneratedFees = $this->getPaymentsByPSRecords($arrPSRecordsIds);
                        if (count($arrGeneratedFees) === count($arrPSRecordsIds)) {
                            $arrFeesIds = array_map('intval', array_merge($arrFeesIds, $arrGeneratedFees));
                            $arrFeesIds = Settings::arrayUnique($arrFeesIds);
                        } else {
                            // Cannot be here!
                            $strError = $this->_tr->translate('Some PS records were not processed');
                        }
                    } else {
                        $strError = $this->_tr->translate('PS records were not processed');
                    }
                }
            }

            if (empty($strError)) {
                $arrInvoiceDetails      = $this->getInvoiceGroupedDetails($companyTAId, $arrFeesIds, array());
                $strError               = $arrInvoiceDetails['strError'];
                $arrInvoiceAssignedFees = $arrInvoiceDetails['arrInvoiceFees'];
                $arrInvoicePayments     = $arrInvoiceDetails['arrInvoicePayments'];
                $paymentsTotal          = $arrInvoiceDetails['payments_total'];
                $outstanding            = $arrInvoiceDetails['outstanding'];
                $total                  = $arrInvoiceDetails['total'];
                $fee                    = $arrInvoiceDetails['fee'];
                $tax                    = $arrInvoiceDetails['tax'];
                $currency               = $arrInvoiceDetails['currency'];

                //get invoice number
                $iNumber = $this->getMaxInvoiceNumber($companyTAId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success'        => empty($strError),
            'message'        => $strError,
            'arrFeesIds'     => $arrFeesIds,
            'invoice_num'    => empty($strError) ? $iNumber : 1,
            'fee'            => empty($strError) ? $fee : 0,
            'tax'            => empty($strError) ? $tax : 0,
            'total'          => empty($strError) ? $total : 0,
            'currency'       => empty($strError) ? $currency : '',
            'fees'           => $arrInvoiceAssignedFees,
            'payments'       => $arrInvoicePayments,
            'payments_total' => empty($strError) ? $paymentsTotal : 0,
            'outstanding'    => empty($strError) ? $outstanding : 0,
        );
    }

    /**
     * Get a list of new invoice templates (a hardcoded list for now)
     * They must be created, e.g. for template with id 1:
     * clients/accounting/invoice_template_1.phtml
     *
     * @param bool $booIdsOnly
     * @return array
     */
    public function getInvoiceTemplates($booIdsOnly = false)
    {
        $arrRecords = [];

        $arrRecords[] = [
            'templateId'   => 1,
            'templateName' => 'Template 1',
        ];

        $arrRecords[] = [
            'templateId'   => 2,
            'templateName' => 'Template 2',
        ];

        if ($booIdsOnly) {
            $arrRecords = Settings::arrayColumn($arrRecords, 'templateId');
        }

        return $arrRecords;
    }

    /**
     * Provides list of fields available for system templates.
     * @param EventInterface $e
     * @return array
     */
    public function getSystemTemplateFields(EventInterface $e)
    {
        $templateType = $e->getParam('templateType');
        if ($templateType == 'mass_email') {
            return [];
        }

        // Invoice info
        $arrInvoiceFields = array(
            array('name' => 'invoice: number', 'label' => 'Invoice #'),
            array('name' => 'invoice: subscription fee', 'label' => 'Subscription Fee'),
            array('name' => 'invoice: support fee', 'label' => 'Support and Training Fee'),
            array('name' => 'invoice: subtotal', 'label' => 'Subtotal'),
            array('name' => 'invoice: total', 'label' => 'Total Amount'),
            array('name' => 'invoice: gst/hst fee', 'label' => 'GST/HST Fee'),
            array('name' => 'invoice: date', 'label' => 'Invoice Date'),
            array('name' => 'invoice: free users', 'label' => 'Free Users including admin'),
            array('name' => 'invoice: additional users fee', 'label' => 'Fee for Additional Users'),
            array('name' => 'invoice: additional users', 'label' => 'Additional Users'),
            array('name' => 'invoice: free storage', 'label' => 'Free Storage'),
            array('name' => 'invoice: additional storage', 'label' => 'Additional Storage'),
            array('name' => 'invoice: additional storage charges', 'label' => 'Additional Storage Charges'),
            array('name' => 'invoice: price per user', 'label' => 'Price Per User'),
            array('name' => 'invoice: price per storage', 'label' => 'Price Per Storage'),
            array('name' => 'invoice: quantity', 'label' => 'Quantity'),
            array('name' => 'invoice: discount', 'label' => 'Discount'),
            array('name' => 'invoice: currency', 'label' => 'Currency'),
            array('name' => 'invoice: amount paid', 'label' => 'Amount Paid'),
            array('name' => 'invoice: payment method', 'label' => 'Payment Method'),
        );

        foreach ($arrInvoiceFields as &$field6) {
            $field6['n']     = 5;
            $field6['group'] = 'Company Invoice';
        }
        unset($field6);

        //Special CC Charge
        $arrSpecialChargeFields = array(
            array('name' => 'special cc charge: net', 'label' => 'Net'),
            array('name' => 'special cc charge: fax', 'label' => 'Tax'),
            array('name' => 'special cc charge: amount', 'label' => 'Amount'),
            array('name' => 'special cc charge: notes', 'label' => 'Notes')
        );

        foreach ($arrSpecialChargeFields as &$field7) {
            $field7['n']     = 6;
            $field7['group'] = 'Special CC Charge';
        }
        unset($field7);

        return array_merge($arrInvoiceFields, $arrSpecialChargeFields);
    }
}
