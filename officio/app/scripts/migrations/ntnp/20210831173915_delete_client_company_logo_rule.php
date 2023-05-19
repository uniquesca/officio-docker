<?php

use Officio\Migration\AbstractMigration;

class DeleteClientCompanyLogoRule extends AbstractMigration
{
    protected $clearAclCache = true;

    public function up() {
        $builder = $this->getQueryBuilder();
        $builder->delete('acl_rule_details')->where(['resource_privilege' => 'get-client-company-logo'])->execute();
    }

    public function down() {
        $builder = $this->getQueryBuilder();
        $builder
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
