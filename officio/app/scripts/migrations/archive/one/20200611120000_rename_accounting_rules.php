<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class renameAccountingRules extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Fees Due: Charge Client' WHERE `rule_description`='Financial Transactions: Add Fees Due'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Fees Due: Mark as Paid' WHERE `rule_description`='Financial Transactions: Add Fees Received'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Fees Due: Generate Invoice' WHERE `rule_description`='Financial Transactions: Generate Invoice'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Fees Due: Generate Receipt' WHERE `rule_description`='Financial Transactions: Generate Receipt'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Fees Due: Error Correction' WHERE `rule_description`='Financial Transactions: Error Correction'");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Financial Transactions: Add Fees Due' WHERE `rule_description`='Fees Due: Charge Client'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Financial Transactions: Add Fees Received' WHERE `rule_description`='Fees Due: Mark as Paid'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Financial Transactions: Generate Invoice' WHERE `rule_description`='Fees Due: Generate Invoice'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Financial Transactions: Generate Receipt' WHERE `rule_description`='Fees Due: Generate Receipt'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Financial Transactions: Error Correction' WHERE `rule_description`='Fees Due: Error Correction'");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}
