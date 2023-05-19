<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class RemoveImmiFunctionality extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 140 AND `resource_privilege` = 'submit-immi';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE rule_id = 140 AND `resource_privilege` = 'stop-immi-submission';");

        $this->execute("ALTER TABLE `country_master`
                            DROP COLUMN `immi_code_4`,
                            DROP COLUMN `immi_code_num`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'index', 'submit-immi');");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ('140', 'forms', 'index', 'stop-immi-submission');");

        $this->execute("ALTER TABLE `country_master`
                            ADD COLUMN `immi_code_4` VARCHAR(5) NOT NULL DEFAULT '' AFTER `immi_code_3`,
                            ADD COLUMN `immi_code_num` VARCHAR(5) NOT NULL DEFAULT '' AFTER `immi_code_4`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}