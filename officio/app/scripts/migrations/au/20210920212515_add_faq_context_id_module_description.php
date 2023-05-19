<?php

use Phinx\Migration\AbstractMigration;

class AddFaqContextIdModuleDescription extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        $table = $this->table('faq_context_ids');
        $table->addColumn('faq_context_id_module_description', 'text')
            ->save();
    }
    /**
     * Migrate Down.
     */
    public function down()
    {
        $table = $this->table('faq_context_ids');
        $table->removeColumn('faq_context_id_module_description')
            ->save();
    }
}
