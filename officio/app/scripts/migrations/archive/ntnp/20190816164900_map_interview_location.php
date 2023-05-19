<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class MapInterviewLocation extends AbstractMigration
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
        $formField = $this->getFieldId('syncA_InterviewLocation');

        if (empty($formField)) {
            $this->execute("INSERT INTO `FormSynField` (FieldName) VALUES ('syncA_InterviewLocation');");
            $formField = $this->getFieldId('syncA_InterviewLocation');
        }

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$formField AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$formField AND `ToProfileFamilyMemberId`='main_applicant';"
        );
        if (!empty($formField)) {
            $this->execute(
                "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$formField AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$formField AND `ToProfileFamilyMemberId`='main_applicant';"
            );
        }

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
            ('main_applicant', $formField, 'main_applicant', $formField, 'main_applicant', 'interview_location', NULL, 3);
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
        $formField = $this->getFieldId('syncA_InterviewLocation');
        if (empty($formField)) {
            throw new Exception('Fields not found');
        }

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$formField AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$formField AND `ToProfileFamilyMemberId`='main_applicant';"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}