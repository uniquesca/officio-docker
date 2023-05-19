<?php

use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroduceDmForms extends AbstractMigration
{
    public function up()
    {
        $this->execute("
            INSERT INTO `FormFolder` (`ParentId`, `FolderName`)
            SELECT 0, 'Dominica Forms';
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 1, 1, '', CURRENT_TIMESTAMP(), 'D1_Citizenship_by_investment.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'D1 Application for Citizenship By Investment Form', '', '';
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 2, 2, '', CURRENT_TIMESTAMP(), '12_Particulars_of_Applicant.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'Form 12 - Particulars of Applicant', '', '';
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 3, 3, '', CURRENT_TIMESTAMP(), 'D3_Medical_Qnr.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'D3 Medical Questionnaire', '', '';
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 4, 4, '', CURRENT_TIMESTAMP(), 'D4_Investment_Agreement.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'D4 Investment Agreement', '', '';
        ");

        $this->execute("
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'Dominica Forms';
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