<?php

use Phinx\Migration\AbstractMigration;

class AddUpdateDataToProspectTemplate extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_prospects_templates` CHANGE COLUMN `create_date` `create_date` DATETIME NULL DEFAULT NULL AFTER `template_default`;");
        $this->execute("ALTER TABLE `company_prospects_templates` ADD COLUMN `update_date` DATETIME NULL DEFAULT NULL AFTER `create_date`;");
        $this->execute("ALTER TABLE `company_prospects_templates` ADD COLUMN `updated_by_id` BIGINT(20) NULL DEFAULT NULL AFTER `author_id`;");
        $this->execute('ALTER TABLE `company_prospects_templates` ADD CONSTRAINT `FK1_company_prospects_templates_members` FOREIGN KEY (`updated_by_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute("UPDATE company_prospects_templates SET updated_by_id = author_id;");
        $this->execute("UPDATE company_prospects_templates SET update_date = create_date;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects_templates` DROP COLUMN `update_date`;");
        $this->execute("ALTER TABLE `company_prospects_templates` DROP FOREIGN KEY `FK1_company_prospects_templates_members`;");
        $this->execute("ALTER TABLE `company_prospects_templates` DROP COLUMN `updated_by_id`;");
    }
}
