<?php

use Officio\Migration\AbstractMigration;

class RemoveExtraDefaultOptions extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM `client_form_default` WHERE `form_default_id` IN (160600, 160601, 160602, 160603, 160604, 160605);");
    }

    public function down()
    {
    }
}
