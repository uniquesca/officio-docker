<?php

use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;
use Laminas\Cache\Storage\StorageInterface;

class NewSiSyncFields extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "           
            CALL `createCaseGroup` ('Post-Secondary Education', 3, 'Skills Immigration Application');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_edu_bc_to', 34, 'Education in BC: To', 0, 'N', 'N', 'Post-Secondary Education', 'Skills Immigration Application', 'syncA_App_EduBC_To');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_edu_bc_institution', 34, 'Education in BC: Institution', 0, 'N', 'N', 'Post-Secondary Education', 'Skills Immigration Application', 'syncA_App_EduBC_Institution');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_edu_bc_field', 34, 'Education in BC: Field', 0, 'N', 'N', 'Post-Secondary Education', 'Skills Immigration Application', 'syncA_App_EduBC_Field');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_edu_can_to', 34, 'Education in Canada: To', 0, 'N', 'N', 'Post-Secondary Education', 'Skills Immigration Application', 'syncA_App_EduCAN_To');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_edu_can_institution', 34, 'Education in Canada: Institution', 0, 'N', 'N', 'Post-Secondary Education', 'Skills Immigration Application', 'syncA_App_EduCAN_Institution');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_edu_can_field', 34, 'Education in Canada: Field', 0, 'N', 'N', 'Post-Secondary Education', 'Skills Immigration Application', 'syncA_App_EduCAN_Field');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_job_offer_full_time', 1, 'Applicant has offer of full-time employment', 0, 'N', 'N', 'Job Offer', 'Skills Immigration Application', 'syncA_App_FullTimeEmpOffer');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_job_offer_permanent', 1, 'Job offer is permanent', 0, 'N', 'N', 'Job Offer', 'Skills Immigration Application', 'syncA_App_FullTimeEmpOfferInd');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_job_offer_address', 34, 'Work Address', 0, 'N', 'N', 'Job Offer', 'Skills Immigration Application', 'syncA_App_Job_WorkLocationAddr');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_job_offer_phone', 34, 'Work Phone', 0, 'N', 'N', 'Job Offer', 'Skills Immigration Application', 'syncA_App_Job_WorkLocationPhone');
        "
        );

        $this->execute(
            "
            CALL `createCaseField` ('si_app_job_offer_ends_at', 8, 'Offer ends at', 0, 'N', 'N', 'Job Offer', 'Skills Immigration Application', 'syncA_App_Job_OfferEndDate');
        "
        );

        $this->execute(
            "
            UPDATE client_form_fields SET type = 34 WHERE company_field_id IN (
              'postSecEdLevel', 
              'postSecEdLevelCan', 
              'jobOfferCity', 
              'jobOfferPostal'
            );
        "
        );

        $this->execute(
            "
            UPDATE client_form_fields SET label = 'Education in BC: Level' WHERE company_field_id = 'postSecEdLevel'; 
        "
        );

        $this->execute(
            "
            UPDATE client_form_fields SET label = 'Education in Canada: Level' WHERE company_field_id = 'postSecEdLevelCan'; 
        "
        );

        $this->execute(
            "
            UPDATE client_form_order cfo
            INNER JOIN client_form_fields cff ON cff.field_id = cfo.field_id
            LEFT OUTER JOIN client_form_groups cfg ON cfg.company_id = cff.company_id AND cfg.title = 'Post-Secondary Education'  
            SET cfo.group_id = cfg.group_id
            WHERE cff.company_field_id IN ('postSecEdLevel', 'postSecEdLevelCan');
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