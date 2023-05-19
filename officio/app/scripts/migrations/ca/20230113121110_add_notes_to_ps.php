<?php

use Officio\Migration\AbstractMigration;

class AddNotesToPs extends AbstractMigration
{
    public function up()
    {
        $this->table('u_payment_schedule')
            ->addColumn('notes', 'string', ['limit' => 255, 'null' => true, 'after' => 'description'])
            ->save();
    }

    public function down()
    {
        $this->table('u_payment_schedule')
            ->removeColumn('notes')
            ->save();
    }
}