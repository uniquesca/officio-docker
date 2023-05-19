<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;

class FixEmployerFieldsMappings extends AbstractMigration
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
        $entityNameId               = $this->getFieldId('syncA_entity_name');
        $registeredCompanyNameIdOld = $this->getFieldId('syncA_registered_company_name');
        $registeredCompanyNameId    = $this->getFieldId('syncA_CompanyName');

        if (empty($entityNameId)) {
            $this->execute("INSERT INTO `FormSynField` (FieldName) VALUES ('syncA_entity_name');");
            $entityNameId = $this->getFieldId('syncA_entity_name');
        }

        if (empty($registeredCompanyNameId)) {
            $this->execute("INSERT INTO `FormSynField` (FieldName) VALUES ('syncA_CompanyName');");
            $registeredCompanyNameId = $this->getFieldId('syncA_CompanyName');
        }

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$entityNameId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$entityNameId AND `ToProfileFamilyMemberId`='main_applicant';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$registeredCompanyNameId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$registeredCompanyNameId AND `ToProfileFamilyMemberId`='main_applicant';"
        );
        if (!empty($registeredCompanyNameIdOld)) {
            $this->execute(
                "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$registeredCompanyNameIdOld AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$registeredCompanyNameIdOld AND `ToProfileFamilyMemberId`='main_applicant';"
            );
        }

        $this->execute(
            "INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
            ('main_applicant', $entityNameId, 'main_applicant', $entityNameId, 'main_applicant', 'entity_name', NULL, 7),
            ('main_applicant', $registeredCompanyNameId, 'main_applicant', $registeredCompanyNameId, 'main_applicant', 'registered_company_name', NULL, 7);"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $entityNameId            = $this->getFieldId('syncA_entity_name');
        $registeredCompanyNameId = $this->getFieldId('syncA_CompanyName');
        if (empty($entityNameId) || empty($registeredCompanyNameId)) {
            throw new Exception('Fields not found');
        }

        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$entityNameId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$entityNameId AND `ToProfileFamilyMemberId`='main_applicant';"
        );
        $this->execute(
            "DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=$registeredCompanyNameId AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=$registeredCompanyNameId AND `ToProfileFamilyMemberId`='main_applicant';"
        );

        /** @var StorageInterface $cache */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}