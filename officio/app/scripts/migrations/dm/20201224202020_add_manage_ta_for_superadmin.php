<?php

use Laminas\Cache\Storage\FlushableInterface;
use Laminas\Cache\Storage\StorageInterface;
use Officio\Migration\AbstractMigration;

class addManageTaForSuperadmin extends AbstractMigration
{
    public function up()
    {
        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();

        $builder      = $this->getQueryBuilder();

        $statement    = $builder
            ->select('rule_id')
            ->from('acl_rules')
            ->where(
                [
                    'rule_check_id' => 'manage-superadmin-roles'
                ]
            )
            ->execute();

        $parentRuleId = false;
        $row = $statement->fetch();
        if (count($row)) {
            $parentRuleId =  $row[array_key_first($row)];
        }
        if (empty($parentRuleId)) {
            throw new Exception('Parent rule not found.');
        }

        $statement = $builder
            ->insert(
                array(
                    'rule_parent_id',
                    'module_id',
                    'rule_description',
                    'rule_check_id',
                    'superadmin_only',
                    'crm_only',
                    'rule_visible',
                    'rule_order',
                )
            )
            ->into('acl_rules')
            ->values(
                array(
                    'rule_parent_id'   => 4,
                    'module_id'        => 'superadmin',
                    'rule_description' => 'Client Account Settings',
                    'rule_check_id'    => 'superadmin-trust-account-settings',
                    'superadmin_only'  => 1,
                    'crm_only'         => 'N',
                    'rule_visible'     => 1,
                    'rule_order'       => 0,
                )
            )
            ->execute();
        $ruleId = $statement->lastInsertId('acl_rules');

        $this->insert(
            'acl_rule_details',
            array(
                'rule_id'            => $ruleId,
                'module_id'          => 'superadmin',
                'resource_id'        => 'trust-account-settings',
                'resource_privilege' => '',
                'rule_allow'         => 1,
            )
        );

        $this->insert(
            'packages_details',
            array(
                'package_id'                 => 1,
                'rule_id'                    => $ruleId,
                'package_detail_description' => 'Client Account Settings',
                'visible'                    => 1,
            )
        );

        $this->query(
            "INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                SELECT a.role_id, $ruleId
                FROM acl_role_access AS a
                LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                WHERE a.rule_id = $parentRuleId"
        );

        $this->execute("UPDATE `acl_rules` SET `rule_description`='%ta_label% History' WHERE  `rule_check_id`='trust-account-history-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='%ta_label% Import' WHERE  `rule_check_id`='trust-account-import-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='%ta_label% Assign' WHERE  `rule_check_id`='trust-account-assign-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='%ta_label% Edit' WHERE  `rule_check_id`='trust-account-edit-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='%ta_label% Transaction Settings' WHERE  `rule_check_id`='trust-account-settings-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='%ta_label% Transaction Settings' WHERE  `rule_check_id`='superadmin-trust-account-settings';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='%ta_label%' WHERE  `rule_check_id`='trust-account-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='General: Change Currency or %ta_label%' WHERE  `rule_check_id`='clients-accounting-change-currency';");

        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }

    public function down()
    {
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account History' WHERE  `rule_check_id`='trust-account-history-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Import' WHERE  `rule_check_id`='trust-account-import-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Assign' WHERE  `rule_check_id`='trust-account-assign-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Edit' WHERE  `rule_check_id`='trust-account-edit-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Transaction Settings' WHERE  `rule_check_id`='trust-account-settings-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account Transaction Settings' WHERE  `rule_check_id`='superadmin-trust-account-settings';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='Client Account' WHERE  `rule_check_id`='trust-account-view';");
        $this->execute("UPDATE `acl_rules` SET `rule_description`='General: Change Currency or Client Account' WHERE  `rule_check_id`='clients-accounting-change-currency';");
        $this->execute("DELETE FROM acl_rules WHERE rule_check_id = 'superadmin-trust-account-settings'");

        $application    = self::getApplication();
        $serviceManager = $application->getServiceManager();
        /** @var $cache StorageInterface */
        $cache = $serviceManager->get('cache');
        if ($cache instanceof FlushableInterface) {
            $cache->flush();
        }
    }
}