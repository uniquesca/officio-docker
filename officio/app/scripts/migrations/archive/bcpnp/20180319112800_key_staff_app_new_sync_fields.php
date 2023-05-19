<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class KeyStaffAppNewSyncFields extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
          INSERT INTO `FormSynField` (`FieldName`) VALUES
          ('syncA_App_BirthPlace_Country'),
          ('syncA_ExtUsr_Citizenship');
            
          INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`)
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'birth_country' 
            FROM `FormSynField` WHERE `FieldName` = 'syncA_App_BirthPlace_Country'
            
            UNION
            
            SELECT 'main_applicant', `SynFieldId`, 'main_applicant', `SynFieldId`, 'main_applicant', 'country_of_citizenship' 
            FROM `FormSynField` WHERE `FieldName` = 'syncA_ExtUsr_Citizenship';
        "
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {
    }
}