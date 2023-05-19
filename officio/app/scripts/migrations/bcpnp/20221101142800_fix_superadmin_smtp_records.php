<?php

use Officio\Migration\AbstractMigration;

class FixSuperadminSmtpRecords extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM superadmin_smtp;");
        $this->table('superadmin_smtp')
            ->insert([
                'smtp_id'   => 1,
                'smtp_on'   => 'N',
                'smtp_port' => 25
            ])
            ->insert([
                'smtp_id'   => 2,
                'smtp_on'   => 'N',
                'smtp_port' => 25
            ])
            ->insert([
                'smtp_id'      => 3,
                'smtp_on'      => 'N',
                'smtp_port'    => 587,
                'smtp_use_ssl' => 'tls'
            ])
            ->saveData();
    }

    public function down()
    {
    }
}
