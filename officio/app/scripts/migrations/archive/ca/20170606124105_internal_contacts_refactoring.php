<?php

use Officio\Migration\AbstractMigration;

class InternalContactsRefactoring extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select(['member_id'])
            ->from('members')
            ->where(['userType' => 9])
            ->execute();

        $arrMemberIds = array_column($statement->fetchAll('assoc'), 'member_id');

        if ($arrMemberIds) {
            $statement = $this->getQueryBuilder()
                ->select('member_id')
                ->from('automatic_reminders_processed')
                ->where(['member_id IN' => $arrMemberIds])
                ->group(['member_id'])
                ->execute();

            $reminderProcessed = array_column($statement->fetchAll('assoc'), 'member_id');

            $statement = $this->getQueryBuilder()
                ->distinct()
                ->select(['child_member_id', 'parent_member_id'])
                ->from('members_relations')
                ->where(['child_member_id IN' => $arrMemberIds])
                ->execute();

            $parents = $statement->fetchAll('assoc');

            $arrParentsGrouped = [];
            foreach ($parents as $arrParentInfo) {
                $arrParentsGrouped[$arrParentInfo['child_member_id']] = $arrParentInfo['parent_member_id'];
            }

            foreach ($reminderProcessed as $memberId) {
                if (isset($arrParentsGrouped[$memberId])) {
                    $this->getQueryBuilder()
                        ->update('automatic_reminders_processed')
                        ->set(['member_id' => $arrParentsGrouped[$memberId]])
                        ->where(['member_id' => $memberId])
                        ->execute();
                }
            }
        }
    }

    public function down()
    {
    }
}
