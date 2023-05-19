<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class KeyStaffFormIntoFolder extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BCPNP'
            WHERE fv.FileName = 'Key Staff Application Form';
        "
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
    }
}