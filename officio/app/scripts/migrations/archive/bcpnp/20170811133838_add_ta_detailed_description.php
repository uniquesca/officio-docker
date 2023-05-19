<?php

use Phinx\Migration\AbstractMigration;

class AddTaDetailedDescription extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();
        $this->execute("ALTER TABLE `company_ta` ADD COLUMN `detailed_description` TEXT NULL DEFAULT NULL AFTER `name`;");
        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();
        $this->execute("ALTER TABLE `company_ta` DROP COLUMN `detailed_description`;");
        $db->commit();
    }
}