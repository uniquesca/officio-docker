<?php

use Laminas\Filter\Decrypt;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class ReencryptEmlPasswords extends AbstractMigration
{
    private $key;

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
        $decoded = '';

        $uncompressed = @gzuncompress(stripslashes(base64_decode(strtr($encoded, '-_,', '+/='))));
        if ($uncompressed === false) {
            $str  = str_repeat('*', 10);
            $info = $str . ' Encoded ' . $str . PHP_EOL . print_r($encoded, true) . PHP_EOL . PHP_EOL . $str . ' Table ' . $str . PHP_EOL . print_r($table, true) . PHP_EOL;

            /** @var Log $log */
            $log = self::getService('log');
            $fileName = 'migration_eml_passwords.log';
            $log->debugToFile('Error decode:' . PHP_EOL . $info, 0, 1, $fileName);
        } else {
            $oFilter = new Decrypt([
                'adapter'   => 'Officio\Encrypt\Adapter\Mcrypt',
                'algorithm' => MCRYPT_RIJNDAEL_256,
                'vector'    => null,
                'mode'      => MCRYPT_MODE_ECB,
                'key'       => $this->key
            ]);

            $encoded = unserialize($uncompressed);
            $decoded = rtrim($oFilter->filter($encoded), "\0");
        }

        return $decoded;
    }

    public function up()
    {
        // Full unlim
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            $this->key = self::getKey();

            /** @var Encryption $encryption */
            $encryption = self::getService(Encryption::class);

            $this->execute("ALTER TABLE `eml_accounts`
                CHANGE COLUMN `inc_password` `inc_password` TEXT NULL DEFAULT NULL AFTER `inc_login`,
                CHANGE COLUMN `out_password` `out_password` TEXT NULL DEFAULT NULL AFTER `out_login`;");

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('eml_accounts')
                ->whereNotNull('inc_password')
                ->where(['inc_password !=' => ''])
                ->execute();

            $arrEmlAccounts = $statement->fetchAll('assoc');

            foreach ($arrEmlAccounts as $arrEmlInfo) {
                $encoded = $arrEmlInfo['inc_password'];
                $decoded = $this->decodeWithMcrypt($encoded, 'eml_accounts');
                if ($decoded) {
                    $reencryptedPassword = $encryption->encode($decoded);

                    $this->getQueryBuilder()
                        ->update('eml_accounts')
                        ->set(['inc_password' => $reencryptedPassword])
                        ->where(['id' => $arrEmlInfo['id']])
                        ->execute();
                } else {
                    /** @var Log $log */
                    $log = self::getService('log');
                    $log->debugToFile(
                        'eml inc failed to decode: ' . $arrEmlInfo['id'] . ' - ' . $arrEmlInfo['inc_password']
                    );
                }

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');
            }

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('eml_accounts')
                ->whereNotNull('out_password')
                ->where(['out_password !=' => ''])
                ->execute();

            $arrEmlAccounts = $statement->fetchAll('assoc');

            foreach ($arrEmlAccounts as $arrEmlInfo) {
                $encoded = $arrEmlInfo['out_password'];
                $decoded = $this->decodeWithMcrypt($encoded, 'eml_accounts');
                if ($decoded) {
                    $reencryptedPassword = $encryption->encode($decoded);

                    $this->getQueryBuilder()
                        ->update('eml_accounts')
                        ->set(['out_password' => $reencryptedPassword])
                        ->where(['id' => $arrEmlInfo['id']])
                        ->execute();
                } else {
                    /** @var Log $log */
                    $log = self::getService('log');
                    $log->debugToFile(
                        'eml out failed to decode: ' . $arrEmlInfo['id'] . ' - ' . $arrEmlInfo['out_password']
                    );
                }

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}
