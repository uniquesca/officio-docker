<?php

use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class FillCompanyCategories extends AbstractMigration
{
    public function up()
    {
        // Took 140s on local server...

        try {
            $this->execute("CREATE TABLE `company_default_options` (
                      `default_option_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `company_id` BIGINT(20) NULL DEFAULT NULL,
                      `default_option_type` ENUM('categories') NULL DEFAULT 'categories',
                      `default_option_name` CHAR(255) NULL DEFAULT '',
                      `default_option_order` TINYINT(3) UNSIGNED NULL DEFAULT '0',
                      PRIMARY KEY (`default_option_id`),
                      CONSTRAINT `FK_company_default_options` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            $statement = $this->getQueryBuilder()
                ->select(['f.company_id', 'd.form_default_id', 'd.field_id', 'd.value', 'd.order'])
                ->from(array('f' => 'client_form_fields'))
                ->innerJoin(array('d' => 'client_form_default'), 'd.field_id = f.field_id')
                ->where(['f.company_field_id' => 'categories'])
                ->execute();

            $arrCompanyFieldsOptions = $statement->fetchAll('assoc');

            foreach ($arrCompanyFieldsOptions as $arrCompanyFieldOptionInfo) {
                $arrInsert = array(
                    'company_id'           => $arrCompanyFieldOptionInfo['company_id'],
                    'default_option_type'  => 'categories',
                    'default_option_name'  => $arrCompanyFieldOptionInfo['value'],
                    'default_option_order' => $arrCompanyFieldOptionInfo['order'],
                );

                $statement = $this->getQueryBuilder()
                    ->insert(array_keys($arrInsert))
                    ->into('company_default_options')
                    ->values($arrInsert)
                    ->execute();

                $newId = $statement->lastInsertId('company_default_options');

                $this->getQueryBuilder()
                    ->update('client_form_data')
                    ->set('value', $newId)
                    ->where([
                        'field_id' => (int)$arrCompanyFieldOptionInfo['field_id'],
                        'value'    => $arrCompanyFieldOptionInfo['value']
                    ])
                    ->execute();
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
