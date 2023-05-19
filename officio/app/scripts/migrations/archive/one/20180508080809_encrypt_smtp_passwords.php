<?php

use Officio\Encryption;
use Phinx\Migration\AbstractMigration;

class EncryptSmtpPasswords extends AbstractMigration
{
    public function up()
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('s' => 'superadmin_smtp'));

        $arrSavedData = $db->fetchAll($select);

        foreach ($arrSavedData as $arrSavedRow) {
            $db->update(
                'superadmin_smtp',
                array('smtp_password' => Encryption::encode($arrSavedRow['smtp_password'])),
                $db->quoteInto('smtp_id = ?', $arrSavedRow['smtp_id'], 'INT')
            );
        }
    }

    public function down()
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('s' => 'superadmin_smtp'));

        $arrSavedData = $db->fetchAll($select);

        foreach ($arrSavedData as $arrSavedRow) {
            $db->update(
                'superadmin_smtp',
                array('smtp_password' => Encryption::decode($arrSavedRow['smtp_password'])),
                $db->quoteInto('smtp_id = ?', $arrSavedRow['smtp_id'], 'INT')
            );
        }
    }
}