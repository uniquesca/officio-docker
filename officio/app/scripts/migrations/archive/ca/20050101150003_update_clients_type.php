<?php

use Officio\Migration\AbstractMigration;

class UpdateClientsType extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE clients AS c
        INNER JOIN members as m ON m.member_id = c.member_id
        INNER JOIN client_types as ct ON ct.company_id = m.company_id
        SET c.client_type_id  = ct.client_type_id");
    }

    public function down()
    {
    }
}
