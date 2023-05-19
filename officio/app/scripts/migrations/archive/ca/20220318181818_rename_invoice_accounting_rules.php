<?php

use Officio\Migration\AbstractMigration;

class RenameInvoiceAccountingRules extends AbstractMigration
{
    public function up()
    {
        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Pay Now')
            ->where(['rule_check_id' => 'clients-accounting-ft-add-fees-received'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Generate Invoice')
            ->where(['rule_check_id' => 'clients-accounting-ft-generate-invoice'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Generate Receipt')
            ->where(['rule_check_id' => 'clients-accounting-ft-generate-receipt'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees & Disbursements: Delete')
            ->where(['rule_check_id' => 'clients-accounting-ft-error-correction'])
            ->execute();
    }

    public function down()
    {
        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees Due: Pay Now')
            ->where(['rule_check_id' => 'clients-accounting-ft-add-fees-received'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees Due: Generate Invoice')
            ->where(['rule_check_id' => 'clients-accounting-ft-generate-invoice'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees Due: Generate Receipt')
            ->where(['rule_check_id' => 'clients-accounting-ft-generate-receipt'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees Due: Error Correction')
            ->where(['rule_check_id' => 'clients-accounting-ft-error-correction'])
            ->execute();
    }
}
