<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class IncreaseDependantsChildrenCount extends AbstractMigration
{
    public function up()
    {
        try {

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $fieldId = (int)$this->getSynFieldIdByName('syncA_pass_expiry_date1_c7');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES
                        ('child7', 13, 'main_applicant', $fieldId, 'child7', 'passport_date', NULL, 3),
                        ('child7', 13, 'spouse', $fieldId, 'child7', 'passport_date', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_pass_expiry_date1_c8');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child8', 13, 'main_applicant', $fieldId, 'child8', 'passport_date', NULL, 3),
                        ('child8', 13, 'spouse', $fieldId, 'child8', 'passport_date', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_pass_expiry_date1_c9');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child9', 13, 'main_applicant', $fieldId, 'child9', 'passport_date', NULL, 3),
                        ('child9', 13, 'spouse', $fieldId, 'child9', 'passport_date', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_pass_expiry_date1_c10');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                         ('child10', 13, 'main_applicant', $fieldId, 'child10', 'passport_date', NULL, 3),
                         ('child10', 13, 'spouse', $fieldId, 'child10', 'passport_date', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_family_name_c9');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                         ('child9', 1, 'main_applicant', $fieldId, 'child9', 'lName', NULL, 8),
                         ('child9', 1, 'spouse', $fieldId, 'child9', 'lName', NULL, 8);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_family_name_c10');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                         ('child10', 1, 'main_applicant', $fieldId, 'child10', 'lName', NULL, 8),
                          ('child10', 1, 'spouse', $fieldId, 'child10', 'lName', NULL, 8);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_given_name_c9');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                         ('child9', 2, 'main_applicant', $fieldId, 'child9', 'fName', NULL, 3),
                         ('child9', 2, 'spouse', $fieldId, 'child9', 'fName', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_given_name_c10');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                         ('child10', 2, 'main_applicant', $fieldId, 'child10', 'fName', NULL, 3),
                         ('child10', 2, 'spouse', $fieldId, 'child10', 'fName', NULL, 3);"
                );
            }


            //It seems that these fields are exist but we should be sure 100%
            $fieldId = (int)$this->getSynFieldIdByName('syncA_family_name_c7');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                         ('child7', 1, 'main_applicant', $fieldId, 'child7', 'lName', NULL, 8),
                         ('child7', 1, 'spouse', $fieldId, 'child7', 'lName', NULL, 8);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_given_name_c7');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child7', 2, 'main_applicant', $fieldId, 'child7', 'fName', NULL, 3),
                        ('child7', 2, 'spouse', $fieldId, 'child7', 'fName', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_DOB_c7');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child7', 4, 'main_applicant', $fieldId, 'child7', 'DOB', NULL, 3),
                        ('child7', 4, 'spouse', $fieldId, 'child7', 'DOB', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_family_name_c8');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child8', 1, 'main_applicant', $fieldId, 'child8', 'lName', NULL, 8),
                        ('child8', 1, 'spouse', $fieldId, 'child8', 'lName', NULL, 8);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_given_name_c8');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child8', 2, 'main_applicant', $fieldId, 'child8', 'fName', NULL, 3),
                        ('child8', 2, 'spouse', $fieldId, 'child8', 'fName', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_DOB_c8');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child8', 4, 'main_applicant', $fieldId, 'child8', 'DOB', NULL, 3),
                        ('child8', 4, 'spouse', $fieldId, 'child8', 'DOB', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_DOB_c9');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child9', 4, 'main_applicant', $fieldId, 'child9', 'DOB', NULL, 3),
                        ('child9', 4, 'spouse', $fieldId, 'child9', 'DOB', NULL, 3);"
                );
            }

            $fieldId = (int)$this->getSynFieldIdByName('syncA_DOB_c10');
            if (!empty($fieldId)) {
                $this->execute("INSERT INTO `FormMap` (`FromFamilyMemberId`, `FromSynFieldId`, `ToFamilyMemberId`, `ToSynFieldId`, `ToProfileFamilyMemberId`, `ToProfileFieldId`, `form_map_type`, `parent_member_type`) VALUES                   
                        ('child10', 4, 'main_applicant', $fieldId, 'child10', 'DOB', NULL, 3),
                        ('child10', 4, 'spouse', $fieldId, 'child10', 'DOB', NULL, 3);"
                );
            }

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $db->beginTransaction();

            $this->execute("DELETE FROM `FormSynField` WHERE FieldName IN (
                'syncA_pass_expiry_date1_c7', 
                'syncA_pass_expiry_date1_c8', 
                'syncA_pass_expiry_date1_c9',
                'syncA_pass_expiry_date1_c10',
                'syncA_family_name_c9',
                'syncA_family_name_c10',
                'syncA_given_name_c9',
                'syncA_given_name_c10'
            );");

            $db->commit();

            /** @var $cache StorageInterface */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
        } catch (\Exception $e) {
            $db->rollBack();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }

    }

    public function getSynFieldIdByName($fieldName)
    {
        try {

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select    = $db->select()
                ->from('FormSynField', array('SynFieldId'))
                ->where('FieldName = ?', $fieldName);

            $fieldId = $db->fetchOne($select);

            if (empty($fieldId)) {
                $db->insert(
                    'FormSynField',
                    array(
                        'FieldName' => $fieldName
                    )
                );

                $fieldId = $db->lastInsertId('FormSynField');
            }

            return $fieldId;
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            throw $e;
        }

    }
}