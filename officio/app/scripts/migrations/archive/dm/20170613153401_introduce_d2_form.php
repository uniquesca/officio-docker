<?php

use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroduceD2Form extends AbstractMigration
{
    public function up()
    {
        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 5, 5, '', CURRENT_TIMESTAMP(), 'D2_Fingerprint_Photo_Verification.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'D2 Fingerprint and Photos Verification Form', '', '';
        ");

        $this->execute("
            INSERT INTO FormUpload
            SELECT 5, ff.FolderId
            FROM FormFolder ff
            WHERE ff.FolderName = 'Dominica Forms';
        ");

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        
    }
}