<?php

use Officio\Migration\AbstractMigration;

class AddPrimaryKeysForClients extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_data`
            CHANGE COLUMN `member_id` `member_id` BIGINT(19) NOT NULL FIRST,
            CHANGE COLUMN `field_id` `field_id` INT(10) UNSIGNED NOT NULL AFTER `member_id`,
            DROP FOREIGN KEY `FK_client_form_data_2`,
            DROP FOREIGN KEY `FK_client_form_data_1`;");
        $this->execute("ALTER TABLE `client_form_data` ADD PRIMARY KEY (`member_id`, `field_id`);");
        $this->execute("ALTER TABLE `client_form_data`
            ADD CONSTRAINT `FK_client_form_data_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            ADD CONSTRAINT `FK_client_form_data_client_form_fields` FOREIGN KEY (`field_id`) REFERENCES `client_form_fields` (`field_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
    }

    public function down()
    {
    }
}
