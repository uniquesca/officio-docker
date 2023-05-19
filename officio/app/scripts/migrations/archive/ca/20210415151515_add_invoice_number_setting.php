<?php

use Officio\Common\Json;
use Officio\Migration\AbstractMigration;

class AddInvoiceNumberSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `invoice_number_settings` TEXT NULL DEFAULT NULL COMMENT 'Invoice number generation settings' AFTER `case_number_settings`;");

        // Prefill default settings
        $arrCompanies = $this->fetchAll('SELECT * FROM company');
        foreach ($arrCompanies as $arrCompanyInfo) {
            $arrCompanyTAs = $this->fetchAll(sprintf('SELECT company_ta_id FROM company_ta WHERE company_id = %d', $arrCompanyInfo['company_id']));

            $maxInvoiceNumber = 0;
            if (!empty($arrCompanyTAs)) {
                $arrCompanyTAIds = array();
                foreach ($arrCompanyTAs as $arrCompanyTAInfo) {
                    $arrCompanyTAIds[] = $arrCompanyTAInfo['company_ta_id'];
                }

                // Get the max from the invoices table
                $arrCompanyInvoiceInfo = $this->fetchRow(sprintf('SELECT invoice_num FROM u_invoice WHERE company_ta_id IN (%s) ORDER BY (invoice_num+0) DESC', implode(',', $arrCompanyTAIds)));
                $invoiceMax            = isset($arrCompanyInvoiceInfo['invoice_num']) ? $arrCompanyInvoiceInfo['invoice_num'] : 0;

                // Get the max from the payments table
                $arrCompanyPaymentInfo = $this->fetchRow(sprintf('SELECT MAX(invoice_number) as max_invoice_num FROM u_payment WHERE company_ta_id IN (%s)', implode(',', $arrCompanyTAIds)));
                $paymentMax            = isset($arrCompanyPaymentInfo['max_invoice_num']) ? $arrCompanyPaymentInfo['max_invoice_num'] : 0;

                $maxInvoiceNumber = max($invoiceMax, $paymentMax);
            }

            $maxInvoiceNumber = empty($maxInvoiceNumber) ? 1 : $maxInvoiceNumber + 1;

            $arrSet = [
                'format'     => '{sequence_number}',
                'start_from' => $maxInvoiceNumber
            ];

            $this->getQueryBuilder()
                ->update('company_details')
                ->set('invoice_number_settings', Json::encode($arrSet))
                ->where(['company_id' => $arrCompanyInfo['company_id']])
                ->execute();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `invoice_number_settings`;");
    }
}
