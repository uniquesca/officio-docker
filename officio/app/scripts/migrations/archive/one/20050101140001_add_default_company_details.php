<?php

use Phinx\Migration\AbstractMigration;

class AddDefaultCompanyDetails extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->query('SET FOREIGN_KEY_CHECKS=0');
        $db->insert('company_details', array('company_id' => 0));
        $companyId = $db->lastInsertId('company_details');
        $db->update('company_details', array('company_id' => 0), $db->quoteInto('company_id = ?', $companyId, 'INT'));
        $db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down()
    {
        $this->execute('DELETE FROM `company_details` WHERE  `company_id` = 0');
    }
}