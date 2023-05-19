<?php

use Officio\Migration\AbstractMigration;

class UpdateClientsGroupType extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE client_form_groups AS g
        INNER JOIN client_types as ct ON ct.company_id = g.company_id
        SET g.client_type_id  = ct.client_type_id");
    }

    public function down()
    {
    }
}
