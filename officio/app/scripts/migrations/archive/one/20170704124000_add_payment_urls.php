<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddPaymentUrls extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (70, 'default', 'tran-page', 'pre-request', 1)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (70, 'default', 'tran-page', 'generate-invoice', 1)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1, 'default', 'tran-page', 'process-response', 1)");

        $this->execute("CREATE TABLE `u_payment_invoices` (
        	`u_payment_invoice_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        	`member_id` BIGINT(20) NOT NULL,
        	`company_ta_id` INT(11) NOT NULL,
        	`amount` DOUBLE(11,2) NOT NULL,
        	`message` TEXT NULL,
        	`tranpage_approval_code` VARCHAR(12) NULL DEFAULT NULL,
        	`invoice_date` DATETIME NOT NULL,
        	`status` ENUM('C','F','Q') NOT NULL DEFAULT 'Q' COMMENT 'C - complete, F - failed, Q - queued',
        	PRIMARY KEY (`u_payment_invoice_id`),
        	INDEX `FK_u_payment_invoices_members` (`member_id`),
        	INDEX `FK_u_payment_invoices_company_ta` (`company_ta_id`),
        	CONSTRAINT `FK_u_payment_invoices_company_ta` FOREIGN KEY (`company_ta_id`) REFERENCES `company_ta` (`company_ta_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_u_payment_invoices_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='TranPage invoices will be saved here'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=70 AND `module_id`='default' AND `resource_id`='tran-page' AND `resource_privilege`='pre-request'");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=70 AND `module_id`='default' AND `resource_id`='tran-page' AND `resource_privilege`='generate-invoice'");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1 AND `module_id`='default' AND `resource_id`='tran-page' AND `resource_privilege`='process-response'");
        $this->execute("DROP TABLE `u_payment_invoices`");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}
