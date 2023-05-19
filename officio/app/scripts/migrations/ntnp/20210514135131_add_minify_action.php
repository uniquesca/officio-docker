<?php

use Officio\Migration\AbstractMigration;

class AddMinifyAction extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $builder = $this->getQueryBuilder();

        $statement = $builder
            ->select('rule_id')
            ->from(array('r' => 'acl_rules'))
            ->where(
                [
                    'r.rule_check_id' => 'access-to-default'
                ]
            )
            ->execute();

        $aclRulesRow = $statement->fetch();

        if (empty($aclRulesRow)) {
            throw new Exception('There is no access to default rule.');
        }

        $parentId = $aclRulesRow[0];

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentId, 'officio', 'index', 'min');");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'officio' AND `resource_id` = 'index' AND `resource_privilege` = 'min';");
    }
}
