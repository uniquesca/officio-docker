<?php

use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class UpdateCaTemplates extends AbstractMigration
{
    public function up()
    {
        // Took 45s on local server...
        try {
            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('templates')
                ->where("message LIKE '%\_can%'")
                ->execute();

            $arrTemplates = $statement->fetchAll('assoc');

            $arrSearch = array(
                'outstanding_balance_can',
                'outstanding_balance_non_can',
                'trust_ac_balance_can',
                'trust_ac_balance_non_can',
                'non_can_curr_fin_transaction_table',
                'can_curr_fin_transaction_table',
                'non_can_curr_trustac_summary_table',
                'can_curr_trustac_summary_table',
            );

            $arrReplace = array(
                'outstanding_balance_cdn',
                'outstanding_balance_non_cdn',
                'trust_ac_balance_cdn',
                'trust_ac_balance_non_cdn',
                'non_cdn_curr_fin_transaction_table',
                'cdn_curr_fin_transaction_table',
                'non_cdn_curr_trustac_summary_table',
                'cdn_curr_trustac_summary_table',
            );

            foreach ($arrTemplates as $arrTemplateInfo) {
                $this->getQueryBuilder()
                    ->update('templates')
                    ->set(array('message' => str_replace($arrSearch, $arrReplace, $arrTemplateInfo['message'])))
                    ->where(['template_id' => $arrTemplateInfo['template_id']])
                    ->execute();
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
    }
}
