<?php

use Laminas\Cache\Storage\StorageInterface;
use Officio\Service\Acl;
use Officio\Migration\AbstractMigration;

class AddMinifyAction extends AbstractMigration
{
    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

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

        /** @var Acl $acl */
        $acl = $serviceManager->get('acl');
        /** @var StorageInterface $cache */
        $cache = $serviceManager->get('cache');
        $acl->clearCache($cache);
    }

    public function down()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $this->execute("DELETE FROM `acl_rule_details` WHERE `module_id` = 'officio' AND `resource_id` = 'index' AND `resource_privilege` = 'min';");

        /** @var StorageInterface $cache */
        $cache = $serviceManager->get('cache');

        /** @var Acl $acl */
        $acl = $serviceManager->get('acl');
        $acl->clearCache($cache);
    }
}
