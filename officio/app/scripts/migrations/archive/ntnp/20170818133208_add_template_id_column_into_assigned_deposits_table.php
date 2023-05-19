<?php

use Phinx\Migration\AbstractMigration;

class AddTemplateIdColumnIntoAssignedDepositsTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_assigned_deposits` ADD COLUMN `template_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `trust_account_id`;");
        $this->execute("ALTER TABLE `u_assigned_deposits` ADD CONSTRAINT `FK_u_assigned_deposits_templates` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`) ON UPDATE SET NULL ON DELETE SET NULL;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_assigned_deposits` DROP FOREIGN KEY `FK_u_assigned_deposits_templates`;");
        $this->execute("ALTER TABLE `u_assigned_deposits` DROP COLUMN `template_id`;");
    }
}