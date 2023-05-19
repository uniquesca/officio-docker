<?php

use Officio\Migration\AbstractMigration;

class FixIncorrectInternalContacts extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select('member_id')
            ->from('members')
            ->where(
                [
                    'userType' => 8
                ]
            )
            ->execute();

        $parentMemberIdIn = array_column($statement->fetchAll('assoc'), 'member_id');

        $statement = $this->getQueryBuilder()
            ->select('member_id')
            ->from('members')
            ->where(
                [
                    'userType' => 9
                ]
            )
            ->execute();

        $childMemberIdIn = array_column($statement->fetchAll('assoc'), 'member_id');

        $statement = $this->getQueryBuilder()
            ->select(['mr.*', 'm.company_id'])
            ->from(array('mr' => 'members_relations'))
            ->leftJoin(array('m' => 'members'), 'm.member_id = mr.parent_member_id')
            ->where(function ($exp) use ($parentMemberIdIn, $childMemberIdIn) {
                return $exp
                    ->in('mr.parent_member_id', $parentMemberIdIn)
                    ->in('mr.child_member_id', $childMemberIdIn);
            })
            ->execute();

        $arrRelations = $statement->fetchAll('assoc');

        $arrGrouped = array();
        foreach ($arrRelations as $arrRelationInfo) {
            $arrGrouped[$arrRelationInfo['parent_member_id']][] = $arrRelationInfo;
        }

        foreach ($arrGrouped as $parentId => $arrGroupedContacts) {
            if (count($arrGroupedContacts) == 2) {
                if ($arrGroupedContacts[0]['child_member_id'] != $arrGroupedContacts[1]['child_member_id']) {
                    $this->getQueryBuilder()
                        ->update('applicant_form_data')
                        ->set(array('applicant_id' => $arrGroupedContacts[0]['child_member_id']))
                        ->where(
                            [
                                'applicant_id' => (int)$arrGroupedContacts[1]['child_member_id']
                            ]
                        )
                        ->execute();

                    $this->getQueryBuilder()
                        ->update('members_relations')
                        ->set(array('child_member_id' => $arrGroupedContacts[0]['child_member_id']))
                        ->where(
                            [
                                'parent_member_id' => (int)$parentId,
                                'child_member_id' => (int)$arrGroupedContacts[1]['child_member_id']
                            ]
                        )
                        ->execute();
                }
            } elseif (count($arrGroupedContacts) == 1) {
                $statement = $this->getQueryBuilder()
                    ->select(['applicant_block_id'])
                    ->from(array('b' => 'applicant_form_blocks'))
                    ->where([
                        'b.member_type_id' => 8,
                        'b.company_id'     => $arrGroupedContacts[0]['company_id'],
                        'b.contact_block'  => 'Y'
                    ])
                    ->execute();

                $arrBlocks = array_column($statement->fetchAll('assoc'), 'applicant_block_id');

                if (count($arrBlocks)) {
                    $statement = $this->getQueryBuilder()
                        ->select('applicant_group_id')
                        ->from(array('g' => 'applicant_form_groups'))
                        ->whereInList('g.applicant_block_id', $arrBlocks)
                        ->andWhere(
                            [
                                'g.company_id' => $arrGroupedContacts[0]['company_id']
                            ]
                        )
                        ->execute();

                    $arrGroups = array_column($statement->fetchAll('assoc'), 'applicant_group_id');
                    if (count($arrGroups) > 1) {
                        foreach ($arrGroups as $groupId) {
                            if ($groupId != $arrGroupedContacts[0]['applicant_group_id']) {
                                $arrInsert = array(
                                    'parent_member_id'   => $arrGroupedContacts[0]['parent_member_id'],
                                    'child_member_id'    => $arrGroupedContacts[0]['child_member_id'],
                                    'applicant_group_id' => $groupId,
                                    'row'                => 0
                                );

                                $this->getQueryBuilder()
                                    ->insert(array_keys($arrInsert))
                                    ->into('members_relations')
                                    ->values($arrInsert)
                                    ->execute();
                            }
                        }
                    }
                }
            }
        }
    }

    public function down()
    {
    }
}
