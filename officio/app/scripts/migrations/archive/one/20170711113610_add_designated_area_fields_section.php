<?php

use Phinx\Migration\AbstractMigration;

class AddDesignatedAreaFieldsSection extends AbstractMigration
{
    public function up()
    {
        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $db->beginTransaction();

        //Fix bug when delete section - change "No Action" into "Cascade"
        $db->query("ALTER TABLE `company_questionnaires_fields` DROP FOREIGN KEY `FK_company_questionnaires_fields_1`;");
        $db->query("ALTER TABLE `company_questionnaires_fields`
	        ADD CONSTRAINT `FK_company_questionnaires_fields_1` FOREIGN KEY (`q_section_id`) REFERENCES `company_questionnaires_sections` (`q_section_id`) ON UPDATE CASCADE ON DELETE CASCADE;
	    ");

        // NOTE: don't add other QNR fields like in AU

        $db->commit();
    }

    public function down()
    {
    }
}