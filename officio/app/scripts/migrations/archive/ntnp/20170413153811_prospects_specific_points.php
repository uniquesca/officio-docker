<?php

use Phinx\Migration\AbstractMigration;

class ProspectsSpecificPoints extends AbstractMigration
{
    public function up()
    {
        $this->getAdapter()->beginTransaction();

        $this->execute(
            "ALTER TABLE `company_prospects`	ADD COLUMN `points_skilled_worker` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `assessment`, ADD COLUMN `points_express_entry` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `points_skilled_worker`;"
        );

        $this->getAdapter()->commitTransaction();
        // Also call /system/index/update-prospects-points action (updateProspectsPointsAction) to fill data from already saved data
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `points_skilled_worker`, DROP COLUMN `points_express_entry`;");
    }
}