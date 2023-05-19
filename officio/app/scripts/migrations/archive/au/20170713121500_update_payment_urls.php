<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class UpdatePaymentUrls extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=70 AND `module_id`='default' AND `resource_id`='tran-page' AND `resource_privilege`='pre-request'");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=70 AND `module_id`='default' AND `resource_id`='tran-page' AND `resource_privilege`='generate-invoice'");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1 AND `module_id`='default' AND `resource_id`='tran-page' AND `resource_privilege`='process-response'");

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (70, 'default', 'tran-page', 'process-payment', 1)");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (70, 'default', 'tran-page', 'pre-request', 1)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (70, 'default', 'tran-page', 'generate-invoice', 1)");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1, 'default', 'tran-page', 'process-response', 1)");

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=70 AND `module_id`='default' AND `resource_id`='tran-page' AND `resource_privilege`='process-payment'");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}
