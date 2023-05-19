<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class AddKosovoCountry extends AbstractMigration
{
    public function up()
    {
        $this->getAdapter()->beginTransaction();

        $this->execute(
            "INSERT INTO `country_master` (`countries_name`, `countries_iso_code_2`, `countries_iso_code_3`) 
            VALUES ('Kosovo', 'XK', 'UNK');
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }

        $this->getAdapter()->commitTransaction();
    }

    public function down()
    {
        $this->getAdapter()->beginTransaction();

        $this->execute("DELETE FROM `country_master` WHERE  `countries_iso_code_2` = 'XK' && `countries_iso_code_3` = 'UNK';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }

        $this->getAdapter()->commitTransaction();
    }
}