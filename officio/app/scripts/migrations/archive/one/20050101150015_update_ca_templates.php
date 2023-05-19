<?php

use Phinx\Migration\AbstractMigration;

class UpdateCaTemplates extends AbstractMigration
{
    public function up()
    {
        // Took 77.2964s on local server...
        try {
            /** @var Zend_Db_Adapter_Abstract $db */
            $db = Zend_Registry::get('serviceManager')->get('db');

            $select = $db->select()
                ->from('templates')
                ->where("message LIKE '%_can%'");

            $arrTemplates = $db->fetchAll($select);

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
                $db->update(
                    'templates',
                    array('message' => str_replace($arrSearch, $arrReplace, $arrTemplateInfo['message'])),
                    sprintf('template_id = %d', $arrTemplateInfo['template_id'])
                );
            }

        } catch (\Exception $e) {
            echo 'Fatal error' . print_r($e->getTraceAsString(), 1);
        }
    }

    public function down()
    {
    }
}