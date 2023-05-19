<?php

use Phinx\Migration\AbstractMigration;

class FixIncorrectInternalContacts extends AbstractMigration
{
    public function up()
    {
        /** @var Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('mr' => 'members_relations'))
            ->joinLeft(array('m' => 'members'), 'm.member_id = mr.parent_member_id', 'company_id')
            ->where('mr.parent_member_id IN (SELECT member_id FROM members WHERE userType = 8)')
            ->where('mr.child_member_id IN (SELECT member_id FROM members WHERE userType = 9)');

        $arrRelations = $db->fetchAll($select);

        $arrGrouped = array();
        foreach ($arrRelations as $arrRelationInfo) {
            $arrGrouped[$arrRelationInfo['parent_member_id']][] = $arrRelationInfo;
        }

        foreach ($arrGrouped as $parentId => $arrGroupedContacts) {
            if (count($arrGroupedContacts) == 2) {
                if ($arrGroupedContacts[0]['child_member_id'] != $arrGroupedContacts[1]['child_member_id']) {
                    $db->update(
                        'applicant_form_data',
                        array('applicant_id' => $arrGroupedContacts[0]['child_member_id']),
                        $db->quoteInto('applicant_id = ?', $arrGroupedContacts[1]['child_member_id'], 'INT')
                    );

                    $db->update(
                        'members_relations',
                        array('child_member_id' => $arrGroupedContacts[0]['child_member_id']),
                        $db->quoteInto('parent_member_id = ?', $parentId, 'INT') .
                        $db->quoteInto(' AND child_member_id = ?', $arrGroupedContacts[1]['child_member_id'], 'INT')
                    );
                }
            } elseif (count($arrGroupedContacts) == 1) {
                $select = $db->select()
                    ->from(array('b' => 'applicant_form_blocks'))
                    ->where('b.member_type_id = 8')
                    ->where('b.company_id = ?', $arrGroupedContacts[0]['company_id'])
                    ->where('b.contact_block = ?', 'Y');

                $arrBlocks = $db->fetchCol($select);

                if (count($arrBlocks)) {
                    $select = $db->select()
                        ->from(array('g' => 'applicant_form_groups'), 'applicant_group_id')
                        ->where('g.applicant_block_id IN (?)', $arrBlocks)
                        ->where('g.company_id = ?', $arrGroupedContacts[0]['company_id']);

                    $arrGroups = $db->fetchCol($select);
                    if (count($arrGroups) > 1) {
                        foreach ($arrGroups as $groupId) {
                            if ($groupId != $arrGroupedContacts[0]['applicant_group_id']) {
                                $arrInsert = array(
                                    'parent_member_id'   => $arrGroupedContacts[0]['parent_member_id'],
                                    'child_member_id'    => $arrGroupedContacts[0]['child_member_id'],
                                    'applicant_group_id' => $groupId,
                                    'row'                => 0
                                );
                                $db->insert(
                                    'members_relations',
                                    $arrInsert
                                );
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
