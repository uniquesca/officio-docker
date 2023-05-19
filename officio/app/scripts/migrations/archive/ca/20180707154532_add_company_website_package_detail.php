<?php

use Phinx\Migration\AbstractMigration;

class AddCompanyWebsitePackageDetail extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "INSERT INTO `packages_details` (`package_id`, `rule_id`, `package_detail_description`, `visible`) VALUES
            (3, 1400, 'Company Website', 1);"
        );
    }

    public function down()
    {
        $this->execute("DELETE FROM `packages_details` WHERE `rule_id` = 1400;");
    }
}