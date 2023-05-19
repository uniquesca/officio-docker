<?php

use Officio\Encryption;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ReencryptVevoPasswords extends AbstractMigration
{
    private $key;

    private static function getKey()
    {
        $strEncryptionKey = Zend_Registry::get('serviceManager')->get('config')['security']['encryption_key'];

        // Key is too large
        if (strlen($strEncryptionKey) > 32) {
            throw new Exception('Encryption key is too long. Please check config.');
        }

        // Allowed sizes
        $arrSizes = array(16, 24, 32);

        // Loop through sizes and pad key
        foreach ($arrSizes as $strSize) {
            while (strlen($strEncryptionKey) < $strSize) {
                $strEncryptionKey .= "\0";
            }
            if (strlen($strEncryptionKey) == $strSize) {
                // Finish if the key matches a size
                break;
            }
        }

        return $strEncryptionKey;
    }

    public function decodeWithMcrypt($encoded, $table)
    {
        $decoded = '';

        $uncompressed = @gzuncompress(stripslashes(base64_decode(strtr($encoded, '-_,', '+/='))));
        if ($uncompressed === false) {
            $str = str_repeat('*', 10);
            $info = $str . ' Encoded ' . $str . PHP_EOL . print_r($encoded, true) . PHP_EOL . PHP_EOL .
                $str . ' Table ' . $str . PHP_EOL . print_r($table, true) . PHP_EOL;
           /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile('Error decode', $info, 'crypt');
        } else {
            $arrParams = array(
                'adapter'   => 'mcrypt',
                'algorithm' => MCRYPT_RIJNDAEL_256,
                'vector'    => null,
                'mode'      => MCRYPT_MODE_ECB,
                'key'       => $this->key
            );

            $encoded = unserialize($uncompressed);
            $oFilter = new Zend_Filter_Decrypt($arrParams);
            $decoded = rtrim($oFilter->filter($encoded), "\0");
        }

        return $decoded;
    }


    public function up()
    {
        // Full unlim
        set_time_limit(0);
        ini_set('memory_limit', -1);

        try {
            $this->key = self::getKey();

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');
            $db->beginTransaction();

            $select = $db->select()
                ->from("users")
                ->where("vevo_password IS NOT NULL AND TRIM(vevo_password) <> ''");

            $arrUsers = $db->fetchAll($select);

            foreach ($arrUsers as $arrUserInfo) {
                $encoded = $arrUserInfo['vevo_password'];
                $decoded = $this->decodeWithMcrypt($encoded, 'users');
                if ($decoded) {
                    $reencryptedPassword = Encryption::encode($decoded);
                    $db->update(
                        'users',
                        array('vevo_password' => $reencryptedPassword),
                        sprintf('user_id = %d', $arrUserInfo['user_id'])
                    );
                } else {
                   /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugToFile(
                        'vevo failed to decode: ' . $arrUserInfo['user_id'] . ' - ' . $arrUserInfo['vevo_password']
                    );
                }

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
           /** @var Log $log */
            $log = Zend_Registry::get('serviceManager')->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}