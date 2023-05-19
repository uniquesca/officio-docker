<?php

use Officio\Migration\AbstractMigration;

class AddDesignatedAreaFieldsSection extends AbstractMigration
{
    public function up()
    {
        //Fix bug when delete section - change "No Action" into "Cascade"
        $this->query("ALTER TABLE `company_questionnaires_fields` DROP FOREIGN KEY `FK_company_questionnaires_fields_1`;");
        $this->query("ALTER TABLE `company_questionnaires_fields` ADD CONSTRAINT `FK_company_questionnaires_fields_1` FOREIGN KEY (`q_section_id`) REFERENCES `company_questionnaires_sections` (`q_section_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        // NOTE: don't add other QNR fields like in AU
    }

    public function down()
    {
    }
}
