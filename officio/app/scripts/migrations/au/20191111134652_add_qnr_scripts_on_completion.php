<?php

use Phinx\Migration\AbstractMigration;

class AddQnrScriptsOnCompletion extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires` ADD COLUMN `q_script_analytics_on_completion` TEXT NULL DEFAULT NULL AFTER `q_script_facebook_pixel`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_questionnaires` DROP COLUMN `q_script_analytics_on_completion`;");
    }
}