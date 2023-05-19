<?php

use Phinx\Migration\AbstractMigration;

class SetTaOffices extends AbstractMigration
{
    public function up()
    {
        // Took 1s on local server...
        try {
            /** @var Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from('divisions', array('division_id', 'company_id'));

            $arrOffices = $db->fetchAll($select);

            $select = $db->select()
                ->from('company_ta', array('company_ta_id', 'company_id'));

            $arrTa = $db->fetchAll($select);

            $booInsert = false;
            $query     = "INSERT INTO company_ta_divisions (company_ta_id, division_id) VALUES ";
            foreach ($arrTa as $arrTaInfo) {
                foreach ($arrOffices as $arrOfficeInfo) {
                    if ($arrOfficeInfo['company_id'] == $arrTaInfo['company_id']) {
                        $query .= ' (' . $arrTaInfo['company_ta_id'] . ', ' . $arrOfficeInfo['division_id'] . '),';
                        $booInsert = true;
                    }
                }
            }

            if ($booInsert) {
                $query = rtrim($query, ',');
                $db->query($query);
            }
        } catch (\Exception $e) {
            echo 'Fatal error' . print_r($e->getTraceAsString(), 1);
            throw $e;
        }
    }

    public function down()
    {
    }
}