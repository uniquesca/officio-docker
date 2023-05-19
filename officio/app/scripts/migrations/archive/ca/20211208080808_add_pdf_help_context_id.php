<?php

use Officio\Migration\AbstractMigration;

class AddPdfHelpContextId extends AbstractMigration
{
    public function up()
    {
        $arrContextInfo = [
            'faq_context_id_text'               => 'form_filling',
            'faq_context_id_description'        => 'View, annotate, sign, fill PDF forms',
            'faq_context_id_module_description' => '',
        ];

        $this->getQueryBuilder()
            ->insert(array_keys($arrContextInfo))
            ->into('faq_context_ids')
            ->values($arrContextInfo)
            ->execute();
    }

    public function down()
    {
        $this->getQueryBuilder()
            ->delete('faq_context_ids')
            ->where(['faq_context_id_text' => 'form_filling'])
            ->execute();
    }
}
