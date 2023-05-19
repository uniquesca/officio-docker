<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddKosovoCountry extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `country_master` VALUES (NULL,'Kosovo','XK','UNK','RKS','KOSO',122);
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute(
            "
            DELETE FROM country_master WHERE countries_name = 'Kosovo';
        "
        );
    }
}