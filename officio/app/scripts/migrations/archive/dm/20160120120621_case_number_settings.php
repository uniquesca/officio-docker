<?php

use Phinx\Migration\AbstractMigration;

class CaseNumberSettings extends AbstractMigration
{

    protected function killKey()
    {
        $this->execute('ALTER TABLE `company_details` DROP FOREIGN KEY `FK_company_details_company`;');
    }

    protected function createKey()
    {
        $this->execute('ALTER TABLE `company_details` ADD CONSTRAINT `FK_company_details_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');
    }

    public function up()
    {
        $this->killKey();
        $this->execute('ALTER TABLE `company_details` ALTER `company_id` DROP DEFAULT;');
        $this->execute('ALTER TABLE `company_details` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL FIRST;');
        $this->createKey();
    }

    public function down()
    {
        $this->killKey();
        $this->execute('ALTER TABLE `company_details` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AUTO_INCREMENT FIRST;');
        $this->createKey();
    }
}
