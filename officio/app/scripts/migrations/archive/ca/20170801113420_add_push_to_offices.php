<?php

use Officio\Migration\AbstractMigration;

class AddPushToOffices extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select(['division_group_id', 'division_id'])
            ->from('divisions')
            ->order(['company_id', 'division_group_id'])
            ->execute();

        $arrGroupsDivisions = $statement->fetchAll('assoc');

        $arrGroupedDivisions = array();
        foreach ($arrGroupsDivisions as $arr) {
            if (isset($arrGroupedDivisions[$arr['division_group_id']])) {
                $arrGroupedDivisions[$arr['division_group_id']][] = $arr['division_id'];
            } else {
                $arrGroupedDivisions[$arr['division_group_id']] = array($arr['division_id']);
            }
        }

        $statement = $this->getQueryBuilder()
            ->select(['member_type_id'])
            ->from('members_types')
            ->where(['member_type_name IN' => ['admin', 'user', 'agent']])
            ->execute();

        $arrUserTypeIds = array_column($statement->fetchAll('assoc'), 'member_type_id');

        $statement = $this->getQueryBuilder()
            ->select(['member_id', 'division_group_id'])
            ->from('members')
            ->where(['userType IN' => $arrUserTypeIds])
            ->execute();

        $arrMembers = $statement->fetchAll('assoc');

        $arrMembersGrouped = [];
        foreach ($arrMembers as $arrMemberInfo) {
            $arrMembersGrouped[$arrMemberInfo['member_id']] = $arrMemberInfo['division_group_id'];
        }

        $this->query("ALTER TABLE `members_divisions` CHANGE COLUMN `type` `type` ENUM('access_to','responsible_for','pull_from','push_to') NULL DEFAULT 'access_to' AFTER `division_id`;");

        $arrRowsInsert = [];
        foreach ($arrMembersGrouped as $memberId => $divisionGroupId) {
            if (isset($arrGroupedDivisions[$divisionGroupId])) {
                foreach ($arrGroupedDivisions[$divisionGroupId] as $divisionId) {
                    $arrRowsInsert[] = sprintf(
                        "(%d, %d, '%s')",
                        $memberId,
                        $divisionId,
                        'push_to'
                    );
                }
            }
        }

        if (count($arrRowsInsert)) {
            $query = sprintf(
                "INSERT IGNORE INTO members_divisions (`member_id`, `division_id`, `type`) VALUES %s;",
                implode(',', $arrRowsInsert)
            );

            $this->execute($query);
        }
    }

    public function down()
    {
        $this->query("DELETE FROM `members_divisions` WHERE type = 'push_to'");
        $this->query("ALTER TABLE `members_divisions` CHANGE COLUMN `type` `type` ENUM('access_to','responsible_for','pull_from') NULL DEFAULT 'access_to' AFTER `division_id`;");
    }
}
