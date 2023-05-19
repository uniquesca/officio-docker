<?php

use Phinx\Migration\AbstractMigration;

class RemoveRedundantCaseTypes extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "
            SELECT MIN(ct.client_type_id) INTO @leaveCaseType 
            FROM client_types ct
            INNER JOIN company c ON ct.company_id = c.company_id
            WHERE ct.client_type_name = 'Skills Immigration Registration' AND 
              c.companyName = 'Default Company'; 
            
            
            DELETE ct FROM client_types ct
            INNER JOIN company c ON ct.company_id = c.company_id
            WHERE ct.client_type_name = 'Skills Immigration Registration' AND 
              c.companyName = 'Default Company' AND
              ct.client_type_id <> @leaveCaseType;
        "
        );
    }

    public function down()
    {
    }
}