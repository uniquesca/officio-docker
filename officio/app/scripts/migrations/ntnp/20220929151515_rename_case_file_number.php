<?php

use Officio\Migration\AbstractMigration;

class RenameCaseFileNumber extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Define Case File Number Settings' WHERE rule_description = 'Define Case Reference Number Settings';");
        $this->execute("UPDATE `packages_details` SET `package_detail_description`='Define Case File Number Settings' WHERE `package_detail_description`='Define Case Reference Number Settings';");
        $this->execute("ALTER TABLE `file_number_reservations` COMMENT = 'Contains company reserved case file numbers.';");
    }

    public function down()
    {
    }
}
