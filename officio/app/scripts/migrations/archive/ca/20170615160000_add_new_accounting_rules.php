<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class addNewAccountingRules extends AbstractMigration
{
    private function getNewRules()
    {
        $arrRules = array(
            array(
                'rule_description' => 'General: Change Currency or Client Account',
                'rule_check_id' => 'clients-accounting-change-currency',
            ),

            array(
                'rule_description' => 'General: Email Case Accounting',
                'rule_check_id'    => 'clients-accounting-email-accounting',
            ),

            array(
                'rule_description' => 'General: Generate Reports',
                'rule_check_id'    => 'clients-accounting-reports',
            ),

            array(
                'rule_description' => 'General: Print',
                'rule_check_id'    => 'clients-accounting-print',
            ),

            array(
                'rule_description' => 'Financial Transactions: Add Fees Due',
                'rule_check_id'    => 'clients-accounting-ft-add-fees-due',
            ),

            array(
                'rule_description' => 'Financial Transactions: Add Fees Received',
                'rule_check_id'    => 'clients-accounting-ft-add-fees-received',
            ),

            array(
                'rule_description' => 'Financial Transactions: Generate Invoice',
                'rule_check_id'    => 'clients-accounting-ft-generate-invoice',
            ),

            array(
                'rule_description' => 'Financial Transactions: Generate Receipt',
                'rule_check_id'    => 'clients-accounting-ft-generate-receipt',
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
                ->where(['rule_check_id' => 'clients-accounting-view'])
                ->execute();

            $accountingRuleId = false;
            $row = $statement->fetch();
            if (!empty($row)) {
                $accountingRuleId =  $row[array_key_first($row)];
            }

            if (empty($accountingRuleId)) {
                throw new Exception('Accounting rule not found.');
            }

            $arrRules = $this->getNewRules();

            $order = 0;
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
                            'rule_parent_id'   => $accountingRuleId,
                            'module_id'        => 'clients',
                            'rule_description' => $arrRuleInfo['rule_description'],
                            'rule_check_id'    => $arrRuleInfo['rule_check_id'],
                            'superadmin_only'  => 0,
                            'crm_only'         => 'N',
                            'rule_visible'     => 1,
                            'rule_order'       => $order++,
                        ]
                    )
                    ->execute();
                $ruleId = $statement->lastInsertId('acl_rules');

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
                            'module_id'          => 'clients',
                            'resource_id'        => 'accounting',
                            'resource_privilege' => 'index',
                            'rule_allow'         => 1,
                        ]
                    )
                    ->execute();

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
                            'package_detail_description' => "Client's Accounting - " . $arrRuleInfo['rule_description'],
                            'visible'                    => 1,
                        ]
                    )
                    ->execute();

                $this->query("INSERT INTO `acl_role_access` (`role_id`, `rule_id`)
                            SELECT a.role_id, $ruleId
                            FROM acl_role_access AS a
                            LEFT JOIN acl_roles AS r ON r.role_parent_id = a.role_id
                            WHERE a.rule_id = $accountingRuleId"
                );
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
        $arrRules = $this->getNewRules();

        $arrRuleIds = array();
        foreach ($arrRules as $arrRuleInfo) {
            $arrRuleIds[] = $arrRuleInfo['rule_check_id'];
        }

        $this->getQueryBuilder()
            ->delete('acl_rules')
            ->where(['rule_check_id IN' => $arrRuleIds])
            ->execute();
    }
}
