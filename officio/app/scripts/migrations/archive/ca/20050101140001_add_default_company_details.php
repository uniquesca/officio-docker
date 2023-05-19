<?php

use Officio\Migration\AbstractMigration;

class AddDefaultCompanyDetails extends AbstractMigration
{
    public function up()
    {
        $this->query('SET FOREIGN_KEY_CHECKS=0');

        $statement = $this->getQueryBuilder()
            ->insert(array('company_id'))
            ->into('company_details')
            ->values(array('company_id' => 0))
            ->execute();
        $companyId = $statement->lastInsertId('company_details');

        $this->getQueryBuilder()
            ->update('company_details')
            ->set('company_id', '0')
            ->where(['company_id' => (int)$companyId])
            ->execute();
        $this->query('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down()
    {
        $this->execute('DELETE FROM `company_details` WHERE  `company_id` = 0');
    }
}
