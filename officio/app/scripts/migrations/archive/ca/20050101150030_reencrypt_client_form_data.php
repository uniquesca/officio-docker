<?php

use Laminas\Filter\Decrypt;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class ReencryptClientFormData extends AbstractMigration
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
            $fileName = 'migration_crypt_client_form_data.log';
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

            $statement = $this->getQueryBuilder()
                ->select('d.*')
                ->from(['d' => 'client_form_data'])
                ->innerJoin(['f' => 'client_form_fields'], ['d.field_id = f.field_id'])
                ->where(['f.encrypted' => 'Y'])
                ->whereNotNull('d.value')
                ->where(['d.value !=' => ''])
                ->execute();

            $arrValues = $statement->fetchAll('assoc');

            foreach ($arrValues as $arrValueInfo) {
                $encoded = $arrValueInfo['value'];
                $decoded = $this->decodeWithMcrypt($encoded, 'client_form_data');
                if ($decoded) {
                    $reencryptedValue = $encryption->encode($decoded);

                    $this->getQueryBuilder()
                        ->update('client_form_data')
                        ->set(['value' => $reencryptedValue])
                        ->where([
                            'member_id' => (int)$arrValueInfo['member_id'],
                            'field_id'  => (int)$arrValueInfo['field_id']
                        ])
                        ->execute();
                } else {
                   /** @var Log $log */
                    $log = self::getService('log');
                    $log->debugToFile(
                        'client data failed to decode: ' . $arrValueInfo['member_id'] . ' - ' . $arrValueInfo['field_id'] . ' - ' . $arrValueInfo['value']
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
