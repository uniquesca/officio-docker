<?php

use Phinx\Migration\AbstractMigration;

class RenameLibya extends AbstractMigration
{
    public function up()
    {
        try {
            $this->execute("UPDATE `country_master` SET `countries_name`='Libya' WHERE `countries_name`='Libyan Arab Jamahiriya';");
        } catch (Exception $e) {
            Zend_Registry::get('log')->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $this->execute("UPDATE `country_master` SET `countries_name`='Libyan Arab Jamahiriya' WHERE `countries_name`='Libya';");
        } catch (Exception $e) {
            Zend_Registry::get('log')->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}
