<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class AddKskeyFieldToEiApp extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            CALL `createCaseField` ('kskeydid', 37, 'KSKEYdId', 0, 'N', 'N', 'Case Details', 'Business Immigration Application', null);
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
    }
}