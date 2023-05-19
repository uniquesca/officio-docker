<?php

use Officio\Migration\AbstractMigration;

class makeFieldsSearchable extends AbstractMigration
{

    protected $clearCache = true;

    public function up()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='Y' WHERE  `field_type_text_id` IN ('active_users', 'auto_calculated', 'multiple_text_fields', 'categories', 'list_of_occupations');");
    }

    public function down()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='N' WHERE  `field_type_text_id` IN ('active_users', 'auto_calculated', 'multiple_text_fields', 'categories', 'list_of_occupations');");
    }
}
