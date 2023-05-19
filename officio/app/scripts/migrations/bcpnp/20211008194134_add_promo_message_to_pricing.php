<?php

use Phinx\Migration\AbstractMigration;

class AddPromoMessageToPricing extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('pricing_categories');
        $table->addColumn('key_message', 'string', [
            'after' => 'key_string',
            'null' => true
        ])
            ->save();
    }
    /**
     * Migrate Down.
     */
    public function down()
    {
        $table = $this->table('pricing_categories');
        $table->removeColumn('key_message')
            ->save();
    }
}
