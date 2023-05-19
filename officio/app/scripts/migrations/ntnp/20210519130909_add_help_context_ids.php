<?php

use Phinx\Migration\AbstractMigration;

class addHelpContextIds extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM faq_context_ids;");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-list', 'Clients module - list of clients for search or office/queues');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-profile', 'Clients - Profile or Case Details subtabs');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-tasks', 'Clients - Tasks subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-file-notes', 'Clients - File Notes subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-forms', 'Clients - Forms subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-documents', 'Clients - Documents subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-checklist', 'Clients - Checklist subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-accounting', 'Clients - Accounting subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'clients-time-log', 'Clients - Time Log subtab');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'prospects-list', 'Prospects module- prospects list');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'prospects-prospect', 'Prospects - Profile, Occupations, Business, Assessment subtabs');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'prospects-tasks', 'Prospects - Tasks subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'prospects-file-notes', 'Prospects - Notes subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'prospects-documents', 'Prospects - Documents subtab');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'contacts-list', 'Contacts module - list of contacts');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'contacts-profile', 'Contacts module - Profile subtab');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'marketplace-prospects-list', 'Marketplace module - prospects list');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'marketplace-prospect', 'Marketplace - Profile, Occupations, Business, Assessment subtabs');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'marketplace-file-notes', 'Marketplace - Notes subtab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'marketplace-documents', 'Marketplace - Documents subtab');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'my-tasks', 'My Tasks module - tasks list');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'my-email', 'My Email module - email list');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'my-email-settings', 'My Email - Settings subtab');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'my-calendar', 'My Calendar module');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'my-documents', 'My Documents module- list of folders and files');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'client-templates', 'Client Templates module - list of client templates');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'client-template-details', 'Client Templates - Settings or Content subtabs');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'prospect-templates', 'Prospect Templates module - list of prospect templates');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'prospect-template-details', 'Prospect Templates - Settings or Content subtabs');");

        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'trust-account-list', 'Client/Trust Account module - T/A list');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'trust-account-details', 'Client/Trust Account - Opened T/A tab');");
        $this->execute("INSERT INTO `faq_context_ids` (`faq_context_id`, `faq_context_id_text`, `faq_context_id_description`) VALUES (NULL, 'trust-account-reconciliation-report', 'Client/Trust Account - Reconciliation dialog');");
    }

    public function down()
    {
        $this->execute("DELETE FROM faq_context_ids;");
    }
}
