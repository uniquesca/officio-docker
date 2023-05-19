<?php

use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\ExpressionInterface;
use Cake\Database\Query;
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
            $info = $str . ' Encoded ' . $str . PHP_EOL . print_r($encoded, true) . PHP_EOL . PHP_EOL . $str . ' Table ' . $str . PHP_EOL . print_r($table, true) . PHP_EOL;

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

            /** @var Log $log */
            $log      = self::getService('log');
            $fileName = 'migration_members_passwords_regeneration.log';
            $log->debugToFile('start', 0, 1, $fileName);

            $config = self::getService('config');
            if ($config['security']['password_hashing_algorithm'] === 'default') {
                throw new Exception('Please change `security.password_hashing_algorithm` setting to `password_hash`.');
            }

            // Make sure that there is no password set if username isn't set
            $this->getQueryBuilder()
                ->update('members')
                ->set(['password' => ''])
                ->where(function (QueryExpression $exp) {
                    return $exp
                        ->eq('username', '')
                        ->notEq('password', '')
                        ->isNotNull('password');
                })
                ->execute();

            $statement = $this->getQueryBuilder()
                ->select(['member_id', 'password'])
                ->from('members')
                ->where(function (QueryExpression $exp, Query $query) {
                    $wrapIdentifier = function ($field) {
                        if ($field instanceof ExpressionInterface) {
                            return $field;
                        }

                        return new IdentifierExpression($field);
                    };

                    $firstCondition = $query->newExpr()
                        ->notEq('password', '')
                        ->isNotNull('password')
                        ->isNull('password_hash');

                    $secondCondition = $query->newExpr()
                        ->notEq('password', '')
                        ->isNotNull('password')
                        ->notEq($wrapIdentifier('password'), $wrapIdentifier('password_used_for_hash'));

                    return $exp->or([
                        $firstCondition,
                        $secondCondition
                    ]);
                })
                ->order(['member_id' => 'ASC'])
                ->execute();

            $arrMembers = $statement->fetchAll('assoc');

            $log->debugToFile('to update: ' . count($arrMembers), 1, 1, $fileName);

            $count = 0;

            /** @var Encryption $encryption */
            $encryption = self::getService(Encryption::class);
            foreach ($arrMembers as $arrMemberInfo) {
                $decoded = $this->decodeWithMcrypt($arrMemberInfo['password'], 'members');
                if ($decoded) {
                    $count++;

                    $this->getQueryBuilder()
                        ->update('members')
                        ->set(['password_hash' => $encryption->hashPassword($decoded)])
                        ->where(['member_id' => $arrMemberInfo['member_id']])
                        ->execute();

                    if ($count % 100 == 0) {
                        $log->debugToFile('updated: ' . $count, 1, 1, $fileName);

                        // Ping, so phinx connection will be alive
                        $this->fetchRow('SELECT 1');
                    }
                } else {
                    $log->debugToFile('members failed to decode: ' . $arrMemberInfo['member_id'] . ' - ' . $arrMemberInfo['password'], 1, 1, $fileName);
                }
            }
            $log->debugToFile('total updated: ' . $count, 1, 1, $fileName);
        } catch (Exception $e) {
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
