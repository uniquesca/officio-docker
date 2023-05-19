<?php

use Phinx\Migration\AbstractMigration;

class AddConNumberStorage extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            CREATE TABLE `dm_con_numbers` (
                `dm_con_number_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
                `company_id` BIGINT(20) NOT NULL,
                `member_id` BIGINT(20) NOT NULL,
                `dependent_id` BIGINT(20) UNSIGNED DEFAULT NULL,
                `year` INT(11) NOT NULL,
                `number` INT(11) NOT NULL,
                PRIMARY KEY (`dm_con_number_id`),
                KEY `FK_dm_con_numbers_company_id` (`company_id`),
                KEY `FK_dm_con_numbers_member_id` (`member_id`),
                KEY `FK_dm_con_numbers_dependent_id` (`dependent_id`),
                CONSTRAINT `UQ_con_number` UNIQUE (`company_id`, `year`, `number`),
                CONSTRAINT `FK_dm_con_numbers_company_id` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_dm_con_numbers_member_id` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                CONSTRAINT `FK_dm_con_numbers_dependent_id` FOREIGN KEY (`dependent_id`) REFERENCES `client_form_dependents` (`dependent_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB;
        "
        );
    }

    public function down()
    {
        $this->execute("DELETE TABLE dm_con_numbers;");
    }
}