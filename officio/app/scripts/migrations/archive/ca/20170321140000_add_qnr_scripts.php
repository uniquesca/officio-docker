<?php

use Phinx\Migration\AbstractMigration;

class AddQnrScripts extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires` ADD COLUMN `q_script_google_analytics` TEXT NULL DEFAULT NULL AFTER `q_template_thank_you`;");
        $this->execute("ALTER TABLE `company_questionnaires` ADD COLUMN `q_script_facebook_pixel` TEXT NULL DEFAULT NULL AFTER `q_script_google_analytics`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_questionnaires` DROP COLUMN `q_script_facebook_pixel`;");
        $this->execute("ALTER TABLE `company_questionnaires` DROP COLUMN `q_script_google_analytics`;");
    }
}