<?php

use Officio\Migration\AbstractMigration;

class ChangeKskeydidFieldTypeUseFor extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `field_types` SET `field_type_use_for`='case' WHERE  `field_type_id`=37;");
    }

    public function down()
    {
        $this->execute("UPDATE `field_types` SET `field_type_use_for`='all' WHERE  `field_type_id`=37;");
    }
}
