<?php

use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class makeMultipleComboSearchable extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='Y' WHERE `field_type_text_id`='multiple_combo';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='N' WHERE `field_type_text_id`='multiple_combo';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}
