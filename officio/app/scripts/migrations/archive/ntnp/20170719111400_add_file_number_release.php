<?php

use Phinx\Migration\AbstractMigration;

class AddFileNumberRelease extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            CREATE FUNCTION release_file_number (company_id_to_delete INT(11), file_number_to_delete VARCHAR(255))
                RETURNS INT(1)
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
                END 
        "
        );
    }

    public function down()
    {
        $this->execute("DROP FUNCTION IF EXISTS release_file_number;");
    }
}
