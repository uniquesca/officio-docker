<?php

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;

class IntroduceMoreBnpForms extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`) VALUES
            (5, 5, '', CURRENT_TIMESTAMP(), 'BNP-Expression_Of_Interest.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-Expression Of Interest', '', ''),
            (6, 6, '', CURRENT_TIMESTAMP(), 'BNP-Formal_Application.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-Formal Application', '', ''),
            (7, 7, '', CURRENT_TIMESTAMP(), 'BNP-Good_Faith_Deposit.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-Good Faith Deposit', '', ''),
            (8, 8, '', CURRENT_TIMESTAMP(), 'BNP-Arrival_Report.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-Arrival Report', '', ''),
            (9, 9, '', CURRENT_TIMESTAMP(), 'BNP-BPA_Interim_Report_One.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-BPA Interim Report One', '', ''),
            (10, 10, '', CURRENT_TIMESTAMP(), 'BNP-BPA_Interim_Report_Two.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-BPA Interim Report Two', '', ''),
            (11, 11, '', CURRENT_TIMESTAMP(), 'BNP-BPA_Interim_Report_Three.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'BNP-BPA Interim Report Three', '', '');                                                                                                                                                                                    (1, 1, '', CURRENT_TIMESTAMP(), 'EDS-Employer_Eligibility_Application.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EDS-Employer Eligibility Application', '', '');
        "
        );

        $this->execute(
            "
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BNP Forms'
            WHERE fv.FormVersionId IN (5, 6, 7, 8, 9, 10, 11); 
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
            DELETE FROM `FormVersion` WHERE `FormVersionId` IN (5, 6, 7, 8, 9, 10, 11);
        "
        );

        $this->execute(
            "
            DELETE FROM `FormUpload` WHERE `FormId` IN (5, 6, 7, 8, 9, 10, 11);
        "
        );
    }
}