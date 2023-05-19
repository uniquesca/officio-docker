<?php

use Phinx\Migration\AbstractMigration;

class FixEmptyAccessToFieldsForAdmins extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        $select    = $db->select()
            ->from('members_types', array('member_type_id'))
            ->where('member_type_name IN (?)', array('admin', 'user', 'agent'));
        $arrUserTypeIds = $db->fetchCol($select);

        $select    = $db->select()
            ->from('acl_roles', array('role_id', 'role_type'));
        $arrRolesTypes = $db->fetchAssoc($select);

        // Create mapper for member_id -> division_group_id
        $select    = $db->select()
            ->from('members', array('member_id','division_group_id'))
            ->where('userType IN (?)', $arrUserTypeIds);

        $arrMembers = $db->fetchAssoc($select);

        // Create mapper for division_group -> division
        $select    = $db->select()
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

        foreach ($arrMembers as $member) {
            $select    = $db->select()
                ->from('members_roles', 'role_id')
                ->where("member_id = ?", $member['member_id']);
            $arrRoles = $db->fetchCol($select);
            foreach ($arrRoles as $roleId) {
                if (isset($arrRolesTypes[$roleId]['role_type']) && $arrRolesTypes[$roleId]['role_type'] == 'admin') {
                    foreach ($arrGroupedDivisions[$member['division_group_id']] as $divisionId) {
                        $this->execute("
                            INSERT IGNORE INTO `members_divisions`
                            (`member_id`, `division_id`, `type`) VALUES
                            (" . $member['member_id'] . ", $divisionId, 'access_to')
                        ");
                    }
                    break;
                }
            }
        }

        $db->commit();
    }

    public function down()
    {
    }
}