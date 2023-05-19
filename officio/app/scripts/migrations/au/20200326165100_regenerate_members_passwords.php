<?php

use Laminas\Filter\Decrypt;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class RegenerateMembersPasswords extends AbstractMigration
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
        $decoded = '';

        $uncompressed = @gzuncompress(stripslashes(base64_decode(strtr($encoded, '-_,', '+/='))));
        if ($uncompressed === false) {
            $str  = str_repeat('*', 10);
            $info = $str . ' Encoded ' . $str . PHP_EOL . print_r($encoded, true) . PHP_EOL . PHP_EOL .
                $str . ' Table ' . $str . PHP_EOL . print_r($table, true) . PHP_EOL;

            /** @var Log $log */
            $log      = self::getService('log');
            $fileName = 'migration_crypt_members.log';
            $log->debugToFile('Error decode:' . PHP_EOL . $info, 0, 1, $fileName);
        } else {
            $encoded = unserialize($uncompressed);
            $decoded = rtrim($this->decrypt->filter($encoded), "\0");
        }

        return $decoded;
    }

    public function up()
    {
        // No limits
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            if (!function_exists('password_hash')) {
                throw new Exception('password_hash is not supported.');
            }

            $this->decrypt = new Decrypt([
                'adapter'   => 'Officio\Encrypt\Adapter\Mcrypt',
                'algorithm' => MCRYPT_RIJNDAEL_256,
                'vector'    => null,
                'mode'      => MCRYPT_MODE_ECB,
                'key'       => self::getKey()
            ]);

            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
            /** @var Log $log */
            $log      = $serviceManager->get('log');
            $fileName = 'passwords_regeneration.log';
            $log->debugToFile('start', 0, 1, $fileName);

            $table = $this->table('members');
            if (!$table->hasColumn('password_new')) {
                $this->execute('ALTER TABLE `members` ADD COLUMN `password_new` TEXT NULL DEFAULT NULL AFTER `password`');
                $log->debugToFile('new column was created', 1, 1, $fileName);
            } else {
                $log->debugToFile('new column was already created, skipped.', 1, 1, $fileName);
            }

            $config = $serviceManager->get('config');
            if ($config['security']['password_hashing_algorithm'] === 'default') {
                throw new Exception('Please change `security.password_hashing_algorithm` setting to `password_hash`.');
            }

            $statement = $this->getQueryBuilder()
                ->select(['member_id', 'password'])
                ->from('members')
                ->whereNotNull('password')
                ->where(['password !=' => ''])
                ->where(['OR' => [['password_new IS' => NULL], ['password_new NOT LIKE' => '$2y$%']]])
                ->order(['last_access' => 'DESC', 'member_id'])
                ->execute();

            $arrMembers = $statement->fetchAll('assoc');

            $log->debugToFile('total: ' . count($arrMembers), 1, 1, $fileName);

            $count = 0;
            /** @var Encryption $encryption */
            $encryption = self::getService(Encryption::class);
            foreach ($arrMembers as $arrMemberInfo) {
                $decoded = $this->decodeWithMcrypt($arrMemberInfo['password'], 'members');
                if ($decoded) {
                    $count++;

                    $this->getQueryBuilder()
                        ->update('members')
                        ->set(['password_new' => $encryption->hashPassword($decoded)])
                        ->where(['member_id' => $arrMemberInfo['member_id']])
                        ->execute();

                    if ($count % 100 == 0) {
                        $log->debugToFile('updated: ' . $count, 1, 1, $fileName);
                    }
                } else {
                    $log->debugToFile(
                        'members failed to decode: ' . $arrMemberInfo['member_id'] . ' - ' . $arrMemberInfo['password'],
                        1,
                        1,
                        $fileName
                    );
                }

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');
            }
        } catch (Exception $e) {
            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
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
