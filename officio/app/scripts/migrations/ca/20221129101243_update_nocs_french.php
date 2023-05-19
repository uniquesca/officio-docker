<?php

use Officio\Migration\AbstractMigration;

class UpdateNocsFrench extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE company_prospects_noc_job_titles ADD column noc_language enum ('en','fr') DEFAULT 'en';
            ALTER TABLE company_prospects_noc ADD column noc_french_title varchar(255) DEFAULT NULL;");
        $sql = file_get_contents('scripts/db/noc_2021_french.sql');
        $this->execute($sql);
    }

    public function down()
    {
        $this->execute(
            "DELETE FROM company_prospects_noc_job_titles WHERE noc_language = 'fr';
            ALTER TABLE company_prospects_noc_job_titles DROP column noc_language;
            ALTER TABLE company_prospects_noc DROP column noc_french_title;");
    }
}
