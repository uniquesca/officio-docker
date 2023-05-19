<?php

use Phinx\Migration\AbstractMigration;

class RegenerateMembersPasswords extends AbstractMigration
{
    public function up()
    {
        // No limits
        set_time_limit(0);
        ini_set('memory_limit', -1);

        if (!function_exists('password_hash')) {
            throw new Exception('password_hash is not supported.');
        }

        /** @var Uniques_Log */
        $log = Zend_Registry::get('log');

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('dbAdapter');


        $arrAllMembers = $this->fetchAll("SELECT `member_id`, `password_used_for_hash` FROM members WHERE `password_used_for_hash` != '' AND `password_used_for_hash` IS NOT NULL AND `password_hash` IS NULL");

        $fileName = 'migration_members_passwords_regeneration.log';
        $log->debugToFile('to update: ' . count($arrAllMembers), 1, 1, $fileName);

        $count = 0;
        foreach ($arrAllMembers as $arrMemberInfo) {
            $decoded = Uniques_Encryption::decode($arrMemberInfo['password_used_for_hash']);
            if (!empty($decoded)) {
                $count++;

                $db->update(
                    'members',
                    array(
                        'password_hash' => password_hash($decoded, PASSWORD_BCRYPT, array('cost' => 11)),
                    ),

                    $db->quoteInto('member_id = ?', $arrMemberInfo['member_id'], 'INT')
                );

                if ($count % 100 == 0) {
                    $log->debugToFile('updated: ' . $count, 1, 1, $fileName);

                    // Ping, so phinx connection will be alive
                    $this->fetchRow('SELECT 1');
                }
            } else {
                $log->debugToFile('members failed to decode: ' . $arrMemberInfo['member_id'] . ' - ' . $arrMemberInfo['password_used_for_hash'], 1, 1, $fileName);
            }
        }

        $log->debugToFile('total updated: ' . $count, 1, 1, $fileName);
    }

    public function down()
    {
    }
}
