<?php

use Phinx\Migration\AbstractMigration;

class AddReceiptNumberColumn extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();
        $this->execute("ALTER TABLE `u_assigned_deposits` ADD COLUMN `receipt_number` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `notes`;");

        // Add receipt numbers for old assigned deposits (unique for each company)
        /*        $select    = $db->select()
                    ->from('company_ta', array('company_ta_id', 'company_id'))
                    ->order(array('company_id', 'company_ta_id'));
                $arrCompanyCompanyTa = $db->fetchAll($select);

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
                    $select    = $db->select()
                        ->from('u_assigned_deposits')
                        ->where('company_ta_id IN (?)', $arrCompanyTaIds)
                        ->order('date_of_event');
                    $arrCompanyAssignedDeposits = $db->fetchAll($select);
                    foreach ($arrCompanyAssignedDeposits as $arrDeposit) {
                        $receiptNumber++;
                        $arrToInsert = array(
                            'receipt_number'   => $db->quote($receiptNumber, 'INTEGER'),
                        );

                        $db->update('u_assigned_deposits', $arrToInsert, 'deposit_id = ' . $arrDeposit['deposit_id']);
                    }
                }*/

        $this->execute("ALTER TABLE `company_details` ADD COLUMN `max_receipt_number` BIGINT(20) NULL DEFAULT NULL AFTER `pricing_category_id`;");
        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();
        $this->execute("ALTER TABLE `u_assigned_deposits` DROP COLUMN `receipt_number`;");

        $this->execute("ALTER TABLE `company_details` DROP COLUMN `max_receipt_number`;");
        $db->commit();
    }
}