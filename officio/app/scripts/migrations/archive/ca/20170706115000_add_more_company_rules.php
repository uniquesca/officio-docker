<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class AddMoreCompanyRules extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description'   => 'Define Authorised Agents',
                'rule_check_id'      => 'define-authorised-agents',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-divisions-groups',
                'resource_privilege' => array('')
            ),

            array(
                'rule_description'   => 'Define %office_label%',
                'rule_check_id'      => 'manage-offices',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-offices',
                'resource_privilege' => array('')
            ),

            array(
                'rule_description'   => 'Define Case Reference Number Settings',
                'rule_check_id'      => 'manage-case-reference-number-settings',
                'module_id'          => 'superadmin',
                'resource_id'        => 'manage-company',
                'resource_privilege' => array('case-number-settings', 'case-number-settings-save')
            ),
        );

        return $arrRules;
    }

    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select(['rule_id'])
                ->from('acl_rules')
                ->where(['rule_check_id' => 'manage-company'])
                ->execute();

            $ruleParentId = false;
            $row          = $statement->fetch();
            if (!empty($row)) {
                $ruleParentId = $row[array_key_first($row)];
            }

            if (empty($ruleParentId)) {
                throw new Exception('Manage company rule not found.');
            }

            $arrRules = $this->getNewRules();

            $order = 4;
            foreach ($arrRules as $arrRuleInfo) {
                $statement = $this->getQueryBuilder()
                    ->insert(
                        [
                            'rule_parent_id',
                            'module_id',
                            'rule_description',
                            'rule_check_id',
                            'superadmin_only',
                            'crm_only',
                            'rule_visible',
                            'rule_order',
                        ]
                    )
                    ->into('acl_rules')
                    ->values(
                        [
                            'rule_parent_id'   => $ruleParentId,
                            'module_id'        => $arrRuleInfo['module_id'],
                            'rule_description' => $arrRuleInfo['rule_description'],
                            'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                            'superadmin_only'  => 0,
                            'crm_only'         => 'N',
                            'rule_visible'     => 1,
                            'rule_order'       => $order++,
                        ]
                    )
                    ->execute();
                $ruleId    = $statement->lastInsertId('acl_rules');

                foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                    $this->getQueryBuilder()
                        ->insert(
                            [
                                'rule_id',
                                'module_id',
                                'resource_id',
                                'resource_privilege',
                                'rule_allow',
                            ]
                        )
                        ->into('acl_rule_details')
                        ->values(
                            [
                                'rule_id'            => $ruleId,
                                'module_id'          => $arrRuleInfo['module_id'],
                                'resource_id'        => $arrRuleInfo['resource_id'],
                                'resource_privilege' => $resourcePrivilege,
                                'rule_allow'         => 1,
                            ]
                        )
                        ->execute();
                }

                $this->getQueryBuilder()
                    ->insert(
                        [
                            'package_id',
                            'rule_id',
                            'package_detail_description',
                            'visible',
                        ]
                    )
                    ->into('packages_details')
                    ->values(
                        [
                            'package_id'                 => 1,
                            'rule_id'                    => $ruleId,
                            'package_detail_description' => $arrRuleInfo['rule_description'],
                            'visible'                    => 1,
                        ]
                    )
                    ->execute();

                $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                                        SELECT a.role_id, $ruleId
                                        FROM acl_role_access AS a
                                        LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                                        WHERE a.rule_id = $ruleParentId");

                foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                    $this->getQueryBuilder()
                        ->delete('acl_rule_details')
                        ->where(
                            [
                                'rule_id'            => (int)$ruleParentId,
                                'module_id'          => $arrRuleInfo['module_id'],
                                'resource_id'        => $arrRuleInfo['resource_id'],
                                'resource_privilege' => $resourcePrivilege
                            ]
                        )
                        ->execute();
                }
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        try {
            $arrRules   = $this->getNewRules();
            $arrRuleIds = array();
            foreach ($arrRules as $arrRuleInfo) {
                $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
            }

            $this->getQueryBuilder()
                ->delete('acl_rules')
                ->where(['rule_check_id IN' => $arrRuleIds])
                ->execute();

            $statement = $this->getQueryBuilder()
                ->select(['rule_id'])
                ->from('acl_rules')
                ->where(['rule_check_id' => 'manage-company'])
                ->execute();

            $ruleParentId = false;
            $row          = $statement->fetch();
            if (!empty($row)) {
                $ruleParentId = $row[array_key_first($row)];
            }

            foreach ($arrRules as $arrRuleInfo) {
                foreach ($arrRuleInfo['resource_privilege'] as $resourcePrivilege) {
                    $this->getQueryBuilder()
                        ->insert(
                            [
                                'rule_id',
                                'module_id',
                                'resource_id',
                                'resource_privilege',
                                'rule_allow',
                            ]
                        )
                        ->into('acl_rule_details')
                        ->values(
                            [
                                'rule_id'            => $ruleParentId,
                                'module_id'          => $arrRuleInfo['module_id'],
                                'resource_id'        => $arrRuleInfo['resource_id'],
                                'resource_privilege' => $resourcePrivilege,
                                'rule_allow'         => 1,
                            ]
                        )
                        ->execute();
                }
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }
}
