<?php

use Officio\Migration\AbstractMigration;

class AddColumnsToClientFormFields extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `client_form_fields`
            ADD COLUMN `min_value` INT(11) NULL DEFAULT NULL AFTER `custom_height`,
            ADD COLUMN `max_value` INT(11) NULL DEFAULT NULL AFTER `min_value`;"
        );
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE `client_form_fields`
          DROP COLUMN `min_value`,
          DROP COLUMN `max_value`;"
        );
    }
}
