<?php

use Clients\Service\Members;
use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class FixEmptyDivisionGroups extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('members')
            ->execute();

        $arrMembers = $statement->fetchAll('assoc');

        $arrAssignedGroups   = array();
        $arrAssignedOffices  = array();
        $arrIncorrectOffices = array();
        foreach ($arrMembers as $arrMemberInfo) {
            $statement = $this->getQueryBuilder()
                ->select('division_group_id')
                ->from('divisions_groups', )
                ->where(
                    [
                        'company_id' => $arrMemberInfo['company_id']
                    ]
                )
                ->execute();

            $arrDivisionGroups = array_column($statement->fetchAll('assoc'), 'division_group_id');

            if (empty($arrDivisionGroups)) {
                throw new Exception(sprintf("Company %d doesn't have assigned division groups", $arrMemberInfo['company_id']));
            }

            $divisionGroupId = $arrMemberInfo['division_group_id'];
            if (empty($divisionGroupId)) {
                $divisionGroupId = $arrDivisionGroups[0];

                $this->getQueryBuilder()
                    ->update('members')
                    ->set(
                        array('division_group_id' => $divisionGroupId)
                    )
                    ->where(
                        [
                            'member_id' => (int)$arrMemberInfo['member_id']
                        ]
                    )
                    ->execute();

                $arrAssignedGroups[$arrMemberInfo['member_id']] = $divisionGroupId;
            } elseif (!in_array($divisionGroupId, $arrDivisionGroups)) {
                throw new Exception(sprintf("Member %d has assigned incorrect division group: %d", $arrMemberInfo['member_id'], $divisionGroupId));
            }

            if (in_array($arrMemberInfo['userType'], Members::getMemberType('admin'))) {
                // If this is an admin - check if there are assigned offices from the same division group
                $statement = $this->getQueryBuilder()
                    ->select('division_id')
                    ->from('members_divisions')
                    ->where(
                        [
                            'member_id' => $arrMemberInfo['member_id']
                        ]
                    )
                    ->where(
                        [
                            'type' => 'access_to'
                        ]
                    )
                    ->execute();

                $arrMemberDivisions = array_column($statement->fetchAll('assoc'), 'division_id');

                $statement = $this->getQueryBuilder()
                    ->select('division_id')
                    ->from('divisions')
                    ->where(
                        [
                            'division_group_id' => (int)$divisionGroupId,
                            'company_id' => (int)$arrMemberInfo['company_id']
                        ]
                    )
                    ->execute();

                $arrGroupDivisions = array_column($statement->fetchAll('assoc'), 'division_id');


                if (empty($arrMemberDivisions)) {
                    foreach ($arrGroupDivisions as $arrGroupDivisionId) {
                        $this->table('members_divisions')
                            ->insert([
                                [
                                    'member_id'   => $arrMemberInfo['member_id'],
                                    'division_id' => $arrGroupDivisionId,
                                    'type'        => 'access_to'
                                ]
                            ])
                            ->saveData();

                        $arrAssignedOffices[$arrMemberInfo['member_id']][] = $arrGroupDivisionId;
                    }
                } else {
                    $res = array_diff($arrMemberDivisions, $arrGroupDivisions);
                    if (!empty($res)) {
                        $arrIncorrectOffices[$arrMemberInfo['member_id']] = $res;
                    }
                }
            }
        }

        // These logs must be checked to be sure all is ok
        // For sure "Incorrect offices" must be empty
        /** @var Log $log */
        $log      = self::getService('log');
        $fileName = 'migration_empty_offices.log';
        $log->debugToFile('start', 0, 1, $fileName);
        $log->debugToFile('Assigned groups:' . PHP_EOL . print_r($arrAssignedGroups, true), 1, 1, $fileName);
        $log->debugToFile('Assigned offices:' . PHP_EOL . print_r($arrAssignedOffices, true), 1, 1, $fileName);
        $log->debugToFile('Incorrect offices:' . PHP_EOL . print_r($arrIncorrectOffices, true), 1, 1, $fileName);
    }

    public function down()
    {
    }
}