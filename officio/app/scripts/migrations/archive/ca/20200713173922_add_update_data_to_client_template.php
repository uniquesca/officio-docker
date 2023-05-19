<?php

use Officio\Migration\AbstractMigration;

class AddUpdateDataToClientTemplate extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `templates` CHANGE COLUMN `create_date` `create_date` DATETIME NULL DEFAULT NULL AFTER `message`;");
        $this->execute("ALTER TABLE `templates` ADD COLUMN `update_date` DATETIME NULL DEFAULT NULL AFTER `create_date`;");
        $this->execute("ALTER TABLE `templates` ADD COLUMN `updated_by_id` BIGINT(20) NULL DEFAULT NULL AFTER `member_id`;");
        $this->execute('ALTER TABLE `templates` ADD CONSTRAINT `FK1_templates_members` FOREIGN KEY (`updated_by_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;');

        $this->execute("UPDATE templates SET updated_by_id = member_id;");
        $this->execute("UPDATE templates SET update_date = create_date;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `templates` DROP COLUMN `update_date`;");
        $this->execute("ALTER TABLE `templates` DROP FOREIGN KEY `FK1_templates_members`;");
        $this->execute("ALTER TABLE `templates` DROP COLUMN `updated_by_id`;");
    }
}
