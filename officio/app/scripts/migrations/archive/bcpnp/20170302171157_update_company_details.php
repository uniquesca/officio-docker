<?php

use Phinx\Migration\AbstractMigration;

class UpdateCompanyDetails extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details`
            ADD COLUMN `decision_rationale_tab_name` VARCHAR(255) NULL DEFAULT 'Draft Notes' AFTER `allow_decision_rationale_tab`;
        ");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details`
	        DROP COLUMN `decision_rationale_tab_name`;");
    }
}