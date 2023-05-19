<?php

use Phinx\Migration\AbstractMigration;

class RemoveLinkCaseTypes extends AbstractMigration
{
    public function up()
    {
        $this->execute('UPDATE `client_types` SET `parent_client_type_id` = NULL');
    }

    public function down()
    {
    }
}
