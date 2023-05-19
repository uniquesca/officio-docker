<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddNewRulesForTasksAdd extends AbstractMigration
{
    public function up()
    {
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('r' => 'acl_rules'), new Zend_Db_Expr('MAX(rule_id)'));
        $maxId  = (int)$db->fetchOne($select);


        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (" . ($maxId + 1) . ", 25, 'tasks', 'Add Tasks', 'clients-tasks-add', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (" . ($maxId + 1) . ", 'tasks', 'index', 'add');");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (" . ($maxId + 2) . ", 210, 'tasks', 'Add Tasks', 'tasks-add', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (" . ($maxId + 2) . ", 'tasks', 'index', 'add');");
        $this->execute("INSERT INTO `acl_rules` (`rule_id`, `rule_parent_id`, `module_id`, `rule_description`, `rule_check_id`, `rule_visible`) VALUES (" . ($maxId + 3) . ", 201, 'tasks', 'Add Tasks', 'prospects-tasks-add', 1);");
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES (" . ($maxId + 3) . ", 'tasks', 'index', 'add');");

        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, " . ($maxId + 1) . ", 'Add Clients Tasks', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, " . ($maxId + 2) . ", 'Add Tasks', 1);");
        $this->execute("INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES (1, " . ($maxId + 3) . ", 'Add Prospects Tasks', 1);");

        $this->execute("UPDATE `acl_rules` SET `rule_order`='1' WHERE  `rule_check_id`='clients-tasks-delete';");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='1' WHERE  `rule_check_id`='tasks-delete';");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='1' WHERE  `rule_check_id`='prospects-tasks-delete';");


        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, " . ($maxId + 1) . "
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 25;"
        );

        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, " . ($maxId + 2) . "
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 210;"
        );

        $this->execute(
            "INSERT INTO `acl_role_access`
         (`role_id`, `rule_id`)
        SELECT a.role_id, " . ($maxId + 3) . "
        FROM acl_role_access AS a
        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
        WHERE a.rule_id = 201;"
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rules` WHERE `rule_check_id` IN ('clients-tasks-add', 'tasks-add', 'prospects-tasks-add');");

        $this->execute("UPDATE `acl_rules` SET `rule_order`='0' WHERE  `rule_check_id`='clients-tasks-delete';");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='0' WHERE  `rule_check_id`='tasks-delete';");
        $this->execute("UPDATE `acl_rules` SET `rule_order`='0' WHERE  `rule_check_id`='prospects-tasks-delete';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}