<?php

use Phinx\Migration\AbstractMigration;

class UpdateClientsType extends AbstractMigration
{
    public function up()
    {
        // Took 824.7612s on local server...

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from('client_types', array('company_id', 'client_type_id'));

        $arrClientTypes = $db->fetchAssoc($select);

        $select = $db->select()
            ->from(array('c' => 'clients'), 'client_id')
            ->joinInner(array('m' => 'members'), 'c.member_id = m.member_id', 'company_id')
            ->where('c.client_type_id IS NULL');

        $arrClients = $db->fetchAssoc($select);

        foreach ($arrClients as $arrClientInfo) {
            if (isset($arrClientTypes[$arrClientInfo['company_id']])) {
                $db->update(
                    'clients',
                    array('client_type_id' => $arrClientTypes[$arrClientInfo['company_id']]['client_type_id']),
                    $db->quoteInto('client_id = ?', $arrClientInfo['client_id'])
                );
            } else {
                echo 'Not found: ' . $arrClientInfo['client_id'] . PHP_EOL;
            }
        }

        echo 'Done. Processed: ' . count($arrClients) . PHP_EOL;
    }

    public function down()
    {
    }
}