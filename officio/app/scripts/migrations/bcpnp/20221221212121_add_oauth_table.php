<?php

use Officio\Migration\AbstractMigration;

class AddOauthTable extends AbstractMigration
{
    public function up()
    {
        $this->table('acl_remote_auth')
            ->addColumn('member_id', 'biginteger', ['signed' => true, 'null' => false])
            ->addColumn('provider', 'enum', ['values' => ['google', 'microsoft', 'yahoo', 'apple', 'keycloak'], 'null' => false])
            ->addColumn('remote_account_id', 'string', ['limit' => 255, 'null' => false, 'default' => ''])
            ->addColumn('access_token', 'text', ['null' => true])
            ->addColumn('refresh_token', 'text', ['null' => true])
            ->addColumn('additional_data', 'text', ['null' => true])
            ->addIndex(array('remote_account_id'), array('name' => 'FK_remote_account_id'))
            ->addForeignKey('member_id', 'members', 'member_id', array('delete' => 'CASCADE', 'update' => 'CASCADE'))
            ->create();

        $this->table('eml_accounts')
            ->addColumn('inc_login_type', 'enum', ['values' => ['', 'oauth2'], 'default' => '', 'after' => 'inc_ssl'])
            ->addColumn('out_login_type', 'enum', ['values' => ['', 'oauth2'], 'default' => '', 'after' => 'out_ssl'])
            ->save();
    }

    public function down()
    {
        $this->table('acl_remote_auth')
            ->drop()
            ->save();

        $this->table('eml_accounts')
            ->removeColumn('inc_login_type')
            ->removeColumn('out_login_type')
            ->save();
    }
}