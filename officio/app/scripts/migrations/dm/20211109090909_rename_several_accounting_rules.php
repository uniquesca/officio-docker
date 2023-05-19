<?php

use Officio\Migration\AbstractMigration;

class RenameSeveralAccountingRules extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'New Fees or Disbursements i.e. charge client')
            ->where(['rule_check_id' => 'clients-accounting-ft-add-fees-due'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees Due: Pay Now')
            ->where(['rule_check_id' => 'clients-accounting-ft-add-fees-received'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', '%ta_label% Assign payments to invoices')
            ->where(['rule_check_id' => 'trust-account-assign-view'])
            ->execute();
    }

    public function down()
    {
        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees Due: Charge Client')
            ->where(['rule_check_id' => 'clients-accounting-ft-add-fees-due'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', 'Fees Due: Mark as Paid')
            ->where(['rule_check_id' => 'clients-accounting-ft-add-fees-received'])
            ->execute();

        $this->getQueryBuilder()
            ->update('acl_rules')
            ->set('rule_description', '%ta_label% Assign')
            ->where(['rule_check_id' => 'trust-account-assign-view'])
            ->execute();
    }
}
