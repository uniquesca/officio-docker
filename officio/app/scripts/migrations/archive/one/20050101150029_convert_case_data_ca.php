<?php

use Clients\Service\Clients;
use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Country;
use Phinx\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ConvertCaseDataCa extends AbstractMigration
{
    public function up()
    {
        // Took 60 522 s on local server...
        try {
            /** @var \Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');
            /** @var Clients $oClients */
            $oClients          = Zend_Registry::get('serviceManager')->get(Clients::class);
            $oFieldTypes = $oClients->getFieldTypes();
            /** @var \Officio\Service\Country $oCountry */
            $oCountry         = Zend_Registry::get('serviceManager')->get(Country::class);

            $arrFieldTypes = array(
                $oFieldTypes->getFieldTypeId('combo'),
                $oFieldTypes->getFieldTypeId('country'),
                $oFieldTypes->getFieldTypeId('radio'),
                $oFieldTypes->getFieldTypeId('categories'),
            );

            $select = $db->select()
                ->from(array('d' => 'client_form_data'), 'value')
                ->joinLeft(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', array('field_id', 'type'))
                ->where('f.type IN (?)', $arrFieldTypes)
                ->group(array('field_id', 'value'));

            $arrSavedCasesData = $db->fetchAll($select);

            $arrFieldIds = array();
            foreach ($arrSavedCasesData as $arrCaseData) {
                $arrFieldIds[$arrCaseData['field_id']] = $arrCaseData['field_id'];
            }

            $arrFieldsOptions = $oClients->getFields()->getFieldsOptions(array_values($arrFieldIds));

            foreach ($arrSavedCasesData as $arrCaseData) {
                $newVal = null;
                switch ($arrCaseData['type']) {
                    case $oFieldTypes->getFieldTypeId('combo'):
                    case $oFieldTypes->getFieldTypeId('categories'):
                    case $oFieldTypes->getFieldTypeId('radio'):
                        foreach ($arrFieldsOptions as $arrFieldsOptionInfo) {
                            if ($arrFieldsOptionInfo['field_id'] == $arrCaseData['field_id'] && $arrFieldsOptionInfo['value'] == $arrCaseData['value']) {
                                $newVal = $arrFieldsOptionInfo['form_default_id'];
                                break;
                            }
                        }
                        break;

                    case $oFieldTypes->getFieldTypeId('country'):
                        if (is_numeric($arrCaseData['value'])) {
                            $newVal = $oCountry->getCountryName($arrCaseData['value']);
                            if (empty($newVal)) {
                                $newVal = null;
                            }
                        }
                        break;

                    default:
                        break;
                }

                if (!is_null($newVal)) {
                    $sql = sprintf(
                        'UPDATE client_form_data SET value = %s WHERE field_id = %d AND value = %s',
                        $db->quoteInto('?', $newVal),
                        $arrCaseData['field_id'],
                        $db->quoteInto('?', $arrCaseData['value'])
                    );

                    $this->execute($sql);
                }
            }

            /** @var StorageInterface $cache */
            $cache = Zend_Registry::get('serviceManager')->get('cache');
            if ($cache instanceof FlushableInterface) {
                $cache->flush();
            }
            echo 'Done.' . PHP_EOL;

        } catch (\Exception $e) {
            echo 'Fatal error' . $e->getTraceAsString();
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