<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class FixArrivalReport extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "           
            DELETE cfd, cffa, cfo
            FROM client_form_fields cff
              LEFT JOIN  client_form_data cfd ON cff.field_id = cfd.field_id
              LEFT JOIN client_form_field_access cffa ON cff.field_id = cffa.field_id
              LEFT JOIN client_form_order cfo ON cfo.field_id = cff.field_id
            WHERE cff.company_field_id IN (
                'ei_ar_wp_client_id',
                'ei_ar_wp_valid'
            );

            DELETE FROM FormMap
            WHERE ToProfileFieldId IN (
                'ei_ar_wp_client_id',
                'ei_ar_wp_valid'
            );

            DELETE
            FROM client_form_fields
            WHERE company_field_id IN (
                'ei_ar_wp_client_id',
                'ei_ar_wp_valid'
            );
            
            DELETE FROM company_default_options WHERE default_option_abbreviation IN ('BCEAR');
            
            DELETE ctk FROM client_types_kinds ctk
            INNER JOIN client_types ct ON ct.client_type_id = ctk.client_type_id
            WHERE ct.client_type_name = 'Business Immigration Arrival Report';
            
            DELETE cffa FROM client_types ct INNER JOIN client_form_field_access cffa ON cffa.client_type_id = ct.client_type_id WHERE client_type_name = 'Business Immigration Arrival Report';
            DELETE FROM client_types WHERE client_type_name = 'Business Immigration Arrival Report';
            
            INSERT INTO FormUpload
            SELECT fv.FormId, ff.FolderId
            FROM FormVersion fv
            LEFT OUTER JOIN FormFolder ff ON ff.FolderName = 'BCPNP'
            WHERE fv.FileName = 'EI Arrival Report Form';
        "
        );

        $this->execute(
            "    
            CALL `createCaseGroup` ('Work Permit', 3, 'Business Immigration Application');
            CALL `createCaseField` ('ei_wp_client_id', 1, 'Work Permit ID', 20, 'N', 'N', 'Work Permit', 'Business Immigration Application', 'syncA_App_WorkPermit_ClientID');
            CALL `createCaseField` ('ei_wp_client_signed_on', 1, 'Work Permit Date Signed', 20, 'N', 'N', 'Work Permit', 'Business Immigration Application', 'syncA_App_WorkPermit_DateSigned');
            CALL `createCaseField` ('ei_wp_valid', 1, 'Work Permit Expiry Date', 20, 'N', 'N', 'Work Permit', 'Business Immigration Application', 'syncA_App_WorkPermit_Valid');
        "
        );

        $this->execute(
            " 
            INSERT INTO `divisions` (`company_id`, `name`, `order`)
                SELECT company_id, 'EI Arrival Report Intake', 7
                FROM company
                
                UNION
                
                SELECT company_id, 'EI Arrival Report Expired', 8
                FROM company
        "
        );

        $this->execute(
            "
            INSERT INTO `client_form_default` (`field_id`, `value`, `order`)
                SELECT `field_id`, 'EI Arrival Report Submitted', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
                
                UNION
                
                SELECT `field_id`, 'EI Arrival Report Expired', 1
                FROM `client_form_fields`
                WHERE `company_field_id` = 'file_status'
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
            DELETE cfd, cffa, cfo
            FROM client_form_fields cff
              LEFT JOIN  client_form_data cfd ON cff.field_id = cfd.field_id
              LEFT JOIN client_form_field_access cffa ON cff.field_id = cffa.field_id
              LEFT JOIN client_form_order cfo ON cfo.field_id = cff.field_id
            WHERE cff.company_field_id IN (
                'ei_ar_wp_client_id',
                'ei_ar_wp_valid'
            );

            DELETE FROM FormMap
            WHERE ToProfileFieldId IN (
                'ei_ar_wp_client_id',
                'ei_ar_wp_valid'
            );
            
            DELETE FROM divisions
            WHERE `name` IN ('EI Arrival Report Intake', 'EI Arrival Report Expired');
            
            DELETE FROM client_form_default
            WHERE `value` IN ('EI Arrival Report Submitted', 'EI Arrival Report Expired');
        "
        );
    }
}