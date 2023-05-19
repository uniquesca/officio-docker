<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddClientFileNumberSettings extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
            (1040, 'superadmin', 'manage-company', 'client-file-number-settings', 1),
            (1040, 'superadmin', 'manage-company', 'client-file-number-settings-save', 1),
            (11, 'clients', 'profile', 'generate-client-file-number', 1);"
        );

        $this->execute(
            "ALTER TABLE `company_details`
	        ADD COLUMN `client_file_number_settings` TEXT NULL COMMENT 'Client file number generation settings' AFTER `marketplace_module_enabled`;"
        );

        $this->execute(
            "ALTER TABLE `clients`
            ADD COLUMN `client_number_in_company` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `fileNumber`;"
        );

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select        = $db->select()
            ->from(array('m' => 'members'))
            ->where('userType = ?', 3, 'INT')
            ->order('member_id ASC');
        $arrAllClients = $db->fetchAll($select);

        $arrGroupedClients = array();
        foreach ($arrAllClients as $arrAllClientInfo) {
            $arrGroupedClients[$arrAllClientInfo['company_id']][] = $arrAllClientInfo['member_id'];
        }

        foreach ($arrGroupedClients as $arrGroupedMembers) {
            foreach ($arrGroupedMembers as $num => $memberId) {
                $db->update(
                    'clients',
                    array('client_number_in_company' => $num + 1),
                    $db->quoteInto('member_id = ?', $memberId, 'INT')
                );
            }
        }

        $this->execute(
            "INSERT INTO `admin_navigation` (`section_id`, `resource_id`, `action`, `title`, `order_id`) VALUES 
            (4, 'manage-company', 'client-file-number-settings', 'Client File Number Settings', 15),
            (2, 'manage-company', 'client-file-number-settings', 'Default Client File Number Settings', 10);"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1040 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='client-file-number-settings';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1040 AND `module_id`='superadmin' AND `resource_id`='manage-company' AND `resource_privilege`='client-file-number-settings-save';");
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=11 AND `module_id`='clients' AND `resource_id`='profile' AND `resource_privilege`='generate-client-file-number';");

        $this->execute(
            "ALTER TABLE `company_details`
	      DROP COLUMN `client_file_number_settings`;"
        );

        $this->execute(
            "ALTER TABLE `clients`
	      DROP COLUMN `client_number_in_company`;"
        );

        $this->execute("DELETE FROM `admin_navigation` WHERE `resource_id`='manage-company' AND `action`='client-file-number-settings';");
    }
}