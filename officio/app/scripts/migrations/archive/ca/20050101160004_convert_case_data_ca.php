<?php

use Clients\Service\Clients;
use Officio\Common\Service\Country;
use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ConvertCaseDataCa extends AbstractMigration
{
    public function up()
    {
        // Took 5493s on the local server...
        try {
            /** @var Clients $oClients */
            $oClients    = self::getService(Clients::class);
            $oFieldTypes = $oClients->getFieldTypes();

            /** @var Country $oCountry */
            $oCountry = self::getService(Country::class);

            $arrFieldTypes = array(
                $oFieldTypes->getFieldTypeId('combo'),
                $oFieldTypes->getFieldTypeId('country'),
                $oFieldTypes->getFieldTypeId('radio'),
                $oFieldTypes->getFieldTypeId('categories'),
            );

            $statement = $this->getQueryBuilder()
                ->select(['d.value', 'f.field_id', 'f.type', 'f.company_id'])
                ->from(['d' => 'client_form_data'])
                ->leftJoin(['f' => 'client_form_fields'], ['d.field_id = f.field_id'])
                ->where(['f.type IN ' => $arrFieldTypes])
                ->execute();

            $arrSavedCasesData = $statement->fetchAll('assoc');

            $arrFieldIds   = array();
            $arrCompanyIds = array();
            foreach ($arrSavedCasesData as $arrCaseData) {
                $arrFieldIds[$arrCaseData['field_id']] = $arrCaseData['field_id'];
                $arrCompanyIds[$arrCaseData['company_id']] = $arrCaseData['company_id'];
            }

            $arrCompaniesCategories = array();
            if (!empty($arrCompanyIds)) {
                $statement = $this->getQueryBuilder()
                    ->select('*')
                    ->from(['company_default_options'])
                    ->where([
                        'company_id IN' => array_values($arrCompanyIds),
                        'default_option_type' => 'categories'
                    ])
                    ->execute();

                $arrAllCompaniesCategories = $statement->fetchAll('assoc');
                foreach ($arrAllCompaniesCategories as $arrAllCompaniesCategoriesInfo) {
                    $arrCompaniesCategories[$arrAllCompaniesCategoriesInfo['company_id']][$arrAllCompaniesCategoriesInfo['default_option_name']] = $arrAllCompaniesCategoriesInfo['default_option_id'];
                }
            }

            $arrFieldsOptions = $oClients->getFields()->getFieldsOptions(array_values($arrFieldIds));

            foreach ($arrSavedCasesData as $arrCaseData) {
                $newVal = null;
                switch ($arrCaseData['type']) {
                    case $oFieldTypes->getFieldTypeId('combo'):
                    case $oFieldTypes->getFieldTypeId('radio'):
                        foreach ($arrFieldsOptions as $arrFieldsOptionInfo) {
                            if ($arrFieldsOptionInfo['field_id'] == $arrCaseData['field_id'] && $arrFieldsOptionInfo['value'] == $arrCaseData['value']) {
                                $newVal = $arrFieldsOptionInfo['form_default_id'];
                                break;
                            }
                        }
                        break;

                    case $oFieldTypes->getFieldTypeId('categories'):
                        if (!empty($arrCaseData['value']) && isset($arrCompaniesCategories[$arrCaseData['company_id']][$arrCaseData['value']])) {
                            $newVal = $arrCompaniesCategories[$arrCaseData['company_id']][$arrCaseData['value']];
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
                    $this->getQueryBuilder()
                        ->update('client_form_data')
                        ->set(['value' => $newVal])
                        ->where([
                            'field_id' => $arrCaseData['field_id'],
                            'value'    => $arrCaseData['value']
                        ])
                        ->execute();
                }
            }

            echo 'Done.' . PHP_EOL;
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
