<?php

use Officio\Migration\AbstractMigration;

class AddReceiptNumberColumn extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_assigned_deposits` ADD COLUMN `receipt_number` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `notes`;");

        // Add receipt numbers for old assigned deposits (unique for each company)
        $statement = $this->getQueryBuilder()
            ->select(array('company_ta_id', 'company_id'))
            ->from('company_ta')
            ->order(array('company_id', 'company_ta_id'))
            ->execute();

        $arrCompanyCompanyTa = $statement->fetchAll('assoc');

        $arrGroupedCompanyTa = array();
        foreach ($arrCompanyCompanyTa as $arr) {
            if (isset($arrGroupedCompanyTa[$arr['company_id']])) {
                $arrGroupedCompanyTa[$arr['company_id']][] = $arr['company_ta_id'];
            } else {
                $arrGroupedCompanyTa[$arr['company_id']] = array($arr['company_ta_id']);
            }
        }

        foreach ($arrGroupedCompanyTa as $companyId => $arrCompanyTaIds) {
            $receiptNumber = 0;

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('u_assigned_deposits')
                ->where(['company_ta_id IN' => $arrCompanyTaIds])
                ->order('date_of_event')
                ->execute();

            $arrCompanyAssignedDeposits = $statement->fetchAll('assoc');
            foreach ($arrCompanyAssignedDeposits as $arrDeposit) {
                $receiptNumber++;
                $arrToInsert = array(
                    'receipt_number' => $receiptNumber,
                );

                $this->getQueryBuilder()
                    ->update('u_assigned_deposits')
                    ->set($arrToInsert)
                    ->where(['deposit_id' => $arrDeposit['deposit_id']])
                    ->execute();
            }
        }

        $this->execute("ALTER TABLE `company_details` ADD COLUMN `max_receipt_number` BIGINT(20) NULL DEFAULT NULL AFTER `pricing_category_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_assigned_deposits` DROP COLUMN `receipt_number`;");
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `max_receipt_number`;");
    }
}
