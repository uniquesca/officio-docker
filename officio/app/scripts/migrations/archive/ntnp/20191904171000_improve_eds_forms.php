<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class ImproveEdsForms extends AbstractMigration
{
    private function getFieldId($syncFieldId)
    {
        $arrRow = $this->fetchRow('SELECT * FROM FormSynField WHERE FieldName = "' . $syncFieldId . '";');
        if (!empty($arrRow)) {
            return $arrRow['SynFieldId'];
        }

        return false;
    }

    public function up()
    {
        $wageFieldId        = $this->getFieldId('syncA_EmployeeMedianWage');
        $familyCountFieldId = $this->getFieldId('syncA_FamilyMembersCount');

        if (empty($wageFieldId)) {
            $this->execute("INSERT INTO `FormSynField` (FieldName) VALUES ('syncA_EmployeeMedianWage');");
            $wageFieldId = $this->getFieldId('syncA_EmployeeMedianWage');
        }
        if (empty($familyCountFieldId)) {
            $this->execute("INSERT INTO `FormSynField` (FieldName) VALUES ('syncA_FamilyMembersCount');");
            $familyCountFieldId = $this->getFieldId('syncA_FamilyMembersCount');
        }

        if (!empty($wageFieldId)) {
            $this->execute(
                "DELETE FROM `FormMap` WHERE `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$wageFieldId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$wageFieldId AND `ToProfileFamilyMemberId`='main_applicant';"
            );
        }
        if (!empty($familyCountFieldId)) {
            $this->execute(
                "DELETE FROM `FormMap` WHERE `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$familyCountFieldId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$familyCountFieldId AND `ToProfileFamilyMemberId`='main_applicant';"
            );
        }

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
            ('main_applicant', $wageFieldId, 'main_applicant', $wageFieldId, 'main_applicant', 'median_wage', NULL, 3);
        "
        );
        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
            ('main_applicant', $familyCountFieldId, 'main_applicant', $familyCountFieldId, 'main_applicant', 'dependants_count', NULL, 3);
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
        $wageFieldId        = $this->getFieldId('syncA_EmployeeMedianWage');
        $familyCountFieldId = $this->getFieldId('syncA_FamilyMembersCount');
        if (empty($wageFieldId) || empty($familyCountFieldId)) {
            throw new Exception('Fields not found');
        }

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$wageFieldId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$wageFieldId AND `ToProfileFamilyMemberId`='main_applicant';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$familyCountFieldId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$familyCountFieldId AND `ToProfileFamilyMemberId`='main_applicant';"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}
