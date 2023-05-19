<?php

use Officio\Migration\AbstractMigration;

class AddAgentsKeyForProspects extends AbstractMigration
{
    public function up()
    {
        // Took 69s on the local server
        $this->execute('UPDATE company_prospects SET agent_id = NULL WHERE agent_id NOT IN (SELECT m.member_id FROM members as m);');
        $this->execute('ALTER TABLE `company_prospects` CHANGE COLUMN `agent_id` `agent_id` BIGINT(20) NULL DEFAULT NULL AFTER `preferred_language`;');
        $this->execute('ALTER TABLE `company_prospects` ADD CONSTRAINT `FK_company_prospects_members` FOREIGN KEY (`agent_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `company_prospects` DROP FOREIGN KEY `FK_company_prospects_members`;');
        $this->execute('ALTER TABLE `company_prospects` CHANGE COLUMN `agent_id` `agent_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `preferred_language`;');
    }
}