<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddDeleteCompanyInvoiceRule extends AbstractMigration
{
    public function up()
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->insert('acl_rules', array(
            'rule_parent_id'   => 1042,
            'module_id'        => 'superadmin',
            'rule_description' => 'Delete Company Invoices',
            'rule_check_id'    => 'delete-company-invoices',
            'superadmin_only'  => 1,
            'rule_visible'     => 1,
            'rule_order'       => 1,
        ));
        $id = $db->lastInsertId('acl_rules');

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($id, 'superadmin', 'manage-company', 'delete-invoice');");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, $id, 'Delete Company Invoices', 1);");
        $this->execute("INSERT IGNORE INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, $id
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 1042 AND r.role_type = 'superadmin';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_check_id` = 'delete-company-invoices';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}