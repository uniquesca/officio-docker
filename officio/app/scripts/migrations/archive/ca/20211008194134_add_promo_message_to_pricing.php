<?php

use Officio\Migration\AbstractMigration;

class AddPromoMessageToPricing extends AbstractMigration
{
    public function up()
    {
        $this->table('pricing_categories')
            ->addColumn('key_message', 'string', [
                'after' => 'key_string',
                'null'  => true
            ])
            ->save();
    }

    public function down()
    {
        $this->table('pricing_categories')
            ->removeColumn('key_message')
            ->save();
    }
}
