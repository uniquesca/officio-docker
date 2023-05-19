<?php

use Phinx\Migration\AbstractMigration;

class AddFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `client_form_group_access` WHERE `group_id` NOT IN (SELECT group_id FROM client_form_groups);");
        $this->execute("ALTER TABLE `client_form_group_access` DROP FOREIGN KEY `FK_client_form_group_access_1`;");
        $this->execute("ALTER TABLE `client_form_group_access` ADD CONSTRAINT `FK_client_form_group_access_1` FOREIGN KEY (`group_id`) REFERENCES `client_form_groups` (`group_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("DELETE FROM `client_form_groups` WHERE `company_id` NOT IN (SELECT company_id FROM company);");
        $this->execute("ALTER TABLE `client_form_groups` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL DEFAULT NULL AFTER `group_id`;");
        $this->execute("ALTER TABLE `client_form_groups` ADD CONSTRAINT `FK_client_form_groups_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        
        $this->execute("ALTER TABLE `client_form_groups` ADD COLUMN `client_type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `company_id`;");

        $this->execute("ALTER TABLE `client_form_groups` ADD CONSTRAINT `FK_client_form_groups_client_types` FOREIGN KEY (`client_type_id`) REFERENCES `client_types` (`client_type_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
    }
}