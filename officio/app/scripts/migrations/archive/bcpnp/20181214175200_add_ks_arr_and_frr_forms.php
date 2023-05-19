<?php

use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class AddKsArrAndFrrForms extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "    
            CALL `createCaseGroup` ('Work Permit', 3, 'Key Staff Application');
            CALL `createCaseField` ('ei_ks_wp_client_id', 1, 'Work Permit ID', 20, 'N', 'N', 'Work Permit', 'Key Staff Application', 'syncA_App_WorkPermit_ClientID');
            CALL `createCaseField` ('ei_ks_wp_client_signed_on', 1, 'Work Permit Date Signed', 20, 'N', 'N', 'Work Permit', 'Key Staff Application', 'syncA_App_WorkPermit_DateSigned');
            CALL `createCaseField` ('ei_ks_wp_valid', 1, 'Work Permit Expiry Date', 20, 'N', 'N', 'Work Permit', 'Key Staff Application', 'syncA_App_WorkPermit_Valid');
        "
        );

        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 14, MAX(FormId) + 1, '', '2018-12-14 00:00:00', '2018-12-14 00:00:00.pdf', '21kb', '2018-12-14 00:00:00', 1, 'Key Staff Arrival Report Form', '', ''
            FROM FormVersion;
        "
        );

        $this->execute(
            "
            INSERT INTO `FormVersion` (`FormVersionId`, `FormId`, `FormType`, `VersionDate`, `FilePath`, `Size`, `UploadedDate`, `UploadedBy`, `FileName`, `Note1`, `Note2`)
            SELECT 15, MAX(FormId) + 1, '', '2018-12-14 00:00:00', '2018-12-14 00:00:00.pdf', '21kb', '2018-12-14 00:00:00', 1, 'Key Staff Final Report Form', '', ''
            FROM FormVersion;
        "
        );

        $this->execute(
            "
            INSERT INTO FormUpload
                SELECT fv.FormId, ff.FolderId
                FROM FormVersion fv
                LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BCPNP'
                WHERE fv.FileName = 'Key Staff Arrival Report Form';
        "
        );

        $this->execute(
            "
            INSERT INTO FormUpload
                SELECT fv.FormId, ff.FolderId
                FROM FormVersion fv
                LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BCPNP'
                WHERE fv.FileName = 'Key Staff Final Report Form';
        "
        );

        $this->execute(
            " 
            INSERT INTO `divisions` (division_group_id, `company_id`, `name`, `order`)
                SELECT dg.division_group_id, c.company_id, 'EI KS Arrival Report Intake', 7
                FROM company c
                INNER JOIN divisions_groups dg ON dg.company_id = c.company_id
                
                UNION
                
                SELECT dg.division_group_id, c.company_id, 'EI KS Arrival Report Expired', 8
                FROM company c
                INNER JOIN divisions_groups dg ON dg.company_id = c.company_id

                UNION

                SELECT dg.division_group_id, c.company_id, 'EI KS Final Report Intake', 9
                FROM company c
                INNER JOIN divisions_groups dg ON dg.company_id = c.company_id

                UNION
                
                SELECT dg.division_group_id, c.company_id, 'EI KS Final Report Expired', 10
                FROM company c
                INNER JOIN divisions_groups dg ON dg.company_id = c.company_id;
        "
        );

        $this->execute(
            "
            INSERT INTO `client_form_default` (`field_id`, `value`, `order`)
                SELECT `field_id`, 'EI KS Arrival Report Submitted', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
                
                UNION
                
                SELECT `field_id`, 'EI KS Arrival Report Expired', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'

                UNION
            
                SELECT `field_id`, 'EI KS Final Report Submitted', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
                
                UNION
                
                SELECT `field_id`, 'EI KS Final Report Expired', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'

                UNION

                SELECT `field_id`, 'EI KS - Approved - WP', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
                
                UNION
                
                SELECT `field_id`, 'EI KS Arrival Report Expired', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
        "
        );


        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        \Officio\Service\Acl::clearCache($cache);
    }

    public function down()
    {
    }
}