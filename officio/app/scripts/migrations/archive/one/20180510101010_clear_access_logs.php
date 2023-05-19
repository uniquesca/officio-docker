<?php

use Phinx\Migration\AbstractMigration;

class ClearAccessLogs extends AbstractMigration
{
    public function up()
    {
        $this->execute('TRUNCATE `access_logs`;');
    }

    public function down()
    {
    }
}