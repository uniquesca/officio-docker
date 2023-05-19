<?php

namespace TrustAccount\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Comms\Service\Mailer;
use Officio\Import\Import;
use Officio\Service\Company;
use Officio\Common\Service\Settings;

/**
 * TrustAccount ImportController - this controller is for transactions
 * importing on Client Account page
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ImportController extends BaseController
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_files   = $services[Files::class];
        $this->_mailer  = $services[Mailer::class];
    }

    public function uploadFileAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $error                 = '';
        $file                  = '';
        $fileName              = '';
        $booShowOpeningBalance = false;
        $openingBalance        = 0;
        $firstTransactionDate  = '';
        $fileElementName       = 'import-dialog1-file';

        try {
            if (!empty($_FILES[$fileElementName]['error'])) {
                switch ($_FILES[$fileElementName]['error']) {
                    case '1' :
                        $error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
                        break;
                    case '2' :
                        $error = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
                        break;
                    case '3' :
                        $error = 'The uploaded file was only partially uploaded';
                        break;
                    case '4' :
                        $error = 'No file was uploaded.';
                        break;
                    case '6' :
                        $error = 'Missing a temporary folder';
                        break;
                    case '7' :
                        $error = 'Failed to write file to disk';
                        break;
                    case '8' :
                        $error = 'File upload stopped by extension';
                        break;
                    case '999':
                    default:
                        $error = 'No error code available';
                }
            }

            if (empty($error) && (empty($_FILES[$fileElementName]['tmp_name']) || $_FILES[$fileElementName]['tmp_name'] == 'none')) {
                $error = 'No file was uploaded..';
            }

            if (empty($error)) {
                $ta_id = (int)$this->findParam('ta_id');

                // Check if current user has access to this Client Account
                if (!$this->_clients->hasCurrentMemberAccessToTA($ta_id)) {
                    // Incorrect id or has not access to it
                    $error = 'Insufficient access rights.';
                }
            }

            if (empty($error)) {
                $fileName = $_FILES[$fileElementName]['name'];
                $fileName = FileTools::cleanupFileName($fileName);
                $fileExt  = strtolower(FileTools::getFileExtension($fileName ?? ''));

                if (!in_array($fileExt, array('csv', 'xls', 'qbo', 'qfx', 'ofx', 'qif'))) {
                    $error = 'Incorrect file format! Please select CSV, XLS, QBO, QFX, QIF or OFX file.';
                } else {
                    $file = $this->_files->createTempFile($fileExt);
                    rename($_FILES[$fileElementName]['tmp_name'], $file);

                    $arrCompanyTaInfo       = $this->_clients->getAccounting()->getCompanyTAbyId($ta_id);
                    $lastReconcileDate      = $arrCompanyTaInfo['last_reconcile'];
                    $lastICCRCReconcileDate = $arrCompanyTaInfo['last_reconcile_iccrc'];

                    $attr         = array();
                    $import       = new Import($this->_settings, $this->_mailer, $this->_company, $this->_members);
                    $data         = $import->importFile($file, $attr); #import/import.php
                    $recordsCount = is_array($data) ? count($data) : 0;

                    if (!empty($recordsCount)) {
                        $openingBalance        = $this->_clients->getAccounting()::formatPrice($data[0]['balance']);
                        $firstTransactionDate  = date('M j, Y', $data[0]['date']);
                        $booShowOpeningBalance = Settings::isDateEmpty($lastReconcileDate) && Settings::isDateEmpty($lastICCRCReconcileDate) && !$this->_clients->getAccounting()->hasTrustAccountTransactions($ta_id);
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'                => empty($error),
            'file'                   => $file,
            'fileName'               => $fileName,
            'show_opening_balance'   => $booShowOpeningBalance,
            'opening_balance'        => $openingBalance,
            'first_transaction_date' => $firstTransactionDate,
            'error'                  => $error
        );

        return $view->setVariable('content', Json::encode($arrResult));
    }

    public function saveOpeningBalanceAction()
    {
        $view = new JsonModel();

        $error = '';

        try {
            $ta_id          = (int)$this->findParam('ta_id');
            $openingBalance = (double)$this->findParam('balance');

            // Check if current user has access to this Client Account
            if (!$this->_clients->hasCurrentMemberAccessToTA($ta_id)) {
                // Incorrect id or has not access to it
                $error = 'Insufficient access rights.';
            }

            if (empty($error)) {
                if ($this->_clients->getAccounting()->startBalanceRecordExists($ta_id)) {
                    $this->_clients->getAccounting()->updateStartBalance($ta_id, $openingBalance);
                } else {
                    $this->_clients->getAccounting()->createStartBalance($ta_id, $openingBalance);
                }
            }
        } catch (Exception $e) {
            $error = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($error),
            'error'   => $error
        );

        return $view->setVariables($arrResult);
    }

    //        Menu to select the columns from the transactions table
    private function selectBox($current, $num, $ext)
    {
        $arrOptions    = array('Date from bank', 'Deposit', 'Withdrawal', 'Balance', 'Type', 'Description');
        $booQuickbooks = in_array($ext, array('qbo', 'qfx', 'ofx'));
        $out           = '<select id="DCH_' . $num . '" onChange="ItaTitleChanger(' . $num . ', this.value);" class="DCH" ' . ($booQuickbooks ? 'disabled="disabled"' : '') . '>';
        $i             = 1;
        foreach ($arrOptions as $opt) {
            $out .= sprintf(
                '<option value="%s" %s>%s</option>',
                $i++,
                ($opt === $current ? ' selected="selected"' : ''),
                $opt
            );
        }
        $out .= '</select>';

        return $out;
    }

    //check transaction for match in DB
    private function checkForMatch($line, $ta_id)
    {
        if (@$line['fit'] == '') {
            return true;
        }

        $select = (new Select())
            ->from(['ta' => 'u_trust_account'])
            ->columns(['trust_account_id'])
            ->where([
                'ta.fit'           => $line['fit'],
                'ta.company_ta_id' => $ta_id
            ])
            ->limit(1);

        $ta = $this->_db2->fetchOne($select);

        return empty($ta);
    }

    private function exitOnError($strError, $strOutput = '')
    {
        $view = new ViewModel(['content' => $strOutput . "<div class='error'>$strError</div>"]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function showValidationAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => null
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }

        $filter = new StripTags();

        $ta_id = (int)$this->findParam('ta_id');

        $arrCompanyTaInfo = $this->_clients->getAccounting()->getCompanyTAbyId($ta_id);

        // Get the latest reconcile date
        $lastReconcileDate             = $arrCompanyTaInfo['last_reconcile'];
        $lastGeneralReconcileTimestamp = Settings::isDateEmpty($lastReconcileDate) ? 0 : strtotime($lastReconcileDate);
        $lastICCRCReconcileDate        = $arrCompanyTaInfo['last_reconcile_iccrc'];
        $lastICCRCReconcileTimestamp   = Settings::isDateEmpty($lastICCRCReconcileDate) ? 0 : strtotime($lastICCRCReconcileDate);

        $lastReconcileTimestamp = max($lastGeneralReconcileTimestamp, $lastICCRCReconcileTimestamp);

        // Check if current user has access to this Client Account
        if (!$this->_clients->hasCurrentMemberAccessToTA($ta_id)) {
            // Incorrect id or has no access to it
            return $this->exitOnError('Insufficient access rights.');
        }

        // Get and save file
        $file     = $filter->filter(Json::decode($this->findParam('file'), Json::TYPE_ARRAY));
        $fileName = $filter->filter(Json::decode($this->findParam('fileName'), Json::TYPE_ARRAY));
        $fileExt  = FileTools::getFileExtension($fileName ?? '');
        $attr     = array();
        $import   = new Import($this->_settings, $this->_mailer, $this->_company, $this->_members);
        $data     = $import->importFile($file, $attr); #import/import.php

        $attr['dtstart'] = $data[0]['date'];
        $attr['dtend']   = $data[count($data) - 1]['date'];

        $recordsCount = count($data);

        // check all records: whether date is before reconcile date
        foreach ($data as &$d) {
            $d['before_reconcile'] = false;
        }
        unset($d);

        $booAllBeforeReconcile = false;
        if (!empty($lastReconcileTimestamp)) {
            $booAllBeforeReconcile = true;
            foreach ($data as $key => $d) {
                $booBefore = $lastReconcileTimestamp > $d['date'];

                if (!$booBefore) {
                    $booAllBeforeReconcile = false;
                }

                $data[$key]['before_reconcile'] = $booBefore;
            }
        }

        // If there are no records - exit
        if (empty($recordsCount)) {
            return $this->exitOnError('There are no records available for importing.');
        }


        $dateFormat = $this->_settings->variable_get("dateFormatFull");

        //show file info
        switch (strtolower($fileExt)) {
            case 'qbo' :
                $ext = 'QuickBooks';
                break;
            case 'qfx' :
                $ext = 'Quicken';
                break;
            case 'qif' :
                $ext = 'Quicken Interchange Format';
                break;
            case 'ofx' :
                $ext = 'MSMoney';
                break;
            case 'xls' :
                $ext = 'Microsoft Office Excel';
                break;
            case 'csv' :
                $ext = 'Comma-separated values';
                break;
            default    :
                $ext = '';
                break;
        }

        $strOutput = '
            <div class="bluetxtdark">Import Selected Transactions from File</div>
            <table cellpadding="0" cellspacing="0" border="0" width="100%"  class="textbox" style="line-height:16px; padding: 5px 0;">
            <tr>
                <td align="left" valign="top">
                Uploaded File Name: <b>' . $fileName . '</b><br />
                File type: ' . $ext . '<br />
                Records in file: ' . $recordsCount . '<br />
            ';

        if (@$attr['dtstart'] != '' && @$attr['dtend'] != '') {
            $strOutput .= 'Transactions during period: ' . date($dateFormat, $attr['dtstart']) . ' to ' . date($dateFormat, $attr['dtend']);
        }

        $strOutput .= '
            </td>
            <td align="left" valign="top" width="550" style="line-height:20px;">
            <span class="import-ta-match-msg1">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> - A similar record exists in the database or a record date is before reconcile date.
            <br />
            <span class="import-ta-match-msg2">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> - A new records.
            </td>
            </tr>
            </table>
            ';

        // Check if all is correct
        $arrAlreadyImported = $this->_clients->getAccounting()->getImportSummaryInfo($ta_id);

        foreach ($data as &$line) {
            $line['date'] = date('M j, Y', $line['date']);
        }
        unset($line);

        // There are already imported data? If yes - check
        if (is_array($arrAlreadyImported) && count($arrAlreadyImported) > 0 && !empty($arrAlreadyImported['min_dt_start'])) {
            $strError = '';

            $importStart = strtotime($arrAlreadyImported['min_dt_start']);

            $arrStartBalance = $this->_clients->getAccounting()->getStartBalanceInfo($ta_id);
            $balance         = (double)$arrStartBalance['deposit'];

            // If an account is Reconciled until the end of April, no file with ALL transactions before April can be imported.
            if (empty($strError) && $booAllBeforeReconcile) {
                if ($attr['dtstart'] < $lastReconcileTimestamp) {
                    $strRecDate = date('F Y', $lastReconcileTimestamp);
                    $strError   = sprintf("Your account was Reconciled until the end of %s, no file with all transactions before %s can be imported.", $strRecDate, $strRecDate);
                }
            }

            if (!empty($strError)) {
                // Send email
                $member_id            = $this->_auth->getCurrentUserId();
                $arrCurrentMemberInfo = $this->_members->getMemberInfo($member_id);

                $arrCompanyInfo = $this->_company->getCompanyInfo($arrCurrentMemberInfo['company_id']);

                $strHtmlError = '<h2>Incorrect Balance</h2>' . $strError;

                $strHtmlError .= '<h2>Company:</h2>' . $arrCompanyInfo['companyName'];
                $strHtmlError .= '<h2>User:</h2>' . $arrCurrentMemberInfo['full_name'];

                $this->_mailer->sendEmailToSupport(
                    'Balance in import file is not same to already saved in DB', // subject
                    $strHtmlError,                                               // body
                    null,                                                        // to
                    null,                                                        // from
                    null,                                                        // cc
                    null,                                                        // bcc
                    true,                                                        // use subject prefix
                    array($file)                                                 // attachments
                );

                // Show error message
                $strError = 'Your new import has a gap &amp; missing some transactions since the last import. No gap is allowed in imported transactions.<br/>To maintain the integrity of your imported transaction, you need to import all transactions from your bank.';
            }

            // 3. Check if start balance is assigned, and we try import prior to it - disallow importing
            if (empty($strError)) {
                $booAssignedStartBalance = $this->_clients->getAccounting()->isStartBalanceAssigned($ta_id);
                if ($booAssignedStartBalance && $importStart > @$attr['dtstart']) {
                    $strError = sprintf('The previous starting balance is already assigned to some cases. You cannot import any more transactions prior to %s.', date($dateFormat, $importStart));
                }
            }

            if (empty($strError)) {
                $select = (new Select())
                    ->from('u_trust_account')
                    ->columns(['date' => 'date_from_bank', 'deposit', 'withdrawal', 'balance', 'purpose'])
                    ->where(['company_ta_id' => $ta_id])
                    ->order('date_from_bank ASC');

                $transactions = $this->_db2->fetchAll($select);

                foreach ($transactions as $transaction) {
                    if (strtotime($transaction['date']) <= strtotime($data[0]['date'])) {
                        if ($transaction['purpose'] == $this->_clients->getAccounting()->startBalanceTransactionId) {
                            $balance = $transaction['deposit'];
                        } else {
                            $balance = $transaction['balance'] + $transaction['deposit'] - $transaction['withdrawal'];
                        }
                    }
                }

                $deposit    = 0.00;
                $withdrawal = 0.00;

                foreach ($data as &$line) {
                    $balance         = $balance + $deposit - $withdrawal;
                    $line['balance'] = $balance;

                    $booDeposit = array_key_exists('credit', $line);
                    $deposit    = $booDeposit ? (double)$line['credit'] : 0.00;
                    $withdrawal = $booDeposit ? 0.00 : (double)$line['debit'];
                }
                unset($line);
            }
        }

        // On error show error message and exit
        if (!empty($strError)) {
            return $this->exitOnError($strError, $strOutput);
        }

        $strOutput .= '
            <table cellpadding="0" cellspacing="0" border="0" class="i-table" width="100%">
            <thead>
                <tr>
                <th width="25">
            ';

        if (!in_array($fileExt, array('qbo', 'qfx', 'ofx'))) {
            $strOutput .= '<img src="images/refresh.png" alt="Refresh" onclick="set_default();" width="16" height="16" />';
        }

        $strOutput .= '
            </th>
            <th width="50">' . $this->selectBox('Date from bank', 1, $fileExt) . '</th>
            <th width="50">' . $this->selectBox('Deposit', 2, $fileExt) . '</th>
            <th width="50">' . $this->selectBox('Withdrawal', 3, $fileExt) . '</th>
            <th width="50">' . $this->selectBox('Balance', 4, $fileExt) . '</th>
            <th width="100">' . $this->selectBox('Type', 5, $fileExt) . '</th>
            <th width="350" style="text-align: left">' . $this->selectBox('Description', 6, $fileExt) . '</th>
            </tr>
            </thead>
            ';

        //get content
        $i          = 0;
        $match_line = '';
        foreach ($data as $line) {
            //match
            $match      = ($this->checkForMatch($line, $ta_id) && $line['before_reconcile'] !== true);
            $match_line .= ($match ? 1 : 0) . ',';

            $booDeposit = array_key_exists('credit', $line);
            $deposit    = $booDeposit ? $this->_clients->getAccounting()::formatPrice($line['credit']) : '&nbsp;';
            $withdrawal = $booDeposit ? '&nbsp;' : $this->_clients->getAccounting()::formatPrice($line['debit']);

            $strOutput .= '<tr ' . ($match ? '' : ' class="import-ta-match"') . '>
                     <td align="center"><input type="checkbox" id="cb' . $i . '" name="cb' . $i . '" ' . ($match ? 'checked="checked"' : '') . ' ' . ($line['before_reconcile'] ? ' disabled="disabled"' : '') . ' /></td>
                     <td>' . $line['date'] . '</td>
                     <td>' . $deposit . '</td>
                     <td>' . $withdrawal . '</td>
                     <td>' . $this->_clients->getAccounting()::formatPrice(@$line['balance']) . '</td>
                     <td>' . (@$line['type']) . '</td>
                     <td>' . $line['description'] . '</td>
                  </tr>';

            $i++;
        }

        $strOutput .= '
            </table>
            <input type="hidden" id="match_line" name="match_line" value="' . $match_line . '" />
            <input type="hidden" id="records" name="records" value="' . $recordsCount . '" />
            ';

        $view = new ViewModel(['content' => $strOutput]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function saveAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit;
        }

        // How many transactions added
        $recordsAddedCount = 0;

        try {
            $companyTAId = (int)$this->findParam('ta_id');

            // Check if current user has access to this Client Account
            if (!$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                // Incorrect id or has no access to it
                exit(Json::encode(array('success' => false, 'records' => array())));
            }

            // Get incoming params
            $filter   = new StripTags();
            $file     = $filter->filter(Json::decode($this->findParam('file'), Json::TYPE_ARRAY));
            $fileName = $filter->filter(Json::decode($this->findParam('fileName'), Json::TYPE_ARRAY));

            $attr         = array();
            $import       = new Import($this->_settings, $this->_mailer, $this->_company, $this->_members);
            $data         = $import->importFile($file, $attr); #import/import.php
            $recordsCount = is_array($data) ? count($data) : 0;
            $dch          = explode(',', substr($_REQUEST['dch'] ?? '', 0, -1));
            $cb           = explode(',', substr($_REQUEST['cb'] ?? '', 0, -1));
            $rows         = array(1 => 'date', 2 => 'debit', 3 => 'credit', 4 => 'balance', 5 => 'type', 6 => 'description');

            $startDate = '';

            // Save import info
            $arrInsertImportData = array(
                'company_ta_id'   => $companyTAId,
                'author_id'       => $this->_auth->getCurrentUserId(),
                'dt_start'        => date('Y-m-d', $attr['dtstart']),
                'dt_end'          => date('Y-m-d', $attr['dtend']),
                'bankid'          => (int)$attr['dbankid'],
                'import_datetime' => date('Y-m-d H:i:s'),
                'records'         => $recordsCount,
                'filename'        => $fileName
            );

            $import_id = $this->_db2->insert('u_import_transactions', $arrInsertImportData);

            // Check if balance record is assigned
            $booAssignedStartBalance = $this->_clients->getAccounting()->isStartBalanceAssigned($companyTAId);

            if (!$booAssignedStartBalance) {
                // Check if we need update Balance
                $arrAlreadyImportedIfRowExists = $this->_clients->getAccounting()->getImportSummaryInfo($companyTAId);
                $importStart                   = strtotime($arrAlreadyImportedIfRowExists['min_dt_start']);

                $arrAlreadyImportedRows = $this->_clients->getAccounting()->getTrustAccountIdByCompanyTAId($companyTAId);
                $arrStartBalanceInfo    = $this->_clients->getAccounting()->getStartBalanceInfo($companyTAId);

                // Update if date for first import transaction is less than date for first imported transaction
                if ((empty($importStart) || $data[0][$rows[$dch[0]]] < $importStart)) {
                    $firstTransactionDate = date('c', $data[0][$rows[$dch[0]]] - 60 * 60 * 24);

                    // Update start balance's date only, if there are NO other imported records (only start balance)
                    if (count($arrAlreadyImportedRows) == 1 && is_array($arrStartBalanceInfo) && count($arrStartBalanceInfo)) {
                        // Update date only
                        $this->_clients->getAccounting()->updateStartBalance($companyTAId, null, $firstTransactionDate);
                    } else {
                        // Delete balance record if exists
                        $this->_clients->getAccounting()->deleteStartRecord($companyTAId);

                        // Insert a new balance record
                        $firstTransactionBalance = array_key_exists($rows[$dch[3]], $data[0]) ? $data[0][$rows[$dch[3]]] : 0;

                        $this->_clients->getAccounting()->createStartBalance($companyTAId, $firstTransactionBalance, $firstTransactionDate);
                    }

                    if (empty($startDate)) {
                        $startDate = $firstTransactionDate;
                    }
                }
            }


            for ($i = 0; $i < $recordsCount; $i++) {
                if ($cb[$i] == 0) {
                    continue;
                }

                //sort columns
                $fit         = @$data[$i]['fit'];
                $cdate       = @$data[$i][$rows[$dch[0]]];
                $credit      = !array_key_exists($rows[$dch[1]], $data[$i]) ? null : (double)$data[$i][$rows[$dch[1]]];
                $debit       = !array_key_exists($rows[$dch[2]], $data[$i]) ? null : (double)$data[$i][$rows[$dch[2]]];
                $balance     = @$data[$i][$rows[$dch[3]]];
                $type        = @$data[$i][$rows[$dch[4]]];
                $description = @$data[$i][$rows[$dch[5]]];

                $arrInsertData = array(
                    'company_ta_id'  => $companyTAId,
                    'import_id'      => (int)$import_id,
                    'fit'            => $fit,
                    'date_from_bank' => date('Y-m-d', $cdate),
                    'description'    => $description,
                    'deposit'        => $debit,
                    'withdrawal'     => $credit,
                    'balance'        => (double)$balance,
                    'purpose'        => $type
                );
                $this->_db2->insert('u_trust_account', $arrInsertData);

                if (empty($startDate)) {
                    $startDate = date('Y-m-d', $cdate);
                }
                $recordsAddedCount++;
            }

            // update bankID (always) and allow_new_bank_id (if needed)
            $this->_db2->update('company_ta', ['bankid' => (int)$attr['dbankid']], ['company_ta_id' => $companyTAId]);

            $this->_clients->getAccounting()->updateTrustAccountRecordsBalance($companyTAId, $startDate);

            // Unlink temporary file
            @unlink($file);
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'records' => $recordsAddedCount
        );

        $view = new JsonModel();
        $view->setTerminal(true);
        return $view->setVariables($arrResult);
    }

    /**
     * Add manual transactions to the Client Account
     */
    public function addManualTransactionsAction()
    {
        $strError = '';

        try {
            // Check if user has access to Company T/A id
            $companyTaId = (int)$this->params()->fromPost('ta_id');
            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTaId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $arrRecords = Json::decode($this->params()->fromPost('ta_records'), Json::TYPE_ARRAY);
            if (empty($strError) && (!is_array($arrRecords) || !count($arrRecords))) {
                $strError = $this->_tr->translate('Please add transaction records.');
            }

            $booUpdateStartBalanceDate = false;
            $arrAllTransactions        = array();
            if (empty($strError)) {
                $arrAllTransactions = $this->_clients->getAccounting()->getTrustAccount()->getTransactionsByCompanyTaId($companyTaId);

                $startBalanceInfo  = $this->_clients->getAccounting()->getStartBalanceInfo($companyTaId);
                $startBalanceDate  = isset($startBalanceInfo['date_from_bank']) ? date('Y-m-d', strtotime($startBalanceInfo['date_from_bank'] . ' + 1 days')) : '';
                $lastReconcileDate = $this->_clients->getAccounting()->getLastReconcileDate($companyTaId, true);

                // Allow to create new transactions if there is only one start balance record and no reconciliation was done
                if (Settings::isDateEmpty($lastReconcileDate) && count($arrAllTransactions) == 1 && isset($startBalanceInfo['date_from_bank'])) {
                    $startDate                 = '';
                    $booUpdateStartBalanceDate = true;
                } else {
                    $startDate = Settings::isDateEmpty($lastReconcileDate) ? $startBalanceDate : date('Y-m-d', strtotime($lastReconcileDate . ' + 1 days'));
                }

                $filter = new StripTags();
                foreach ($arrRecords as &$arrNewRecordInfo) {
                    if (!$this->_settings->isValidDate($arrNewRecordInfo['rec_date'], 'Y-m-d')) {
                        $strError = $this->_tr->translate('Incorrectly selected date.');
                    }

                    if (!Settings::isDateEmpty($startDate) && strtotime($arrNewRecordInfo['rec_date']) < strtotime($startDate)) {
                        $strError = $this->_tr->translate('Please enter a date after ' . date('d M Y', strtotime($startDate)));
                    }

                    if (empty($strError) && empty($arrNewRecordInfo['rec_description'])) {
                        $strError = $this->_tr->translate('Please specify a description.');
                    } else {
                        $arrNewRecordInfo['rec_description'] = $filter->filter($arrNewRecordInfo['rec_description']);
                    }

                    if (empty($strError) && (!array_key_exists('rec_deposit', $arrNewRecordInfo) || !is_numeric($arrNewRecordInfo['rec_deposit']))) {
                        $arrNewRecordInfo['rec_deposit'] = 0;
                    }

                    if (empty($strError) && (!array_key_exists('rec_withdrawal', $arrNewRecordInfo) || !is_numeric($arrNewRecordInfo['rec_withdrawal']))) {
                        $arrNewRecordInfo['rec_withdrawal'] = 0;
                    }

                    if (empty($strError) && empty($arrNewRecordInfo['rec_withdrawal']) && empty($arrNewRecordInfo['rec_deposit'])) {
                        $strError = $this->_tr->translate('Please specify deposit or withdrawal for the record.');
                    }

                    if (empty($strError) && !empty($arrNewRecordInfo['rec_withdrawal']) && !empty($arrNewRecordInfo['rec_deposit'])) {
                        $strError = $this->_tr->translate('Please specify ONLY deposit or withdrawal for the record.');
                    }
                    if (empty($strError) && (!is_numeric($arrNewRecordInfo['rec_withdrawal']) || !is_numeric($arrNewRecordInfo['rec_deposit']))) {
                        $strError = $this->_tr->translate('Please specify correct deposit or withdrawal for the record.');
                    }

                    // Don't check next records
                    if (!empty($strError)) {
                        break;
                    }
                }
                unset($arrNewRecordInfo);
            }

            $arrInsertInfo = array();
            $minDate       = 0;
            $maxDate       = 0;
            if (empty($strError)) {
                // Sort records by date
                foreach ($arrRecords as $key => $row) {
                    $arrDates[$key] = strtotime($row['rec_date']);
                }
                array_multisort($arrDates, SORT_ASC, $arrRecords);

                // Calculate starting balance (for the record before the first new manual record)
                $balance = is_array($arrAllTransactions) && count($arrAllTransactions) ? $arrAllTransactions[0]['balance'] + $arrAllTransactions[0]['deposit'] - $arrAllTransactions[0]['withdrawal'] : 0;

                // Prepare data to insert to DB
                foreach ($arrRecords as $arrNewRecordInfo) {
                    $time    = strtotime($arrNewRecordInfo['rec_date']);
                    $minDate = empty($minDate) ? $time : $minDate;
                    $minDate = min($minDate, $time);
                    $maxDate = $maxDate > $time ? $maxDate : $time;

                    $arrInsertInfo[] = array(
                        'company_ta_id'  => $companyTaId,
                        'import_id'      => null,
                        'fit'            => null,
                        'date_from_bank' => $arrNewRecordInfo['rec_date'],
                        'description'    => $arrNewRecordInfo['rec_description'],
                        'deposit'        => empty($arrNewRecordInfo['rec_deposit']) ? null : (double)$arrNewRecordInfo['rec_deposit'],
                        'withdrawal'     => empty($arrNewRecordInfo['rec_withdrawal']) ? null : (double)$arrNewRecordInfo['rec_withdrawal'],
                        'balance'        => (double)$balance,
                        'purpose'        => empty($arrNewRecordInfo['rec_deposit']) ? 'DEBIT' : 'CREDIT'
                    );

                    $balance = $balance + $arrNewRecordInfo['rec_deposit'] - $arrNewRecordInfo['rec_withdrawal'];
                }
            }

            if (empty($strError) && count($arrInsertInfo)) {
                foreach ($arrInsertInfo as $arrInsert) {
                    $this->_db2->insert('u_trust_account', $arrInsert);
                }

                $arrInsertImportData = array(
                    'company_ta_id'   => $companyTaId,
                    'author_id'       => $this->_auth->getCurrentUserId(),
                    'dt_start'        => date('Y-m-d', $minDate),
                    'dt_end'          => date('Y-m-d', $maxDate),
                    'bankid'          => null,
                    'import_datetime' => date('Y-m-d H:i:s'),
                    'records'         => count($arrInsertInfo),
                    'filename'        => null
                );

                $this->_db2->insert('u_import_transactions', $arrInsertImportData);

                // if start balance record was not created => create it
                $select = (new Select())
                    ->from('u_trust_account')
                    ->where(['company_ta_id' => $companyTaId])
                    ->order('date_from_bank');

                $arrFirstRecord = $this->_db2->fetchRow($select);

                if (!empty($arrFirstRecord) && !$this->_clients->getAccounting()->startBalanceRecordExists($companyTaId)) {
                    $this->_clients->getAccounting()->createStartBalance(
                        $companyTaId,
                        $arrFirstRecord['balance'],
                        date('c', strtotime($arrFirstRecord['date_from_bank']) - 60 * 60 * 24)
                    );
                } elseif ($booUpdateStartBalanceDate) {
                    $this->_clients->getAccounting()->updateStartBalance(
                        $companyTaId,
                        null,
                        date('c', $minDate - 60 * 60 * 24)
                    );
                }

                $this->_clients->getAccounting()->updateTrustAccountRecordsBalance($companyTaId, date('Y-m-d', $minDate));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'msg' => $strError));
    }

    public function indexAction()
    {
        exit;
    }
}
