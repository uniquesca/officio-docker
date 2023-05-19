<?php

use Phinx\Migration\AbstractMigration;

class InternalContactsRefactoring extends AbstractMigration
{
    public function up()
    {
        $this->getAdapter()->beginTransaction();

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select1 = $db->select()
            ->from(array('members'), array('member_id'))
            ->where('userType = ?', 9);

        $memberIds = $db->fetchCol($select1);

        $select2 = $db->select()
            ->from(array('automatic_reminders_processed'))
            ->where('member_id IN (?)', $memberIds);

        $reminderProcessed = $db->fetchAll($select2);

        $select3 = $db->select()
            ->distinct()
            ->from(array('members_relations'), array('child_member_id', 'parent_member_id'))
            ->where('child_member_id IN (?)', $memberIds);

        $parents = $db->fetchAssoc($select3);

        foreach ($reminderProcessed as $reminder) {
            if (isset($parents[$reminder['member_id']]['parent_member_id'])) {
                $db->update(
                    'automatic_reminders_processed',
                    array(
                        'member_id' => $parents[$reminder['member_id']]['parent_member_id'],
                    ),
                    $db->quoteInto('member_id = ?', $reminder['member_id'])
                );
            }
        }

        $this->getAdapter()->commitTransaction();
    }

    public function down()
    {
    }
}