<?php

use Officio\Migration\AbstractMigration;

class RenameLitePackage extends AbstractMigration
{
    public function up()
    {
        $this->getQueryBuilder()
            ->update('subscriptions')
            ->set('subscription_name', 'OfficioSolo')
            ->where(['subscription_name' => 'OfficioLite'])
            ->execute();
    }

    public function down()
    {
        $this->getQueryBuilder()
            ->update('subscriptions')
            ->set('subscription_name', 'OfficioLite')
            ->where(['subscription_name' => 'OfficioSolo'])
            ->execute();
    }
}