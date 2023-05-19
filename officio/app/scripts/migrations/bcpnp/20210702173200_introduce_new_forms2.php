<?php

use Officio\Migration\AbstractMigration;

class IntroduceNewForms2 extends AbstractMigration
{
    protected $clearAclCache = true;

    public function up()
    {
        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 16, MAX(FormId) + 1, '', '2021-07-02 00:00:00', 'si-reg-v2.pdf', '21kb', '2021-07-02 00:00:00', 1, 'Skills Registration Form v2', '', ''
            FROM FormVersion;
        ");

        $this->execute("
            UPDATE client_types_forms ctf
            INNER JOIN client_types ct ON ct.client_type_id = ctf.client_type_id 
            SET ctf.form_version_id = 16 
            WHERE ct.client_type_name = 'Skills Immigration Registration';
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 17, MAX(FormId) + 1, '', '2021-07-02 00:00:00', 'si-app-v3.pdf', '21kb', '2021-07-02 00:00:00', 1, 'Skills Application Form v3', '', ''
            FROM FormVersion;
        ");

        $this->execute("
            UPDATE client_types_forms ctf
            INNER JOIN client_types ct ON ct.client_type_id = ctf.client_type_id 
            SET ctf.form_version_id = 17 
            WHERE ct.client_type_name = 'Skills Immigration Application';
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 18, MAX(FormId) + 1, '', '2021-07-02 00:00:00', 'ei-arrep-v2.pdf', '21kb', '2021-07-02 00:00:00', 1, 'Business Arrival Report Form v2', '', ''
            FROM FormVersion;
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 19, MAX(FormId) + 1, '', '2021-07-02 00:00:00', 'ei-finrep-v2.pdf', '21kb', '2021-07-02 00:00:00', 1, 'Business Final Report Form v2', '', ''
            FROM FormVersion;
        ");

        $this->execute("
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 20, MAX(FormId) + 1, '', '2021-07-02 00:00:00', 'revreq-v2.pdf', '21kb', '2021-07-02 00:00:00', 1, 'Request for Review Form v2', '', ''
            FROM FormVersion;
        ");

        $this->execute("
            INSERT INTO FormUpload
                SELECT fv.FormId, ff.FolderId
                FROM FormVersion fv
                LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BCPNP'
                WHERE fv.FileName IN (
                    'Skills Registration Form v2',
                    'Skills Application Form v3',
                    'Business Arrival Report Form v2',
                    'Business Final Report Form v2',
                    'Request for Review Form v2'
                )
        ");
    }

    public function down()
    {

    }
}