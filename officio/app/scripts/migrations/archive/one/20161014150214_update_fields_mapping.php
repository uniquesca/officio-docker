<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Phinx\Migration\AbstractMigration;

class UpdateFieldsMapping extends AbstractMigration
{
    public function up()
    {
        /** @var Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->insert('FormSynField', array('FieldName' => 'syncA_english_test_scores'));
        $newId = $db->lastInsertId('FormSynField');

        $db->insert(
            'FormMap',
            array(
                'FromFamilyMemberId'      => 'main_applicant',
                'FromSynFieldId'          => $newId,
                'ToFamilyMemberId'        => 'main_applicant',
                'ToSynFieldId'            => $newId,
                'ToProfileFamilyMemberId' => 'main_applicant',
                'ToProfileFieldId'        => 'english_test_scores',
                'form_map_type'           => new Zend_Db_Expr('NULL'),
                'parent_member_type'      => 7
            )
        );

        $db->insert('FormSynField', array('FieldName' => 'syncA_date_of_english_test'));
        $newId = $db->lastInsertId('FormSynField');

        $db->insert(
            'FormMap',
            array(
                'FromFamilyMemberId'      => 'main_applicant',
                'FromSynFieldId'          => $newId,
                'ToFamilyMemberId'        => 'main_applicant',
                'ToSynFieldId'            => $newId,
                'ToProfileFamilyMemberId' => 'main_applicant',
                'ToProfileFieldId'        => 'date_of_english_test',
                'form_map_type'           => new Zend_Db_Expr('NULL'),
                'parent_member_type'      => 7
            )
        );

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }

    public function down()
    {

        $this->execute("DELETE FROM `FormSynField` WHERE  `FieldName`='syncA_english_test_scores';");
        $this->execute("DELETE FROM `FormSynField` WHERE  `FieldName`='syncA_date_of_english_test';");

        /** @var $cache StorageInterface */
        $cache = Zend_Registry::get('serviceManager')->get('cache');
        Acl::clearCache($cache);
    }
}