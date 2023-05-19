<?php

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;

class IntroduceBnpFinalReport extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`) VALUES
            (12, 12, '', CURRENT_TIMESTAMP(), 'BNP-Final_Report.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-Final Report', '', '');                                                                                                                                                                                    (1, 1, '', CURRENT_TIMESTAMP(), 'EDS-Employer_Eligibility_Application.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EDS-Employer Eligibility Application', '', '');
        "
        );

        $this->execute(
            "
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BNP Forms'
            WHERE fv.FormVersionId IN (12); 
        "
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute(
            "
            DELETE FROM `FormVersion` WHERE `FormVersionId` IN (12);
        "
        );

        $this->execute(
            "
            DELETE FROM `FormUpload` WHERE `FormId` IN (12);
        "
        );
    }
}