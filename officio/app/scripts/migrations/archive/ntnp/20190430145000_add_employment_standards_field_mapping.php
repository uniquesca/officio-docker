<?php

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Cache\Storage\FlushableInterface;
use Phinx\Migration\AbstractMigration;

class AddEmploymentStandardsFieldMapping extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `FormSynField` (`SynFieldId`, `FieldName`) VALUES
                        (1174, 'syncA_EmploymentStandardsRegulated');"
        );

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 1174, 'main_applicant', 1174, 'main_applicant', 'employment_standards_regulated', NULL, 3);"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1174 AND `FieldName`='syncA_EmploymentStandardsRegulated';");

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1174 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1174 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='employment_standards_regulated';"
        );
    }
}