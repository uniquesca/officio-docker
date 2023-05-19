<?php

use Phinx\Migration\AbstractMigration;

class AddCompaniesPurgedField extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `company_details`
        	ADD COLUMN `purged` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `use_annotations`,
        	ADD COLUMN `purged_details` TEXT NULL DEFAULT NULL AFTER `purged`;"
        );


        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('c' => 'company'), array('company_id'))
            ->joinLeft(array('cd' => 'company_details'), 'c.company_id = cd.company_id')
            ->where('c.`Status` = ?', 0)
            ->where('cd.next_billing_date < ?', '2018-03-01');

        $arrCompanyIds = $db->fetchCol($select);


        $i = 0;

        $companiesCount = count($arrCompanyIds);
        foreach ($arrCompanyIds as $companyId) {
            $subSelect = $select = $db->select()
                ->from(array('members'), array('member_id'))
                ->where('company_id = ?', $companyId, 'INT');


            $select = $db->select()
                ->from(array('eml_accounts'), array('id'))
                ->where('member_id IN (?)', new Zend_Db_Expr($subSelect->__toString()));

            $arrEmlAccounts = $db->fetchCol($select);

            $accountsCount = count($arrEmlAccounts);

            $emailsCount = 0;
            if ($accountsCount) {
                $select = $db->select()
                    ->from(array('eml_messages'), new Zend_Db_Expr('COUNT(id)'))
                    ->where('id_account IN (?)', $arrEmlAccounts, 'INT');

                $emailsCount = $db->fetchOne($select);
            }

            if ($accountsCount) {
                $msg = sprintf('Feb 16, 2019 - All email data deleted  (%d email accounts with total of %d emails)', $accountsCount, $emailsCount);
            } else {
                $msg = 'Feb 16, 2019 - No email to delete';
            }

            $db->update(
                'company_details',
                array(
                    'purged'         => 'Y',
                    'purged_details' => $msg,
                ),

                $db->quoteInto('company_id = ?', $companyId, 'INT')
            );

            echo sprintf(
                    'Processed company id %d (%d from %d companies)',
                    $companyId,
                    ++$i,
                    $companiesCount
                ) . PHP_EOL;
        }
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `company_details`
        	DROP COLUMN `purged`,
        	DROP COLUMN `purged_details`;"
        );
    }
}