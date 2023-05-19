<?php

use Phinx\Migration\AbstractMigration;

class UpdateClientsGroupType extends AbstractMigration
{
    public function up()
    {
        // Took 46s on local server...

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from('client_types', array('company_id', 'client_type_id'));

        $arrClientTypes = $db->fetchAssoc($select);

        $select = $db->select()
            ->from(array('g' => 'client_form_groups'))
            ->where('g.client_type_id IS NULL');

        $arrGroups = $db->fetchAll($select);

        foreach ($arrGroups as $arrGroupInfo) {
            if (isset($arrClientTypes[$arrGroupInfo['company_id']])) {
                $db->update(
                    'client_form_groups',
                    array('client_type_id' => $arrClientTypes[$arrGroupInfo['company_id']]['client_type_id']),
                    $db->quoteInto('group_id = ?', $arrGroupInfo['group_id'])
                );
            } else {
                echo '<br>Not found: ' . $arrGroupInfo['group_id'] . '<br>';
            }
        }

        echo 'Done. Processed: ' . count($arrGroups) . PHP_EOL;
    }

    public function down()
    {
    }
}