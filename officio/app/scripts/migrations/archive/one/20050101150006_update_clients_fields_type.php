<?php

use Phinx\Migration\AbstractMigration;

class UpdateClientsFieldsType extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_field_access` ADD COLUMN `client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `field_id`;");
        $this->execute("ALTER TABLE `client_form_field_access` ADD CONSTRAINT `FK_client_form_field_access_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE NO ACTION ON DELETE NO ACTION;");

        // Took 1681.2992s on local server...

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from('client_types', array('company_id', 'client_type_id'));

        $arrClientTypes = $db->fetchAssoc($select);

        $select = $db->select()
            ->from(array('a' => 'client_form_field_access'))
            ->joinLeft(array('f' => 'client_form_fields'), 'a.field_id = f.field_id', 'company_id')
            ->where('a.client_type_id IS NULL');

        $arrFields = $db->fetchAll($select);

        foreach ($arrFields as $arrFieldInfo) {
            if (isset($arrClientTypes[$arrFieldInfo['company_id']])) {
                $db->update(
                    'client_form_field_access',
                    array('client_type_id' => $arrClientTypes[$arrFieldInfo['company_id']]['client_type_id']),
                    $db->quoteInto('access_id = ?', $arrFieldInfo['access_id'])
                );
            } else {
                echo '<br>Not found: ' . $arrFieldInfo['field_id'] . '<br>';
            }
        }

        echo 'Done. Processed: ' . count($arrFields) . PHP_EOL;
    }

    public function down()
    {
    }
}