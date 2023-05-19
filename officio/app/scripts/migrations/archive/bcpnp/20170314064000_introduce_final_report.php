<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class IntroduceFinalReport extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 9, MAX(FormId) + 1, '', CURRENT_TIMESTAMP(), 'EI_Final_Report_form.pdf', '0Kb', CURRENT_TIMESTAMP(), 1, 'EI Final Report Form', '', ''
            FROM FormVersion;
            
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BCPNP'
            WHERE fv.FileName = 'EI Final Report Form';
        "
        );

        $this->execute(
            " 
            INSERT INTO `divisions` (`company_id`, `name`, `order`)
                SELECT company_id, 'EI Final Report Intake', 7
                FROM company
                
                UNION
                
                SELECT company_id, 'EI Final Report Expired', 8
                FROM company
        "
        );

        $this->execute(
            "
            INSERT INTO `client_form_default` (`field_id`, `value`, `order`)
                SELECT `field_id`, 'EI Final Report Submitted', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
                
                UNION
                
                SELECT `field_id`, 'EI Final Report Expired', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
        "
        );

        $this->execute(
            "
            INSERT INTO `FormSynField` (`FieldName`) VALUES
              ('syncA_Final_ExtUsr_LastName'),
              ('syncA_Final_ExtUsr_FirstName'),
              ('syncA_Final_ExtUsr_DOB'),
              ('syncA_Final_Contact_Phone'),
              ('syncA_Final_Contact_Email');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'last_name' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_ExtUsr_LastName'
                   
                UNION
                   
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'first_name' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_ExtUsr_FirstName'
                
                UNION
                
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'DOB' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_ExtUsr_DOB'
                   
                UNION
                   
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'phone' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_Contact_Phone'
                
                UNION
                
                SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'emailAddress' 
                FROM `FormSynField` WHERE `FieldName` = 'syncA_Final_Contact_Email';
        "
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
        $this->execute(
            "           
            DELETE FROM divisions
            WHERE `name` IN ('EI Final Report Intake', 'EI Final Report Expired');
            
            DELETE FROM client_form_default
            WHERE `value` IN ('EI Final Report Submitted', 'EI Final Report Expired');
        "
        );
    }
}