<?php

use Phinx\Migration\AbstractMigration;

class AddCompanyProspectsActivitiesTable extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "CREATE TABLE `company_prospects_activities` (
             `company_id` BIGINT(20) DEFAULT NULL,
             `prospect_id` BIGINT(20) DEFAULT NULL,
             `member_id` BIGINT(20) DEFAULT NULL,
             `activity` ENUM('email') NOT NULL,
             `date` DATETIME NULL DEFAULT NULL,
             CONSTRAINT `FK_company_prospects_activities_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
             CONSTRAINT `FK_company_prospects_activities_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE,
             CONSTRAINT `FK_company_prospects_activities_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
            )
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;"
        );
    }

    public function down()
    {
        $this->execute("DROP TABLE `company_prospects_activities`;");
    }
}