<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddMissingOffices extends AbstractMigration
{
    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select(['c.company_id'])
                ->from(['c' => 'company'])
                ->leftJoin(['d' => 'divisions'], ['c.company_id = d.company_id'])
                ->whereNull(['d.company_id'])
                ->execute();

            $arrCompanyIds = array_column($statement->fetchAll('assoc'), 'company_id');

            foreach ($arrCompanyIds as $companyId) {
                $statement = $this->getQueryBuilder()
                    ->insert(
                        [
                            'company_id',
                            'name',
                        ]
                    )
                    ->into('divisions')
                    ->values(
                        [
                            'company_id' => $companyId,
                            'name'       => 'Main',
                        ]
                    )
                    ->execute();

                $divisionId = $statement->lastInsertId('divisions');

                $statement = $this->getQueryBuilder()
                    ->select(['member_id'])
                    ->from('members')
                    ->where(['company_id' => $companyId])
                    ->execute();

                $arrMemberIds = array_column($statement->fetchAll('assoc'), 'member_id');

                foreach ($arrMemberIds as $memberId) {
                    $this->getQueryBuilder()
                        ->insert(
                            [
                                'member_id',
                                'division_id'
                            ]
                        )
                        ->into('members_divisions')
                        ->values(
                            [
                                'member_id'   => $memberId,
                                'division_id' => $divisionId
                            ]
                        )
                        ->execute();
                }
            }

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
