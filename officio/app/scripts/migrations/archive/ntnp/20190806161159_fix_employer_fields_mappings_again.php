<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class FixEmployerFieldsMappingsAgain extends AbstractMigration
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
        $entityNameId = $this->getFieldId('syncA_entity_name');
        $operatingAs  = $this->getFieldId('syncA_OperatingAs');

        if (empty($operatingAs)) {
            $this->execute("INSERT INTO `FormSynField` (FieldName) VALUES ('syncA_OperatingAs');");
            $entityNameId = $this->getFieldId('syncA_OperatingAs');
        }

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$entityNameId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$entityNameId AND `ToProfileFamilyMemberId`='main_applicant';"
        );
        if (!empty($operatingAs)) {
            $this->execute(
                "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$operatingAs AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$operatingAs AND `ToProfileFamilyMemberId`='main_applicant';"
            );
        }

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
            ('main_applicant', $operatingAs, 'main_applicant', $operatingAs, 'main_applicant', 'entity_name', NULL, 7);
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
        $operatingAs = $this->getFieldId('syncA_OperatingAs');
        if (empty($operatingAs)) {
            throw new Exception('Fields not found');
        }

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$operatingAs AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$operatingAs AND `ToProfileFamilyMemberId`='main_applicant';"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}