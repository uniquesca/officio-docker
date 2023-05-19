<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class ChangeKskeydidFieldTypeUseFor extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `field_types` SET `field_type_use_for`='case' WHERE  `field_type_id`=37;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("UPDATE `field_types` SET `field_type_use_for`='all' WHERE  `field_type_id`=37;");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}