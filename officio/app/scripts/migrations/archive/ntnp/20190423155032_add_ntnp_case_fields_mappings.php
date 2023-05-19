<?php

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;

class AddNtnpCaseFieldsMappings extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `FormSynField` (`SynFieldId`, `FieldName`) VALUES
                        (1164, 'syncA_application_fee_paid'),
                        (1165, 'syncA_initial_submission_received_date'),
                        (1166, 'syncA_JobTitle'),
                        (1167, 'syncA_JobLocation'),
                        (1168, 'syncA_JobNOC'),
                        (1169, 'syncA_TypeOfEmployment'),
                        (1170, 'syncA_EmployeeHoursPerWeek'),
                        (1171, 'syncA_EmployeeSalary'),
                        (1172, 'syncA_PositionLanguageRequired'),
                        (1173, 'syncA_ReceiveLMIA');"
        );

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 1164, 'main_applicant', 1164, 'main_applicant', 'application_fee_paid', NULL, 3),
                        ('main_applicant', 1165, 'main_applicant', 1165, 'main_applicant', 'initial_submission_received_date', NULL, 3),
                        ('main_applicant', 1166, 'main_applicant', 1166, 'main_applicant', 'job_title', NULL, 3),
                        ('main_applicant', 1167, 'main_applicant', 1167, 'main_applicant', 'job_location', NULL, 3),
                        ('main_applicant', 1168, 'main_applicant', 1168, 'main_applicant', 'job_noc_code', NULL, 3),
                        ('main_applicant', 1169, 'main_applicant', 1169, 'main_applicant', 'type_of_employment', NULL, 3),
                        ('main_applicant', 1170, 'main_applicant', 1170, 'main_applicant', 'hours_per_week', NULL, 3),
                        ('main_applicant', 1171, 'main_applicant', 1171, 'main_applicant', 'wage_rate', NULL, 3),
                        ('main_applicant', 1172, 'main_applicant', 1172, 'main_applicant', 'language_required', NULL, 3),
                        ('main_applicant', 1173, 'main_applicant', 1173, 'main_applicant', 'received_lmia', NULL, 3),
                        ('main_applicant', 1128, 'main_applicant', 1128, 'main_applicant', 'business_name', NULL, 3);"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1164 AND `FieldName`='syncA_application_fee_paid';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1165 AND `FieldName`='syncA_initial_submission_received_date';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1166 AND `FieldName`='syncA_JobTitle';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1167 AND `FieldName`='syncA_JobLocation';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1168 AND `FieldName`='syncA_JobNOC';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1169 AND `FieldName`='syncA_TypeOfEmployment';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1170 AND `FieldName`='syncA_EmployeeHoursPerWeek';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1171 AND `FieldName`='syncA_EmployeeSalary';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1172 AND `FieldName`='syncA_PositionLanguageRequired';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1173 AND `FieldName`='syncA_ReceiveLMIA';");

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1164 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1164 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='application_fee_paid';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1165 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1165 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='initial_submission_received_date';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1166 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1166 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='job_title';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1167 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1167 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='job_location';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1168 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1168 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='job_noc_code';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1169 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1169 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='type_of_employment';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1170 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1170 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='hours_per_week';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1171 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1171 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='wage_rate';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1172 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1172 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='language_required';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1173 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1173 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='received_lmia';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1128 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1128 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='business_name';"
        );
    }
}