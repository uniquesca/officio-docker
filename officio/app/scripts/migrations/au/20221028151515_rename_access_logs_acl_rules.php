<?php

use Officio\Migration\AbstractMigration;

class RenameAccessLogsAclRules extends AbstractMigration
{
    protected $clearAclCache = true;

    public function up()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Events Log' WHERE `rule_check_id`='access-logs-delete';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Delete Events Entry' WHERE `rule_check_id`='access-logs-delete';");
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Access Logs' WHERE `rule_check_id`='access-logs-delete';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Delete Access Logs' WHERE `rule_check_id`='access-logs-delete';");
    }
}
