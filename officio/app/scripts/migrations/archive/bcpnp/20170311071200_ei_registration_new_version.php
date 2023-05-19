<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class EiRegistrationNewVersion extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 8, FormId, '', CURRENT_TIMESTAMP(), 'EI_Registration_Form_v2.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EI Registration Form v2', '', ''
            FROM FormVersion
            WHERE FileName = 'EI Registration Form';
        "
        );

        $this->execute(
            "
            UPDATE client_types SET form_version_id = 8 WHERE client_type_name = 'Business Immigration Registration';
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