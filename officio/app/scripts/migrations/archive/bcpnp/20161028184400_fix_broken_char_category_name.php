<?php

use Officio\Service\AuthHelper;
use Phinx\Migration\AbstractMigration;

class FixBrokenCharCategoryName extends AbstractMigration
{

    public function authenticateAsSuperadmin(Zend_Db_Adapter_Abstract $db)
    {
        $_SERVER['HTTP_HOST']   = 'localhost';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $superAdminQuery = $db->select()
            ->from(array('m' => 'members'), array('m.username', 'm.password'))
            ->join(array('mt' => 'members_types'), 'mt.member_type_id = m.userType')
            ->where('mt.member_type_name = ?', 'superadmin')
            ->limit(1);
        if (!$superadmin = $db->fetchRow($superAdminQuery)) {
            return false;
        }

        $username          = $superadmin['username'];
        $passwordEncrypted = $superadmin['password'];
        $password          = Officio\Encryption::decode($passwordEncrypted);

        /** @var AuthHelper $auth */
        $auth = Zend_Registry::get('serviceManager')->get(AuthHelper::class);

        return $auth->login($username, $password, false, true);
    }

    public function authenticateAsCompanyAdmin(Zend_Db_Adapter_Abstract $db, $companyName)
    {
        $_SERVER['HTTP_HOST']   = 'localhost';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $adminQuery = $db->select()
            ->from(array('m' => 'members'), array('m.username', 'm.password'))
            ->join(array('mt' => 'members_types'), 'mt.member_type_id = m.userType')
            ->join(array('c' => 'company'), 'c.company_id = m.company_id')
            ->where('mt.member_type_name = ?', 'admin')
            ->where('c.companyName = ?', $companyName)
            ->limit(1);
        if (!$admin = $db->fetchRow($adminQuery)) {
            return false;
        }

        $username          = $admin['username'];
        $passwordEncrypted = $admin['password'];
        $password          = Officio\Encryption::decode($passwordEncrypted);

        /** @var AuthHelper $auth */
        $auth = Zend_Registry::get('serviceManager')->get(AuthHelper::class);

        return $auth->login($username, $password, false);
    }


    public function up()
    {
        /** @var Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->query(
            "
            UPDATE client_form_data SET value = REPLACE(value, 'â€“', '–');
        "
        );
    }

    public function down()
    {
    }
}
