<?php

use Officio\Migration\AbstractMigration;

class UpdateNocsFrench extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE company_prospects_noc_job_titles ADD column noc_language enum ('en','fr') DEFAULT 'en';
            ALTER TABLE company_prospects_noc ADD column noc_french_title varchar(255) DEFAULT NULL;");
    }

    public function down()
    {
        $this->execute(
            "ALTER TABLE company_prospects_noc_job_titles DROP column noc_language;
            ALTER TABLE company_prospects_noc DROP column noc_french_title;");
    }
}
