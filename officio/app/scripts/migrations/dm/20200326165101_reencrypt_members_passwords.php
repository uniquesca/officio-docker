<?php

use Laminas\Filter\Decrypt;
use Officio\Common\Service\Encryption;
use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ReencryptMembersPasswords extends AbstractMigration
{
    /** @var Decrypt */
    private $decrypt;

    private static function getKey()
    {
        $strEncryptionKey = self::getService('config')['security']['encryption_key'];

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
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $decoded = '';

        $uncompressed = @gzuncompress(stripslashes(base64_decode(strtr($encoded, '-_,', '+/='))));
        if ($uncompressed === false) {
            $str = str_repeat('*', 10);
            $info = $str . ' Encoded ' . $str . PHP_EOL . print_r($encoded, true) . PHP_EOL . PHP_EOL .
                $str . ' Table ' . $str . PHP_EOL . print_r($table, true) . PHP_EOL;
            /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugErrorToFile('Error decode', $info, 'crypt');
        } else {
            $encoded = unserialize($uncompressed);
            $decoded = rtrim($this->decrypt->filter($encoded), "\0");
        }

        return $decoded;
    }


    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder = $this->getQueryBuilder();

        try {
            $this->decrypt = new Decrypt([
                'adapter'   => 'Officio\Encrypt\Adapter\Mcrypt',
                'algorithm' => MCRYPT_RIJNDAEL_256,
                'vector'    => null,
                'mode'      => MCRYPT_MODE_ECB,
                'key'       => self::getKey()
            ]);

            $config = $serviceManager->get('config');
            if ($config['security']['password_hashing_algorithm'] === 'default') {
                throw new Exception('Please change `security.password_hashing_algorithm` setting to `password_hash`.');
            }

            $this->getAdapter()->beginTransaction();

            $statement = $builder
                ->select('*')
                ->from("members")
                ->where(function ($exp) {
                    return $exp
                        ->isNotNull('password')
                        ->notEq('TRIM(password)', '');
                })
                ->execute();

            $arrMembers = $statement->fetchAll('assoc');

            foreach ($arrMembers as $arrMemberInfo) {
                $encoded = $arrMemberInfo['password'];
                $decoded = $this->decodeWithMcrypt($encoded, 'members');
                if ($decoded) {
                    $reencryptedPassword = $serviceManager->get(Encryption::class)->hashPassword($decoded);
                    $builder
                        ->update('members')
                        ->set(array('password' => $reencryptedPassword))
                        ->where(
                            [
                                'member_id' => $arrMemberInfo['member_id']
                            ]
                        )
                        ->execute();
                }
            }

            $this->getAdapter()->commitTransaction();
        } catch (\Exception $e) {
            $this->getAdapter()->rollbackTransaction();
           /** @var Log $log */
            $log = $serviceManager->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}