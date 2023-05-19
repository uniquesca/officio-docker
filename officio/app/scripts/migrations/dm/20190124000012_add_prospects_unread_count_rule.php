<?php

use Officio\Migration\AbstractMigration;

class AddProspectsUnreadCountRule extends AbstractMigration
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
                    'r.rule_check_id' => 'prospects-view'
                ]
            )
            ->execute();

        $parentId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $parentId =  $row[array_key_first($row)];
        }

        if (empty($parentId)) {
            throw new Exception('There is no prospects rule.');
        }

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($parentId, 'prospects', 'index', 'get-prospects-unread-counts');");

        $statement = $builder
            ->select('rule_id')
            ->from(array('r' => 'acl_rules'))
            ->where(
                [
                    'r.rule_check_id' =>'marketplace-view'
                ]
            )
            ->execute();

        $marketplaceParentId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $marketplaceParentId =  $row[array_key_first($row)];
        }

        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`, `module_id`, `resource_id`, `resource_privilege`) VALUES ($marketplaceParentId, 'prospects', 'index', 'get-prospects-unread-counts');");

        $this->execute("ALTER TABLE `company_prospects`
            ADD INDEX `company_id` (`company_id`),
            ADD INDEX `status` (`status`),
            ADD INDEX `qualified` (`qualified`);
        "
        );

        $this->execute(
            "ALTER TABLE `company_prospects_divisions`
	        ADD INDEX `prospect_id_office_id` (`prospect_id`, `office_id`);
        "
        );
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("DELETE FROM `acl_rules` WHERE  `resource_privilege` = 'get-prospects-unread-counts' AND `module` = 'prospects';");
        $this->execute("ALTER TABLE `company_prospects_divisions` DROP INDEX `prospect_id_office_id`;");
        $this->execute(
            "ALTER TABLE `company_prospects`
            DROP INDEX `company_id`,
            DROP INDEX `status`,
            DROP INDEX `qualified`;
        "
        );
    }
}