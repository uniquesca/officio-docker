<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class UpdateFieldsMapping extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `FormSynField` (`SynFieldId`, `FieldName`) VALUES
                        (1093, 'syncA_english_test_scores'),
                        (1094, 'syncA_date_of_english_test');");

        $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('main_applicant', 135, 'main_applicant', 135, 'main_applicant', 'emailAddress', NULL, 8),
                        ('main_applicant', 1093, 'main_applicant', 1093, 'main_applicant', 'english_test_scores', NULL, 7),
                        ('main_applicant', 1094, 'main_applicant', 1094, 'main_applicant', 'date_of_english_test', NULL, 7);");


        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {

        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1093 AND `FieldName`='syncA_english_test_scores';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `SynFieldId`=1094 AND `FieldName`='syncA_date_of_english_test';");

        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=135 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=135 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='emailAddress';");
        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1093 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1093 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='english_test_scores';");
        $this->execute("DELETE FROM `FormMap` WHERE  `FromFamilyMemberId`='main_applicant' AND `FromSynFieldId`=1094 AND `ToFamilyMemberId`='main_applicant' AND `ToSynFieldId`=1094 AND `ToProfileFamilyMemberId`='main_applicant' AND `ToProfileFieldId`='date_of_english_test';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}