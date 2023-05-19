<?php

use Officio\Migration\AbstractMigration;

class DeleteClientCompanyLogoRule extends AbstractMigration
{
    public function up() {
        $this->getQueryBuilder()
            ->delete('acl_rule_details')
            ->where(['resource_privilege' => 'get-client-company-logo'])
            ->execute();
    }

    public function down() {
        $this->getQueryBuilder()
            ->insert(
                [
                    'rule_id',
                    'module_id',
                    'resource_id',
                    'resource_privilege',
                    'rule_allow'
                ]
            )
            ->into('acl_rule_details')
            ->values(
                [
                    'rule_id' => 6,
                    'module_id' => 'clients',
                    'resource_id' => 'index',
                    'resource_privilege' => 'get-client-company-logo',
                    'rule_allow' => 1
                ]
            )
            ->execute();
    }
}
