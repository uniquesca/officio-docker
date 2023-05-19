<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class SiApplicationNewVersion extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 10, FormId, '', CURRENT_TIMESTAMP(), 'SI_Application_Form_v2.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'SI Application Form v2', '', ''
            FROM FormVersion
            WHERE FileName = 'SI Application Form';
        "
        );

        $this->execute(
            "
            UPDATE client_types_forms ctf
            INNER JOIN client_types ct ON ct.client_type_id = ctf.client_type_id 
            SET ctf.form_version_id = 10 
            WHERE ct.client_type_name = 'Skills Immigration Application';
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