<?php

use Officio\Encryption;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ReencryptMembersPasswords extends AbstractMigration
{
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
            $oConfig = Zend_Registry::get('serviceManager')->get('config')['security']['encoding_decoding'];

            $adapter = 'mcrypt';
            $algorithm = empty($oConfig['mcrypt_algorithm']) ? MCRYPT_RIJNDAEL_256 : $oConfig['mcrypt_algorithm'];
            $mode = empty($oConfig['mcrypt_mode']) ? MCRYPT_MODE_ECB : $oConfig['mcrypt_mode'];
            $vector = empty($oConfig['mcrypt_vector']) ? null : $oConfig['mcrypt_vector'];

            $arrParams = array(
                'adapter' => $adapter,
                'algorithm' => $algorithm,
                'vector' => $vector,
                'mode' => $mode,
                'key' => self::getKey()
            );

            $encoded = unserialize($uncompressed);
            $oFilter = new Zend_Filter_Decrypt($arrParams);
            $decoded = rtrim($oFilter->filter($encoded), "\0");
        }

        return $decoded;
    }


    public function up()
    {
        try {
            $config = Zend_Registry::get('serviceManager')->get('config');
            if ($config['security']['password_hashing_algorithm'] === 'default') {
                throw new Exception('Please change `security.password_hashing_algorithm` setting to `password_hash`.');
            }

            /** @var $db Zend_Db_Adapter_Abstract */
            $db = Zend_Registry::get('serviceManager')->get('db');
            $db->beginTransaction();

            $select = $db->select()
                ->from("members")
                ->where("password IS NOT NULL AND TRIM(password) <> ''");

            $arrMembers = $db->fetchAll($select);

            foreach ($arrMembers as $arrMemberInfo) {
                $encoded = $arrMemberInfo['password'];
                $decoded = $this->decodeWithMcrypt($encoded, 'members');
                if ($decoded) {
                    $reencryptedPassword = Encryption::hashPassword($decoded);
                    $db->update(
                        'members',
                        array('password' => $reencryptedPassword),
                        sprintf('member_id = %d', $arrMemberInfo['member_id'])
                    );
                }
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