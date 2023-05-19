<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class addAccountingEmailRules extends AbstractMigration
{
    public function up()
    {
        try {
            $statement = $this->getQueryBuilder()
                ->select(['rule_id'])
                ->from('acl_rules')
                ->where(['rule_check_id' => 'clients-accounting-email-accounting'])
                ->execute();

            $accountingRuleId = false;
            $row = $statement->fetch();
            if (!empty($row)) {
                $accountingRuleId =  $row[array_key_first($row)];
            }

            if (empty($accountingRuleId)) {
                throw new Exception('Accounting rule not found.');
            }

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
                        'rule_id'            => $accountingRuleId,
                        'module_id'          => 'mail',
                        'resource_id'        => 'index',
                        'resource_privilege' => 'send',
                        'rule_allow'         => 1,
                    ]
                )
                ->execute();
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
        $statement = $this->getQueryBuilder()
            ->select(['rule_id'])
            ->from('acl_rules')
            ->where(['rule_check_id' => 'clients-accounting-email-accounting'])
            ->execute();

        $accountingRuleId = false;
        $row = $statement->fetch();
        if (!empty($row)) {
            $accountingRuleId =  $row[array_key_first($row)];
        }

        if (!empty($accountingRuleId)) {
            $this->getQueryBuilder()
                ->delete('acl_rule_details')
                ->where(
                    [
                        'rule_id' => (int)$accountingRuleId,
                        'module_id' => 'mail',
                        'resource_id' => 'index',
                        'resource_privilege' => 'send'
                    ]
                )
                ->execute();
        }
    }
}
