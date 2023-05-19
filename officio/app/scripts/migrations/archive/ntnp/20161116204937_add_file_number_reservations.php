<?php

use Phinx\Migration\AbstractMigration;

class AddFIleNumberReservations extends AbstractMigration
{
    public function up()
    {
        $this->query(
            "
            CREATE TABLE `file_number_reservations` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `company_id` INT(11) NOT NULL,
                `file_number` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `file_number` (`company_id`, `file_number`)
            ) COLLATE='utf8_general_ci' ENGINE=InnoDB;
            
            INSERT IGNORE INTO file_number_reservations (company_id, file_number)
            SELECT m.company_id, c.fileNumber 
            FROM members as m 
            LEFT JOIN clients as c ON c.member_id = m.member_id
            WHERE (c.fileNumber IS NOT NULL) AND (c.fileNumber <> '');
        "
        );

        $this->query(
            "
            CREATE FUNCTION reserve_file_number (company_id_to_check INT(11), file_number_to_check VARCHAR(255))
                RETURNS INT(1)
                NOT DETERMINISTIC
                MODIFIES SQL DATA
                BEGIN
                    DECLARE result INT(1);
                    SET @exists = (SELECT  COUNT(*) FROM `file_number_reservations` WHERE `file_number` = file_number_to_check AND company_id = company_id_to_check);
                
                    IF (@exists > 0) THEN
                        SET result = 0;
                    ELSE
                        INSERT INTO file_number_reservations (company_id, file_number) VALUES (company_id_to_check, file_number_to_check);
                        SET result = 1;
                    END IF;
                    
                    RETURN result;
                END
        "
        );
    }

    public function down()
    {
        $this->query(
            "
            DROP FUNCTION IF EXISTS reserve_file_number;
            DROP TABLE `file_number_reservations`;
        "
        );
    }
}