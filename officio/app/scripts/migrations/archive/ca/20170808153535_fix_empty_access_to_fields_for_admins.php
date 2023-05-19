<?php

use Officio\Migration\AbstractMigration;

class FixEmptyAccessToFieldsForAdmins extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select(['member_type_id'])
            ->from('members_types')
            ->where(['member_type_name IN' => ['admin', 'user', 'agent']])
            ->execute();

        $arrUserTypeIds = array_column($statement->fetchAll('assoc'), 'member_type_id');

        $statement = $this->getQueryBuilder()
            ->select(['role_id', 'role_type'])
            ->from('acl_roles')
            ->execute();

        $arrRolesTypes = $statement->fetchAll('assoc');

        $arrRolesTypesGrouped = [];
        foreach ($arrRolesTypes as $arrRolesTypeInfo) {
            $arrRolesTypesGrouped[$arrRolesTypeInfo['role_id']] = $arrRolesTypeInfo['role_type'];
        }

        // Create mapper for member_id -> division_group_id
        $statement = $this->getQueryBuilder()
            ->select(['member_id', 'division_group_id'])
            ->from('members')
            ->where(['userType IN' => $arrUserTypeIds])
            ->execute();

        $arrMembers = $statement->fetchAll('assoc');

        // Create mapper for division_group -> division
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

        $arrRowsInsert = [];
        foreach ($arrMembers as $member) {
            $statement = $this->getQueryBuilder()
                ->select(['role_id'])
                ->from('members_roles')
                ->where(['member_id' => $member['member_id']])
                ->execute();

            $arrRoles = array_column($statement->fetchAll('assoc'), 'role_id');
            foreach ($arrRoles as $roleId) {
                if (isset($arrRolesTypesGrouped[$roleId]) && $arrRolesTypesGrouped[$roleId] == 'admin') {
                    foreach ($arrGroupedDivisions[$member['division_group_id']] as $divisionId) {
                        $arrRowsInsert[] = sprintf(
                            "(%d, %d, '%s')",
                            $member['member_id'],
                            $divisionId,
                            'access_to'
                        );
                    }
                    break;
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
    }
}
