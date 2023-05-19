<?php

use Officio\Migration\AbstractMigration;

class AddQnrAgentKey extends AbstractMigration
{
    public function up()
    {
        $this->execute('UPDATE company_questionnaires SET q_agent_id = NULL WHERE q_agent_id NOT IN (SELECT member_id FROM members);');
        $this->execute('ALTER TABLE `company_questionnaires` CHANGE COLUMN `q_agent_id` `q_agent_id` BIGINT(20) NULL DEFAULT NULL AFTER `q_office_id`;');
        $this->execute('ALTER TABLE `company_questionnaires` ADD CONSTRAINT `FK_company_questionnaires_members` FOREIGN KEY (`q_agent_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');
    }

    public function down()
    {
        $this->execute('ALTER TABLE `company_questionnaires` DROP FOREIGN KEY `FK_company_questionnaires_members`;');
        $this->execute('ALTER TABLE `company_questionnaires` CHANGE COLUMN `q_agent_id` `q_agent_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `q_office_id`;');
    }
}