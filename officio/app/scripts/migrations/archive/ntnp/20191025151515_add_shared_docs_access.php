<?php

use Phinx\Migration\AbstractMigration;

class AddSharedDocsAccess extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `folder_access_by_division` (
        	`division_id` INT(11) UNSIGNED NOT NULL,
        	`folder_name` CHAR(255) NULL DEFAULT NULL,
        	`access` ENUM('R','RW','') NOT NULL DEFAULT '',
        	INDEX `division_id_folder_name` (`division_id`, `folder_name`),
        	CONSTRAINT `FK_folder_access_by_office_divisions` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`division_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Access rights to specific client folders based on offices'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB"
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `folder_access_by_division`;");
    }
}
