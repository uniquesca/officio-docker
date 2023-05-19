<?php

use Officio\Migration\AbstractMigration;

class AddFaqContextIdModuleDescription extends AbstractMigration
{
    public function up()
    {
        $this->table('faq_context_ids')
            ->addColumn('faq_context_id_module_description', 'text')
            ->save();
    }

    public function down()
    {
        $this->table('faq_context_ids')
            ->removeColumn('faq_context_id_module_description')
            ->save();
    }
}
