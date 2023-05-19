<?php

use Clients\Service\Members;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class FixEmptyDivisionGroups extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from('members');

        $arrMembers = $db->fetchAll($select);

        $arrAssignedGroups   = array();
        $arrAssignedOffices  = array();
        $arrIncorrectOffices = array();
        foreach ($arrMembers as $arrMemberInfo) {
            $select = $db->select()
                ->from('divisions_groups', 'division_group_id')
                ->where('company_id = ?', $arrMemberInfo['company_id']);

            $arrDivisionGroups = $db->fetchCol($select);

            if (empty($arrDivisionGroups)) {
                throw new Exception(sprintf("Company %d doesn't have assigned division groups", $arrMemberInfo['company_id']));
            }

            $divisionGroupId = $arrMemberInfo['division_group_id'];
            if (empty($divisionGroupId)) {
                $divisionGroupId = $arrDivisionGroups[0];

                $db->update(
                    'members',
                    array('division_group_id' => $divisionGroupId),
                    $db->quoteInto('member_id = ?', $arrMemberInfo['member_id'], 'INT')
                );

                $arrAssignedGroups[$arrMemberInfo['member_id']] = $divisionGroupId;
            } elseif (!in_array($divisionGroupId, $arrDivisionGroups)) {
                throw new Exception(sprintf("Member %d has assigned incorrect division group: %d", $arrMemberInfo['member_id'], $divisionGroupId));
            }

            if (in_array($arrMemberInfo['userType'], Members::getMemberType('admin'))) {
                // If this is an admin - check if there are assigned offices from the same division group
                $select = $db->select()
                    ->from('members_divisions', 'division_id')
                    ->where('member_id = ?', $arrMemberInfo['member_id'], 'INT')
                    ->where('type = ?', 'access_to');

                $arrMemberDivisions = $db->fetchCol($select);

                $select = $db->select()
                    ->from('divisions', 'division_id')
                    ->where('division_group_id = ?', $divisionGroupId, 'INT')
                    ->where('company_id = ?', $arrMemberInfo['company_id'], 'INT');

                $arrGroupDivisions = $db->fetchCol($select);


                if (empty($arrMemberDivisions)) {
                    foreach ($arrGroupDivisions as $arrGroupDivisionId) {
                        $db->insert(
                            'members_divisions',
                            array(
                                'member_id'   => $arrMemberInfo['member_id'],
                                'division_id' => $arrGroupDivisionId,
                                'type'        => 'access_to'
                            )
                        );

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
        $log = Zend_Registry::get('serviceManager')->get('log');
        $log->debugErrorToFile('Assigned groups', print_r($arrAssignedGroups, 1), 'check_fixed_offices');
        $log->debugErrorToFile('Assigned offices', print_r($arrAssignedOffices, 1), 'check_fixed_offices');
        $log->debugErrorToFile('Incorrect offices', print_r($arrIncorrectOffices, 1), 'check_fixed_offices');
    }

    public function down()
    {
    }
}