<?php

use Clients\Service\Clients;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class SetAllCompaniesFields extends AbstractMigration
{
    public function up()
    {
        // Took 190s on local server...
        try {
            /** @var \Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            /** @var Clients $clients */
            $clients = \Zend_Registry::get(Clients::class);
            $internalContactId = $clients->getMemberTypeIdByName('internal_contact');

            $select = $db->select()
                ->from('company', array('company_id'))
                ->order('company_id ASC');

            $arrCompanyIds = $db->fetchCol($select);

            //INSERT BLOCKS
            $blocksCount = 0;
            foreach ($arrCompanyIds as $companyId) {
                $db->insert(
                    'applicant_form_blocks',
                    array(
                        'member_type_id'    => $internalContactId,
                        'company_id'        => $companyId,
                        'applicant_type_id' => null,
                        'order'             => 0,
                    )
                );
                $blocksCount++;
            }

            echo sprintf('%d blocks were created.', $blocksCount) . PHP_EOL;

            $select = $db->select()
                ->from('applicant_form_blocks')
                ->where('applicant_form_blocks.member_type_id = ?', $internalContactId);

            $insertedBlocks = $db->fetchAll($select);

            $groupsCount = 0;
            $fieldsCount = 0;
            foreach ($insertedBlocks as $insertedBlock) {
                // INSERT GROUPS
                $db->insert(
                    'applicant_form_groups',
                    array(
                        'applicant_block_id' => $insertedBlock['applicant_block_id'],
                        'company_id'         => $insertedBlock['company_id'],
                        'title'              => 'Main Group',
                        'cols_count'         => 3,
                        'order'              => 0,
                    )
                );
                $groupId = $db->lastInsertId('applicant_form_groups');
                $groupsCount++;

                // Place fields to this group
                $select = $db->select()
                    ->from('applicant_form_fields')
                    ->where('member_type_id = ?', $internalContactId, 'INT')
                    ->where('company_id = ?', $insertedBlock['company_id'], 'INT');

                $currentGroupFields = $db->fetchAll($select);

                $order = 0;
                foreach ($currentGroupFields as $currentGroupField) {
                    $db->insert(
                        'applicant_form_order',
                        array(
                            'applicant_group_id' => $groupId,
                            'applicant_field_id' => $currentGroupField['applicant_field_id'],
                            'use_full_row'       => 'N',
                            'field_order'        => $order++
                        )
                    );
                    $fieldsCount++;
                }
            }

            echo sprintf('%d groups were created.', $groupsCount) . PHP_EOL;
            echo sprintf('%d fields were placed.', $fieldsCount) . PHP_EOL . PHP_EOL;


            /** @var StorageInterface $cache */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
            echo 'Done.' . PHP_EOL;

        } catch (\Exception $e) {
            echo 'Fatal error' . $e->getTraceAsString();
            /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            throw $e;
        }
    }

    public function down()
    {
    }
}