<?php

use Phinx\Migration\AbstractMigration;

class AddRecentlyViewedProspects extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `company_prospects_last_access` (
            `member_id` BIGINT(20) NOT NULL,
            `prospect_id` BIGINT(20) NOT NULL,
            `access_date` DATETIME NULL DEFAULT NULL,
            UNIQUE INDEX `member_id_prospect_id` (`member_id`, `prospect_id`),
            INDEX `FK_company_prospects_last_access_members` (`member_id`),
            INDEX `FK_company_prospects_last_access_company_prospects` (`prospect_id`),
            CONSTRAINT `FK_company_prospects_last_access_company_prospects` FOREIGN KEY (`prospect_id`) REFERENCES `company_prospects` (`prospect_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_company_prospects_last_access_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Last viewed prospects for a specific member'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB");
    }

    public function down()
    {
        $this->execute("DROP TABLE `company_prospects_last_access`;");
    }
}
