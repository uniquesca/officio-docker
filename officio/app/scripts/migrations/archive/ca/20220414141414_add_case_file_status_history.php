<?php

use Officio\Migration\AbstractMigration;

class AddCaseFileStatusHistory extends AbstractMigration
{
    public function up()
    {
        $rule = $this->fetchRow("SELECT rule_id FROM acl_rules WHERE rule_check_id = 'clients-view';");
        if (!$rule || !isset($rule['rule_id'])) {
            throw new Exception('ACL rule not found for public access.');
        }

        $this->table('acl_rule_details')->insert(
            [
                [
                    'rule_id'            => $rule['rule_id'],
                    'module_id'          => 'applicants',
                    'resource_id'        => 'profile',
                    'resource_privilege' => 'get-case-file-status-history',
                    'rule_allow'         => 1
                ]
            ]
        )->save();

        $this->execute("CREATE TABLE `client_statuses_history` (
            `history_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
            `member_id` BIGINT(20) NOT NULL,
            `client_status_id` INT(11) UNSIGNED NULL DEFAULT NULL,
            `history_client_status_name` VARCHAR(255) NULL DEFAULT NULL,
            `user_id` BIGINT(20) NULL DEFAULT NULL,
            `history_user_name` VARCHAR(255) NULL DEFAULT NULL,
            `history_date` DATETIME NOT NULL,
            PRIMARY KEY (`history_id`) USING BTREE,
            INDEX `FK_client_file_status_history_1` (`member_id`) USING BTREE,
            INDEX `FK_client_file_status_history_2` (`user_id`) USING BTREE,
            INDEX `FK_client_file_status_history_3` (`client_status_id`) USING BTREE,
            CONSTRAINT `FK_client_file_status_history_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT `FK_client_file_status_history_2` FOREIGN KEY (`user_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL,
            CONSTRAINT `FK_client_file_status_history_3` FOREIGN KEY (`client_status_id`) REFERENCES `client_statuses` (`client_status_id`) ON UPDATE CASCADE ON DELETE SET NULL
        )
        COMMENT='Case file statuses changes history'");
    }

    public function down()
    {
        $this->execute("DROP TABLE `client_statuses_history`;");
        $this->execute("DELETE FROM acl_rule_details WHERE module_id = 'applicants' AND resource_privilege = 'get-case-file-status-history';");
    }
}
