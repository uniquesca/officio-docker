<?php

use Officio\Migration\AbstractMigration;

class AddExpireAbandonedFileNumberReservations extends AbstractMigration
{
    public function up()
    {
        $this->execute("
        CREATE FUNCTION `reserve_file_number`(company_id_to_check INT(11), file_number_to_check VARCHAR(255), increment INT(11)) RETURNS int(1)
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
        END");

        $this->execute("
        CREATE FUNCTION `release_file_number`(company_id_to_delete INT(11), file_number_to_delete VARCHAR(255)) RETURNS int(1)
        BEGIN
            DECLARE result INT(1);
            SET @exists = (
                SELECT  COUNT(*)
                FROM `clients` c
                INNER JOIN members m ON m.member_id = c.member_id
                WHERE c.`fileNumber` = file_number_to_delete AND m.company_id = company_id_to_delete);
        
            IF (@exists > 0) THEN
                SET result = 0;
            ELSE
                DELETE FROM file_number_reservations WHERE file_number = file_number_to_delete AND company_id = company_id_to_delete;
                SET result = 1;
            END IF;
        
            RETURN result;
        END");

        $this->execute("
        CREATE FUNCTION `expire_abandoned_file_number_reservations`(expire_before INT) RETURNS int(11)
        BEGIN
            DELETE fnr
            FROM file_number_reservations fnr
            LEFT OUTER JOIN clients c ON c.fileNumber = fnr.file_number
            LEFT OUTER JOIN members m ON m.member_id = c.member_id AND m.company_id = fnr.company_id
            WHERE fnr.reserved_on < expire_before AND m.member_id IS NULL;
        
            RETURN ROW_COUNT();
        END");
    }

    public function down()
    {
        $this->execute("DROP FUNCTION `expire_abandoned_file_number_reservations`;");
        $this->execute("DROP FUNCTION `release_file_number`;");
        $this->execute("DROP FUNCTION `reserve_file_number`;");
    }
}