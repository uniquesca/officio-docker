<?php

use Officio\Migration\AbstractMigration;

class AddCaseNumberOfParentEmployer extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `clients` ADD COLUMN `case_number_of_parent_employer` SMALLINT(5) UNSIGNED NULL DEFAULT NULL AFTER `case_number_of_parent_client`;");

        $statement = $this->getQueryBuilder()
            ->select(['r.*'])
            ->from(['r' => 'members_relations'])
            ->leftJoin(['m' => 'members'], ['m.member_id = r.parent_member_id'])
            ->leftJoin(['m2' => 'members'], ['m2.member_id = r.child_member_id'])
            ->where([
                'm.userType'  => 7, // employer
                'm2.userType' => 3, // case
            ])
            ->order(['r.parent_member_id' => 'ASC', 'r.child_member_id' => 'ASC'])
            ->execute();

        $arrRelations = $statement->fetchAll('assoc');

        $arrGroupedCases   = [];
        $arrGroupedClients = [];
        foreach ($arrRelations as $arrRelationInfo) {
            if (isset($arrGroupedClients[$arrRelationInfo['parent_member_id']])) {
                $arrGroupedClients[$arrRelationInfo['parent_member_id']] += 1;
            } else {
                $arrGroupedClients[$arrRelationInfo['parent_member_id']] = 1;
            }

            $arrGroupedCases[$arrGroupedClients[$arrRelationInfo['parent_member_id']]][] = $arrRelationInfo['child_member_id'];
        }

        foreach ($arrGroupedCases as $num => $arrCasesIds) {
            $this->getQueryBuilder()
                ->update('clients')
                ->set([
                    'case_number_of_parent_employer' => $num,
                ])
                ->where(['member_id IN ' => $arrCasesIds])
                ->execute();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `clients` DROP COLUMN `case_number_of_parent_employer`;");
    }
}