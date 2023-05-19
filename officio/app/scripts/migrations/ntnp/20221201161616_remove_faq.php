<?php

use Officio\Migration\AbstractMigration;

class RemoveFaq extends AbstractMigration
{
    public function up()
    {
        $this->execute("DELETE FROM acl_rules WHERE rule_check_id='faq-view'");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Help (public)', `rule_check_id`='help-public-view' WHERE rule_check_id='faq-public-view'");
    }

    public function down()
    {
    }
}
