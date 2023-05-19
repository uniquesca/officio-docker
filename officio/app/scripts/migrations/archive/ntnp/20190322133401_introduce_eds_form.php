<?php

use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroduceEdsForm extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormFolder` (`ParentId`, `FolderName`)
            SELECT 0, 'EDS Forms';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`) VALUES
            (1, 1, '', CURRENT_TIMESTAMP(), 'EDS-Employer_Eligibility_Application.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EDS-Employer Eligibility Application', '', ''),
            (2, 2, '', CURRENT_TIMESTAMP(), 'EDS-Foreign_National_Submission.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EDS-Foreign National Submission', '', '');                                                                                                                                                                                                (1, 1, '', CURRENT_TIMESTAMP(), 'EDS-Employer_Eligibility_Application.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EDS-Employer Eligibility Application', '', '');
        "
        );

        $this->execute(
            "
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'EDS Forms'
            WHERE fv.FormVersionId IN (1, 2); 
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
            DELETE FROM `FormFolder` WHERE `FolderName` = 'EDS Forms';
        "
        );

        $this->execute(
            "
            DELETE FROM `FormVersion` WHERE `FormVersionId` IN (1, 2);
        "
        );

        $this->execute(
            "
            DELETE FROM `FormUpload` WHERE `FormId` IN (1, 2);
        "
        );
    }
}