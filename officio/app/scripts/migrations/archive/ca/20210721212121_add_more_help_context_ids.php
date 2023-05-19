<?php

use Officio\Migration\AbstractMigration;

class addMoreHelpContextIds extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'dashboard', 'Dashboard');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'officio-studio', 'Officio Studio');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'admin', 'Admin');");
    }

    public function down()
    {
        $this->execute("DELETE FROM faq_context_ids WHERE faq_context_id_text IN ('dashboard', 'officio-studio', 'admin')");
    }
}
