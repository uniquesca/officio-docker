<?php

use Phinx\Migration\AbstractMigration;

class UpdateLanguageFieldsOrderForTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=13 WHERE  `q_field_id`=181 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=14 WHERE  `q_field_id`=158 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=15 WHERE  `q_field_id`=159 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=16 WHERE  `q_field_id`=160 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=17 WHERE  `q_field_id`=161 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=18 WHERE  `q_field_id`=162 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=19 WHERE  `q_field_id`=163 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=20 WHERE  `q_field_id`=164 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=21 WHERE  `q_field_id`=165 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=22 WHERE  `q_field_id`=166 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=23 WHERE  `q_field_id`=167 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=24 WHERE  `q_field_id`=168 AND `q_section_id`=5;");

        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=25 WHERE  `q_field_id`=147 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=26 WHERE  `q_field_id`=148 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=27 WHERE  `q_field_id`=149 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=28 WHERE  `q_field_id`=150 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=29 WHERE  `q_field_id`=151 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=30 WHERE  `q_field_id`=152 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=31 WHERE  `q_field_id`=153 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=32 WHERE  `q_field_id`=154 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=33 WHERE  `q_field_id`=155 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=34 WHERE  `q_field_id`=156 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=35 WHERE  `q_field_id`=157 AND `q_section_id`=5;");
    }

    public function down()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=24 WHERE  `q_field_id`=181 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=25 WHERE  `q_field_id`=158 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=26 WHERE  `q_field_id`=159 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=27 WHERE  `q_field_id`=160 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=28 WHERE  `q_field_id`=161 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=29 WHERE  `q_field_id`=162 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=30 WHERE  `q_field_id`=163 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=31 WHERE  `q_field_id`=164 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=32 WHERE  `q_field_id`=165 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=33 WHERE  `q_field_id`=166 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=34 WHERE  `q_field_id`=167 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=35 WHERE  `q_field_id`=168 AND `q_section_id`=5;");

        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=13 WHERE  `q_field_id`=147 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=14 WHERE  `q_field_id`=148 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=15 WHERE  `q_field_id`=149 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=16 WHERE  `q_field_id`=150 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=17 WHERE  `q_field_id`=151 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=18 WHERE  `q_field_id`=152 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=19 WHERE  `q_field_id`=153 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=20 WHERE  `q_field_id`=154 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=21 WHERE  `q_field_id`=155 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=22 WHERE  `q_field_id`=156 AND `q_section_id`=5;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_order`=23 WHERE  `q_field_id`=157 AND `q_section_id`=5;");
    }
}