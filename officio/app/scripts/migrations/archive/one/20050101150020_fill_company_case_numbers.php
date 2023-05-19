<?php

use Phinx\Migration\AbstractMigration;

class FillCompanyCaseNumbers extends AbstractMigration
{
    public function up()
    {
        $this->execute('ALTER TABLE `clients` DROP COLUMN `case_number_in_company`;');
        $this->execute('ALTER TABLE `clients` CHANGE COLUMN `client_number_in_company` `case_number_in_company` SMALLINT(5) UNSIGNED NULL DEFAULT NULL AFTER `case_number_of_parent_client`;');
    }

    public function down()
    {
    }
}