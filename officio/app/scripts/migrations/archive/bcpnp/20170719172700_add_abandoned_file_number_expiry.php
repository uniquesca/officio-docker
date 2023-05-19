<?php

use Phinx\Migration\AbstractMigration;

class AddAbandonedFileNumberExpiry extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE file_number_reservations ADD COLUMN reserved_on INT(11) NOT NULL;");


        $this->execute("
            UPDATE file_number_reservations fnr
            INNER JOIN clients c ON c.fileNumber = fnr.file_number
            INNER JOIN members m ON m.company_id = fnr.company_id AND m.member_id = c.member_id
            SET fnr.reserved_on = m.regTime;
        ");

        $this->execute("
            UPDATE file_number_reservations fnr
            SET fnr.reserved_on = UNIX_TIMESTAMP()
            WHERE fnr.reserved_on IS NULL or fnr.reserved_on = 0;
        ");

        $this->execute("
            CREATE FUNCTION expire_abandoned_file_number_reservations (expire_before INT)
                RETURNS INT
                BEGIN
                    DELETE fnr 
                    FROM file_number_reservations fnr
                    LEFT OUTER JOIN clients c ON c.fileNumber = fnr.file_number
                    LEFT OUTER JOIN members m ON m.member_id = c.member_id AND m.company_id = fnr.company_id
                    WHERE fnr.reserved_on < expire_before AND m.member_id IS NULL;
                    
                    RETURN ROW_COUNT();
                END
        ");

        $this->execute("DROP FUNCTION reserve_file_number;");

        $this->execute("
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
        ");
    }

    public function down()
    {
        $this->execute("DROP FUNCTION IF EXISTS expire_abandoned_file_number_reservations;");
        $this->execute("ALTER TABLE file_number_reservations DROP COLUMN reserved_on;");

        $this->execute("DROP FUNCTION reserve_file_number;");

        $this->execute("
            CREATE FUNCTION reserve_file_number (company_id_to_check INT(11), file_number_to_check VARCHAR(255))
                RETURNS INT(1)
                BEGIN
                    DECLARE result INT(1);
                    SET @exists = (SELECT  COUNT(*) FROM `file_number_reservations` WHERE `file_number` = file_number_to_check AND company_id = company_id_to_check);
                
                    IF (@exists > 0) THEN
                        SET result = 0;
                    ELSE
                        INSERT INTO file_number_reservations (company_id, file_number, reserved_on) VALUES (company_id_to_check, file_number_to_check);
                        SET result = 1;
                    END IF;
                    
                    RETURN result;
                END
        ");
    }
}
