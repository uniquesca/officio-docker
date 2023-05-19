<?php

use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroduceBnpForms extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormFolder` (`ParentId`, `FolderName`)
            SELECT 0, 'BNP Forms';
        "
        );

        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`) VALUES
            (3, 3, '', CURRENT_TIMESTAMP(), 'BNP-Initial_Registration.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-Initial Registration', '', ''),
            (4, 4, '', CURRENT_TIMESTAMP(), 'BNP-Pre_Interview_Submission.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-Pre-interview Submission', '', '');                                                                                                                                                                                                (1, 1, '', CURRENT_TIMESTAMP(), 'EDS-Employer_Eligibility_Application.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EDS-Employer Eligibility Application', '', '');
        "
        );

        $this->execute(
            "
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BNP Forms'
            WHERE fv.FormVersionId IN (3, 4); 
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
            DELETE FROM `FormFolder` WHERE `FolderName` = 'BNP Forms';
        "
        );

        $this->execute(
            "
            DELETE FROM `FormVersion` WHERE `FormVersionId` IN (3, 4);
        "
        );

        $this->execute(
            "
            DELETE FROM `FormUpload` WHERE `FormId` IN (3, 4);
        "
        );
    }
}