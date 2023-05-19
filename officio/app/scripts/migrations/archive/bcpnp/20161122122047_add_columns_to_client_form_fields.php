<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddColumnsToClientFormFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields`
	        ADD COLUMN `min_value` INT(11) NULL DEFAULT NULL AFTER `custom_height`,
	        ADD COLUMN `max_value` INT(11) NULL DEFAULT NULL AFTER `min_value`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields`
	      DROP COLUMN `min_value`,
	      DROP COLUMN `max_value`;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}