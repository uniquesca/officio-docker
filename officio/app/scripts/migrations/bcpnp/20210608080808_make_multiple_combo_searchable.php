<?php

use Officio\Migration\AbstractMigration;

class makeMultipleComboSearchable extends AbstractMigration
{

    protected $clearCache = true;

    public function up()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='Y' WHERE `field_type_text_id`='multiple_combo';");
    }

    public function down()
    {
        $this->execute("UPDATE `field_types` SET `field_type_can_be_used_in_search`='N' WHERE `field_type_text_id`='multiple_combo';");
    }
}
