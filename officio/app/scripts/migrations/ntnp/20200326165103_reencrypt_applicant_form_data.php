<?php

use Laminas\Filter\Decrypt;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class ReencryptApplicantFormData extends AbstractMigration
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
            $str            = str_repeat('*', 10);
            $info           = $str . ' Encoded ' . $str . PHP_EOL . print_r($encoded, true) . PHP_EOL . PHP_EOL .
                $str . ' Table ' . $str . PHP_EOL . print_r($table, true) . PHP_EOL;
            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
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
        // Full unlim
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            $this->decrypt = new Decrypt([
                'adapter'   => 'Officio\Encrypt\Adapter\Mcrypt',
                'algorithm' => MCRYPT_RIJNDAEL_256,
                'vector'    => null,
                'mode'      => MCRYPT_MODE_ECB,
                'key'       => self::getKey()
            ]);

            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
            /** @var Encryption $encryption */
            $encryption = $serviceManager->get(Encryption::class);

            $this->getAdapter()->beginTransaction();

            $builder   = $this->getQueryBuilder();
            $statement = $builder
                ->select('*')
                ->from(['d' => 'applicant_form_data'])
                ->innerJoin(['f' => 'applicant_form_fields'], ['d.applicant_field_id = f.applicant_field_id', ''])
                ->where(['f.encrypted' => 'Y'])
                ->whereNotNull('d.value')
                ->where(['d.value !=' => ''])
                ->execute();

            $arrValues = $statement->fetchAll();

            foreach ($arrValues as $arrValueInfo) {
                $encoded = $arrValueInfo['value'];
                $decoded = $this->decodeWithMcrypt($encoded, 'applicant_form_data');
                if ($decoded) {
                    $reencryptedValue = $encryption->encode($decoded);

                    $arrWhere                       = [];
                    $arrWhere['applicant_id']       = (int)$arrValueInfo['applicant_id'];
                    $arrWhere['applicant_field_id'] = (int)$arrValueInfo['applicant_field_id'];
                    $arrWhere['row']                = (int)$arrValueInfo['row'];

                    if (!empty($arrValueInfo['row_id'])) {
                        $arrWhere['row_id'] = $arrValueInfo['row_id'];
                    }

                    $builder = $this->getQueryBuilder();
                    $builder->update('applicant_form_data')
                        ->set(['value' => $reencryptedValue])
                        ->where($arrWhere)
                        ->execute();
                } else {
                    $application    = self::getApplication();
                    $serviceManager = $application->getServiceManager();
                    /** @var Log $log */
                    $log = $serviceManager->get('log');
                    $log->debugToFile(
                        'applicant_form_data failed to decode: ' . $arrValueInfo['applicant_id'] . ' - ' . $arrValueInfo['value']
                    );
                }

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');
            }

            $this->getAdapter()->commitTransaction();
        } catch (\Exception $e) {
            $this->getAdapter()->rollbackTransaction();
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
