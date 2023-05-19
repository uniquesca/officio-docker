<?php

use Officio\Migration\AbstractMigration;

class RenameLmoToLmia extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE client_form_fields SET label = REPLACE(label, 'LMO', 'LMIA');");
    }

    public function down()
    {
        $this->execute("UPDATE client_form_fields SET label = REPLACE(label, 'LMIA', 'LMO');");
    }
}
