<?php

use Clients\Service\Clients;
use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class SetAllCompaniesFields extends AbstractMigration
{
    public function up()
    {
        // Took 190s on local server...
        try {
            /** @var Clients $oClients */
            $oClients          = self::getService(Clients::class);
            $internalContactId = $oClients->getMemberTypeIdByName('internal_contact');

            $statement = $this->getQueryBuilder()
                ->select(array('company_id'))
                ->from('company')
                ->orderAsc('company_id')
                ->execute();

            $arrCompanyIds = array_column($statement->fetchAll('assoc'), 'company_id');

            //INSERT BLOCKS
            $blocksCount = 0;
            foreach ($arrCompanyIds as $companyId) {
                $arrInsert = [
                    'member_type_id'    => $internalContactId,
                    'company_id'        => $companyId,
                    'applicant_type_id' => null,
                    'order'             => 0,
                ];

                $this->getQueryBuilder()
                    ->insert(array_keys($arrInsert))
                    ->into('applicant_form_blocks')
                    ->values($arrInsert)
                    ->execute();

                $blocksCount++;
            }

            echo sprintf('%d blocks were created.', $blocksCount) . PHP_EOL;

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('applicant_form_blocks')
                ->where(
                    [
                        'member_type_id' => $internalContactId
                    ]
                )
                ->execute();

            $insertedBlocks = $statement->fetchAll('assoc');

            $groupsCount = 0;
            $fieldsCount = 0;
            foreach ($insertedBlocks as $insertedBlock) {
                // INSERT GROUPS
                $arrInsert = [
                    'applicant_block_id' => $insertedBlock['applicant_block_id'],
                    'company_id'         => $insertedBlock['company_id'],
                    'title'              => 'Main Group',
                    'cols_count'         => 3,
                    'order'              => 0,
                ];

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrInsert))
                    ->into('applicant_form_groups')
                    ->values($arrInsert)
                    ->execute();

                $groupId = $statement->lastInsertId('applicant_form_groups');
                $groupsCount++;

                // Place fields to this group
                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from('applicant_form_fields')
                    ->where(
                        [
                            'member_type_id' => (int)$internalContactId,
                            'company_id'     => (int)$insertedBlock['company_id']
                        ]
                    )
                    ->execute();

                $currentGroupFields = $statement->fetchAll('assoc');

                $order = 0;
                foreach ($currentGroupFields as $currentGroupField) {
                    $arrInsert = [
                        'applicant_group_id' => $groupId,
                        'applicant_field_id' => $currentGroupField['applicant_field_id'],
                        'use_full_row'       => 'N',
                        'field_order'        => $order++
                    ];

                    $this->getQueryBuilder()
                        ->insert(array_keys($arrInsert))
                        ->into('applicant_form_order')
                        ->values($arrInsert)
                        ->execute();

                    $fieldsCount++;
                }
            }

            echo sprintf('%d groups were created.', $groupsCount) . PHP_EOL;
            echo sprintf('%d fields were placed.', $fieldsCount) . PHP_EOL . PHP_EOL;

            echo 'Done.' . PHP_EOL;
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}