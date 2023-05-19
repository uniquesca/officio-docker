<?php

use Officio\Migration\AbstractMigration;

class SetSystemNotesToAdmin extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select(['member_id'])
            ->from('members')
            ->where(['username' => 'admin'])
            ->execute();

        $arrMemberInfo = $statement->fetchAll('assoc');

        if (empty($arrMemberInfo) || empty($arrMemberInfo[0]['member_id'])) {
            throw new Exception('Admin user not found.');
        }


        $this->execute(sprintf("UPDATE `u_notes` SET `is_system`='Y' WHERE `author_id`=%d;", $arrMemberInfo[0]['member_id']));
    }

    public function down()
    {
    }
}
