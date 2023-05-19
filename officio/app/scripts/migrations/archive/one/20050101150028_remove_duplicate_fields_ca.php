<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class RemoveDuplicateFieldsCa extends AbstractMigration
{
    public function up()
    {
        // Took 579s on local server...
        try {
            /** @var \Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from(array('c' => 'company'), array('company_id', 'storage_location'))
                ->order('company_id ASC');
            $arrCompanies = $db->fetchAll($select);

            /** @var Clients $clients */
            $clients = Zend_Registry::get('serviceManager')->get(Clients::class);
            $oFieldTypes      = $clients->getFieldTypes();
            /** @var Files $oFiles */
            $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);

            $applicantTypeId  = 0;
            $individualTypeId = $clients->getMemberTypeIdByName('individual');

            $arrFilesToDelete = array();
            foreach ($arrCompanies as $arrCompanyInfo) {
                $companyId = $arrCompanyInfo['company_id'];
                $booLocal  = $arrCompanyInfo['storage_location'] == 'local';

                echo "Company id: #$companyId";

                $arrParentClientFields = $clients->getApplicantFields()->getCompanyFields(
                    $companyId,
                    $individualTypeId,
                    $applicantTypeId
                );

                $arrCaseFields = $clients->getFields()->getCompanyFields($companyId);


                // Delete fields that were moved to IA
                $arrFieldIdsToDelete = array();
                foreach ($arrParentClientFields as $arrParentClientFieldInfo) {
                    foreach ($arrCaseFields as $arrCaseFieldInfo) {
                        if ($arrCaseFieldInfo['company_field_id'] == $arrParentClientFieldInfo['applicant_field_unique_id']) {
                            $arrFieldIdsToDelete[$arrParentClientFieldInfo['applicant_field_id']] = $arrCaseFieldInfo['field_id'];
                            break;
                        }
                    }
                }

                if (count($arrFieldIdsToDelete)) {
                    // Delete photos, that were move to new parent
                    $select = $db->select()
                        ->from(array('d' => 'client_form_data'))
                        ->joinLeft(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', 'company_id')
                        ->where('d.field_id IN (?)', $arrFieldIdsToDelete)
                        ->where('f.type IN (?)', $oFieldTypes->getFieldTypeId('photo'));
                    $arrCaseSavedPhotoData = $db->fetchAll($select);

                    if(count($arrCaseSavedPhotoData)) {
                        foreach ($arrCaseSavedPhotoData as $arrCaseSavedPhotoDataInfo) {
                            $pathToFile = $oFiles->getPathToClientImages($companyId, $arrCaseSavedPhotoDataInfo['member_id'], $booLocal) . '/' . 'field-' . $arrCaseSavedPhotoDataInfo['field_id'];

                            $arrFilesToDelete[] = array(
                                'local' => (int)$booLocal,
                                'path'  => $pathToFile
                            );

//                            $oFiles->deleteFile(
//                                $pathToFile,
//                                $oCompany->isCompanyStorageLocationLocal($arrCaseSavedPhotoDataInfo['company_id'])
//                            );
                        }
                    }

                    $select = $db->select()
                        ->from(array('r' => 'automatic_reminders'))
                        ->where('r.company_id = ?', $companyId, 'INT')
                        ->where('r.type = ?', 'PROFILE')
                        ->where('r.prof IN (?)', $arrFieldIdsToDelete, 'INT');

                    $arrReminders = $db->fetchAll($select);

                    foreach ($arrFieldIdsToDelete as $applicantId => $caseId) {
                        foreach ($arrReminders as $arrReminderInfo) {
                            if ($arrReminderInfo['prof'] === $caseId) {
                                $db->update(
                                    'automatic_reminders',
                                    array('prof' => $applicantId),
                                    $db->quoteInto('prof = ?', $caseId, 'INT')
                                );
                            }
                        }
                    }

                    // Delete all moved fields data
                    $strWhere = $db->quoteInto('company_id = ?', $companyId, 'INT');
                    $strWhere .= $db->quoteInto(' AND field_id IN (?)', $arrFieldIdsToDelete, 'INT');
                    $db->delete(
                        'client_form_fields',
                        $strWhere
                    );
                }

                echo sprintf(" Deleted %d fields (%s)", count($arrFieldIdsToDelete), implode(', ', $arrFieldIdsToDelete)) . PHP_EOL;

                // Delete empty groups (e.g. when we moved all fields from the group)
                $db->query(sprintf("DELETE FROM client_form_groups WHERE company_id = %d AND group_id NOT IN (SELECT group_id FROM client_form_order GROUP BY group_id) AND title <> 'Dependants'", $companyId));
            }

            /** @var StorageInterface $cache */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
            echo 'Done.' . PHP_EOL;

            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile('Delete such converted clients files: ', print_r($arrFilesToDelete, 1), 'clients_converted_delete_files');

        } catch (\Exception $e) {
            echo 'Fatal error' . $e->getTraceAsString();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'cases_converted_ca');
            throw $e;
        }
    }

    public function down()
    {
    }
}