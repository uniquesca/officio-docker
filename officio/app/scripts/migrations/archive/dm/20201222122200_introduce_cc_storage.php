<?php

use Phinx\Migration\AbstractMigration;

class IntroduceCcStorage extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `cc_tmp` (
            	`id` INT(11) NOT NULL AUTO_INCREMENT,
            	`case_id` INT(11) NOT NULL,
            	`charged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            	`name` VARCHAR(255) NOT NULL,
            	`number` VARCHAR(255) NOT NULL,
            	`exp_month` VARCHAR(255) NOT NULL,
            	`exp_year` VARCHAR(255) NOT NULL,
            	`amount` VARCHAR(255) NOT NULL,
            	`description` VARCHAR(1024),
            	PRIMARY KEY (`id`)
            )
            COLLATE='utf8_general_ci'
            ENGINE=InnoDB"
        );
    }

    public function down()
    {
        $this->execute("DELETE TABLE cc_tmp;");
    }
}