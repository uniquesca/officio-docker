<?php

use Phinx\Migration\AbstractMigration;

class FillCompanyCategories extends AbstractMigration
{
    public function up()
    {
        // Took 228s on local server...
        $this->execute("CREATE TABLE `company_default_options` (
                  `default_option_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `company_id` BIGINT(20) NULL DEFAULT NULL,
                  `default_option_type` ENUM('categories') NULL DEFAULT 'categories',
                  `default_option_name` CHAR(255) NULL DEFAULT '',
                  `default_option_order` TINYINT(3) UNSIGNED NULL DEFAULT '0',
                  PRIMARY KEY (`default_option_id`),
                  CONSTRAINT `FK_company_default_options` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

        try {
            /** @var Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from(array('c' => 'company'), array(new Zend_Db_Expr('SQL_CALC_FOUND_ROWS c.company_id')))
                ->order('company_id ASC');

            $companyIds = $db->fetchCol($select);


            $select = $db->select()
                ->from(array('f' => 'client_form_fields'), array('company_id'))
                ->joinLeft(array('d' => 'client_form_default'), 'd.field_id = f.field_id')
                ->where("f.company_field_id = ?", 'categories')
                ->where("f.company_id IN (?)", $companyIds);
            $arrCompanyFieldsOptions = $db->fetchAll($select);

            foreach ($arrCompanyFieldsOptions as $arrCompanyFieldOptionInfo) {
                $arrInsert = array(
                    'company_id'           => $arrCompanyFieldOptionInfo['company_id'],
                    'default_option_type'  => 'categories',
                    'default_option_name'  => $arrCompanyFieldOptionInfo['value'],
                    'default_option_order' => $arrCompanyFieldOptionInfo['order'],
                );
                $db->insert('company_default_options', $arrInsert);

                $newId = $db->lastInsertId('company_default_options');
                $oldId = $arrCompanyFieldOptionInfo['form_default_id'];

                $db->update(
                    'client_form_data',
                    array('value' => $newId),
                    $db->quoteInto('field_id = ? AND ', $arrCompanyFieldOptionInfo['field_id'], 'INT') .
                    $db->quoteInto('value = ?', $oldId)
                );
            }
        } catch (\Exception $e) {
            echo 'Fatal error' . print_r($e->getTraceAsString(), 1);
        }
    }

    public function down()
    {
    }
}