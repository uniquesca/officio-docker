<?php

use Officio\Migration\AbstractMigration;

class FixTablesCharacterSet extends AbstractMigration
{
    public function up()
    {
        // Took 40s on the local server
        $this->execute("ALTER TABLE `clients` ROW_FORMAT=DEFAULT;");
        $this->execute("ALTER TABLE `members_last_access` ROW_FORMAT=DEFAULT;");

        $this->execute("ALTER TABLE `company_marketplace_profiles` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
        $this->execute("ALTER TABLE `company_prospects_activities` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
        $this->execute("ALTER TABLE `company_prospects_converted` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
        $this->execute("ALTER TABLE `company_prospects_invited` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
        $this->execute("ALTER TABLE `company_prospects_settings` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
        $this->execute("ALTER TABLE `members_pua` CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;");
    }

    public function down()
    {
    }
}
