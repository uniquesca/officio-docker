<?php

use Phinx\Migration\AbstractMigration;

class AddFileNumberReservationIncrement extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `file_number_reservations`
	        ADD COLUMN `increment` INT(11) NULL DEFAULT NULL AFTER `reserved_on`;"
        );

        $this->execute("DROP FUNCTION IF EXISTS reserve_file_number;");

        $this->execute(
            "
            CREATE FUNCTION reserve_file_number (company_id_to_check INT(11), file_number_to_check VARCHAR(255), increment INT(11))
                RETURNS INT(1)
                BEGIN
                    DECLARE result INT(1);
                    SET @exists = (SELECT  COUNT(*) FROM `file_number_reservations` WHERE `file_number` = file_number_to_check AND company_id = company_id_to_check);
                
                    IF (@exists > 0) THEN
                        SET result = 0;
                    ELSE
                        INSERT INTO file_number_reservations (company_id, file_number, reserved_on, increment) VALUES (company_id_to_check, file_number_to_check, UNIX_TIMESTAMP(), increment);
                        SET result = 1;
                    END IF;
                    
                    RETURN result;
                END
        "
        );
    }

    public function down()
    {
        $this->execute("DROP FUNCTION reserve_file_number;");

        $this->execute(
            "
            CREATE FUNCTION reserve_file_number (company_id_to_check INT(11), file_number_to_check VARCHAR(255))
                RETURNS INT(1)
                BEGIN
                    DECLARE result INT(1);
                    SET @exists = (SELECT  COUNT(*) FROM `file_number_reservations` WHERE `file_number` = file_number_to_check AND company_id = company_id_to_check);
                
                    IF (@exists > 0) THEN
                        SET result = 0;
                    ELSE
                        INSERT INTO file_number_reservations (company_id, file_number, reserved_on) VALUES (company_id_to_check, file_number_to_check, UNIX_TIMESTAMP());
                        SET result = 1;
                    END IF;
                    
                    RETURN result;
                END
        "
        );

        $this->execute("ALTER TABLE `file_number_reservations` DROP COLUMN `increment`;");
    }
}