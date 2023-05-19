<?php

use Phinx\Migration\AbstractMigration;

class AddPushToOffices extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select             = $db->select()
            ->from('divisions', array('division_group_id', 'division_id'))
            ->order(array('company_id', 'division_group_id'));
        $arrGroupsDivisions = $db->fetchAll($select);

        $arrGroupedDivisions = array();
        foreach ($arrGroupsDivisions as $arr) {
            if (isset($arrGroupedDivisions[$arr['division_group_id']])) {
                $arrGroupedDivisions[$arr['division_group_id']][] = $arr['division_id'];
            } else {
                $arrGroupedDivisions[$arr['division_group_id']] = array($arr['division_id']);
            }
        }

        $select         = $db->select()
            ->from('members_types', array('member_type_id'))
            ->where('member_type_name IN (?)', array('admin', 'user', 'agent'));
        $arrUserTypeIds = $db->fetchCol($select);

        $select = $db->select()
            ->from('members', array('member_id', 'division_group_id'))
            ->where('userType IN (?)', $arrUserTypeIds);

        $arrMembers = $db->fetchAssoc($select);

        $db->beginTransaction();

        $db->query(
            "ALTER TABLE `members_divisions`
	        CHANGE COLUMN `type` `type` ENUM('access_to','responsible_for','pull_from','push_to') NULL DEFAULT 'access_to' AFTER `division_id`;
	    "
        );

        foreach ($arrMembers as $memberId => $item) {
            $divisionGroupId = $item['division_group_id'];

            if (isset($arrGroupedDivisions[$divisionGroupId])) {
                foreach ($arrGroupedDivisions[$divisionGroupId] as $divisionId) {
                    $this->execute(
                        "
                        INSERT INTO `members_divisions`
                        (`member_id`, `division_id`, `type`) VALUES
                        ($memberId, $divisionId, 'push_to')
                    "
                    );
                }
            }
        }

        $db->commit();
    }

    public function down()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');
        $db->beginTransaction();

        $db->query("DELETE FROM `members_divisions` WHERE type = 'push_to'");

        $db->query(
            "ALTER TABLE `members_divisions`
	        CHANGE COLUMN `type` `type` ENUM('access_to','responsible_for','pull_from') NULL DEFAULT 'access_to' AFTER `division_id`;
	    "
        );

        $db->commit();
    }
}