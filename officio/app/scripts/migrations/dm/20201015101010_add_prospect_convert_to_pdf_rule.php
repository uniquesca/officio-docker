<?php

use Officio\Migration\AbstractMigration;

class addProspectConvertToPdfRule extends AbstractMigration
{

    protected $clearAclCache = true;

    public function up()
    {
        $application = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder = $this->getQueryBuilder();

        $statement = $builder
            ->select('rule_id')
            ->from(array('r' => 'acl_rules'))
            ->where(
                [
                    'r.rule_check_id' => 'prospects-documents'
                ]
            )
            ->execute();

        $parentId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $parentId =  $row[array_key_first($row)];
        }

        if (empty($parentId)) {
            throw new Exception('There is no prospect documents rule.');
        }

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentId, 'prospects', 'index', 'convert-to-pdf');");
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'prospects' AND `resource_id` = 'index' AND `resource_privilege` = 'convert-to-pdf';");
    }
}